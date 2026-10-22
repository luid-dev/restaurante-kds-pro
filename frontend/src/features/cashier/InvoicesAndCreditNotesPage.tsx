import { useMutation, useQuery } from "@tanstack/react-query"
import { Download, FileCode2, FileDown, FileMinus2, LoaderCircle, ReceiptText, Search } from "lucide-react"
import { useState, type ReactNode } from "react"
import { toast } from "sonner"

import { reportDownloads, reportQueries } from "@/api/reports"
import { findNavigationItem } from "@/components/layout/navigation"
import { Amount } from "@/components/shared/amount"
import { DateRangePicker } from "@/components/shared/date-range-picker"
import { EmptyPanel } from "@/components/shared/empty-panel"
import { ModuleHeader } from "@/components/shared/module-header"
import { Pager } from "@/components/shared/pager"
import { ReportSection } from "@/components/shared/report-section"
import { StatusDot } from "@/components/shared/status-dot"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { Segmented } from "@/features/cashier/charge-form-sections"
import { CreditNoteDialog } from "@/features/cashier/credit-note-dialog"
import { ReceiptPreviewDialog } from "@/features/cashier/receipt-preview-dialog"
import { DOCUMENT_SHORT_LABELS, SUNAT_STATUS_META } from "@/features/cashier/sunat-status"
import { useCan } from "@/hooks/use-active-session"
import { presetRange, recentTaxPeriods } from "@/lib/dates"
import { formatTicketDateTime } from "@/lib/format"
import { cn } from "@/lib/utils"
import type { SaleDocumentType, SaleSummary } from "@/types/operations"
import type { DateRange } from "@/types/reports"

type DocumentFilter = SaleDocumentType | "ALL"

const DOCUMENT_FILTERS: readonly { readonly value: DocumentFilter; readonly label: string }[] = [
  { value: "ALL", label: "Todos" },
  { value: "01", label: "Facturas" },
  { value: "03", label: "Boletas" },
  { value: "07", label: "Notas de crédito" },
  { value: "00", label: "Notas de venta" },
]

/** Registro de comprobantes emitidos: estado SUNAT, ticket, XML/CDR, nota de crédito y archivo SIRE. */
export default function InvoicesAndCreditNotesPage() {
  const item = findNavigationItem("invoices")
  const canCredit = useCan("sales.credit_note")
  const canExport = useCan("reports.view")
  const [range, setRange] = useState<DateRange>(() => presetRange("month"))
  const [documentType, setDocumentType] = useState<DocumentFilter>("ALL")
  const [search, setSearch] = useState("")
  const [page, setPage] = useState(1)
  const [previewId, setPreviewId] = useState<string | null>(null)
  const [crediting, setCrediting] = useState<SaleSummary | null>(null)
  const periods = recentTaxPeriods(12)
  const [taxPeriod, setTaxPeriod] = useState(periods[0]?.value ?? "")

  const sales = useQuery(reportQueries.sales({ ...range, documentType: documentType === "ALL" ? null : documentType, search, page }))
  const sire = useMutation({
    mutationFn: () => reportDownloads.sire(taxPeriod),
    onSuccess: (filename) => toast.success(`Registro de ventas SIRE generado: ${filename}`),
    onError: (error) => toast.error(error.message),
  })
  const file = useMutation({
    mutationFn: ({ saleId, kind }: { saleId: string; kind: "xml" | "cdr" }) => (kind === "xml" ? reportDownloads.xml(saleId) : reportDownloads.cdr(saleId)),
    onError: (error) => toast.error(error.message),
  })

  return (
    <>
      <ModuleHeader
        item={item}
        actions={
          canExport ? (
            <span className="flex items-center gap-1.5">
              <Select value={taxPeriod} onValueChange={setTaxPeriod}>
                <SelectTrigger size="sm" className="w-40 capitalize" aria-label="Periodo tributario SIRE">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {periods.map((period) => (
                    <SelectItem key={period.value} value={period.value} className="capitalize">
                      <span className="font-mono text-xs tabular-nums">{period.value}</span> {period.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button size="sm" variant="outline" disabled={sire.isPending || taxPeriod === ""} onClick={() => sire.mutate()}>
                {sire.isPending ? <LoaderCircle className="size-3.5 animate-spin" aria-hidden="true" /> : <Download className="size-3.5" strokeWidth={1.5} aria-hidden="true" />}
                SIRE RVIE (.txt)
              </Button>
            </span>
          ) : null
        }
      />

      <div className="flex flex-col gap-4 p-4 md:p-6">
        <div className="flex flex-wrap items-center gap-2">
          <DateRangePicker
            value={range}
            onChange={(next) => {
              setRange(next)
              setPage(1)
            }}
          />
          <Segmented
            label="Tipo de comprobante"
            value={documentType}
            options={DOCUMENT_FILTERS}
            onChange={(value) => {
              setDocumentType(value)
              setPage(1)
            }}
          />
          <div className="relative ml-auto w-full sm:w-64">
            <Label htmlFor="sales-search" className="sr-only">
              Buscar comprobante o cliente
            </Label>
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
            <Input
              id="sales-search"
              value={search}
              placeholder="F001-00000012, RUC o razón social"
              className="h-8 pl-8 font-mono text-xs"
              onChange={(event) => {
                setSearch(event.target.value)
                setPage(1)
              }}
            />
          </div>
        </div>

        {sales.isPending ? (
          <Skeleton className="h-72 w-full" />
        ) : sales.isError ? (
          <p className="text-sm text-status-delayed">{sales.error.message}</p>
        ) : sales.data.data.length === 0 ? (
          <EmptyPanel icon={ReceiptText} title="Sin comprobantes en el rango elegido" description="Las facturas, boletas, notas de venta y notas de crédito emitidas en caja aparecerán aquí con su estado ante SUNAT." />
        ) : (
          <ReportSection title="Comprobantes emitidos">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Emisión</TableHead>
                  <TableHead>Comprobante</TableHead>
                  <TableHead>Cliente</TableHead>
                  <TableHead className="text-right">Total</TableHead>
                  <TableHead>Estado SUNAT</TableHead>
                  <TableHead>Vínculo</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {sales.data.data.map((sale) => {
                  const status = SUNAT_STATUS_META[sale.sunat.status]
                  const creditable = canCredit && (sale.document_type === "01" || sale.document_type === "03") && (sale.credit_note ?? null) === null && sale.sunat.status !== "REJECTED"

                  return (
                    <TableRow key={sale.id} className={cn(sale.credit_note !== null && sale.credit_note !== undefined && "text-muted-foreground")}>
                      <TableCell className="font-mono text-xs tabular-nums whitespace-nowrap">{formatTicketDateTime(new Date(sale.issued_at))}</TableCell>
                      <TableCell className="whitespace-nowrap">
                        <span className="font-mono font-medium tabular-nums text-foreground">{sale.document_number}</span>
                        <span className="ml-2 text-xs text-muted-foreground">{DOCUMENT_SHORT_LABELS[sale.document_type]}</span>
                      </TableCell>
                      <TableCell className="max-w-56">
                        <span className="block truncate">{sale.customer.name ?? "CLIENTES VARIOS"}</span>
                        <span className="font-mono text-[11px] text-muted-foreground">{sale.customer.document_number ?? "-"}</span>
                      </TableCell>
                      <TableCell className={cn("text-right", sale.document_type === "07" && "text-status-delayed")}>
                        {sale.document_type === "07" ? "-" : ""}
                        <Amount value={sale.totals.total_amount} />
                      </TableCell>
                      <TableCell>
                        <span className="flex items-center gap-1.5 text-xs whitespace-nowrap" title={sale.sunat.response_description ?? undefined}>
                          <StatusDot tone={status.tone} />
                          {status.label}
                        </span>
                      </TableCell>
                      <TableCell className="font-mono text-xs whitespace-nowrap">
                        {sale.related_document !== null && sale.related_document !== undefined ? (
                          <span>
                            Modifica {sale.related_document.document_number}
                            {sale.credit_note_reason === null ? null : <span className="ml-1 text-muted-foreground">({sale.credit_note_reason.code})</span>}
                          </span>
                        ) : sale.credit_note !== null && sale.credit_note !== undefined ? (
                          <span className="text-status-delayed">Anulado por {sale.credit_note.document_number}</span>
                        ) : (
                          <span className="text-muted-foreground">-</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <span className="flex justify-end gap-1">
                          <IconAction label="Ver ticket 80 mm" onClick={() => setPreviewId(sale.id)}>
                            <ReceiptText className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
                          </IconAction>
                          <IconAction label="Descargar XML firmado" disabled={!sale.sunat.has_xml || file.isPending} onClick={() => file.mutate({ saleId: sale.id, kind: "xml" })}>
                            <FileCode2 className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
                          </IconAction>
                          <IconAction
                            label={sale.sunat.has_cdr ? "Descargar CDR de SUNAT" : "CDR no disponible: el comprobante aún no se envía a SUNAT"}
                            disabled={!sale.sunat.has_cdr || file.isPending}
                            onClick={() => file.mutate({ saleId: sale.id, kind: "cdr" })}
                          >
                            <FileDown className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
                          </IconAction>
                          {creditable ? (
                            <IconAction label="Emitir nota de crédito (07)" onClick={() => setCrediting(sale)}>
                              <FileMinus2 className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
                            </IconAction>
                          ) : null}
                        </span>
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
            <Pager pagination={sales.data.meta.pagination} onPage={setPage} label="Páginas de comprobantes" />
          </ReportSection>
        )}
      </div>

      {previewId === null ? null : <ReceiptPreviewDialog key={previewId} saleId={previewId} onClose={() => setPreviewId(null)} />}
      {crediting === null ? null : <CreditNoteDialog key={crediting.id} sale={crediting} onClose={() => setCrediting(null)} />}
    </>
  )
}

function IconAction({ label, disabled = false, onClick, children }: { readonly label: string; readonly disabled?: boolean; readonly onClick: () => void; readonly children: ReactNode }) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        {/* El span conserva el tooltip al pasar el puntero aunque el botón esté deshabilitado. */}
        <span>
          <Button variant="outline" size="icon" className="size-7" aria-label={label} disabled={disabled} onClick={onClick}>
            {children}
          </Button>
        </span>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}
