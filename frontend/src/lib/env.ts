import { z } from "zod"

/**
 * Variables de entorno validadas al arrancar. Un valor mal configurado falla
 * de inmediato en lugar de producir errores difusos en caja o cocina.
 */
const envSchema = z.object({
  VITE_APP_NAME: z.string().min(1).default("KDS Pro"),
  VITE_API_BASE_URL: z.string().min(1).default("/api/v1"),
  VITE_REVERB_APP_KEY: z
    .string()
    .trim()
    .optional()
    .transform((value) => (value === "" ? undefined : value)),
  VITE_REVERB_HOST: z.string().min(1).default("localhost"),
  VITE_REVERB_PORT: z.coerce.number().int().min(1).max(65535).default(8080),
  VITE_REVERB_SCHEME: z.enum(["http", "https"]).default("http"),
  VITE_DEMO_MODE: z.enum(["true", "false"]).default("false"),
})

const parsed = envSchema.parse(import.meta.env)

export interface RealtimeConfig {
  readonly key: string
  readonly host: string
  readonly port: number
  readonly forceTLS: boolean
}

export const env = {
  appName: parsed.VITE_APP_NAME,
  apiBaseUrl: parsed.VITE_API_BASE_URL,
  mode: import.meta.env.MODE,
  /** Accesos de prueba por rol (portafolio). Ver features/auth/demo-access-bar.tsx. */
  demoMode: parsed.VITE_DEMO_MODE === "true",
  /** null cuando Reverb no está configurado: la UI lo informa en la barra de estado. */
  realtime:
    parsed.VITE_REVERB_APP_KEY === undefined
      ? null
      : ({
          key: parsed.VITE_REVERB_APP_KEY,
          host: parsed.VITE_REVERB_HOST,
          port: parsed.VITE_REVERB_PORT,
          forceTLS: parsed.VITE_REVERB_SCHEME === "https",
        } satisfies RealtimeConfig),
} as const
