import { useMutation, useQuery } from "@tanstack/react-query"
import { Download, LoaderCircle } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { reportDownloads, reportQueries } from "@/api/reports"
import { Amount } from "@/components/shared/amount"
import { DateRangePicker } from "@/components/shared/date-range-picker"
import { Pager } from "@/components/shared/pager"
import { ReportSection } from "@/components/shared/report-section"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { presetRange } from "@/lib/dates"
import { formatTicketDateTime } from "@/lib/format"
import { cn } from "@/lib/utils"
import { KARDEX_MOVEMENT_LABELS, KARDEX_MOVEMENT_TYPES, type DateRange, type KardexMovementType } from "@/types/reports"

const ALL = "ALL"

function isMovementType(value: string): value is KardexMovementType {
  return KARDEX_MOVEMENT_TYPES.some((type) => type === value)
}

/** Kardex valorizado (formato 13.1) filtrable por insumo y auditoría de mermas por anulación. */
export function KardexTab() {
  const [range, setRange] = useState<DateRange>(() => presetRange("month"))
  const [ingredient, setIngredient] = useState<string | null>(null)
  const [movementType, setMovementType] = useState<KardexMovementType | null>(null)
  const [page, setPage] = useState(1)
  const ingredients = useQuery(reportQueries.ingredients())
  const kardex = useQuery(reportQueries.kardex({ ...range, ingredient, movementType, page }))
  const waste = useQuery(reportQueries.waste(range))

  const exportKardex = useMutation({
    mutationFn: () => reportDownloads.kardex131(range, ingredient),
    onSuccess: (filename) => toast.success(`Kardex 13.1 exportado: ${filename}`),
    onError: (error) => toast.error(error.message),
  })
  const exportWaste = useMutation({
    mutationFn: () => reportDownloads.wasteCsv(range),
    onSuccess: (filename) => toast.success(`Auditoría de mermas exportada: ${filename}`),
    onError: (error) => toast.error(error.message),
  })

  function changeRange(next: DateRange) {
    setRange(next)
    setPage(1)
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <DateRangePicker value={range} onChange={changeRange} />
        <Select
          value={ingredient ?? ALL}
          onValueChange={(value) => {
            setIngredient(value === ALL ? null : value)
            setPage(1)
          }}
        >
          <SelectTrigger size="sm" className="w-64" aria-label="Insumo">
            <SelectValue placeholder="Insumo" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Todos los insumos</SelectItem>
            {(ingredients.data?.data ?? []).map((row) => (
              <SelectItem key={row.id} value={row.id}>
                <span className="font-mono text-xs text-muted-foreground">{row.sku}</span> {row.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select
          value={movementType ?? ALL}
          onValueChange={(value) => {
            setMovementType(isMovementType(value) ? value : null)
            setPage(1)
          }}
        >
          <SelectTrigger size="sm" className="w-52" aria-label="Tipo de operación">
            <SelectValue placeholder="Tipo de operación" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Todas las operaciones</SelectItem>
            {KARDEX_MOVEMENT_TYPES.map((type) => (
              <SelectItem key={type} value={type}>
                {KARDEX_MOVEMENT_LABELS[type]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button size="sm" variant="outline" className="ml-auto" disabled={exportKardex.isPending} onClick={() => exportKardex.mutate()}>
          {exportKardex.isPending ? <LoaderCircle className="size-3.5 animate-spin" aria-hidden="true" /> : <Download className="size-3.5" strokeWidth={1.5} aria-hidden="true" />}
          Exportar Kardex 13.1 (CSV)
        </Button>
      </div>

      <ReportSection title="Kardex valorizado · formato SUNAT 13.1 · promedio ponderado">
        {kardex.isPending ? (
          <Skeleton className="m-4 h-48" />
        ) : kardex.isError ? (
          <p className="p-4 text-sm text-status-delayed">{kardex.error.message}</p>
        ) : (
          <>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Fecha</TableHead>
                  <TableHead>Insumo</TableHead>
                  <TableHead>Tipo op. (T12)</TableHead>
                  <TableHead>Documento</TableHead>
                  <TableHead className="text-right">Entrada</TableHead>
                  <TableHead className="text-right">Salida</TableHead>
                  <TableHead className="text-right">Costo unit.</TableHead>
                  <TableHead className="text-right">Saldo</TableHead>
                  <TableHead className="text-right">Saldo valorizado</TableHead>
                  <TableHead>Usuario</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {kardex.data.data.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={10} className="py-6 text-center text-sm text-muted-foreground">
                      Sin movimientos en el rango y filtros elegidos.
                    </TableCell>
                  </TableRow>
                ) : (
                  kardex.data.data.map((movement) => (
                    <TableRow key={movement.id} title={movement.notes ?? undefined}>
                      <TableCell className="font-mono text-xs tabular-nums whitespace-nowrap">{formatTicketDateTime(new Date(movement.occurred_at))}</TableCell>
                      <TableCell>
                        <span className="font-mono text-[11px] text-muted-foreground">{movement.ingredient.sku}</span> {movement.ingredient.name}
                      </TableCell>
                      <TableCell className={cn("font-mono text-xs whitespace-nowrap", movement.movement_type === "WASTE" && "text-status-delayed")}>
                        {KARDEX_MOVEMENT_LABELS[movement.movement_type]}
                      </TableCell>
                      <TableCell className="font-mono text-xs">{movement.document_reference ?? "-"}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums">{movement.quantity_in === "0.0000" ? "" : movement.quantity_in}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums">{movement.quantity_out === "0.0000" ? "" : movement.quantity_out}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums text-muted-foreground">{movement.unit_cost}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums whitespace-nowrap">
                        {movement.balance_quantity} <span className="text-muted-foreground">{movement.ingredient.unit_code}</span>
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">{movement.balance_total_cost}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{movement.created_by ?? "-"}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
            <Pager pagination={kardex.data.meta.pagination} onPage={setPage} label="Páginas del kardex" />
          </>
        )}
      </ReportSection>

      <ReportSection
        title="Mermas y anulaciones de cocina"
        aside={
          <span className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
            {waste.data === undefined ? null : (
              <>
                <span>
                  <span className="font-mono tabular-nums text-foreground">{waste.data.incidents}</span> incidentes
                </span>
                <span>
                  Costo perdido <Amount value={waste.data.total_cost} className="text-foreground" />
                </span>
              </>
            )}
            <Button size="sm" variant="outline" className="h-7" disabled={exportWaste.isPending} onClick={() => exportWaste.mutate()}>
              <Download className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
              CSV
            </Button>
          </span>
        }
      >
        {waste.isPending ? (
          <Skeleton className="m-4 h-32" />
        ) : waste.isError ? (
          <p className="p-4 text-sm text-status-delayed">{waste.error.message}</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Fecha</TableHead>
                <TableHead>Origen</TableHead>
                <TableHead>Plato / documento</TableHead>
                <TableHead>Insumo perdido</TableHead>
                <TableHead className="text-right">Cantidad</TableHead>
                <TableHead className="text-right">Costo S/</TableHead>
                <TableHead>Autorizó</TableHead>
                <TableHead>Motivo</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {waste.data.rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="py-6 text-center text-sm text-muted-foreground">
                    Sin mermas registradas en el rango elegido.
                  </TableCell>
                </TableRow>
              ) : (
                waste.data.rows.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-mono text-xs tabular-nums whitespace-nowrap">{formatTicketDateTime(new Date(row.occurred_at))}</TableCell>
                    <TableCell className="text-xs whitespace-nowrap">{row.origin === "VOID" ? "Anulación cocina" : "Nota de crédito"}</TableCell>
                    <TableCell>
                      <span>{row.dish ?? "-"}</span>
                      <span className="ml-2 font-mono text-[11px] text-muted-foreground">{row.reference}</span>
                    </TableCell>
                    <TableCell>
                      <span className="font-mono text-[11px] text-muted-foreground">{row.ingredient.sku}</span> {row.ingredient.name}
                    </TableCell>
                    <TableCell className="text-right font-mono tabular-nums whitespace-nowrap">
                      {row.quantity} {row.ingredient.unit_code}
                    </TableCell>
                    <TableCell className="text-right">
                      <Amount value={row.total_cost} />
                    </TableCell>
                    <TableCell className="text-xs">{row.authorized_by ?? "-"}</TableCell>
                    <TableCell className="max-w-64 truncate text-xs text-muted-foreground" title={row.reason ?? undefined}>
                      {row.reason ?? "-"}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        )}
      </ReportSection>
    </div>
  )
}
