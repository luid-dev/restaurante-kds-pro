<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Códigos de error estables del contrato JSON de la API (campo `code`).
 */
enum ErrorCode: string
{
    case ValidationFailed = 'VALIDATION_FAILED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case SessionExpired = 'SESSION_EXPIRED';
    case Forbidden = 'FORBIDDEN';
    case TenantAccessDenied = 'TENANT_ACCESS_DENIED';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case PayloadTooLarge = 'PAYLOAD_TOO_LARGE';
    case CsrfTokenMismatch = 'CSRF_TOKEN_MISMATCH';
    case BusinessRuleViolation = 'BUSINESS_RULE_VIOLATION';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case HttpError = 'HTTP_ERROR';
    case ServerError = 'SERVER_ERROR';

    public static function fromStatus(int $status): self
    {
        return match ($status) {
            401 => self::Unauthenticated,
            403 => self::Forbidden,
            404 => self::NotFound,
            405 => self::MethodNotAllowed,
            409 => self::Conflict,
            413 => self::PayloadTooLarge,
            419 => self::CsrfTokenMismatch,
            422 => self::ValidationFailed,
            429 => self::TooManyRequests,
            503 => self::ServiceUnavailable,
            default => $status >= 500 ? self::ServerError : self::HttpError,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::ValidationFailed => 'Los datos enviados no son válidos.',
            self::Unauthenticated => 'Sesión no iniciada o expirada.',
            self::InvalidCredentials => 'Credenciales incorrectas.',
            self::SessionExpired => 'La sesión de la terminal expiró por inactividad. Ingrese su PIN nuevamente.',
            self::Forbidden => 'No tiene permisos para realizar esta acción.',
            self::TenantAccessDenied => 'La empresa o el usuario no están habilitados.',
            self::NotFound => 'El recurso solicitado no existe.',
            self::MethodNotAllowed => 'Método HTTP no permitido para este recurso.',
            self::Conflict => 'La operación entra en conflicto con el estado actual del recurso.',
            self::PayloadTooLarge => 'La solicitud excede el tamaño permitido.',
            self::CsrfTokenMismatch => 'La sesión de seguridad expiró. Recargue e intente nuevamente.',
            self::BusinessRuleViolation => 'La operación no cumple las reglas del negocio.',
            self::TooManyRequests => 'Demasiadas solicitudes. Intente nuevamente en unos segundos.',
            self::ServiceUnavailable => 'Servicio en mantenimiento. Intente más tarde.',
            self::HttpError => 'No se pudo procesar la solicitud.',
            self::ServerError => 'Error interno del servidor. Reporte el trace_id a soporte.',
        };
    }
}
