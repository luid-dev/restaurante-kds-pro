import { lineCents, toCents } from "@/lib/money"
import { TAKEAWAY_MODIFIER, type MenuItem, type OrderLinePayload, type OrderType } from "@/types/operations"

/** Plato aún no enviado a cocina (vive solo en la terminal del mozo). */
export interface DraftLine {
  readonly key: string
  readonly menuItem: MenuItem
  readonly quantity: number
  readonly modifiers: readonly string[]
  readonly notes: string | null
}

export function usesPackaging(line: DraftLine, orderType: OrderType): boolean {
  return orderType !== "DINE_IN" || line.modifiers.includes(TAKEAWAY_MODIFIER)
}

/** Estimado de la ronda en céntimos (el total oficial lo calcula el backend). */
export function draftCents(lines: readonly DraftLine[], orderType: OrderType): number {
  return lines.reduce((sum, line) => {
    const quantity = `${String(line.quantity)}.00`
    const packaging = usesPackaging(line, orderType) ? lineCents(line.menuItem.packaging_surcharge, quantity) : 0

    return sum + lineCents(line.menuItem.sale_price, quantity) + packaging
  }, 0)
}

export function toPayload(lines: readonly DraftLine[]): OrderLinePayload[] {
  return lines.map((line) => ({
    menu_item_id: line.menuItem.id,
    quantity: String(line.quantity),
    modifiers: line.modifiers,
    notes: line.notes,
  }))
}

/** Dos líneas con el mismo plato, indicaciones y nota se agrupan sumando cantidades. */
export function mergeLine(lines: readonly DraftLine[], line: DraftLine): DraftLine[] {
  const signature = (candidate: DraftLine) => `${candidate.menuItem.id}|${[...candidate.modifiers].sort().join(",")}|${candidate.notes ?? ""}`
  const existing = lines.find((candidate) => signature(candidate) === signature(line))

  if (existing === undefined) {
    return [...lines, line]
  }

  return lines.map((candidate) => (candidate === existing ? { ...candidate, quantity: Math.min(99, candidate.quantity + line.quantity) } : candidate))
}

export function hasSurcharge(item: MenuItem): boolean {
  return toCents(item.packaging_surcharge) > 0
}
