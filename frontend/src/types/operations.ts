import { z } from "zod"

/**
 * Contratos de la operación gastronómica. Montos y cantidades viajan
 * como string decimal ("145.50", "0.2500"): la UI los presenta y, cuando debe
 * operar, lo hace en céntimos enteros (lib/money.ts), nunca con floats.
 */

export const decimal = z.string().regex(/^-?\d+(\.\d+)?$/)
export const isoDate = z.iso.datetime({ offset: true })

export const PRODUCTION_AREAS = ["GRILL_OVEN", "HOT_KITCHEN", "COLD_BAR"] as const
export type ProductionArea = (typeof PRODUCTION_AREAS)[number]

export const PRODUCTION_AREA_LABELS: Record<ProductionArea, string> = {
  GRILL_OVEN: "Horno / Brasas",
  HOT_KITCHEN: "Cocina Caliente",
  COLD_BAR: "Barra / Bebidas",
}

export const TABLE_STATUSES = ["AVAILABLE", "OCCUPIED", "PRE_BILL", "RESERVED"] as const
export type TableStatus = (typeof TABLE_STATUSES)[number]

export const ORDER_STATUSES = ["OPEN", "PRE_BILLED", "PAID", "CANCELLED"] as const
export type OrderStatus = (typeof ORDER_STATUSES)[number]

export const ORDER_TYPES = ["DINE_IN", "TAKEOUT", "DELIVERY"] as const
export type OrderType = (typeof ORDER_TYPES)[number]

export const ITEM_STATUSES = ["PENDING", "PREPARING", "READY", "SERVED", "VOIDED"] as const
export type ItemStatus = (typeof ITEM_STATUSES)[number]

export const ITEM_STATUS_LABELS: Record<ItemStatus, string> = {
  PENDING: "En cola",
  PREPARING: "En preparación",
  READY: "Listo para servir",
  SERVED: "Entregado",
  VOIDED: "Anulado",
}

/* ---------- Plano de mesas ---------- */

export const tableSchema = z.object({
  id: z.uuid(),
  code: z.string(),
  capacity: z.number().int(),
  grid_x: z.number().int(),
  grid_y: z.number().int(),
  status: z.enum(TABLE_STATUSES),
  opened_at: isoDate.nullable(),
  order: z
    .object({
      id: z.uuid(),
      order_number: z.string(),
      status: z.enum(ORDER_STATUSES),
      diners_count: z.number().int(),
      total_amount: decimal,
      waiter_name: z.string().nullable(),
    })
    .nullable(),
})

export type RestaurantTable = z.infer<typeof tableSchema>

export const zoneSchema = z.object({
  id: z.uuid(),
  name: z.string(),
  sort_order: z.number().int(),
  tables: z.array(tableSchema),
})

export type DiningZone = z.infer<typeof zoneSchema>

/* ---------- Comandas ---------- */

export const orderItemSchema = z.object({
  id: z.uuid(),
  menu_item: z.object({ id: z.uuid(), sku: z.string(), name: z.string(), packaging_surcharge: decimal }),
  production_area: z.enum(PRODUCTION_AREAS),
  quantity: decimal,
  paid_quantity: decimal,
  unpaid_quantity: decimal,
  unit_price: decimal,
  line_total: decimal,
  modifiers: z.array(z.string()),
  notes: z.string().nullable(),
  status: z.enum(ITEM_STATUSES),
  sent_to_kitchen_at: isoDate.nullable(),
  ready_at: isoDate.nullable(),
  voided_at: isoDate.nullable(),
  void_reason: z.string().nullable(),
})

export type OrderItem = z.infer<typeof orderItemSchema>

export const orderSchema = z.object({
  id: z.uuid(),
  order_number: z.string(),
  order_type: z.enum(ORDER_TYPES),
  status: z.enum(ORDER_STATUSES),
  business_date: z.string(),
  diners_count: z.number().int(),
  customer_name: z.string().nullable(),
  delivery_address: z.string().nullable(),
  delivery_phone: z.string().nullable(),
  opened_at: isoDate,
  closed_at: isoDate.nullable(),
  table: z.object({ id: z.uuid(), code: z.string() }).nullable().optional(),
  waiter: z.object({ id: z.uuid(), name: z.string() }).optional(),
  totals: z.object({
    subtotal: decimal,
    packaging_total: decimal,
    tax_amount: decimal,
    service_charge: decimal,
    total_amount: decimal,
    paid_amount: decimal,
    balance: decimal,
  }),
  items: z.array(orderItemSchema).optional(),
})

export type Order = z.infer<typeof orderSchema>

export const floorPlanSchema = z.object({
  zones: z.array(zoneSchema),
  counter_orders: z.array(orderSchema),
  summary: z.object({
    available: z.number().int(),
    occupied: z.number().int(),
    pre_bill: z.number().int(),
    open_sales_total: decimal,
    pending_balance: decimal,
  }),
})

export type FloorPlan = z.infer<typeof floorPlanSchema>

/* ---------- Carta ---------- */

export const menuItemSchema = z.object({
  id: z.uuid(),
  sku: z.string(),
  name: z.string(),
  production_area: z.enum(PRODUCTION_AREAS),
  sale_price: decimal,
  packaging_surcharge: decimal,
  preparation_time_minutes: z.number().int(),
  is_available: z.boolean(),
})

export type MenuItem = z.infer<typeof menuItemSchema>

export const menuSchema = z.object({
  categories: z.array(z.object({ id: z.uuid(), name: z.string(), items: z.array(menuItemSchema) })),
  modifiers: z.array(z.object({ id: z.uuid(), name: z.string(), production_area: z.enum(PRODUCTION_AREAS).nullable() })),
})

export type Menu = z.infer<typeof menuSchema>
export type MenuModifier = Menu["modifiers"][number]

/** Indicación que convierte un plato de salón en "para llevar" (cobra empaque). */
export const TAKEAWAY_MODIFIER = "Para llevar"

/* ---------- KDS ---------- */

export const kdsOrderHeaderSchema = z.object({
  id: z.uuid(),
  order_number: z.string(),
  order_type: z.enum(ORDER_TYPES),
  table_code: z.string().nullable(),
  waiter_name: z.string().nullable(),
  customer_name: z.string().nullable(),
})

export const kdsItemSchema = z.object({
  id: z.uuid(),
  name: z.string(),
  production_area: z.enum(PRODUCTION_AREAS),
  quantity: decimal,
  modifiers: z.array(z.string()),
  notes: z.string().nullable(),
  status: z.enum(ITEM_STATUSES),
  sent_to_kitchen_at: isoDate.nullable(),
  ready_at: isoDate.nullable(),
})

export type KdsItem = z.infer<typeof kdsItemSchema>

export const kdsTicketSchema = z.object({
  order: kdsOrderHeaderSchema,
  sent_at: isoDate.nullable(),
  items: z.array(kdsItemSchema),
})

export type KdsTicket = z.infer<typeof kdsTicketSchema>

/* ---------- Eventos WebSocket (Reverb) ---------- */

/** private-company.{uuid}.kds -> .kds.order.sent */
export const orderSentEventSchema = z.object({
  order: kdsOrderHeaderSchema,
  sent_at: isoDate,
  areas: z.partialRecord(z.enum(PRODUCTION_AREAS), z.array(kdsItemSchema)),
})

/** private-company.{uuid}(.kds) -> .kds.item.status */
export const itemStatusEventSchema = z.object({
  order_id: z.uuid(),
  table_code: z.string().nullable(),
  area: z.enum(PRODUCTION_AREAS),
  item_ids: z.array(z.uuid()),
  status: z.enum(ITEM_STATUSES),
})

export type ItemStatusEvent = z.infer<typeof itemStatusEventSchema>

/** private-company.{uuid} -> .table.updated */
export const tableUpdatedEventSchema = z.object({ table: tableSchema })

/** private-company.{uuid} -> .order.updated */
export const orderUpdatedEventSchema = z.object({ order_id: z.uuid(), status: z.enum(ORDER_STATUSES), total_amount: decimal })

/* ---------- Caja y comprobantes ---------- */

export const DOCUMENT_TYPES = ["01", "03", "00"] as const
export type DocumentType = (typeof DOCUMENT_TYPES)[number]

export const cashContextSchema = z.object({
  session: z
    .object({
      id: z.uuid(),
      status: z.enum(["OPEN", "CLOSED"]),
      opening_amount: decimal,
      opened_at: isoDate,
      cash_register: z.object({ id: z.uuid(), code: z.string(), name: z.string() }),
    })
    .nullable(),
  registers: z.array(z.object({ id: z.uuid(), code: z.string(), name: z.string() })),
  series: z.array(z.object({ series: z.string(), document_type: z.enum(DOCUMENT_TYPES), label: z.string(), next_number: z.string() })),
})

export type CashContext = z.infer<typeof cashContextSchema>

export const PAYMENT_METHODS = ["CASH", "YAPE", "PLIN", "CARD"] as const
export type PaymentMethod = (typeof PAYMENT_METHODS)[number]

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
  CASH: "Efectivo",
  YAPE: "Yape",
  PLIN: "Plin",
  CARD: "Tarjeta",
}

export const CONSUMPTION_FORMATS = ["DETAILED", "BY_CONSUMPTION"] as const
export type ConsumptionFormat = (typeof CONSUMPTION_FORMATS)[number]

export const CHARGE_MODES = ["FULL", "ITEMS", "AMOUNT"] as const
export type ChargeMode = (typeof CHARGE_MODES)[number]

export const CUSTOMER_DOCUMENT_TYPES = ["0", "1", "4", "6", "7"] as const
export type CustomerDocumentType = (typeof CUSTOMER_DOCUMENT_TYPES)[number]

export const SALE_DOCUMENT_TYPES = ["01", "03", "07", "00"] as const
export type SaleDocumentType = (typeof SALE_DOCUMENT_TYPES)[number]

export const SUNAT_STATUSES = ["NOT_APPLICABLE", "PENDING", "ACCEPTED", "OBSERVED", "REJECTED"] as const
export type SunatStatus = (typeof SUNAT_STATUSES)[number]

export const TAX_AFFECTATION_TYPES = ["10", "20", "30"] as const
export type TaxAffectationType = (typeof TAX_AFFECTATION_TYPES)[number]

const relatedDocumentSchema = z.object({ id: z.uuid(), document_type: z.enum(SALE_DOCUMENT_TYPES), document_number: z.string() })

export const saleSchema = z.object({
  id: z.uuid(),
  document_type: z.enum(SALE_DOCUMENT_TYPES),
  document_label: z.string(),
  document_number: z.string(),
  consumption_format: z.enum(CONSUMPTION_FORMATS),
  charge_mode: z.enum(CHARGE_MODES),
  issued_at: isoDate,
  issuer: z.object({ ruc: z.string(), business_name: z.string(), trade_name: z.string().nullable(), fiscal_address: z.string().nullable() }),
  customer: z.object({ document_type: z.string(), document_number: z.string().nullable(), name: z.string().nullable() }),
  order: z
    .object({ id: z.uuid(), order_number: z.string(), table_code: z.string().nullable(), status: z.enum(ORDER_STATUSES), balance: decimal })
    .nullable(),
  totals: z.object({
    igv_rate: decimal,
    taxable_amount: decimal,
    exempt_amount: decimal,
    unaffected_amount: decimal,
    igv_amount: decimal,
    service_charge: decimal,
    total_amount: decimal,
  }),
  items: z.array(
    z.object({
      description: z.string(),
      unit_code: z.string(),
      quantity: decimal,
      unit_price: decimal,
      tax_affectation_type: z.enum(TAX_AFFECTATION_TYPES),
      line_total: decimal,
    }),
  ),
  payments: z.array(
    z.object({
      method: z.enum(PAYMENT_METHODS),
      amount: decimal,
      received_amount: decimal.nullable(),
      change_amount: decimal,
      reference: z.string().nullable(),
    }),
  ),
  credit_note_reason: z.object({ code: z.enum(["01", "02", "06"]), label: z.string(), description: z.string().nullable() }).nullable(),
  related_document: relatedDocumentSchema.nullable().optional(),
  credit_note: z.object({ id: z.uuid(), document_number: z.string() }).nullable().optional(),
  sunat: z.object({
    status: z.enum(SUNAT_STATUSES),
    hash: z.string().nullable(),
    response_code: z.string().nullable(),
    response_description: z.string().nullable(),
    qr_payload: z.string().nullable(),
    has_xml: z.boolean(),
    has_cdr: z.boolean(),
  }),
})

export type Sale = z.infer<typeof saleSchema>

/** Fila del registro de comprobantes: sin líneas ni pagos (GET /sales). */
export const saleSummarySchema = saleSchema.extend({
  items: saleSchema.shape.items.optional(),
  payments: saleSchema.shape.payments.optional(),
})

export type SaleSummary = z.infer<typeof saleSummarySchema>

/** Payload de POST /orders/{order}/charges (contrato de ChargeOrderRequest). */
export interface ChargePayload {
  readonly mode: ChargeMode
  readonly series: string
  readonly format: ConsumptionFormat
  readonly amount?: string
  readonly lines?: readonly { readonly order_item_id: string; readonly quantity: string }[]
  readonly customer: {
    readonly document_type: CustomerDocumentType
    readonly document_number?: string
    readonly name?: string
  }
  readonly payments: readonly {
    readonly method: PaymentMethod
    readonly amount: string
    readonly received_amount?: string
    readonly reference?: string
  }[]
}

export interface OrderLinePayload {
  readonly menu_item_id: string
  readonly quantity: string
  readonly modifiers: readonly string[]
  readonly notes: string | null
}
