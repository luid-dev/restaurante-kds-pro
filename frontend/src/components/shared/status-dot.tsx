import { cn } from "@/lib/utils"
import type { StatusTone } from "@/types/realtime"

const TONE_CLASSES: Record<StatusTone, string> = {
  neutral: "bg-status-available",
  success: "bg-status-occupied",
  warning: "bg-status-prebill",
  danger: "bg-status-delayed",
  muted: "bg-muted-foreground/40",
}

interface StatusDotProps {
  readonly tone: StatusTone
  readonly pulse?: boolean
  readonly className?: string
}

/** Punto de estado sobrio (6 px). El significado siempre va acompañado de texto. */
export function StatusDot({ tone, pulse = false, className }: StatusDotProps) {
  return (
    <span aria-hidden="true" className={cn("relative inline-flex size-1.5 shrink-0", className)}>
      {pulse ? (
        <span className={cn("absolute inset-0 animate-ping rounded-full opacity-60 motion-reduce:hidden", TONE_CLASSES[tone])} />
      ) : null}
      <span className={cn("relative inline-flex size-1.5 rounded-full", TONE_CLASSES[tone])} />
    </span>
  )
}
