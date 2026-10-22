import { ThermalPaper, ThermalRow, ThermalRule } from "@/features/printing/thermal-paper"
import { useNow } from "@/hooks/use-now"
import { formatTicketDateTime } from "@/lib/format"
import { formatQuantity } from "@/lib/money"
import type { Company } from "@/types/auth"
import type { Order } from "@/types/operations"

/**
 * Precuenta de salón: detalle interno de consumo (también respalda el comprobante
 * "Por consumo") y espacio para que el cliente anote RUC y razón social.
 */
export function PreBillTicket({ order, company }: { readonly order: Order; readonly company: Company }) {
  const items = (order.items ?? []).filter((item) => item.status !== "VOIDED")
  const printedAt = useNow(30_000)

  return (
    <ThermalPaper>
      <p className="text-center text-[13px] font-bold">{company.display_name}</p>
      <p className="text-center">RUC {company.ruc}</p>
      <p className="mt-1 text-center font-bold">PRE-CUENTA (NO ES COMPROBANTE DE PAGO)</p>
      <ThermalRule />
      <ThermalRow label={order.table === null || order.table === undefined ? order.order_type : `Mesa ${order.table.code}`} value={order.order_number} />
      <ThermalRow label="Comensales" value={order.diners_count} />
      <ThermalRow label="Mozo" value={order.waiter?.name ?? "-"} />
      <ThermalRow label="Fecha" value={formatTicketDateTime(printedAt)} />
      <ThermalRule />
      {items.map((item) => (
        <div key={item.id}>
          <ThermalRow label={`${formatQuantity(item.quantity)} x ${item.menu_item.name}`} value={item.line_total} />
          {item.modifiers.length === 0 ? null : <p className="pl-3 text-[10px]">[{item.modifiers.join("] [")}]</p>}
        </div>
      ))}
      <ThermalRule />
      {order.totals.packaging_total === "0.00" ? null : <ThermalRow label="Empaque para llevar" value={order.totals.packaging_total} />}
      <ThermalRow label="Op. gravada + IGV" value={order.totals.subtotal} />
      <ThermalRow label="IGV incluido" value={order.totals.tax_amount} />
      {order.totals.service_charge === "0.00" ? null : <ThermalRow label="Recargo al consumo" value={order.totals.service_charge} />}
      <ThermalRow label="TOTAL S/" value={order.totals.total_amount} strong />
      {order.totals.paid_amount === "0.00" ? null : (
        <>
          <ThermalRow label="Pagado" value={order.totals.paid_amount} />
          <ThermalRow label="SALDO S/" value={order.totals.balance} strong />
        </>
      )}
      <ThermalRule />
      <p>Comprobante:  [ ] Boleta   [ ] Factura</p>
      <p className="mt-2">RUC / DNI: ______________________</p>
      <p className="mt-2">Razón social: ___________________</p>
      <p className="mt-1">______________________________</p>
      <ThermalRule />
      <p className="text-center">Gracias por su preferencia</p>
    </ThermalPaper>
  )
}
