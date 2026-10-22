import { z } from "zod"

import { decimal, isoDate, ORDER_TYPES, PAYMENT_METHODS, PRODUCTION_AREAS, TAX_AFFECTATION_TYPES } from "@/types/operations"

/**
 * Contratos de rentabilidad, inventario y reportes SUNAT. Cifras como string
 * decimal: la UI solo las presenta (BCMath en el backend es la fuente de verdad).
 */

const periodSchema = z.object({ from: z.string(), to: z.string() })

export const paginationSchema = z.object({
  current_page: z.number().int(),
  per_page: z.number().int(),
  total: z.number().int(),
  last_page: z.number().int(),
})

export type Pagination = z.infer<typeof paginationSchema>

/* ---------- Dashboard de rentabilidad ---------- */

export const dashboardMetricsSchema = z.object({
  period: periodSchema,
  gross_sales: decimal,
  net_sales: decimal,
  igv_total: decimal,
  service_charge_total: decimal,
  credit_notes_total: decimal,
  theoretical_food_cost: decimal,
  food_cost_percentage: decimal.nullable(),
  food_cost_target: decimal,
  waste_cost: decimal,
  average_ticket: decimal,
  orders_count: z.number().int(),
  documents_count: z.number().int(),
  top_dishes: z.array(
    z.object({
      id: z.uuid(),
      sku: z.string(),
      name: z.string(),
      quantity: decimal,
      gross_revenue: decimal,
      net_revenue: decimal,
      unit_cost: decimal,
      theoretical_cost: decimal,
      contribution_margin: decimal,
      margin_percentage: decimal.nullable(),
      food_cost_percentage: decimal.nullable(),
    }),
  ),
  sales_by_channel: z.array(
    z.object({ channel: z.enum(ORDER_TYPES), label: z.string(), total: decimal, orders: z.number().int(), share_percentage: decimal.nullable() }),
  ),
  sales_by_payment_method: z.array(
    z.object({ method: z.enum(PAYMENT_METHODS), label: z.string(), total: decimal, share_percentage: decimal.nullable() }),
  ),
})

export type DashboardMetrics = z.infer<typeof dashboardMetricsSchema>

/* ---------- Insumos, escandallo y kardex ---------- */

export const UNIT_CODES = ["NIU", "KGM", "LTR"] as const

const ingredientRefSchema = z.object({ id: z.uuid(), sku: z.string(), name: z.string(), unit_code: z.enum(UNIT_CODES) })

export const ingredientSchema = z.object({
  id: z.uuid(),
  sku: z.string(),
  name: z.string(),
  unit_code: z.enum(UNIT_CODES),
  unit_symbol: z.string(),
  stock: decimal,
  min_stock: decimal,
  average_cost: decimal,
  stock_value: decimal,
  is_critical: z.boolean(),
})

export type Ingredient = z.infer<typeof ingredientSchema>

export const ingredientsMetaSchema = z.object({ critical_count: z.number().int(), inventory_value: decimal })

export const recipeSchema = z.object({
  id: z.uuid(),
  sku: z.string(),
  name: z.string(),
  category: z.string(),
  production_area: z.enum(PRODUCTION_AREAS),
  sale_price: decimal,
  net_price: decimal,
  tax_affectation_type: z.enum(TAX_AFFECTATION_TYPES),
  packaging_surcharge: decimal,
  dine_in_cost: decimal,
  takeout_cost: decimal,
  food_cost_percentage: decimal.nullable(),
  lines: z.array(
    z.object({
      ingredient: ingredientRefSchema,
      quantity_required: decimal,
      apply_only_on_takeout: z.boolean(),
      dine_in_quantity: decimal,
      takeout_quantity: decimal,
      unit_cost: decimal,
      line_cost: decimal,
    }),
  ),
})

export type Recipe = z.infer<typeof recipeSchema>

export const KARDEX_MOVEMENT_TYPES = ["INITIAL_BALANCE", "PURCHASE", "SALE_CONSUMPTION", "WASTE", "ADJUSTMENT_IN", "ADJUSTMENT_OUT"] as const
export type KardexMovementType = (typeof KARDEX_MOVEMENT_TYPES)[number]

/** Tabla 12 SUNAT: tipo de operación de cada movimiento. */
export const KARDEX_MOVEMENT_LABELS: Record<KardexMovementType, string> = {
  INITIAL_BALANCE: "16 Saldo inicial",
  PURCHASE: "02 Compra",
  SALE_CONSUMPTION: "10 Consumo por venta",
  WASTE: "13 Merma",
  ADJUSTMENT_IN: "99 Ajuste / reverso",
  ADJUSTMENT_OUT: "99 Ajuste salida",
}

export const kardexMovementSchema = z.object({
  id: z.uuid(),
  occurred_at: isoDate,
  ingredient: ingredientRefSchema,
  movement_type: z.enum(KARDEX_MOVEMENT_TYPES),
  sunat_operation_type: z.string(),
  document_reference: z.string().nullable(),
  quantity_in: decimal,
  quantity_out: decimal,
  unit_cost: decimal,
  total_cost: decimal,
  balance_quantity: decimal,
  balance_unit_cost: decimal,
  balance_total_cost: decimal,
  notes: z.string().nullable(),
  created_by: z.string().nullable(),
})

export type KardexMovement = z.infer<typeof kardexMovementSchema>

export const kardexMetaSchema = z.object({ period: periodSchema, pagination: paginationSchema })

export const wasteAuditSchema = z.object({
  period: periodSchema,
  total_cost: decimal,
  incidents: z.number().int(),
  rows: z.array(
    z.object({
      id: z.uuid(),
      occurred_at: isoDate,
      origin: z.enum(["VOID", "CREDIT_NOTE"]),
      reference: z.string().nullable(),
      dish: z.string().nullable(),
      ingredient: z.object({ sku: z.string(), name: z.string(), unit_code: z.enum(UNIT_CODES) }),
      quantity: decimal,
      unit_cost: decimal,
      total_cost: decimal,
      authorized_by: z.string().nullable(),
      reason: z.string().nullable(),
    }),
  ),
})

export type WasteAudit = z.infer<typeof wasteAuditSchema>

/* ---------- Notas de crédito ---------- */

export const CREDIT_NOTE_REASONS = [
  { code: "01", label: "Anulación de la operación", invoiceOnly: false },
  { code: "02", label: "Anulación por error en el RUC", invoiceOnly: true },
  { code: "06", label: "Devolución total", invoiceOnly: false },
] as const

export type CreditNoteReasonCode = (typeof CREDIT_NOTE_REASONS)[number]["code"]

/** Rango de fechas de negocio (YYYY-MM-DD) usado por todos los reportes. */
export interface DateRange {
  readonly from: string
  readonly to: string
}
