import type { LucideIcon } from "lucide-react"
import type { ReactNode } from "react"

interface EmptyPanelProps {
  readonly icon: LucideIcon
  readonly title: string
  readonly description: string
  readonly children?: ReactNode
}

/** Estado vacío de un módulo: dice qué aparecerá aquí y qué lo origina. */
export function EmptyPanel({ icon: Icon, title, description, children }: EmptyPanelProps) {
  return (
    <section className="rounded-md border border-dashed">
      <div className="flex items-start gap-3 px-5 py-6">
        <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
        <div className="max-w-prose">
          <h2 className="text-sm font-medium">{title}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
      </div>
      {children === undefined ? null : <div className="border-t border-dashed px-5 py-4">{children}</div>}
    </section>
  )
}
