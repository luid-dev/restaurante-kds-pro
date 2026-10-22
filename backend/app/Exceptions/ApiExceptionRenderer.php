<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renderizado global de errores en JSON con trace_id.
 *
 * Nunca expone mensajes internos, nombres de modelos ni trazas: los 4xx usan
 * mensajes del catálogo ErrorCode y los 5xx un mensaje genérico. Con APP_DEBUG
 * activo se adjunta un bloque `debug` solo para desarrollo local.
 */
final class ApiExceptionRenderer
{
    use ApiResponse;

    private const string DEFAULT_AUTHORIZATION_MESSAGE = 'This action is unauthorized.';

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        return match (true) {
            $e instanceof HttpResponseException => null,
            $e instanceof ValidationException => $this->error(ErrorCode::ValidationFailed, status: $e->status, errors: $e->errors()),
            $e instanceof AuthenticationException => $this->error(ErrorCode::Unauthenticated, status: 401),
            $e instanceof ApiException => $this->error($e->errorCode(), $e->getMessage(), $e->status(), $e->errors()),
            $e instanceof HttpExceptionInterface => $this->renderHttpException($e),
            default => $this->error(ErrorCode::ServerError, status: 500, extra: $this->debugContext($e)),
        };
    }

    private function renderHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $code = ErrorCode::fromStatus($status);

        /** @var array<string, string> $headers */
        $headers = $e->getHeaders();

        return $this->error(
            $code,
            $this->authorizationMessage($e),
            $status,
            headers: $headers,
            extra: $status >= 500 ? $this->debugContext($e) : [],
        );
    }

    /**
     * Las políticas pueden explicar el rechazo (Response::deny('...')); el resto
     * de mensajes HTTP se reemplaza por el catálogo para no filtrar detalles internos.
     */
    private function authorizationMessage(HttpExceptionInterface $e): ?string
    {
        $previous = $e->getPrevious();

        if ($previous instanceof AuthorizationException && $previous->getMessage() !== self::DEFAULT_AUTHORIZATION_MESSAGE) {
            return $previous->getMessage();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function debugContext(Throwable $e): array
    {
        if (! config('app.debug')) {
            return [];
        }

        return [
            'debug' => [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ],
        ];
    }
}
