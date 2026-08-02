<?php

namespace App\Services\Imports;

final class ImportFieldMatcher
{
    /**
     * @param  array<int, array{key:string,label:string,required:bool,aliases:array<int, string>}>  $fields
     * @param  array<int, string>  $excludedKeys
     */
    public function match(array $fields, string $header, array $excludedKeys = []): array
    {
        $normalizedHeader = $this->normalizeHeader($header);
        $best = [
            'field' => null,
            'confidence' => 0.0,
            'label' => null,
        ];

        foreach ($fields as $field) {
            if (in_array($field['key'], $excludedKeys, true)) {
                continue;
            }

            $candidates = array_merge([$field['key'], $field['label']], $field['aliases']);

            foreach ($candidates as $candidate) {
                $normalizedCandidate = $this->normalizeHeader($candidate);
                if ($normalizedCandidate === '') {
                    continue;
                }

                if ($normalizedHeader === $normalizedCandidate) {
                    return [
                        'field' => $field['key'],
                        'confidence' => 1.0,
                        'label' => $field['label'],
                    ];
                }

                $distance = levenshtein($normalizedHeader, $normalizedCandidate);
                $length = max(strlen($normalizedHeader), strlen($normalizedCandidate));
                $score = $length > 0 ? 1 - ($distance / $length) : 0.0;

                if ($score > $best['confidence']) {
                    $best = [
                        'field' => $field['key'],
                        'confidence' => $score,
                        'label' => $field['label'],
                    ];
                }
            }
        }

        if ($best['confidence'] >= 0.72) {
            return $best;
        }

        return [
            'field' => null,
            'confidence' => $best['confidence'],
            'label' => null,
        ];
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(preg_replace('/[\s_-]+/', '', trim($value)) ?? '');
    }
}
