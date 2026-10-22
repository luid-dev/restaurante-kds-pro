import type { ReactNode } from "react"

import { cn } from "@/lib/utils"

/**
 * Papel térmico de 80 mm (72 mm imprimibles). En pantalla sirve de vista previa;
 * al imprimir se renderiza dentro de #thermal-print-root.
 */
export function ThermalPaper({ children, className }: { readonly children: ReactNode; readonly className?: string }) {
  return (
    <article className={cn("thermal-paper w-[80mm] max-w-full bg-white px-[4mm] py-[3mm] font-mono text-[11px] leading-snug text-zinc-950", className)}>
      {children}
    </article>
  )
}

export function ThermalRule() {
  return <hr className="my-1.5 border-0 border-t border-dashed border-zinc-500" />
}

export function ThermalRow({ label, value, strong = false }: { readonly label: ReactNode; readonly value: ReactNode; readonly strong?: boolean }) {
  return (
    <div className={cn("flex justify-between gap-2 tabular-nums", strong && "text-[13px] font-bold")}>
      <span>{label}</span>
      <span className="text-right">{value}</span>
    </div>
  )
}
