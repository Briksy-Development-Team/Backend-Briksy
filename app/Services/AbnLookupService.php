<?php

namespace App\Services;

use App\Exceptions\AbnLookupBusinessTypeMismatchException;
use App\Exceptions\AbnLookupUnavailableException;
use App\Exceptions\AbnLookupVerificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

class AbnLookupService
{
    private const SOAP_NAMESPACE = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const SOAP_ACTION = 'http://abr.business.gov.au/ABRXMLSearch/SearchByABNv202001';

    public function verify(string $abn, ?string $businessType = null): array
    {
        $normalizedAbn = $this->normalizeAbn($abn);
        $maskedAbn = $this->maskAbn($normalizedAbn);

        Log::info('ABN verification request received.', [
            'abn' => $maskedAbn,
            'business_type' => $businessType,
        ]);

        if (!$this->isPotentiallyValidAbn($normalizedAbn)) {
            Log::warning('ABN verification rejected before API call.', [
                'abn' => $maskedAbn,
            ]);

            throw new AbnLookupVerificationException();
        }

        $guid = trim((string) config('services.abn_lookup.guid'));

        if ($guid === '') {
            Log::error('ABN lookup GUID is not configured.');

            throw new AbnLookupUnavailableException();
        }

        $xml = $this->buildEnvelope($normalizedAbn, $guid);

        try {
            $response = Http::withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => sprintf('"%s"', self::SOAP_ACTION),
                ])
                ->withBody($xml, 'text/xml; charset=utf-8')
                ->timeout((int) config('services.abn_lookup.timeout', 15))
                ->connectTimeout(5)
                ->retry((int) config('services.abn_lookup.retry_attempts', 2), (int) config('services.abn_lookup.retry_sleep_ms', 300))
                ->send('POST', (string) config('services.abn_lookup.endpoint'))
                ->throw();
        } catch (ConnectionException $exception) {
            Log::warning('ABN verification timeout or connection failure.', [
                'abn' => $maskedAbn,
                'error' => $exception->getMessage(),
            ]);

            throw new AbnLookupUnavailableException(previous: $exception);
        } catch (RequestException $exception) {
            Log::error('ABN verification HTTP error.', [
                'abn' => $maskedAbn,
                'error' => $exception->getMessage(),
            ]);

            throw new AbnLookupUnavailableException(previous: $exception);
        }

        try {
            $parsed = $this->parseSoapResponse($response->body());
        } catch (Throwable $exception) {
            Log::error('ABN verification SOAP parsing failed.', [
                'abn' => $maskedAbn,
                'error' => $exception->getMessage(),
            ]);

            throw new AbnLookupUnavailableException(previous: $exception);
        }

        if ($this->isSoapFault($parsed)) {
            $fault = $this->readSoapFault($parsed);
            $this->handleFault($fault, $maskedAbn);
        }

        $businessEntity = $this->extractBusinessEntity($parsed);

        if ($businessEntity === null) {
            Log::info('ABN verification failed: no business entity returned.', [
                'abn' => $maskedAbn,
            ]);

            throw new AbnLookupVerificationException();
        }

        $entityStatus = trim((string) $this->dataGet($businessEntity, ['entityStatus', 'entityStatusCode']));
        $entityTypeCode = strtoupper(trim((string) $this->dataGet($businessEntity, ['entityType', 'entityTypeCode'])));
        $entityType = trim((string) $this->dataGet($businessEntity, ['entityType', 'entityDescription']));
        $entityName = $this->resolveEntityName($businessEntity);
        $state = trim((string) $this->dataGet($businessEntity, ['mainBusinessPhysicalAddress', 'stateCode']));
        $postcode = trim((string) $this->dataGet($businessEntity, ['mainBusinessPhysicalAddress', 'postcode']));
        $acn = trim((string) $this->dataGet($businessEntity, ['ASICNumber']));
        $effectiveFrom = $this->firstNonBlank([
            $this->dataGet($businessEntity, ['entityStatus', 'effectiveFrom']),
            $this->dataGet($businessEntity, ['ABN', 'replacedFrom']),
        ]);
        $gstRegistered = $this->isTruthyDate($this->dataGet($businessEntity, ['goodsAndServicesTax', 'effectiveFrom']))
            && !$this->isExpiredDate($this->dataGet($businessEntity, ['goodsAndServicesTax', 'effectiveTo']));
        $businessNames = $this->extractBusinessNames($businessEntity);

        $statusNormalized = strtolower($entityStatus);

        if ($statusNormalized !== 'active') {
            Log::info('ABN verification failed: ABN is inactive.', [
                'abn' => $maskedAbn,
                'status' => $entityStatus,
            ]);

            throw new AbnLookupVerificationException();
        }

        if ($this->businessTypeMismatches($businessType, $entityTypeCode)) {
            Log::info('ABN verification failed: selected business type does not match ABR entity type.', [
                'abn' => $maskedAbn,
                'selected_business_type' => $businessType,
                'entity_type_code' => $entityTypeCode,
            ]);

            throw new AbnLookupBusinessTypeMismatchException($this->businessTypeMismatchMessage($businessType, $entityTypeCode));
        }

        $result = [
            'valid' => true,
            'abn' => $normalizedAbn,
            'entityName' => $entityName,
            'entityType' => $entityType ?: $entityTypeCode,
            'entityTypeCode' => $entityTypeCode,
            'entityStatus' => $entityStatus ?: 'Active',
            'gstRegistered' => $gstRegistered,
            'state' => $state ?: null,
            'postcode' => $postcode ?: null,
            'effectiveFrom' => $effectiveFrom ?: null,
            'businessNames' => $businessNames,
            'acn' => $acn ?: null,
            'message' => null,
            'rawResponse' => $parsed,
        ];

        Log::info('ABN verification successful.', [
            'abn' => $maskedAbn,
            'entity_type_code' => $entityTypeCode,
            'status' => $entityStatus,
        ]);

        return $result;
    }

    private function buildEnvelope(string $abn, string $guid): string
    {
        return sprintf(
            <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SearchByABNv202001 xmlns="http://abr.business.gov.au/ABRXMLSearch/">
      <searchString>%s</searchString>
      <includeHistoricalDetails>Y</includeHistoricalDetails>
      <authenticationGuid>%s</authenticationGuid>
    </SearchByABNv202001>
  </soap:Body>
</soap:Envelope>
XML,
            e($abn),
            e($guid)
        );
    }

    private function parseSoapResponse(string $xml): array
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS);
        if (!$loaded) {
            throw new AbnLookupVerificationException('Unable to parse the ABN Lookup response.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', self::SOAP_NAMESPACE);
        $xpath->registerNamespace('soap12', 'http://www.w3.org/2003/05/soap-envelope');

        $payloadNode = $xpath->query('//*[local-name()="ABRPayloadSearchResults"]')->item(0);

        if (!$payloadNode instanceof DOMElement) {
            $faultNode = $xpath->query('//*[local-name()="Fault"]')->item(0);

            if ($faultNode instanceof DOMElement) {
                return [
                    'soapFault' => $this->xmlNodeToArray($faultNode),
                ];
            }

            throw new AbnLookupVerificationException('Unable to parse the ABN Lookup response.');
        }

        return $this->xmlNodeToArray($payloadNode);
    }

    private function xmlNodeToArray(DOMElement $element): array|string
    {
        $children = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        if ($children === []) {
            return trim($element->textContent);
        }

        $result = [];

        foreach ($children as $child) {
            $value = $this->xmlNodeToArray($child);
            $name = $child->localName;

            if (array_key_exists($name, $result)) {
                if (!is_array($result[$name]) || !array_is_list($result[$name])) {
                    $result[$name] = [$result[$name]];
                }

                $result[$name][] = $value;
                continue;
            }

            $result[$name] = $value;
        }

        return $result;
    }

    private function extractBusinessEntity(array $parsed): ?array
    {
        $response = $parsed['response'] ?? null;

        if (!is_array($response)) {
            return null;
        }

        foreach (['businessEntity202001', 'businessEntity'] as $nodeName) {
            $businessEntity = $response[$nodeName] ?? null;

            if (is_array($businessEntity) && !array_is_list($businessEntity)) {
                return $businessEntity;
            }

            if (is_array($businessEntity) && array_is_list($businessEntity)) {
                $first = $businessEntity[0] ?? null;

                if (is_array($first)) {
                    return $first;
                }
            }
        }

        return null;
    }

    private function readSoapFault(array $parsed): ?array
    {
        $fault = $parsed['soapFault'] ?? null;

        if (is_array($fault)) {
            return $fault;
        }

        if (is_string($fault) && trim($fault) !== '') {
            return ['faultstring' => trim($fault)];
        }

        return null;
    }

    private function isSoapFault(array $parsed): bool
    {
        return array_key_exists('soapFault', $parsed);
    }

    private function handleFault(?array $fault, string $maskedAbn): never
    {
        $message = trim((string) ($fault['faultstring'] ?? $fault['faultString'] ?? ''));
        $logContext = [
            'abn' => $maskedAbn,
            'fault' => $fault,
        ];

        if ($message !== '') {
            Log::info('ABN verification returned a SOAP fault.', $logContext);

            if (str_contains(strtolower($message), 'no records found')) {
                throw new AbnLookupVerificationException();
            }

            if (str_contains(strtolower($message), 'invalid') || str_contains(strtolower($message), 'not found')) {
                throw new AbnLookupVerificationException();
            }
        }

        Log::error('ABN verification returned an unexpected SOAP fault.', $logContext);

        throw new AbnLookupUnavailableException();
    }

    private function normalizeAbn(string $abn): string
    {
        return preg_replace('/\s+/', '', trim($abn)) ?? '';
    }

    private function isPotentiallyValidAbn(string $abn): bool
    {
        if (!preg_match('/^\d{11}$/', $abn)) {
            return false;
        }

        $digits = array_map('intval', str_split($abn));
        $digits[0] -= 1;
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $sum = 0;

        foreach ($digits as $index => $digit) {
            $sum += $digit * $weights[$index];
        }

        return $sum % 89 === 0;
    }

    private function resolveEntityName(array $businessEntity): string
    {
        foreach ($this->extractNestedStrings($businessEntity, 'mainName', 'organisationName') as $mainName) {
            if ($mainName !== '') {
                return $mainName;
            }
        }

        $givenName = trim((string) $this->dataGet($businessEntity, ['legalName', 'givenName']));
        $otherGivenName = trim((string) $this->dataGet($businessEntity, ['legalName', 'otherGivenName']));
        $familyName = trim((string) $this->dataGet($businessEntity, ['legalName', 'familyName']));
        $legalName = trim(implode(' ', array_filter([$givenName, $otherGivenName, $familyName])));

        if ($legalName !== '') {
            return $legalName;
        }

        foreach ($this->extractNestedStrings($businessEntity, 'businessName', 'organisationName') as $businessName) {
            if ($businessName !== '') {
                return $businessName;
            }
        }

        foreach ($this->extractNestedStrings($businessEntity, 'mainTradingName', 'organisationName') as $mainTradingName) {
            if ($mainTradingName !== '') {
                return $mainTradingName;
            }
        }

        return '';
    }

    private function extractBusinessNames(array $businessEntity): array
    {
        return array_values(array_unique(array_filter($this->extractNestedStrings($businessEntity, 'businessName', 'organisationName'))));
    }

    private function extractNestedStrings(array $data, string $parentKey, string $childKey): array
    {
        $parent = $data[$parentKey] ?? null;

        if ($parent === null) {
            return [];
        }

        $values = [];

        if (is_array($parent) && array_is_list($parent)) {
            foreach ($parent as $item) {
                if (is_array($item) && array_key_exists($childKey, $item)) {
                    foreach ($this->flattenToStrings($item[$childKey]) as $value) {
                        $values[] = $value;
                    }
                }
            }

            return $values;
        }

        if (is_array($parent) && array_key_exists($childKey, $parent)) {
            foreach ($this->flattenToStrings($parent[$childKey]) as $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function flattenToStrings(mixed $value): array
    {
        if (is_array($value)) {
            $values = [];

            foreach ($value as $item) {
                foreach ($this->flattenToStrings($item) as $nestedValue) {
                    $values[] = $nestedValue;
                }
            }

            return $values;
        }

        $value = trim((string) $value);

        return $value !== '' ? [$value] : [];
    }

    private function dataGet(array $data, array $path): mixed
    {
        $cursor = $data;

        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    private function firstNonBlank(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function isTruthyDate(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }

    private function isExpiredDate(mixed $value): bool
    {
        $date = trim((string) $value);

        if ($date === '') {
            return false;
        }

        try {
            return now()->isAfter(\Illuminate\Support\Carbon::parse($date));
        } catch (Throwable) {
            return false;
        }
    }

    private function businessTypeMismatches(?string $businessType, string $entityTypeCode): bool
    {
        if (blank($businessType)) {
            return false;
        }

        $selected = strtolower(trim((string) $businessType));
        $isSoleTraderEntity = $entityTypeCode === 'IND';

        return $selected === 'solo_trader'
            ? !$isSoleTraderEntity
            : $isSoleTraderEntity;
    }

    private function businessTypeMismatchMessage(?string $businessType, string $entityTypeCode): string
    {
        $isSoleTraderEntity = $entityTypeCode === 'IND';

        if ($isSoleTraderEntity) {
            return 'This ABN belongs to an Individual / Sole Trader. Please select Sole Trader.';
        }

        if (blank($businessType) || strtolower((string) $businessType) === 'solo_trader') {
            return 'This ABN belongs to an Organisation / Company. Please select Organisation or Company.';
        }

        return 'This ABN belongs to an Organisation / Company. Please select the matching business type.';
    }

    private function maskAbn(string $abn): string
    {
        if (strlen($abn) !== 11) {
            return $abn;
        }

        return substr($abn, 0, 4) . '*****' . substr($abn, -2);
    }
}
