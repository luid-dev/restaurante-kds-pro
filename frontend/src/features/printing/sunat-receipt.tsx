import { QRCodeSVG } from "qrcode.react"

import { ThermalPaper, ThermalRow, ThermalRule } from "@/features/printing/thermal-paper"
import { formatTicketDateTime } from "@/lib/format"
import { formatQuantity } from "@/lib/money"
import { PAYMENT_METHOD_LABELS, type Sale } from "@/types/operations"

const CUSTOMER_DOCUMENT_LABELS: Record<string, string> = { "0": "DOC", "1": "DNI", "4": "CE", "6": "RUC", "7": "PAS" }

/**
 * Representación impresa del comprobante electrónico (o nota de venta) con el
 * hash de la firma (DigestValue) y el QR en el formato que exige SUNAT.
 */
export function SunatReceipt({ sale }: { readonly sale: Sale }) {
  const electronic = sale.document_type !== "00"

  return (
    <ThermalPaper>
      <p className="text-center text-[13px] font-bold">{sale.issuer.trade_name ?? sale.issuer.business_name}</p>
      <p className="text-center">{sale.issuer.business_name}</p>
      <p className="text-center">RUC {sale.issuer.ruc}</p>
      {sale.issuer.fiscal_address === null ? null : <p className="text-center">{sale.issuer.fiscal_address}</p>}
      <ThermalRule />
      <p className="text-center font-bold uppercase">{sale.document_label}</p>
      <p className="text-center text-[13px] font-bold tabular-nums">{sale.document_number}</p>
      <ThermalRule />
      <ThermalRow label="Emisión" value={formatTicketDateTime(new Date(sale.issued_at))} />
      <ThermalRow label={CUSTOMER_DOCUMENT_LABELS[sale.customer.document_type] ?? "DOC"} value={sale.customer.document_number ?? "-"} />
      <p>{sale.customer.name ?? "CLIENTES VARIOS"}</p>
      {sale.order === null ? null : <ThermalRow label={sale.order.table_code === null ? "Pedido" : `Mesa ${sale.order.table_code}`} value={sale.order.order_number} />}
      {sale.related_document === null || sale.related_document === undefined ? null : (
        <>
          <ThermalRow label="Doc. que modifica" value={sale.related_document.document_number} />
          {sale.credit_note_reason === null ? null : (
            <p>
              Motivo {sale.credit_note_reason.code}: {sale.credit_note_reason.label}
              {sale.credit_note_reason.description === null ? null : ` - ${sale.credit_note_reason.description}`}
            </p>
          )}
        </>
      )}
      <ThermalRule />
      {sale.items.map((item, index) => (
        <div key={`${item.description}-${String(index)}`}>
          <p>{item.description}</p>
          <ThermalRow label={`${formatQuantity(item.quantity)} ${item.unit_code} x ${item.unit_price}`} value={item.line_total} />
        </div>
      ))}
      <ThermalRule />
      <ThermalRow label="Op. gravada" value={sale.totals.taxable_amount} />
      {sale.totals.exempt_amount === "0.00" ? null : <ThermalRow label="Op. exonerada" value={sale.totals.exempt_amount} />}
      {sale.totals.unaffected_amount === "0.00" ? null : <ThermalRow label="Op. inafecta" value={sale.totals.unaffected_amount} />}
      <ThermalRow label={`IGV ${sale.totals.igv_rate}%`} value={sale.totals.igv_amount} />
      {sale.totals.service_charge === "0.00" ? null : <ThermalRow label="Recargo al consumo" value={sale.totals.service_charge} />}
      <ThermalRow label="IMPORTE TOTAL S/" value={sale.totals.total_amount} strong />
      <ThermalRule />
      {sale.payments.map((payment, index) => (
        <div key={`${payment.method}-${String(index)}`}>
          <ThermalRow label={PAYMENT_METHOD_LABELS[payment.method]} value={payment.amount} />
          {payment.received_amount === null ? null : (
            <>
              <ThermalRow label="  Recibido" value={payment.received_amount} />
              <ThermalRow label="  Vuelto" value={payment.change_amount} />
            </>
          )}
          {payment.reference === null ? null : <ThermalRow label="  Operación" value={payment.reference} />}
        </div>
      ))}
      {electronic ? (
        <>
          <ThermalRule />
          {sale.sunat.qr_payload === null ? null : (
            <div className="flex justify-center py-1">
              <QRCodeSVG value={sale.sunat.qr_payload} size={112} level="M" marginSize={0} />
            </div>
          )}
          <p className="break-all text-center text-[10px]">Hash: {sale.sunat.hash ?? "-"}</p>
          <p className="text-center text-[10px]">Representación impresa del comprobante electrónico. Consulte en www.sunat.gob.pe</p>
        </>
      ) : (
        <p className="mt-1 text-center text-[10px]">Documento interno. Canjeable por boleta o factura.</p>
      )}
    </ThermalPaper>
  )
}
