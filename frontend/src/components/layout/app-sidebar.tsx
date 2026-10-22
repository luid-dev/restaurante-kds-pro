import { ChevronsUpDown, Lock, LogOut, UtensilsCrossed } from "lucide-react"

import { NAVIGATION } from "@/components/layout/navigation"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Kbd } from "@/components/ui/kbd"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/components/ui/sidebar"
import { useLogout } from "@/features/auth/use-auth-actions"
import { useWorkspaceStore } from "@/stores/workspace-store"
import { isRole, ROLE_LABELS, type Session } from "@/types/auth"
import type { WorkspaceView } from "@/types/navigation"

interface AppSidebarProps {
  readonly session: Session
  readonly allowedViews: readonly WorkspaceView[]
  readonly activeView: WorkspaceView | null
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter((part) => part.length > 0)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("")
}

export function AppSidebar({ session, allowedViews, activeView }: AppSidebarProps) {
  const setActiveView = useWorkspaceStore((state) => state.setActiveView)
  const logout = useLogout()
  const { user } = session
  const roleLabel = user.roles.filter(isRole).map((role) => ROLE_LABELS[role]).join(", ") || "Sin rol"
  const groups = NAVIGATION.map((group) => ({ ...group, items: group.items.filter((item) => allowedViews.includes(item.view)) })).filter(
    (group) => group.items.length > 0,
  )

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader className="h-12 justify-center border-b border-sidebar-border px-2 py-0">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" className="h-9 cursor-default hover:bg-transparent active:bg-transparent" tooltip={user.company.display_name}>
              <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <UtensilsCrossed className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
              </span>
              <span className="grid min-w-0 leading-tight">
                <span className="truncate text-sm font-semibold">{user.company.display_name}</span>
                <span className="truncate font-mono text-xs tabular-nums text-muted-foreground">RUC {user.company.ruc}</span>
              </span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {groups.map((group) => (
          <SidebarGroup key={group.label} className="py-1.5">
            <SidebarGroupLabel className="h-7">{group.label}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu className="gap-0.5">
                {group.items.map((item) => {
                  const Icon = item.icon
                  const isActive = item.view === activeView

                  return (
                    <SidebarMenuItem key={item.view}>
                      <SidebarMenuButton
                        isActive={isActive}
                        tooltip={`${item.label} (Alt ${item.shortcutLabel})`}
                        aria-current={isActive ? "page" : undefined}
                        onClick={() => setActiveView(item.view)}
                        className="h-8"
                      >
                        <Icon strokeWidth={1.5} aria-hidden="true" />
                        <span className="truncate">{item.label}</span>
                        <Kbd className="ml-auto font-mono group-data-[collapsible=icon]:hidden">{item.shortcutLabel}</Kbd>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  )
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        ))}
      </SidebarContent>

      <SidebarFooter className="border-t border-sidebar-border p-2">
        <SidebarMenu>
          <SidebarMenuItem>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <SidebarMenuButton size="lg" className="h-10" tooltip={user.name}>
                  <span className="flex size-6 shrink-0 items-center justify-center rounded-md border bg-background font-mono text-[11px] tabular-nums">
                    {initials(user.name)}
                  </span>
                  <span className="grid min-w-0 leading-tight">
                    <span className="truncate text-sm">{user.name}</span>
                    <span className="truncate text-xs text-muted-foreground">
                      {roleLabel}
                      {session.authMethod === "PIN" ? " (PIN)" : ""}
                    </span>
                  </span>
                  <ChevronsUpDown className="ml-auto size-4 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
                </SidebarMenuButton>
              </DropdownMenuTrigger>
              <DropdownMenuContent side="top" align="start" className="w-60">
                <DropdownMenuLabel className="font-normal">
                  <p className="text-sm">{user.name}</p>
                  <p className="truncate text-xs text-muted-foreground">{user.email ?? "Acceso solo por PIN"}</p>
                  {session.authMethod === "PIN" ? (
                    <p className="mt-1 text-xs text-muted-foreground">
                      La sesión se cierra tras <span className="font-mono tabular-nums">{session.pinIdleTimeoutMinutes}</span> min sin actividad.
                    </p>
                  ) : null}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem disabled={logout.isPending} onSelect={() => logout.mutate({ lock: true })}>
                  <Lock className="size-4" strokeWidth={1.5} aria-hidden="true" />
                  Bloquear terminal (cambiar usuario)
                </DropdownMenuItem>
                <DropdownMenuItem disabled={logout.isPending} onSelect={() => logout.mutate({ lock: false })}>
                  <LogOut className="size-4" strokeWidth={1.5} aria-hidden="true" />
                  Cerrar sesión
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>

      <SidebarRail />
    </Sidebar>
  )
}
