import { cn } from "@/lib/utils"

interface ShareMeterProps {
  /** Porcentaje "0.00"-"100.00"; null cuando no hay base. */
  readonly percentage: string | null
  readonly className?: string
}

/**
 * Participación en una sola tinta neutra (serie única: sin leyenda ni color de
 * identidad). La cifra exacta siempre se muestra en texto junto a la barra.
 */
export function ShareMeter({ percentage, className }: ShareMeterProps) {
  const width = percentage === null ? 0 : Math.min(100, Math.max(0, Number(percentage)))

  return (
    <span className={cn("flex items-center gap-2", className)}>
      <span className="relative h-1 w-20 overflow-hidden rounded-[1px] bg-muted" aria-hidden="true">
        <span className="absolute inset-y-0 left-0 rounded-[1px] bg-foreground/70" style={{ width: `${String(width)}%` }} />
      </span>
      <span className="w-14 text-right font-mono text-xs tabular-nums text-muted-foreground">{percentage === null ? "-" : `${percentage}%`}</span>
    </span>
  )
}
