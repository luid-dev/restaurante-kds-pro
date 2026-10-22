import type { SaleDocumentType, SunatStatus } from "@/types/operations"
import type { StatusTone } from "@/types/realtime"

export const SUNAT_STATUS_META: Record<SunatStatus, { readonly label: string; readonly tone: StatusTone }> = {
  NOT_APPLICABLE: { label: "Documento interno", tone: "muted" },
  PENDING: { label: "Pendiente de envío", tone: "warning" },
  ACCEPTED: { label: "Aceptado (CDR)", tone: "success" },
  OBSERVED: { label: "Aceptado con observaciones", tone: "warning" },
  REJECTED: { label: "Rechazado", tone: "danger" },
}

export const DOCUMENT_SHORT_LABELS: Record<SaleDocumentType, string> = {
  "01": "Factura",
  "03": "Boleta",
  "07": "Nota de crédito",
  "00": "Nota de venta",
}
