<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

abstract class Controller extends BaseController
{
    protected function success(mixed $data = null, string $message = 'Request completed successfully.', int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function created(mixed $data = null, string $message = 'Resource created successfully.'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function paginated(mixed $data, LengthAwarePaginator $paginator, string $message = 'Records retrieved successfully.'): JsonResponse
    {
        return $this->success($data, $message, 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
