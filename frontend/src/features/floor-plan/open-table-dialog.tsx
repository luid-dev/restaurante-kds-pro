import { useMutation, useQueryClient } from "@tanstack/react-query"
import { LoaderCircle, Minus, Plus } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { openTable, operationKeys } from "@/api/operations"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { useOperationNavigation } from "@/hooks/use-operation-navigation"
import { cn } from "@/lib/utils"
import type { RestaurantTable } from "@/types/operations"

const QUICK_COUNTS = [1, 2, 3, 4, 5, 6] as const

/**
 * Mesa libre: selector de comensales y apertura directa hacia la toma de comanda.
 * Si otro mozo la abrió un instante antes, el backend responde 409 y se avisa.
 */
export function OpenTableDialog({ table, onClose }: { readonly table: RestaurantTable | null; readonly onClose: () => void }) {
  const queryClient = useQueryClient()
  const { goToWaiter } = useOperationNavigation()
  const [diners, setDiners] = useState(2)
  const mutation = useMutation({
    mutationFn: (payload: { tableId: string; diners: number }) => openTable(payload.tableId, payload.diners),
    onSuccess: (order) => {
      void queryClient.invalidateQueries({ queryKey: operationKeys.floorPlan() })
      queryClient.setQueryData(operationKeys.order(order.id), order)
      onClose()
      goToWaiter(order.id)
    },
    onError: (error) => {
      toast.error(error.message)
      void queryClient.invalidateQueries({ queryKey: operationKeys.floorPlan() })
    },
  })

  return (
    <Dialog open={table !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>
            Abrir mesa <span className="font-mono tabular-nums">{table?.code}</span>
          </DialogTitle>
          <DialogDescription>Indique los comensales para iniciar la comanda.</DialogDescription>
        </DialogHeader>

        <div className="grid grid-cols-6 gap-1.5">
          {QUICK_COUNTS.map((count) => (
            <button
              key={count}
              type="button"
              onClick={() => setDiners(count)}
              className={cn(
                "h-10 rounded-md border font-mono text-sm tabular-nums transition-colors hover:bg-accent",
                diners === count && "border-foreground bg-foreground text-background hover:bg-foreground",
              )}
              aria-pressed={diners === count}
            >
              {count}
            </button>
          ))}
        </div>

        <div className="flex items-center justify-between rounded-md border px-3 py-2">
          <span className="text-sm text-muted-foreground">Comensales</span>
          <div className="flex items-center gap-2">
            <Button size="icon-sm" variant="outline" onClick={() => setDiners((value) => Math.max(1, value - 1))} aria-label="Quitar comensal">
              <Minus aria-hidden="true" />
            </Button>
            <output className="w-8 text-center font-mono text-lg font-semibold tabular-nums">{diners}</output>
            <Button size="icon-sm" variant="outline" onClick={() => setDiners((value) => Math.min(99, value + 1))} aria-label="Agregar comensal">
              <Plus aria-hidden="true" />
            </Button>
          </div>
        </div>
        {table !== null && diners > table.capacity ? (
          <p className="text-xs text-status-prebill">Supera las {table.capacity} sillas de la mesa.</p>
        ) : null}

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancelar
          </Button>
          <Button disabled={table === null || mutation.isPending} onClick={() => (table === null ? undefined : mutation.mutate({ tableId: table.id, diners }))}>
            {mutation.isPending ? <LoaderCircle className="animate-spin" aria-hidden="true" /> : null}
            Abrir y tomar comanda
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
