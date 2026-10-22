import type { ReactNode } from "react"

import { Kbd } from "@/components/ui/kbd"
import type { NavigationItem } from "@/components/layout/navigation"

interface ModuleHeaderProps {
  readonly item: NavigationItem
  readonly actions?: ReactNode
}

export function ModuleHeader({ item, actions }: ModuleHeaderProps) {
  const Icon = item.icon

  return (
    <header className="flex items-start justify-between gap-4 border-b px-6 py-4">
      <div className="flex min-w-0 items-start gap-3">
        <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground">
          <Icon className="size-4" strokeWidth={1.5} aria-hidden="true" />
        </span>
        <div className="min-w-0">
          <h1 className="text-base font-semibold leading-6 tracking-tight">{item.label}</h1>
          <p className="text-sm text-muted-foreground">{item.description}</p>
        </div>
      </div>
      <div className="flex shrink-0 items-center gap-2">
        {actions}
        <Kbd className="font-mono" title="Atajo de teclado">
          Alt {item.shortcutLabel}
        </Kbd>
      </div>
    </header>
  )
}
