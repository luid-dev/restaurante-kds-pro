import { useNow } from "@/hooks/use-now"
import { formatBusinessDate, formatClock } from "@/lib/format"

/** Reloj de turno en hora de Lima. Aislado para que solo él re-renderice cada segundo. */
export function LiveClock() {
  const now = useNow()

  return (
    <time dateTime={now.toISOString()} className="flex items-baseline gap-2 text-sm">
      <span className="hidden text-xs text-muted-foreground lg:inline">{formatBusinessDate(now)}</span>
      <span className="font-mono tabular-nums">{formatClock(now)}</span>
    </time>
  )
}
