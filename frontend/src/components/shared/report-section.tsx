import type { ReactNode } from "react"

import { cn } from "@/lib/utils"

interface ReportSectionProps {
  readonly title: string
  readonly aside?: ReactNode
  readonly children: ReactNode
  readonly className?: string
}

/** Bloque técnico de reporte: borde de 1px, cabecera en versalitas y sin sombras. */
export function ReportSection({ title, aside, children, className }: ReportSectionProps) {
  return (
    <section aria-label={title} className={cn("min-w-0 rounded-md border", className)}>
      <header className="flex min-h-10 flex-wrap items-center justify-between gap-2 border-b px-4 py-2">
        <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{title}</h2>
        {aside}
      </header>
      {children}
    </section>
  )
}
