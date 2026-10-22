<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrato JSON único de la API.
 *
 * Éxito: { success: true, message, data, meta? }
 * Error: { success: false, message, code, errors?, trace_id }
 */
trait ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function success(mixed $data = null, ?string $message = null, int $status = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $meta['pagination'] = [
                'current_page' => $data->resource->currentPage(),
                'per_page' => $data->resource->perPage(),
                'total' => $data->resource->total(),
                'last_page' => $data->resource->lastPage(),
            ];
        }

        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $this->resolveData($data),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status, [], JSON_UNESCAPED_UNICODE);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? 'Registro creado correctamente.', Response::HTTP_CREATED);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $extra
     */
    protected function error(
        ErrorCode $code,
        ?string $message = null,
        ?int $status = null,
        array $errors = [],
        array $headers = [],
        array $extra = [],
    ): JsonResponse {
        $traceId = Context::get('trace_id');

        $payload = [
            'success' => false,
            'message' => $message ?? $code->defaultMessage(),
            'code' => $code->value,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        $payload['trace_id'] = is_string($traceId) ? $traceId : null;

        return new JsonResponse([...$payload, ...$extra], $status ?? Response::HTTP_BAD_REQUEST, $headers, JSON_UNESCAPED_UNICODE);
    }

    private function resolveData(mixed $data): mixed
    {
        return match (true) {
            $data instanceof JsonResource => $data->resolve(request()),
            $data instanceof Arrayable => $data->toArray(),
            default => $data,
        };
    }
}
