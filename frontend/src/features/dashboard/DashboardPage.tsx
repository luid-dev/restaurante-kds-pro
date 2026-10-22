import { useQuery } from "@tanstack/react-query"
import { ChartColumn } from "lucide-react"
import { useState, type ReactNode } from "react"

import { reportQueries } from "@/api/reports"
import { findNavigationItem } from "@/components/layout/navigation"
import { Amount } from "@/components/shared/amount"
import { DateRangePicker } from "@/components/shared/date-range-picker"
import { EmptyPanel } from "@/components/shared/empty-panel"
import { ModuleHeader } from "@/components/shared/module-header"
import { ReportSection } from "@/components/shared/report-section"
import { ShareMeter } from "@/components/shared/share-meter"
import { StatusDot } from "@/components/shared/status-dot"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { presetRange } from "@/lib/dates"
import { formatQuantity, toCents } from "@/lib/money"
import { cn } from "@/lib/utils"
import type { StatusTone } from "@/types/realtime"
import type { DashboardMetrics, DateRange } from "@/types/reports"

/** Banda de tolerancia sobre la meta de food cost antes de marcarlo fuera de rango. */
const FOOD_COST_TOLERANCE_CENTS = 300

/** Control de rentabilidad: ventas, food cost teórico, mermas, ticket promedio y margen por plato. */
export default function DashboardPage() {
  const item = findNavigationItem("dashboard")
  const [range, setRange] = useState<DateRange>(() => presetRange("today"))
  const metrics = useQuery(reportQueries.dashboard(range))

  return (
    <>
      <ModuleHeader item={item} actions={<DateRangePicker value={range} onChange={setRange} />} />
      <div className="flex flex-col gap-4 p-4 md:p-6" aria-busy={metrics.isFetching}>
        {metrics.isPending ? (
          <>
            <Skeleton className="h-24 w-full" />
            <Skeleton className="h-64 w-full" />
          </>
        ) : metrics.isError ? (
          <p className="text-sm text-status-delayed">{metrics.error.message}</p>
        ) : (
          <DashboardContent data={metrics.data} />
        )}
      </div>
    </>
  )
}

function DashboardContent({ data }: { readonly data: DashboardMetrics }) {
  const foodCost = foodCostStatus(data.food_cost_percentage, data.food_cost_target)

  return (
    <>
      {/* Divisores de 1px en cualquier ancho: la rejilla deja ver el borde entre bloques. */}
      <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-md border bg-border md:grid-cols-5">
        <Kpi label="Ventas totales (S/)" value={<Amount value={data.gross_sales} />}>
          Neto <Amount value={data.net_sales} className="text-foreground" /> · NC <Amount value={data.credit_notes_total} className="text-foreground" />
        </Kpi>
        <Kpi label="Costo de insumos / recetas (S/)" value={<Amount value={data.theoretical_food_cost} />}>
          Kardex: consumo menos reversos
        </Kpi>
        <Kpi label="% Food cost" value={<span className="font-mono tabular-nums">{data.food_cost_percentage === null ? "-" : `${data.food_cost_percentage}%`}</span>}>
          <span className="flex items-center gap-1.5">
            <StatusDot tone={foodCost.tone} />
            {foodCost.label} <span className="font-mono tabular-nums">&lt; {data.food_cost_target}%</span>
          </span>
        </Kpi>
        <Kpi label="Mermas por anulación (S/)" value={<Amount value={data.waste_cost} />}>
          Tipo SUNAT 13 · cocina y devoluciones
        </Kpi>
        <Kpi label="Ticket promedio (S/)" value={<Amount value={data.average_ticket} />} className="col-span-2 md:col-span-1">
          <span className="font-mono tabular-nums">{data.orders_count}</span> cuentas · <span className="font-mono tabular-nums">{data.documents_count}</span> comprobantes
        </Kpi>
      </dl>

      {data.orders_count === 0 && data.documents_count === 0 ? (
        <EmptyPanel
          icon={ChartColumn}
          title="Sin ventas en el rango elegido"
          description="Los comprobantes cobrados en caja, las anulaciones de cocina y las notas de crédito alimentan estos indicadores en tiempo real."
        />
      ) : (
        <>
          <ReportSection
            title="Platos más vendidos vs. margen de contribución"
            aside={<span className="text-xs text-muted-foreground">Costo estándar de salón al costo promedio ponderado vigente</span>}
          >
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10 text-right">#</TableHead>
                  <TableHead>Plato</TableHead>
                  <TableHead className="text-right">Cant.</TableHead>
                  <TableHead className="text-right">Venta neta</TableHead>
                  <TableHead className="text-right">Costo receta</TableHead>
                  <TableHead className="text-right">Margen S/</TableHead>
                  <TableHead className="text-right">Margen %</TableHead>
                  <TableHead className="w-40">Food cost</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.top_dishes.map((dish, index) => (
                  <TableRow key={dish.id}>
                    <TableCell className="text-right font-mono text-xs tabular-nums text-muted-foreground">{index + 1}</TableCell>
                    <TableCell>
                      <span className="font-medium">{dish.name}</span>
                      <span className="ml-2 font-mono text-[11px] text-muted-foreground">{dish.sku}</span>
                    </TableCell>
                    <TableCell className="text-right font-mono tabular-nums">{formatQuantity(dish.quantity)}</TableCell>
                    <TableCell className="text-right">
                      <Amount value={dish.net_revenue} />
                    </TableCell>
                    <TableCell className="text-right text-muted-foreground">
                      <Amount value={dish.theoretical_cost} />
                    </TableCell>
                    <TableCell className="text-right font-medium">
                      <Amount value={dish.contribution_margin} />
                    </TableCell>
                    <TableCell className="text-right font-mono tabular-nums">{dish.margin_percentage === null ? "-" : `${dish.margin_percentage}%`}</TableCell>
                    <TableCell>
                      <ShareMeter percentage={dish.food_cost_percentage} />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </ReportSection>

          <div className="grid gap-4 lg:grid-cols-2">
            <ReportSection title="Ingresos por canal">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Canal</TableHead>
                    <TableHead className="text-right">Cuentas</TableHead>
                    <TableHead className="text-right">Total S/</TableHead>
                    <TableHead className="w-40">Participación</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data.sales_by_channel.map((channel) => (
                    <TableRow key={channel.channel}>
                      <TableCell>{channel.label}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums">{channel.orders}</TableCell>
                      <TableCell className="text-right">
                        <Amount value={channel.total} />
                      </TableCell>
                      <TableCell>
                        <ShareMeter percentage={channel.share_percentage} />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </ReportSection>

            <ReportSection title="Medio de pago">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Medio</TableHead>
                    <TableHead className="text-right">Cobrado S/</TableHead>
                    <TableHead className="w-40">Participación</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data.sales_by_payment_method.map((method) => (
                    <TableRow key={method.method}>
                      <TableCell>{method.label}</TableCell>
                      <TableCell className="text-right">
                        <Amount value={method.total} />
                      </TableCell>
                      <TableCell>
                        <ShareMeter percentage={method.share_percentage} />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </ReportSection>
          </div>
        </>
      )}

      <p className="text-xs text-muted-foreground">
        Venta total: comprobantes emitidos (IGV y recargo incluidos) menos notas de crédito. Venta neta: valor de venta sin impuestos. Food cost teórico: consumo de
        recetas en el kardex menos reversos por anulación y devolución, sobre la venta neta.
      </p>
    </>
  )
}

function Kpi({ label, value, children, className }: { readonly label: string; readonly value: ReactNode; readonly children: ReactNode; readonly className?: string }) {
  return (
    <div className={cn("flex min-w-0 flex-col gap-1 bg-background px-4 py-3", className)}>
      <dt className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{label}</dt>
      <dd className="text-xl font-semibold tracking-tight">{value}</dd>
      <dd className="text-xs text-muted-foreground">{children}</dd>
    </div>
  )
}

function foodCostStatus(percentage: string | null, target: string): { tone: StatusTone; label: string } {
  if (percentage === null) {
    return { tone: "muted", label: "Sin venta neta" }
  }

  const value = toCents(percentage)
  const limit = toCents(target)

  if (value < limit) {
    return { tone: "success", label: "Óptimo" }
  }

  return value < limit + FOOD_COST_TOLERANCE_CENTS ? { tone: "warning", label: "Atención" } : { tone: "danger", label: "Fuera de rango" }
}
