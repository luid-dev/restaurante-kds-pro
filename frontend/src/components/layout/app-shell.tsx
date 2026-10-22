import { lazy, Suspense, useMemo, type ComponentType, type CSSProperties } from "react"

import { AppSidebar } from "@/components/layout/app-sidebar"
import { AppTopbar } from "@/components/layout/app-topbar"
import { allowedViews, NAVIGATION_ITEMS } from "@/components/layout/navigation"
import { StatusBar } from "@/components/layout/status-bar"
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar"
import { Skeleton } from "@/components/ui/skeleton"
import { ThermalPrintRoot } from "@/features/printing/thermal-print-root"
import { useHotkeys, type Hotkey } from "@/hooks/use-hotkeys"
import { useRealtimeSync } from "@/hooks/use-realtime-sync"
import { useWorkspaceStore } from "@/stores/workspace-store"
import type { Session } from "@/types/auth"
import type { WorkspaceView } from "@/types/navigation"

// Cada módulo se descarga solo al abrirlo: una pantalla KDS no carga el código de caja.
const VIEWS: Record<WorkspaceView, ComponentType> = {
  "floor-plan": lazy(() => import("@/features/floor-plan/FloorPlanPage")),
  "pos-waiter": lazy(() => import("@/features/pos-waiter/WaiterOrderPage")),
  kds: lazy(() => import("@/features/kds/KitchenDisplayPage")),
  cashier: lazy(() => import("@/features/cashier/CashierPage")),
  invoices: lazy(() => import("@/features/cashier/InvoicesAndCreditNotesPage")),
  "recipes-inventory": lazy(() => import("@/features/recipes-inventory/RecipesAndKardexPage")),
  dashboard: lazy(() => import("@/features/dashboard/DashboardPage")),
}

const SIDEBAR_STYLE: CSSProperties & Record<"--sidebar-width", string> = {
  "--sidebar-width": "14.5rem",
}

function ViewFallback() {
  return (
    <div className="flex flex-col gap-3 p-6" aria-busy="true" aria-label="Cargando módulo">
      <Skeleton className="h-5 w-48" />
      <Skeleton className="h-4 w-80" />
      <Skeleton className="mt-4 h-32 w-full" />
    </div>
  )
}

/**
 * AppShell gastronómico: sidebar compacto, barra superior con reloj de turno,
 * área de trabajo del módulo activo y barra de estado de conexión en vivo.
 * Solo muestra los módulos que los permisos del usuario habilitan.
 */
export function AppShell({ session }: { readonly session: Session }) {
  const storedView = useWorkspaceStore((state) => state.activeView)
  const setActiveView = useWorkspaceStore((state) => state.setActiveView)
  const permissions = session.user.permissions
  const allowed = useMemo(() => allowedViews(permissions), [permissions])
  const activeView = allowed.includes(storedView) ? storedView : (allowed[0] ?? null)

  const hotkeys = useMemo<Hotkey[]>(
    () =>
      NAVIGATION_ITEMS.filter((item) => allowed.includes(item.view)).map((item) => ({
        code: item.shortcutCode,
        alt: true,
        handler: () => setActiveView(item.view),
      })),
    [allowed, setActiveView],
  )

  useHotkeys(hotkeys)
  // Un solo canal de empresa por pestaña: el plano y las comandas se actualizan en todos los módulos.
  useRealtimeSync(session.user.company.id)

  const ActiveView = activeView === null ? null : VIEWS[activeView]

  return (
    <SidebarProvider style={SIDEBAR_STYLE} className="h-svh overflow-hidden">
      <AppSidebar session={session} allowedViews={allowed} activeView={activeView} />
      <SidebarInset className="min-h-0 min-w-0">
        <AppTopbar activeView={activeView} />
        <div id="workspace" className="min-h-0 flex-1 overflow-y-auto">
          {ActiveView === null ? (
            <p className="p-6 text-sm text-muted-foreground">Su usuario no tiene módulos habilitados. Contacte al administrador.</p>
          ) : (
            <Suspense fallback={<ViewFallback />}>
              <ActiveView />
            </Suspense>
          )}
        </div>
        <StatusBar />
      </SidebarInset>
      <ThermalPrintRoot />
    </SidebarProvider>
  )
}
