import { Building2, LoaderCircle, Pencil } from "lucide-react"
import { useEffect, useRef, useState } from "react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { AuthError } from "@/features/auth/auth-error"
import { PIN_LENGTH, PinPad } from "@/features/auth/pin-pad"
import { usePinLogin } from "@/features/auth/use-auth-actions"
import { useTerminalStore } from "@/stores/terminal-store"

const RUC_PATTERN = /^\d{11}$/

interface PinLoginPanelProps {
  /** El teclado físico solo se escucha mientras la pestaña PIN está visible. */
  readonly active: boolean
}

function isTypingTarget(target: EventTarget | null): boolean {
  return target instanceof HTMLElement && (target.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName))
}

export function PinLoginPanel({ active }: PinLoginPanelProps) {
  const linkedRuc = useTerminalStore((state) => state.companyRuc)
  const [ruc, setRuc] = useState(linkedRuc ?? "")
  const [editingRuc, setEditingRuc] = useState(linkedRuc === null)
  const [pin, setPinState] = useState("")
  const login = usePinLogin()
  const rucValid = RUC_PATTERN.test(ruc)
  const disabled = login.isPending || !rucValid

  // El PIN también vive en una ref síncrona: pulsaciones muy rápidas entre renders no pierden dígitos.
  const pinRef = useRef("")
  const handlersRef = useRef({ append: (_digit: string) => {}, backspace: () => {}, clear: () => {}, submit: () => {} })

  function setPin(next: string): void {
    pinRef.current = next
    setPinState(next)
  }

  function submit(value: string): void {
    if (value.length !== PIN_LENGTH || !rucValid || login.isPending) {
      return
    }

    login.mutate({ company_ruc: ruc, pin_code: value }, { onError: () => setPin("") })
  }

  function append(digit: string): void {
    const current = pinRef.current

    if (disabled || current.length >= PIN_LENGTH) {
      return
    }

    const next = current + digit
    setPin(next)

    // Ingreso en un solo gesto: al completar el sexto dígito se envía automáticamente.
    if (next.length === PIN_LENGTH) {
      submit(next)
    }
  }

  useEffect(() => {
    handlersRef.current = {
      append,
      backspace: () => setPin(pinRef.current.slice(0, -1)),
      clear: () => setPin(""),
      submit: () => submit(pinRef.current),
    }
  })

  useEffect(() => {
    if (!active) {
      return undefined
    }

    function onKeyDown(event: KeyboardEvent): void {
      if (isTypingTarget(event.target) || event.altKey || event.ctrlKey || event.metaKey) {
        return
      }

      const handlers = handlersRef.current

      if (/^\d$/.test(event.key)) {
        event.preventDefault()
        handlers.append(event.key)
      } else if (event.key === "Backspace") {
        handlers.backspace()
      } else if (event.key === "Escape") {
        handlers.clear()
      } else if (event.key === "Enter") {
        handlers.submit()
      }
    }

    window.addEventListener("keydown", onKeyDown)

    return () => window.removeEventListener("keydown", onKeyDown)
  }, [active])

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-3 rounded-md border bg-muted/40 px-3 py-2">
        <div className="flex min-w-0 items-center gap-2">
          <Building2 className="size-4 shrink-0 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
          {editingRuc ? (
            <div className="flex items-center gap-2 text-sm">
              <label htmlFor="terminal-ruc" className="text-muted-foreground">
                RUC
              </label>
              <Input
                id="terminal-ruc"
                value={ruc}
                onChange={(event) => setRuc(event.target.value.replace(/\D/g, "").slice(0, 11))}
                onBlur={() => rucValid && setEditingRuc(false)}
                inputMode="numeric"
                autoComplete="off"
                placeholder="20xxxxxxxxx"
                aria-label="RUC de la empresa"
                className="h-8 w-36 font-mono tabular-nums"
              />
            </div>
          ) : (
            <p className="truncate text-sm">
              <span className="text-muted-foreground">Empresa RUC </span>
              <span className="font-mono tabular-nums">{ruc}</span>
            </p>
          )}
        </div>
        {editingRuc ? null : (
          <Button type="button" variant="ghost" size="sm" className="h-7 text-xs" onClick={() => setEditingRuc(true)}>
            <Pencil className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
            Cambiar
          </Button>
        )}
      </div>

      <PinPad
        value={pin}
        disabled={disabled}
        invalid={login.isError}
        onDigit={append}
        onClear={() => setPin("")}
        onSubmit={() => submit(pin)}
      />

      {rucValid ? null : <p className="text-sm text-muted-foreground">Ingrese el RUC de 11 dígitos de su empresa para habilitar el teclado.</p>}
      {login.isPending ? (
        <p className="flex items-center gap-2 text-sm text-muted-foreground" aria-live="polite">
          <LoaderCircle className="size-4 animate-spin" strokeWidth={1.5} aria-hidden="true" />
          Validando PIN
        </p>
      ) : null}
      <AuthError error={login.error} />
    </div>
  )
}
