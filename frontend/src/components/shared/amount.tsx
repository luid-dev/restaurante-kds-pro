import { formatPEN } from "@/lib/format"
import { cn } from "@/lib/utils"

interface AmountProps {
  readonly value: string
  readonly className?: string
}

/** Monto en soles con dígitos tabulares: "S/ 145.50". */
export function Amount({ value, className }: AmountProps) {
  return <span className={cn("font-mono tabular-nums", className)}>{formatPEN(value)}</span>
}
