import { useQuery } from "@tanstack/react-query"
import { useState } from "react"

import { reportQueries } from "@/api/reports"
import { Amount } from "@/components/shared/amount"
import { ReportSection } from "@/components/shared/report-section"
import { StatusDot } from "@/components/shared/status-dot"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { cn } from "@/lib/utils"

/** Existencias del almacén principal con costo promedio ponderado y alerta de stock mínimo. */
export function StockTab() {
  const ingredients = useQuery(reportQueries.ingredients())
  const [criticalOnly, setCriticalOnly] = useState(false)

  if (ingredients.isPending) {
    return <Skeleton className="h-72 w-full" />
  }

  if (ingredients.isError) {
    return <p className="text-sm text-status-delayed">{ingredients.error.message}</p>
  }

  const { data: rows, meta } = ingredients.data
  const visible = criticalOnly ? rows.filter((row) => row.is_critical) : rows

  return (
    <ReportSection
      title="Insumos y stock crítico"
      aside={
        <span className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
          <span>
            <span className="font-mono tabular-nums text-foreground">{rows.length}</span> insumos
          </span>
          <span className="flex items-center gap-1.5">
            <StatusDot tone={meta.critical_count > 0 ? "danger" : "success"} />
            <span className="font-mono tabular-nums text-foreground">{meta.critical_count}</span> en stock crítico
          </span>
          <span>
            Inventario valorizado <Amount value={meta.inventory_value} className="text-foreground" />
          </span>
          <Button variant="outline" size="sm" className="h-7 font-mono text-xs" aria-pressed={criticalOnly} onClick={() => setCriticalOnly((value) => !value)}>
            [{criticalOnly ? "Ver todos" : "Solo críticos"}]
          </Button>
        </span>
      }
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-24">SKU</TableHead>
            <TableHead>Insumo</TableHead>
            <TableHead className="text-right">Stock actual</TableHead>
            <TableHead className="text-right">Stock mínimo</TableHead>
            <TableHead className="text-right">Costo prom. pond.</TableHead>
            <TableHead className="text-right">Valorizado</TableHead>
            <TableHead className="w-32">Estado</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {visible.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7} className="py-6 text-center text-sm text-muted-foreground">
                Ningún insumo está por debajo de su stock mínimo.
              </TableCell>
            </TableRow>
          ) : (
            visible.map((row) => (
              <TableRow key={row.id} className={cn(row.is_critical && "bg-status-delayed/5")}>
                <TableCell className="font-mono text-xs text-muted-foreground">{row.sku}</TableCell>
                <TableCell className="font-medium">{row.name}</TableCell>
                <TableCell className="text-right font-mono tabular-nums">
                  {row.stock} <span className="text-muted-foreground">{row.unit_code}</span>
                </TableCell>
                <TableCell className="text-right font-mono tabular-nums text-muted-foreground">
                  {row.min_stock} {row.unit_code}
                </TableCell>
                <TableCell className="text-right font-mono tabular-nums">{row.average_cost}</TableCell>
                <TableCell className="text-right">
                  <Amount value={row.stock_value} />
                </TableCell>
                <TableCell>
                  <span className="flex items-center gap-1.5 text-xs">
                    <StatusDot tone={row.is_critical ? "danger" : "success"} />
                    {row.is_critical ? "Stock crítico" : "Abastecido"}
                  </span>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </ReportSection>
  )
}
