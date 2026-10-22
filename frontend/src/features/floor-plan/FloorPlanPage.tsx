import { useQuery } from "@tanstack/react-query"
import { LayoutGrid, RefreshCw } from "lucide-react"
import { useMemo, useState } from "react"

import { operationQueries } from "@/api/operations"
import { findNavigationItem } from "@/components/layout/navigation"
import { EmptyPanel } from "@/components/shared/empty-panel"
import { ModuleHeader } from "@/components/shared/module-header"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { CounterOrdersPanel } from "@/features/floor-plan/counter-orders-panel"
import { FloorSummaryBar } from "@/features/floor-plan/floor-summary-bar"
import { OpenTableDialog } from "@/features/floor-plan/open-table-dialog"
import { TableAccountSheet } from "@/features/floor-plan/table-account-sheet"
import { TableGrid } from "@/features/floor-plan/table-grid"
import { useCan } from "@/hooks/use-active-session"
import type { RestaurantTable } from "@/types/operations"

const COUNTER_TAB = "counter"

/**
 * Mapa de salones en vivo. Mesa libre: abre selector de comensales y toma de
 * comanda. Mesa ocupada: panel lateral con la cuenta. Los cambios de otras
 * terminales llegan por Reverb (useRealtimeSync en el AppShell).
 */
export default function FloorPlanPage() {
  const item = findNavigationItem("floor-plan")
  const plan = useQuery(operationQueries.floorPlan())
  const canOpen = useCan("tables.open")
  const [zoneTab, setZoneTab] = useState<string | null>(null)
  const [opening, setOpening] = useState<RestaurantTable | null>(null)
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const tables = useMemo(() => plan.data?.zones.flatMap((zone) => zone.tables) ?? [], [plan.data])
  // La mesa seleccionada se lee del caché vivo: si otra terminal la cambia, el panel se actualiza.
  const selected = tables.find((table) => table.id === selectedId) ?? null
  const activeTab = zoneTab ?? plan.data?.zones[0]?.id ?? COUNTER_TAB

  function select(table: RestaurantTable) {
    if (table.status === "AVAILABLE" || table.status === "RESERVED") {
      if (canOpen) {
        setOpening(table)
      }

      return
    }

    setSelectedId(table.id)
  }

  return (
    <>
      <ModuleHeader
        item={item}
        actions={
          <Button variant="ghost" size="icon-sm" onClick={() => void plan.refetch()} aria-label="Actualizar plano" disabled={plan.isFetching}>
            <RefreshCw className={plan.isFetching ? "animate-spin" : undefined} aria-hidden="true" />
          </Button>
        }
      />
      <div className="flex flex-col gap-4 p-4 md:p-6">
        {plan.isPending ? (
          <div className="flex flex-col gap-3">
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-64 w-full" />
          </div>
        ) : plan.isError ? (
          <EmptyPanel icon={LayoutGrid} title="No se pudo cargar el plano" description={plan.error.message} />
        ) : (
          <>
            <FloorSummaryBar summary={plan.data.summary} />
            <Tabs value={activeTab} onValueChange={setZoneTab} className="gap-3">
              <TabsList className="h-9 w-full justify-start overflow-x-auto rounded-md">
                {plan.data.zones.map((zone) => (
                  <TabsTrigger key={zone.id} value={zone.id} className="flex-none px-3">
                    {zone.name}
                    <span className="font-mono text-xs text-muted-foreground tabular-nums">
                      {zone.tables.filter((table) => table.status !== "AVAILABLE").length}/{zone.tables.length}
                    </span>
                  </TabsTrigger>
                ))}
                <TabsTrigger value={COUNTER_TAB} className="flex-none px-3">
                  Pedidos para llevar / delivery
                  <span className="font-mono text-xs text-muted-foreground tabular-nums">{plan.data.counter_orders.length}</span>
                </TabsTrigger>
              </TabsList>
              {plan.data.zones.map((zone) => (
                <TabsContent key={zone.id} value={zone.id}>
                  {zone.tables.length === 0 ? (
                    <EmptyPanel icon={LayoutGrid} title="Zona sin mesas" description="Registre mesas en esta zona para verlas en el plano." />
                  ) : (
                    <TableGrid tables={zone.tables} onSelect={select} />
                  )}
                </TabsContent>
              ))}
              <TabsContent value={COUNTER_TAB}>
                <CounterOrdersPanel orders={plan.data.counter_orders} />
              </TabsContent>
            </Tabs>
          </>
        )}
      </div>

      <OpenTableDialog table={opening} onClose={() => setOpening(null)} />
      <TableAccountSheet table={selected} candidates={tables} onClose={() => setSelectedId(null)} />
    </>
  )
}
