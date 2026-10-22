import axios, { AxiosError, type AxiosResponse } from "axios"
import { z } from "zod"

import { env } from "@/lib/env"
import { apiErrorBodySchema, apiSuccessEnvelopeSchema, type ApiErrorCode } from "@/types/api"

/**
 * Error normalizado de la API. Conserva el trace_id del backend para que
 * caja o soporte puedan reportar exactamente qué petición falló.
 */
export class ApiError extends Error {
  readonly status: number
  readonly code: ApiErrorCode | "NETWORK_ERROR" | "INVALID_RESPONSE"
  readonly traceId: string | null
  readonly fieldErrors: Readonly<Record<string, readonly string[]>>

  constructor(params: {
    message: string
    status: number
    code: ApiError["code"]
    traceId: string | null
    fieldErrors?: Record<string, string[]>
  }) {
    super(params.message)
    this.name = "ApiError"
    this.status = params.status
    this.code = params.code
    this.traceId = params.traceId
    this.fieldErrors = params.fieldErrors ?? {}
  }

  get isClientError(): boolean {
    return this.status >= 400 && this.status < 500
  }
}

/**
 * Cliente HTTP del SPA. Autenticación Sanctum por cookie de sesión (HttpOnly):
 * ningún token se guarda en JavaScript. Axios reenvía XSRF-TOKEN como X-XSRF-TOKEN.
 */
export const http = axios.create({
  baseURL: env.apiBaseUrl,
  timeout: 15_000,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: "XSRF-TOKEN",
  xsrfHeaderName: "X-XSRF-TOKEN",
  headers: {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
})

http.interceptors.response.use(
  (response) => response,
  (error: unknown) => Promise.reject(toApiError(error)),
)

export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) {
    return error
  }

  if (error instanceof AxiosError) {
    const response = error.response

    if (response === undefined) {
      return new ApiError({
        message: "No hay conexión con el servidor.",
        status: 0,
        code: "NETWORK_ERROR",
        traceId: null,
      })
    }

    const body = apiErrorBodySchema.safeParse(response.data)
    const headerTraceId = response.headers["x-trace-id"]
    const traceId = typeof headerTraceId === "string" ? headerTraceId : null

    if (body.success) {
      return new ApiError({
        message: body.data.message,
        status: response.status,
        code: body.data.code,
        traceId: body.data.trace_id ?? traceId,
        ...(body.data.errors === undefined ? {} : { fieldErrors: body.data.errors }),
      })
    }

    return new ApiError({
      message: "Respuesta inesperada del servidor.",
      status: response.status,
      code: "INVALID_RESPONSE",
      traceId,
    })
  }

  return new ApiError({
    message: error instanceof Error ? error.message : "Error desconocido.",
    status: 0,
    code: "NETWORK_ERROR",
    traceId: null,
  })
}

/**
 * Valida el sobre de éxito y el payload con Zod: la UI nunca trabaja con datos sin tipar.
 */
const apiMetaFallbackSchema = z.unknown()

export function unwrap<TData extends z.ZodType>(response: AxiosResponse<unknown>, schema: TData): z.output<TData> {
  return unwrapWithMeta(response, schema, apiMetaFallbackSchema).data
}

/**
 * Igual que unwrap(), pero también valida el bloque `meta` del sobre.
 */
export function unwrapWithMeta<TData extends z.ZodType, TMeta extends z.ZodType>(
  response: AxiosResponse<unknown>,
  dataSchema: TData,
  metaSchema: TMeta,
): { data: z.output<TData>; meta: z.output<TMeta> } {
  const envelope = apiSuccessEnvelopeSchema.safeParse(response.data)
  const data = envelope.success ? dataSchema.safeParse(envelope.data.data) : undefined
  const meta = envelope.success ? metaSchema.safeParse(envelope.data.meta) : undefined

  if (data?.success !== true || meta?.success !== true) {
    const traceId = response.headers["x-trace-id"]

    throw new ApiError({
      message: "Respuesta inesperada del servidor.",
      status: response.status,
      code: "INVALID_RESPONSE",
      traceId: typeof traceId === "string" ? traceId : null,
    })
  }

  return { data: data.data, meta: meta.data }
}

/** Inicializa la cookie CSRF de Sanctum antes de login / cambio de usuario por PIN. */
export async function ensureCsrfCookie(): Promise<void> {
  await http.get("/sanctum/csrf-cookie", { baseURL: "" })
}
