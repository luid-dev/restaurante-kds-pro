import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { CheckCircle2, LoaderCircle, Printer, Receipt } from "lucide-react"
import { useMemo, useState } from "react"
import { toast } from "sonner"

import { chargeOrder, operationKeys, operationQueries, quoteCharge, type QuotePayload } from "@/api/operations"
import { Amount } from "@/components/shared/amount"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Skeleton } from "@/components/ui/skeleton"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { DocumentSection, ItemsSelector, PaymentsSection, Section, Segmented, type CustomerDraft } from "@/features/cashier/charge-form-sections"
import { checkPayments, newPayment, toPaymentPayload, type PaymentDraft } from "@/features/cashier/payment-drafts"
import { PreBillTicket } from "@/features/printing/pre-bill-ticket"
import { usePrintStore } from "@/features/printing/print-store"
import { SunatReceipt } from "@/features/printing/sunat-receipt"
import { useActiveSession } from "@/hooks/use-active-session"
import { isValidDni, isValidRuc, RECEIPT_IDENTIFICATION_THRESHOLD_CENTS } from "@/lib/billing"
import { fromCents, splitEvenly, toCents } from "@/lib/money"
import { cn } from "@/lib/utils"
import type { CashContext, ChargePayload, ConsumptionFormat, Order, Sale } from "@/types/operations"

type Scope = "FULL" | "ITEMS" | "EQUAL"

const PART_OPTIONS = [2, 3, 4, 5, 6, 8] as const

interface CashierCheckoutModalProps {
  readonly orderId: string
  readonly context: CashContext
  readonly onClose: () => void
}

/**
 * Caja: pre-cuenta, cuenta completa o dividida (por platos o partes iguales),
 * comprobante SUNAT detallado o por consumo y pago mixto. El total exacto de cada
 * cobro lo cotiza el backend; los pagos deben cuadrar al céntimo.
 */
export function CashierCheckoutModal({ orderId, context, onClose }: CashierCheckoutModalProps) {
  const order = useQuery(operationQueries.order(orderId))

  return (
    <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="flex max-h-[94vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-6xl">
        <DialogHeader className="border-b px-5 py-3.5">
          <DialogTitle className="flex items-center gap-3">
            <Receipt className="size-4 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
            Cobro <span className="font-mono tabular-nums">{order.data?.table?.code ?? order.data?.order_number ?? ""}</span>
          </DialogTitle>
          <DialogDescription>
            {order.data === undefined ? "Cargando cuenta" : `${order.data.order_number} · Saldo pendiente S/ ${order.data.totals.balance}`}
          </DialogDescription>
        </DialogHeader>
        {order.data === undefined ? (
          <div className="grid gap-4 p-5 lg:grid-cols-2">
            <Skeleton className="h-96" />
            <Skeleton className="h-96" />
          </div>
        ) : (
          <CheckoutBody order={order.data} context={context} onClose={onClose} />
        )}
      </DialogContent>
    </Dialog>
  )
}

function CheckoutBody({ order, context, onClose }: { readonly order: Order; readonly context: CashContext; readonly onClose: () => void }) {
  const queryClient = useQueryClient()
  const session = useActiveSession()
  const print = usePrintStore((state) => state.print)
  const defaultSeries = context.series.find((series) => series.document_type === "03")?.series ?? context.series[0]?.series ?? ""

  const [scope, setScope] = useState<Scope>("FULL")
  const [selected, setSelected] = useState<Readonly<Record<string, number>>>({})
  const [parts, setParts] = useState(2)
  const [remainingParts, setRemainingParts] = useState(2)
  const [series, setSeries] = useState(defaultSeries)
  const [format, setFormat] = useState<ConsumptionFormat>("DETAILED")
  const [customer, setCustomer] = useState<CustomerDraft>({ documentNumber: "", name: "" })
  const [payments, setPayments] = useState<readonly PaymentDraft[]>(() => [newPayment("CASH")])
  const [issued, setIssued] = useState<Sale | null>(null)

  const balanceCents = toCents(order.totals.balance)
  const effectiveFormat: ConsumptionFormat = scope === "EQUAL" ? "BY_CONSUMPTION" : format
  const documentType = context.series.find((candidate) => candidate.series === series)?.document_type

  const quotePayload = useMemo<QuotePayload | null>(() => {
    if (scope === "FULL") {
      return { mode: "FULL", format: effectiveFormat }
    }

    if (scope === "EQUAL") {
      const amount = splitEvenly(balanceCents, Math.max(1, remainingParts))[0] ?? 0

      return amount > 0 ? { mode: "AMOUNT", format: effectiveFormat, amount: fromCents(amount) } : null
    }

    const lines = Object.entries(selected)
      .filter(([, hundredths]) => hundredths > 0)
      .map(([itemId, hundredths]) => ({ order_item_id: itemId, quantity: fromCents(hundredths) }))

    return lines.length === 0 ? null : { mode: "ITEMS", format: effectiveFormat, lines }
  }, [scope, effectiveFormat, balanceCents, remainingParts, selected])

  const quote = useQuery({
    queryKey: [...operationKeys.order(order.id), "quote", quotePayload, order.totals.balance] as const,
    queryFn: ({ signal }) => quoteCharge(order.id, quotePayload ?? { mode: "FULL", format: effectiveFormat }, signal),
    enabled: quotePayload !== null && issued === null && balanceCents > 0,
    placeholderData: keepPreviousData,
    staleTime: 0,
  })

  const totalCents = quote.data === undefined || quotePayload === null ? 0 : toCents(quote.data.total)
  const check = checkPayments(payments, totalCents)
  const customerError = validateCustomer(documentType, customer, totalCents)

  const charge = useMutation({
    mutationFn: (payload: ChargePayload) => chargeOrder(order.id, payload),
    onSuccess: (sale) => {
      setIssued(sale)
      toast.success(`${sale.document_label} ${sale.document_number} emitida.`)
      void queryClient.invalidateQueries({ queryKey: operationKeys.order(order.id) })
      void queryClient.invalidateQueries({ queryKey: operationKeys.floorPlan() })
      void queryClient.invalidateQueries({ queryKey: operationKeys.cash() })
    },
    onError: (error) => {
      toast.error(error.message)
      void queryClient.invalidateQueries({ queryKey: operationKeys.order(order.id) })
    },
  })

  function submit() {
    if (quotePayload === null || !check.valid || customerError !== null) {
      return
    }

    const documentNumber = customer.documentNumber.trim()
    const name = customer.name.trim()

    charge.mutate({
      ...quotePayload,
      series,
      customer:
        documentType === "01"
          ? { document_type: "6", document_number: documentNumber, name }
          : documentType === "03" && documentNumber !== ""
            ? { document_type: "1", document_number: documentNumber, ...(name === "" ? {} : { name }) }
            : { document_type: "0" },
      payments: toPaymentPayload(payments, check.amounts),
    })
  }

  function nextCharge() {
    setIssued(null)
    setSelected({})
    setPayments([newPayment("CASH")])
    setCustomer({ documentNumber: "", name: "" })
    if (scope === "EQUAL") {
      setRemainingParts((value) => Math.max(1, value - 1))
    }
  }

  const settled = issued !== null && issued.order?.balance === "0.00"

  return (
    <div className="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,23rem)_minmax(0,1fr)]">
      <ScrollArea className="min-h-0 border-b bg-muted/40 lg:border-r lg:border-b-0">
        <div className="flex flex-col items-center gap-3 p-4">
          <div className="flex w-full items-center justify-between">
            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{issued === null ? "Pre-cuenta de mesa" : "Comprobante emitido"}</span>
            <Button
              size="sm"
              variant="outline"
              onClick={() => print(issued === null ? <PreBillTicket order={order} company={session.user.company} /> : <SunatReceipt sale={issued} />)}
            >
              <Printer aria-hidden="true" />
              Imprimir 80 mm
            </Button>
          </div>
          <div className="rounded-sm border bg-white">
            {issued === null ? <PreBillTicket order={order} company={session.user.company} /> : <SunatReceipt sale={issued} />}
          </div>
        </div>
      </ScrollArea>

      <ScrollArea className="min-h-0">
        {issued !== null ? (
          <div className="flex flex-col gap-4 p-6">
            <div className="flex items-start gap-3 rounded-md border px-4 py-3">
              <CheckCircle2 className="mt-0.5 size-4 text-status-occupied" strokeWidth={1.5} aria-hidden="true" />
              <div className="text-sm">
                <p className="font-medium">
                  {issued.document_label} <span className="font-mono tabular-nums">{issued.document_number}</span> por <Amount value={issued.totals.total_amount} />
                </p>
                <p className="text-muted-foreground">
                  {settled ? "Cuenta liquidada: la mesa quedó libre." : `Saldo pendiente S/ ${issued.order?.balance ?? "-"}.`}
                  {issued.sunat.status === "PENDING" ? " XML firmado; envío a SUNAT en cola." : null}
                </p>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" onClick={() => print(<SunatReceipt sale={issued} />)}>
                <Printer aria-hidden="true" />
                Imprimir comprobante
              </Button>
              {settled ? (
                <Button onClick={onClose}>Cerrar</Button>
              ) : (
                <Button onClick={nextCharge}>Cobrar siguiente comensal</Button>
              )}
            </div>
          </div>
        ) : (
          <div className="flex flex-col">
            <Section title="Qué se cobra">
              <Tabs value={scope} onValueChange={(value) => setScope(value === "ITEMS" || value === "EQUAL" ? value : "FULL")}>
                <TabsList className="h-9 w-full sm:w-auto">
                  <TabsTrigger value="FULL">Cuenta completa</TabsTrigger>
                  <TabsTrigger value="ITEMS">Dividir por platos</TabsTrigger>
                  <TabsTrigger value="EQUAL">Partes iguales</TabsTrigger>
                </TabsList>
              </Tabs>
              {scope === "ITEMS" ? (
                <ItemsSelector items={order.items ?? []} selected={selected} onChange={(itemId, hundredths) => setSelected((current) => ({ ...current, [itemId]: hundredths }))} />
              ) : null}
              {scope === "EQUAL" ? (
                <div className="flex flex-wrap items-center gap-3">
                  <Segmented
                    label="Cantidad de partes"
                    value={String(parts)}
                    onChange={(value) => {
                      setParts(Number(value))
                      setRemainingParts(Number(value))
                    }}
                    options={PART_OPTIONS.map((count) => ({ value: String(count), label: `${String(count)} partes` }))}
                  />
                  <span className="font-mono text-xs text-muted-foreground tabular-nums">
                    Parte {parts - remainingParts + 1} de {parts}: {splitEvenly(balanceCents, Math.max(1, remainingParts)).map(fromCents).join(" / ")}
                  </span>
                </div>
              ) : null}
              <dl className="grid grid-cols-3 gap-px overflow-hidden rounded-md border bg-border text-sm">
                <QuoteCell label="Consumo" value={quote.data?.gross} loading={quote.isFetching} />
                <QuoteCell label="Recargo" value={quote.data?.service_charge} loading={quote.isFetching} />
                <QuoteCell label="A cobrar" value={quotePayload === null ? undefined : quote.data?.total} loading={quote.isFetching} strong />
              </dl>
              {quote.isError ? <p className="text-xs text-status-delayed">{quote.error.message}</p> : null}
            </Section>

            <DocumentSection
              context={context}
              series={series}
              onSeries={setSeries}
              format={effectiveFormat}
              onFormat={setFormat}
              detailedAllowed={scope !== "EQUAL"}
              customer={customer}
              onCustomer={setCustomer}
              customerError={customerError}
            />

            <PaymentsSection payments={payments} totalCents={totalCents} check={check} onChange={setPayments} />

            <div className="sticky bottom-0 border-t bg-background px-5 py-3">
              <Button size="lg" className="h-12 w-full justify-between" disabled={!check.valid || customerError !== null || quotePayload === null || charge.isPending || quote.isFetching} onClick={submit}>
                <span className="flex items-center gap-2">
                  {charge.isPending ? <LoaderCircle className="animate-spin" aria-hidden="true" /> : <Receipt aria-hidden="true" />}
                  Cobrar y emitir {series}
                </span>
                <span className="font-mono tabular-nums">S/ {fromCents(totalCents)}</span>
              </Button>
            </div>
          </div>
        )}
      </ScrollArea>
    </div>
  )
}

function QuoteCell({ label, value, loading, strong = false }: { readonly label: string; readonly value: string | undefined; readonly loading: boolean; readonly strong?: boolean }) {
  return (
    <div className="flex flex-col gap-0.5 bg-background px-3 py-2">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className={cn(strong && "text-base font-semibold")}>
        {value === undefined ? <span className="font-mono text-muted-foreground">-</span> : <Amount value={value} className={cn(loading && "opacity-50")} />}
      </dd>
    </div>
  )
}

function validateCustomer(documentType: string | undefined, customer: CustomerDraft, totalCents: number): string | null {
  const documentNumber = customer.documentNumber.trim()

  if (documentType === "01") {
    if (!isValidRuc(documentNumber)) {
      return "Ingrese un RUC válido de 11 dígitos."
    }

    return customer.name.trim() === "" ? "Ingrese la razón social para la factura." : null
  }

  if (documentType === "03") {
    if (documentNumber !== "" && !isValidDni(documentNumber)) {
      return "El DNI debe tener 8 dígitos."
    }

    if (documentNumber === "" && totalCents > RECEIPT_IDENTIFICATION_THRESHOLD_CENTS) {
      return "Boletas mayores a S/ 700.00 requieren el DNI del cliente."
    }
  }

  return null
}
