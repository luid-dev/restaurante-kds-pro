import type { ReactNode } from "react"
import { Banknote, CreditCard, Minus, Plus, Smartphone, Trash2, type LucideIcon } from "lucide-react"

import { Amount } from "@/components/shared/amount"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { addPayment, isAmount, type PaymentCheck, type PaymentDraft } from "@/features/cashier/payment-drafts"
import { fromCents, formatQuantity, toCents } from "@/lib/money"
import { cn } from "@/lib/utils"
import { PAYMENT_METHOD_LABELS, PAYMENT_METHODS, type CashContext, type ConsumptionFormat, type OrderItem, type PaymentMethod } from "@/types/operations"

export function Section({ title, children, aside }: { readonly title: string; readonly children: ReactNode; readonly aside?: ReactNode }) {
  return (
    <section className="flex flex-col gap-2.5 border-b px-5 py-4 last:border-b-0">
      <header className="flex items-center justify-between gap-2">
        <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{title}</h3>
        {aside}
      </header>
      {children}
    </section>
  )
}

export function Segmented<TValue extends string>({
  value,
  options,
  onChange,
  label,
}: {
  readonly value: TValue
  readonly options: readonly { readonly value: TValue; readonly label: string; readonly disabled?: boolean }[]
  readonly onChange: (value: TValue) => void
  readonly label: string
}) {
  return (
    <fieldset className="flex flex-wrap gap-1.5 m-0 min-w-0 border-0 p-0">
      <legend className="sr-only">{label}</legend>
      {options.map((option) => (
        <button
          key={option.value}
          type="button"
          aria-pressed={value === option.value}
          disabled={option.disabled}
          onClick={() => onChange(option.value)}
          className={cn(
            "h-9 rounded-md border px-3 font-mono text-xs transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40",
            value === option.value && "border-foreground bg-foreground text-background hover:bg-foreground/90",
          )}
        >
          [{option.label}]
        </button>
      ))}
    </fieldset>
  )
}

/* ---------- Dividir por platos ---------- */

interface ItemsSelectorProps {
  readonly items: readonly OrderItem[]
  /** Cantidad elegida por plato en centésimas (100 = 1 unidad). */
  readonly selected: Readonly<Record<string, number>>
  readonly onChange: (itemId: string, hundredths: number) => void
}

export function ItemsSelector({ items, selected, onChange }: ItemsSelectorProps) {
  const payable = items.filter((item) => item.status !== "VOIDED" && toCents(item.unpaid_quantity) > 0)

  if (payable.length === 0) {
    return <p className="text-sm text-muted-foreground">No quedan platos pendientes de cobro.</p>
  }

  return (
    <div className="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Plato</TableHead>
            <TableHead className="w-20 text-right">Pendiente</TableHead>
            <TableHead className="w-36 text-center">Paga este comensal</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {payable.map((item) => {
            const unpaid = toCents(item.unpaid_quantity)
            const value = selected[item.id] ?? 0
            const step = Math.min(100, unpaid)

            return (
              <TableRow key={item.id} className={cn(value > 0 && "bg-accent/40")}>
                <TableCell className="whitespace-normal">
                  {item.menu_item.name}
                  <span className="block text-xs text-muted-foreground">
                    <Amount value={item.unit_price} /> c/u
                  </span>
                </TableCell>
                <TableCell className="text-right font-mono tabular-nums">{formatQuantity(item.unpaid_quantity)}</TableCell>
                <TableCell>
                  <div className="flex items-center justify-center gap-1">
                    <Button size="icon-xs" variant="outline" disabled={value === 0} onClick={() => onChange(item.id, Math.max(0, value - step))} aria-label="Restar">
                      <Minus aria-hidden="true" />
                    </Button>
                    <span className="w-10 text-center font-mono text-sm tabular-nums">{formatQuantity(fromCents(value))}</span>
                    <Button size="icon-xs" variant="outline" disabled={value >= unpaid} onClick={() => onChange(item.id, Math.min(unpaid, value + step))} aria-label="Sumar">
                      <Plus aria-hidden="true" />
                    </Button>
                    <Button size="xs" variant="ghost" className="font-mono" onClick={() => onChange(item.id, value === unpaid ? 0 : unpaid)}>
                      {value === unpaid ? "Quitar" : "Todo"}
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            )
          })}
        </TableBody>
      </Table>
    </div>
  )
}

/* ---------- Comprobante ---------- */

export interface CustomerDraft {
  readonly documentNumber: string
  readonly name: string
}

interface DocumentSectionProps {
  readonly context: CashContext
  readonly series: string
  readonly onSeries: (series: string) => void
  readonly format: ConsumptionFormat
  readonly onFormat: (format: ConsumptionFormat) => void
  readonly detailedAllowed: boolean
  readonly customer: CustomerDraft
  readonly onCustomer: (customer: CustomerDraft) => void
  readonly customerError: string | null
}

export function DocumentSection({ context, series, onSeries, format, onFormat, detailedAllowed, customer, onCustomer, customerError }: DocumentSectionProps) {
  const documentType = context.series.find((candidate) => candidate.series === series)?.document_type
  const shortLabel = (type: string) => (type === "01" ? "Factura" : type === "03" ? "Boleta" : "Nota de Venta")

  return (
    <Section title="Comprobante">
      <Segmented
        label="Serie del comprobante"
        value={series}
        onChange={onSeries}
        options={context.series.map((candidate) => ({ value: candidate.series, label: `${shortLabel(candidate.document_type)} ${candidate.series}` }))}
      />
      <Segmented
        label="Formato SUNAT"
        value={format}
        onChange={onFormat}
        options={[
          { value: "DETAILED", label: "Detallado por Plato", disabled: !detailedAllowed },
          { value: "BY_CONSUMPTION", label: "Por Consumo" },
        ]}
      />
      {documentType === "00" ? (
        <p className="text-xs text-muted-foreground">La nota de venta es un documento interno: no se envía a SUNAT.</p>
      ) : (
        <div className="grid gap-3 sm:grid-cols-[12rem_minmax(0,1fr)]">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="customer-document">{documentType === "01" ? "RUC del cliente" : "DNI (opcional)"}</Label>
            <Input
              id="customer-document"
              value={customer.documentNumber}
              onChange={(event) => onCustomer({ ...customer, documentNumber: event.target.value.replace(/\D/g, "") })}
              maxLength={documentType === "01" ? 11 : 8}
              inputMode="numeric"
              className="font-mono tabular-nums"
              aria-invalid={customerError !== null}
              autoComplete="off"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="customer-name">{documentType === "01" ? "Razón social" : "Nombre (opcional)"}</Label>
            <Input id="customer-name" value={customer.name} onChange={(event) => onCustomer({ ...customer, name: event.target.value })} maxLength={200} autoComplete="off" />
          </div>
          {customerError === null ? null : <p className="text-xs text-status-delayed sm:col-span-2">{customerError}</p>}
        </div>
      )}
    </Section>
  )
}

/* ---------- Pagos mixtos ---------- */

const METHOD_ICONS: Record<PaymentMethod, LucideIcon> = { CASH: Banknote, YAPE: Smartphone, PLIN: Smartphone, CARD: CreditCard }

interface PaymentsSectionProps {
  readonly payments: readonly PaymentDraft[]
  readonly totalCents: number
  readonly check: PaymentCheck
  readonly onChange: (payments: PaymentDraft[]) => void
}

export function PaymentsSection({ payments, totalCents, check, onChange }: PaymentsSectionProps) {
  const update = (id: string, patch: Partial<PaymentDraft>) => onChange(payments.map((payment) => (payment.id === id ? { ...payment, ...patch } : payment)))

  return (
    <Section
      title="Pagos"
      aside={
        <span className={cn("font-mono text-xs tabular-nums", check.remainingCents === 0 ? "text-status-occupied" : "text-status-delayed")}>
          {check.remainingCents === 0 ? "Cuadrado" : `${check.remainingCents > 0 ? "Falta" : "Excede"} S/ ${fromCents(Math.abs(check.remainingCents))}`}
        </span>
      }
    >
      <ul className="flex flex-col gap-2">
        {payments.map((payment, index) => {
          const Icon = METHOD_ICONS[payment.method]
          const amount = check.amounts[index] ?? 0
          const change = payment.method === "CASH" && isAmount(payment.received) ? toCents(payment.received) - amount : null

          return (
            <li key={payment.id} className="grid grid-cols-[minmax(0,1fr)_7rem_auto] items-end gap-2 rounded-md border p-2.5 sm:grid-cols-[9rem_7rem_minmax(0,1fr)_auto]">
              <div className="flex flex-col gap-1.5">
                <Label className="text-xs text-muted-foreground">Medio</Label>
                <div className="flex h-9 items-center gap-2 rounded-md border px-2.5 text-sm">
                  <Icon className="size-4 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
                  {PAYMENT_METHOD_LABELS[payment.method]}
                </div>
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor={`amount-${payment.id}`} className="text-xs text-muted-foreground">
                  Monto S/{payment.amount === null ? " (resto)" : ""}
                </Label>
                <Input
                  id={`amount-${payment.id}`}
                  value={payment.amount ?? fromCents(amount)}
                  onChange={(event) => update(payment.id, { amount: event.target.value })}
                  inputMode="decimal"
                  className="font-mono tabular-nums"
                  aria-invalid={payment.amount !== null && !isAmount(payment.amount)}
                />
              </div>
              {payment.method === "CASH" ? (
                <div className="col-span-3 flex items-end gap-3 sm:col-span-1">
                  <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`received-${payment.id}`} className="text-xs text-muted-foreground">
                      Recibido S/
                    </Label>
                    <Input
                      id={`received-${payment.id}`}
                      value={payment.received}
                      onChange={(event) => update(payment.id, { received: event.target.value })}
                      inputMode="decimal"
                      className="w-28 font-mono tabular-nums"
                      placeholder={fromCents(amount)}
                    />
                  </div>
                  <div className="pb-2 text-sm">
                    <span className="text-muted-foreground">Vuelto </span>
                    <span className={cn("font-mono font-semibold tabular-nums", change !== null && change < 0 && "text-status-delayed")}>
                      {change === null ? "0.00" : fromCents(change)}
                    </span>
                  </div>
                </div>
              ) : (
                <div className="col-span-3 flex flex-col gap-1.5 sm:col-span-1">
                  <Label htmlFor={`reference-${payment.id}`} className="text-xs text-muted-foreground">
                    N.° de operación
                  </Label>
                  <Input
                    id={`reference-${payment.id}`}
                    value={payment.reference}
                    onChange={(event) => update(payment.id, { reference: event.target.value })}
                    maxLength={40}
                    className="font-mono"
                    autoComplete="off"
                  />
                </div>
              )}
              <Button
                size="icon-sm"
                variant="ghost"
                className="row-start-1 self-end sm:row-start-auto"
                disabled={payments.length === 1}
                onClick={() => onChange(payments.filter((candidate) => candidate.id !== payment.id))}
                aria-label="Quitar pago"
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </li>
          )
        })}
      </ul>
      <div className="flex flex-wrap gap-1.5">
        {PAYMENT_METHODS.map((method) => {
          const Icon = METHOD_ICONS[method]

          return (
            <Button key={method} size="sm" variant="outline" disabled={payments.length >= 6} onClick={() => onChange(addPayment(payments, method, totalCents))}>
              <Icon aria-hidden="true" />
              {PAYMENT_METHOD_LABELS[method]}
            </Button>
          )
        })}
      </div>
    </Section>
  )
}
