<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\AbnLookupException;
use App\Exceptions\AbnLookupUnavailableException;
use App\Exceptions\AbnLookupVerificationException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Auth\VerifyAbnRequest;
use App\Services\AbnLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AbnVerificationController extends Controller
{
    public function __construct(
        private readonly AbnLookupService $abnLookupService
    ) {
    }

    public function store(VerifyAbnRequest $request): JsonResponse
    {
        $abn = $request->string('abn')->toString();
        $businessType = $request->string('business_type')->toString();

        try {
            $result = $this->abnLookupService->verify(
                $abn,
                blank($businessType) ? null : $businessType
            );
        } catch (AbnLookupVerificationException $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus());
        } catch (AbnLookupUnavailableException $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus());
        } catch (AbnLookupException $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus());
        } catch (Throwable $throwable) {
            Log::error('Unexpected ABN verification failure.', [
                'error' => $throwable->getMessage(),
            ]);

            return response()->json([
                'valid' => false,
                'message' => 'Unable to verify your ABN at the moment. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'valid' => true,
            'abn' => $result['abn'],
            'entityName' => $result['entityName'],
            'entityType' => $result['entityType'],
            'gstRegistered' => $result['gstRegistered'],
            'state' => $result['state'],
            'postcode' => $result['postcode'],
            'acn' => $result['acn'],
            'status' => $result['entityStatus'],
            'message' => null,
        ]);
    }
}
