import { useNow } from "@/hooks/use-now"
import { formatDuration, formatElapsed, secondsSince } from "@/lib/format"
import { cn } from "@/lib/utils"

interface ElapsedTimerProps {
  readonly since: string
  /** "clock": 00:34:12 (mesas). "kitchen": 12:45 (KDS). */
  readonly format?: "clock" | "kitchen"
  readonly className?: string
}

/** Cronómetro en vivo. Es una hoja del árbol: solo él se re-renderiza cada segundo. */
export function ElapsedTimer({ since, format = "clock", className }: ElapsedTimerProps) {
  const seconds = secondsSince(since, useNow())

  return (
    <time dateTime={since} className={cn("font-mono tabular-nums", className)}>
      {format === "clock" ? formatDuration(seconds) : formatElapsed(seconds)}
    </time>
  )
}
