import { useMutation, useQueryClient } from "@tanstack/react-query"
import { LoaderCircle, Wallet } from "lucide-react"
import { useState, type FormEvent } from "react"
import { toast } from "sonner"

import { openCashSession, operationKeys } from "@/api/operations"
import { Amount } from "@/components/shared/amount"
import { ElapsedTimer } from "@/components/shared/elapsed-timer"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useCan } from "@/hooks/use-active-session"
import { cn } from "@/lib/utils"
import type { CashContext } from "@/types/operations"

const AMOUNT_PATTERN = /^\d{1,5}(\.\d{1,2})?$/

/** Turno de caja: sin turno abierto no se cobra (cada pago queda asociado a su arqueo). */
export function CashSessionPanel({ context }: { readonly context: CashContext }) {
  const queryClient = useQueryClient()
  const canOpen = useCan("cashier.open_session")
  const [registerId, setRegisterId] = useState(context.registers[0]?.id ?? "")
  const [opening, setOpening] = useState("200.00")
  const mutation = useMutation({
    mutationFn: () => openCashSession(registerId, opening),
    onSuccess: () => {
      toast.success("Turno de caja abierto.")
      void queryClient.invalidateQueries({ queryKey: operationKeys.cash() })
    },
    onError: (error) => toast.error(error.message),
  })

  if (context.session !== null) {
    const session = context.session

    return (
      <section aria-label="Turno de caja" className="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-md border px-4 py-2.5 text-sm">
        <span className="flex items-center gap-2 font-medium">
          <Wallet className="size-4 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
          {session.cash_register.name}
          <span className="font-mono text-xs text-muted-foreground">{session.cash_register.code}</span>
        </span>
        <span className="text-muted-foreground">
          Fondo inicial <Amount value={session.opening_amount} className="text-foreground" />
        </span>
        <span className="text-muted-foreground">
          Turno <ElapsedTimer since={session.opened_at} className="text-foreground" />
        </span>
        <span className="ml-auto flex flex-wrap gap-1.5">
          {context.series.map((series) => (
            <span key={series.series} className="rounded-sm border px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground tabular-nums">
              Próx. {series.next_number}
            </span>
          ))}
        </span>
      </section>
    )
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate()
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-3 rounded-md border border-dashed px-4 py-4">
      <div>
        <h2 className="text-sm font-medium">Abrir turno de caja</h2>
        <p className="text-sm text-muted-foreground">Elija la caja e ingrese el fondo inicial en efectivo.</p>
      </div>
      <div className="flex flex-wrap items-end gap-3">
        <fieldset className="flex flex-wrap gap-1.5 m-0 min-w-0 border-0 p-0">
          <legend className="sr-only">Caja</legend>
          {context.registers.map((register) => (
            <button
              key={register.id}
              type="button"
              aria-pressed={registerId === register.id}
              onClick={() => setRegisterId(register.id)}
              className={cn("h-9 rounded-md border px-3 text-sm hover:bg-accent", registerId === register.id && "border-foreground bg-accent")}
            >
              {register.name} <span className="font-mono text-xs text-muted-foreground">{register.code}</span>
            </button>
          ))}
        </fieldset>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="opening-amount">Fondo inicial S/</Label>
          <Input id="opening-amount" value={opening} onChange={(event) => setOpening(event.target.value)} inputMode="decimal" className="w-32 font-mono tabular-nums" />
        </div>
        <Button type="submit" disabled={!canOpen || registerId === "" || !AMOUNT_PATTERN.test(opening) || mutation.isPending}>
          {mutation.isPending ? <LoaderCircle className="animate-spin" aria-hidden="true" /> : null}
          Abrir turno
        </Button>
      </div>
    </form>
  )
}
