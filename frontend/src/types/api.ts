import { z } from "zod"

/**
 * Contrato JSON del backend (App\Traits\ApiResponse).
 *   Éxito: { success: true, message, data, meta? }
 *   Error: { success: false, message, code, errors?, trace_id }
 */

export const apiErrorCodeSchema = z.enum([
  "VALIDATION_FAILED",
  "UNAUTHENTICATED",
  "INVALID_CREDENTIALS",
  "SESSION_EXPIRED",
  "FORBIDDEN",
  "TENANT_ACCESS_DENIED",
  "NOT_FOUND",
  "METHOD_NOT_ALLOWED",
  "CONFLICT",
  "PAYLOAD_TOO_LARGE",
  "CSRF_TOKEN_MISMATCH",
  "BUSINESS_RULE_VIOLATION",
  "TOO_MANY_REQUESTS",
  "SERVICE_UNAVAILABLE",
  "HTTP_ERROR",
  "SERVER_ERROR",
])

export type ApiErrorCode = z.infer<typeof apiErrorCodeSchema>

export const apiErrorBodySchema = z.object({
  success: z.literal(false),
  message: z.string(),
  // Un código nuevo del backend no debe romper el manejo de errores del cliente.
  code: apiErrorCodeSchema.catch("HTTP_ERROR"),
  errors: z.record(z.string(), z.array(z.string())).optional(),
  trace_id: z.string().nullable(),
})

export type ApiErrorBody = z.infer<typeof apiErrorBodySchema>

/** Sobre de éxito; `data` se valida aparte con el schema del recurso. */
export const apiSuccessEnvelopeSchema = z.object({
  success: z.literal(true),
  message: z.string().nullable(),
  data: z.unknown(),
  meta: z.record(z.string(), z.unknown()).optional(),
})

export const healthSchema = z.object({
  status: z.literal("ok"),
  api_version: z.string(),
  server_time: z.iso.datetime({ offset: true }),
})

export type Health = z.infer<typeof healthSchema>
