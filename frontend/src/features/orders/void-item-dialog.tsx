import { useMutation, useQueryClient } from "@tanstack/react-query"
import { LoaderCircle } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { operationKeys, voidOrderItem } from "@/api/operations"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import type { OrderItem } from "@/types/operations"

interface VoidItemDialogProps {
  readonly orderId: string
  readonly item: OrderItem | null
  readonly onClose: () => void
}

/**
 * Anulación auditada: si cocina no empezó, el insumo vuelve al almacén; si ya se
 * preparó, se registra como merma operativa. Siempre exige motivo.
 */
export function VoidItemDialog({ orderId, item, onClose }: VoidItemDialogProps) {
  const queryClient = useQueryClient()
  const [reason, setReason] = useState("")
  const mutation = useMutation({
    mutationFn: (payload: { itemId: string; reason: string }) => voidOrderItem(orderId, payload.itemId, payload.reason),
    onSuccess: () => {
      toast.success("Plato anulado y registrado en auditoría.")
      void queryClient.invalidateQueries({ queryKey: operationKeys.order(orderId) })
      setReason("")
      onClose()
    },
    onError: (error) => toast.error(error.message),
  })
  const valid = reason.trim().length >= 5

  return (
    <Dialog open={item !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Anular plato</DialogTitle>
          <DialogDescription>
            {item === null ? "" : `${item.menu_item.name}. `}
            {item?.status === "PENDING" ? "Cocina aún no lo inicia: el insumo vuelve al almacén." : "Cocina ya lo inició: se registrará como merma operativa."}
          </DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-2">
          <Label htmlFor="void-reason">Motivo (obligatorio)</Label>
          <Textarea id="void-reason" value={reason} onChange={(event) => setReason(event.target.value)} maxLength={255} rows={3} />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            variant="destructive"
            disabled={!valid || mutation.isPending || item === null}
            onClick={() => (item === null ? undefined : mutation.mutate({ itemId: item.id, reason: reason.trim() }))}
          >
            {mutation.isPending ? <LoaderCircle className="animate-spin" aria-hidden="true" /> : null}
            Anular plato
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
