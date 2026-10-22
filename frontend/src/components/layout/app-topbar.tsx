import { findNavigationItem } from "@/components/layout/navigation"
import { LiveClock } from "@/components/layout/live-clock"
import { ThemeMenu } from "@/components/layout/theme-menu"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { env } from "@/lib/env"
import type { WorkspaceView } from "@/types/navigation"

const ENVIRONMENT_LABELS: Record<string, string> = {
  development: "Desarrollo",
  staging: "Pruebas",
}

export function AppTopbar({ activeView }: { readonly activeView: WorkspaceView | null }) {
  const item = activeView === null ? null : findNavigationItem(activeView)
  const environmentLabel = ENVIRONMENT_LABELS[env.mode]

  return (
    <header className="flex h-12 shrink-0 items-center gap-2 border-b bg-background px-3">
      <SidebarTrigger className="size-8" aria-label="Contraer o expandir menú lateral" />
      <Separator orientation="vertical" className="mx-1 data-[orientation=vertical]:h-4" />
      <span className="truncate text-sm font-medium">{item?.label ?? "Sin módulos"}</span>

      <div className="ml-auto flex items-center gap-3">
        {environmentLabel === undefined ? null : (
          <Badge variant="outline" className="rounded-sm font-normal text-muted-foreground">
            {environmentLabel}
          </Badge>
        )}
        <LiveClock />
        <Separator orientation="vertical" className="data-[orientation=vertical]:h-4" />
        <ThemeMenu />
      </div>
    </header>
  )
}
