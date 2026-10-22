import { z } from "zod"

export const AUTH_METHODS = ["PASSWORD", "PIN"] as const

export type AuthMethod = (typeof AUTH_METHODS)[number]

export const ROLES = ["admin", "cashier", "waiter", "kitchen"] as const

export type Role = (typeof ROLES)[number]

const decimalString = z.string().regex(/^-?\d+(\.\d+)?$/)

export const companySchema = z.object({
  id: z.uuid(),
  ruc: z.string().regex(/^\d{11}$/),
  business_name: z.string(),
  trade_name: z.string().nullable(),
  display_name: z.string(),
  slug: z.string(),
  settings: z.object({
    tax: z.object({
      regime: z.enum(["GENERAL", "MYPE_RESTAURANT"]),
      igv_rate: decimalString,
      prices_include_tax: z.boolean(),
    }),
    service_charge: z.object({
      enabled: z.boolean(),
      rate: decimalString,
      applies_to: z.array(z.string()),
    }),
    production_areas: z.array(
      z.object({
        area: z.enum(["GRILL_OVEN", "HOT_KITCHEN", "COLD_BAR"]),
        name: z.string(),
        kds_enabled: z.boolean(),
        target_minutes: z.number().int(),
      }),
    ),
    branding: z.object({
      accent_color: z.string().nullable(),
      logo_path: z.string().nullable(),
    }),
    currency: z.string(),
    timezone: z.string(),
  }),
})

export const sessionUserSchema = z.object({
  id: z.uuid(),
  name: z.string(),
  email: z.string().nullable(),
  is_active: z.boolean(),
  has_pin: z.boolean(),
  last_login_at: z.string().nullable(),
  roles: z.array(z.string()),
  permissions: z.array(z.string()),
  company: companySchema,
})

export const sessionMetaSchema = z.object({
  auth_method: z.enum(AUTH_METHODS).nullable(),
  pin_idle_timeout_minutes: z.number().int().positive(),
})

export type Company = z.infer<typeof companySchema>
export type SessionUser = z.infer<typeof sessionUserSchema>

export interface Session {
  readonly user: SessionUser
  readonly authMethod: AuthMethod | null
  readonly pinIdleTimeoutMinutes: number
}

export const ROLE_LABELS: Record<Role, string> = {
  admin: "Gerente",
  cashier: "Cajero",
  waiter: "Mozo",
  kitchen: "Cocina KDS",
}

export function isRole(value: string): value is Role {
  return ROLES.some((role) => role === value)
}

/** Formularios de acceso: mismas reglas que los FormRequest del backend. */
export const credentialsSchema = z.object({
  email: z.email({ error: "Ingrese un correo válido." }).max(255),
  password: z.string().min(1, { error: "Ingrese su contraseña." }).max(255),
})

export type Credentials = z.infer<typeof credentialsSchema>

export const pinLoginSchema = z.object({
  company_ruc: z.string().regex(/^\d{11}$/, { error: "El RUC debe tener 11 dígitos." }),
  pin_code: z.string().regex(/^\d{6}$/, { error: "El PIN debe tener 6 dígitos." }),
})

export type PinLogin = z.infer<typeof pinLoginSchema>
