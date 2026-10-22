import { useMutation, useQueryClient } from "@tanstack/react-query"
import { Bike, LoaderCircle, Plus, Receipt, ShoppingBag } from "lucide-react"
import { useState, type FormEvent } from "react"
import { toast } from "sonner"

import { createCounterOrder, operationKeys } from "@/api/operations"
import { Amount } from "@/components/shared/amount"
import { ElapsedTimer } from "@/components/shared/elapsed-timer"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { useCan } from "@/hooks/use-active-session"
import { useOperationNavigation } from "@/hooks/use-operation-navigation"
import { cn } from "@/lib/utils"
import type { Order } from "@/types/operations"

type CounterType = "TAKEOUT" | "DELIVERY"

function NewCounterOrderDialog({ open, onClose }: { readonly open: boolean; readonly onClose: () => void }) {
  const queryClient = useQueryClient()
  const { goToWaiter } = useOperationNavigation()
  const [type, setType] = useState<CounterType>("TAKEOUT")
  const [name, setName] = useState("")
  const [address, setAddress] = useState("")
  const [phone, setPhone] = useState("")
  const mutation = useMutation({
    mutationFn: createCounterOrder,
    onSuccess: (order) => {
      void queryClient.invalidateQueries({ queryKey: operationKeys.floorPlan() })
      queryClient.setQueryData(operationKeys.order(order.id), order)
      onClose()
      goToWaiter(order.id)
    },
    onError: (error) => toast.error(error.message),
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    const clean = (value: string) => (value.trim() === "" ? null : value.trim())
    mutation.mutate({
      order_type: type,
      customer_name: clean(name),
      delivery_address: type === "DELIVERY" ? clean(address) : null,
      delivery_phone: type === "DELIVERY" ? clean(phone) : null,
    })
  }

  return (
    <Dialog open={open} onOpenChange={(value) => (value ? undefined : onClose())}>
      <DialogContent className="sm:max-w-md">
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader>
            <DialogTitle>Nuevo pedido de mostrador</DialogTitle>
            <DialogDescription>Para llevar o delivery: cobra empaque y descuenta envases de la receta.</DialogDescription>
          </DialogHeader>
          <fieldset className="grid grid-cols-2 gap-1.5 m-0 min-w-0 border-0 p-0">
            <legend className="sr-only">Tipo de pedido</legend>
            {(["TAKEOUT", "DELIVERY"] as const).map((option) => (
              <button
                key={option}
                type="button"
                aria-pressed={type === option}
                onClick={() => setType(option)}
                className={cn("flex h-10 items-center justify-center gap-2 rounded-md border text-sm hover:bg-accent", type === option && "border-foreground bg-accent")}
              >
                {option === "TAKEOUT" ? <ShoppingBag className="size-4" strokeWidth={1.5} aria-hidden="true" /> : <Bike className="size-4" strokeWidth={1.5} aria-hidden="true" />}
                {option === "TAKEOUT" ? "Para llevar" : "Delivery"}
              </button>
            ))}
          </fieldset>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="counter-name">Cliente</Label>
            <Input id="counter-name" value={name} onChange={(event) => setName(event.target.value)} maxLength={120} autoComplete="off" />
          </div>
          {type === "DELIVERY" ? (
            <>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="counter-address">Dirección de entrega</Label>
                <Input id="counter-address" value={address} onChange={(event) => setAddress(event.target.value)} maxLength={255} required />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="counter-phone">Teléfono</Label>
                <Input id="counter-phone" value={phone} onChange={(event) => setPhone(event.target.value)} inputMode="tel" pattern="\+?\d{6,15}" className="font-mono" required />
              </div>
            </>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? <LoaderCircle className="animate-spin" aria-hidden="true" /> : null}
              Crear y tomar pedido
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/** Pedidos sin mesa (para llevar y delivery) en curso. */
export function CounterOrdersPanel({ orders }: { readonly orders: readonly Order[] }) {
  const [creating, setCreating] = useState(false)
  const { goToWaiter, goToCashier } = useOperationNavigation()
  const canCreate = useCan("orders.create")
  const canCharge = useCan("cashier.charge")

  return (
    <section className="rounded-md border">
      <header className="flex items-center justify-between gap-3 border-b px-4 py-2.5">
        <h2 className="text-sm font-medium">
          Pedidos para llevar / delivery <span className="font-mono text-muted-foreground tabular-nums">({orders.length})</span>
        </h2>
        <Button size="sm" variant="outline" disabled={!canCreate} onClick={() => setCreating(true)}>
          <Plus aria-hidden="true" />
          Nuevo pedido
        </Button>
      </header>
      {orders.length === 0 ? (
        <p className="px-4 py-8 text-center text-sm text-muted-foreground">Sin pedidos de mostrador en curso.</p>
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Pedido</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead>Cliente</TableHead>
              <TableHead>Tiempo</TableHead>
              <TableHead className="text-right">Total</TableHead>
              <TableHead className="w-56" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {orders.map((order) => (
              <TableRow key={order.id}>
                <TableCell className="font-mono tabular-nums">{order.order_number}</TableCell>
                <TableCell>{order.order_type === "DELIVERY" ? "Delivery" : "Para llevar"}</TableCell>
                <TableCell className="max-w-48 truncate">{order.customer_name ?? "-"}</TableCell>
                <TableCell>
                  <ElapsedTimer since={order.opened_at} />
                </TableCell>
                <TableCell className="text-right">
                  <Amount value={order.totals.total_amount} />
                </TableCell>
                <TableCell>
                  <div className="flex justify-end gap-1.5">
                    <Button size="xs" variant="outline" onClick={() => goToWaiter(order.id)}>
                      Agregar platos
                    </Button>
                    <Button size="xs" disabled={!canCharge || order.totals.balance === "0.00"} onClick={() => goToCashier(order.id)}>
                      <Receipt aria-hidden="true" />
                      Cobrar
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
      <NewCounterOrderDialog open={creating} onClose={() => setCreating(false)} />
    </section>
  )
}
