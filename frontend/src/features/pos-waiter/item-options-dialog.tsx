import { Minus, Plus } from "lucide-react"
import { useState, type FormEvent } from "react"

import { Amount } from "@/components/shared/amount"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { hasSurcharge, type DraftLine } from "@/features/pos-waiter/draft"
import { formatPEN } from "@/lib/format"
import { cn } from "@/lib/utils"
import { PRODUCTION_AREA_LABELS, TAKEAWAY_MODIFIER, type MenuItem, type MenuModifier } from "@/types/operations"

interface ItemOptionsDialogProps {
  readonly item: MenuItem | null
  readonly modifiers: readonly MenuModifier[]
  readonly onAdd: (line: DraftLine) => void
  readonly onClose: () => void
}

function modifierLabel(modifier: MenuModifier, item: MenuItem): string {
  return modifier.name === TAKEAWAY_MODIFIER && hasSurcharge(item) ? `${modifier.name} +${formatPEN(item.packaging_surcharge)}` : modifier.name
}

/** Selector rápido de indicaciones de cocina, cantidad y nota libre. */
export function ItemOptionsDialog({ item, modifiers, onAdd, onClose }: ItemOptionsDialogProps) {
  return (
    <Dialog open={item !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="sm:max-w-md">
        {item === null ? null : <ItemOptionsForm key={item.id} item={item} modifiers={modifiers} onAdd={onAdd} onClose={onClose} />}
      </DialogContent>
    </Dialog>
  )
}

function ItemOptionsForm({ item, modifiers, onAdd, onClose }: ItemOptionsDialogProps & { readonly item: MenuItem }) {
  const [quantity, setQuantity] = useState(1)
  const [selected, setSelected] = useState<readonly string[]>([])
  const [notes, setNotes] = useState("")
  const available = modifiers.filter((modifier) => modifier.production_area === null || modifier.production_area === item.production_area)

  function toggle(name: string) {
    setSelected((current) => (current.includes(name) ? current.filter((value) => value !== name) : [...current, name]))
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    onAdd({ key: crypto.randomUUID(), menuItem: item, quantity, modifiers: selected, notes: notes.trim() === "" ? null : notes.trim() })
    onClose()
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <DialogHeader>
        <DialogTitle>{item.name}</DialogTitle>
        <DialogDescription className="flex items-center gap-3">
          <Amount value={item.sale_price} className="text-foreground" />
          <span>{PRODUCTION_AREA_LABELS[item.production_area]}</span>
          <span className="font-mono tabular-nums">{item.preparation_time_minutes} min</span>
        </DialogDescription>
      </DialogHeader>

      {available.length === 0 ? null : (
        <fieldset className="flex flex-col gap-2">
          <legend className="mb-2 text-xs text-muted-foreground">Indicaciones para cocina</legend>
          <div className="flex flex-wrap gap-1.5">
            {available.map((modifier) => {
              const active = selected.includes(modifier.name)

              return (
                <button
                  key={modifier.id}
                  type="button"
                  onClick={() => toggle(modifier.name)}
                  aria-pressed={active}
                  className={cn(
                    "h-8 rounded-md border px-2.5 font-mono text-xs transition-colors hover:bg-accent",
                    active && "border-foreground bg-foreground text-background hover:bg-foreground/90",
                  )}
                >
                  [{modifierLabel(modifier, item)}]
                </button>
              )
            })}
          </div>
        </fieldset>
      )}

      <div className="flex items-center justify-between rounded-md border px-3 py-2">
        <span className="text-sm text-muted-foreground">Cantidad</span>
        <div className="flex items-center gap-2">
          <Button type="button" size="icon-sm" variant="outline" onClick={() => setQuantity((value) => Math.max(1, value - 1))} aria-label="Restar">
            <Minus aria-hidden="true" />
          </Button>
          <output className="w-8 text-center font-mono text-lg font-semibold tabular-nums">{quantity}</output>
          <Button type="button" size="icon-sm" variant="outline" onClick={() => setQuantity((value) => Math.min(99, value + 1))} aria-label="Sumar">
            <Plus aria-hidden="true" />
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="item-notes">Nota para cocina</Label>
        <Input id="item-notes" value={notes} onChange={(event) => setNotes(event.target.value)} maxLength={255} placeholder="Ej. salsa aparte" autoComplete="off" />
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onClose}>
          Cancelar
        </Button>
        <Button type="submit">
          Agregar a la comanda
        </Button>
      </DialogFooter>
    </form>
  )
}
