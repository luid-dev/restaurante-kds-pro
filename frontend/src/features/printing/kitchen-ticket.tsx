import { ThermalPaper, ThermalRule } from "@/features/printing/thermal-paper"
import { formatTicketDateTime } from "@/lib/format"
import { formatQuantity } from "@/lib/money"
import { PRODUCTION_AREA_LABELS, type KdsTicket } from "@/types/operations"

/** Comanda de cocina: letra grande, solo cantidades, indicaciones y notas (sin precios). */
export function KitchenTicket({ ticket }: { readonly ticket: KdsTicket }) {
  const areas = [...new Set(ticket.items.map((item) => item.production_area))]

  return (
    <ThermalPaper>
      <p className="text-center text-[15px] font-bold">{ticket.order.table_code ?? ticket.order.order_type}</p>
      <p className="text-center">{ticket.order.order_number}</p>
      <p className="text-center">{areas.map((area) => PRODUCTION_AREA_LABELS[area]).join(" / ")}</p>
      <ThermalRule />
      <p>Mozo: {ticket.order.waiter_name ?? "-"}</p>
      {ticket.order.customer_name === null ? null : <p>Cliente: {ticket.order.customer_name}</p>}
      <p className="tabular-nums">Envío: {ticket.sent_at === null ? "-" : formatTicketDateTime(new Date(ticket.sent_at))}</p>
      <ThermalRule />
      <ul className="space-y-1">
        {ticket.items.map((item) => (
          <li key={item.id}>
            <p className="text-[13px] font-bold">
              {formatQuantity(item.quantity)} x {item.name}
            </p>
            {item.modifiers.length === 0 ? null : <p className="pl-3">[{item.modifiers.join("] [")}]</p>}
            {item.notes === null ? null : <p className="pl-3">Nota: {item.notes}</p>}
          </li>
        ))}
      </ul>
      <ThermalRule />
    </ThermalPaper>
  )
}
