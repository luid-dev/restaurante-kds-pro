import { Delete, LogIn } from "lucide-react"

import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

export const PIN_LENGTH = 6

const DIGITS = ["1", "2", "3", "4", "5", "6", "7", "8", "9"] as const

interface PinPadProps {
  readonly value: string
  readonly disabled: boolean
  readonly invalid: boolean
  readonly onDigit: (digit: string) => void
  readonly onClear: () => void
  readonly onSubmit: () => void
}

/**
 * Teclado numérico táctil para PIN de 6 dígitos. También responde al teclado
 * físico (dígitos, Retroceso, Escape, Enter) desde el panel que lo contiene.
 */
export function PinPad({ value, disabled, invalid, onDigit, onClear, onSubmit }: PinPadProps) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-center gap-2" aria-hidden="true">
        {Array.from({ length: PIN_LENGTH }, (_, index) => {
          const filled = index < value.length
          const current = index === value.length && !disabled

          return (
            <span
              key={index}
              className={cn(
                "flex size-11 items-center justify-center rounded-md border bg-background font-mono text-lg tabular-nums",
                current && "border-foreground",
                invalid && "border-destructive",
              )}
            >
              {filled ? "•" : ""}
            </span>
          )
        })}
      </div>
      <p className="sr-only" aria-live="polite">
        {value.length} de {PIN_LENGTH} dígitos ingresados
      </p>

      <div className="grid grid-cols-3 gap-2">
        {DIGITS.map((digit) => (
          <Button
            key={digit}
            type="button"
            variant="outline"
            className="h-12 font-mono text-lg tabular-nums"
            disabled={disabled || value.length >= PIN_LENGTH}
            onClick={() => onDigit(digit)}
            aria-label={`Dígito ${digit}`}
          >
            {digit}
          </Button>
        ))}
        <Button type="button" variant="ghost" className="h-12" disabled={disabled || value.length === 0} onClick={onClear}>
          <Delete className="size-4" strokeWidth={1.5} aria-hidden="true" />
          Limpiar
        </Button>
        <Button
          type="button"
          variant="outline"
          className="h-12 font-mono text-lg tabular-nums"
          disabled={disabled || value.length >= PIN_LENGTH}
          onClick={() => onDigit("0")}
          aria-label="Dígito 0"
        >
          0
        </Button>
        <Button type="button" className="h-12" disabled={disabled || value.length !== PIN_LENGTH} onClick={onSubmit}>
          <LogIn className="size-4" strokeWidth={1.5} aria-hidden="true" />
          Ingresar
        </Button>
      </div>
    </div>
  )
}
