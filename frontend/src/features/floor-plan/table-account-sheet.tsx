import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { ArrowRightLeft, CookingPot, LoaderCircle, Printer, Receipt } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { operationKeys, operationQueries, requestPreBill, transferTable } from "@/api/operations"
import { ElapsedTimer } from "@/components/shared/elapsed-timer"
import { Button } from "@/components/ui/button"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Separator } from "@/components/ui/separator"
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { OrderLines } from "@/features/orders/order-lines"
import { OrderTotals } from "@/features/orders/order-totals"
import { VoidItemDialog } from "@/features/orders/void-item-dialog"
import { PreBillTicket } from "@/features/printing/pre-bill-ticket"
import { usePrintStore } from "@/features/printing/print-store"
import { useActiveSession, useCan } from "@/hooks/use-active-session"
import { useOperationNavigation } from "@/hooks/use-operation-navigation"
import type { OrderItem, RestaurantTable } from "@/types/operations"

interface TableAccountSheetProps {
  readonly table: RestaurantTable | null
  readonly candidates: readonly RestaurantTable[]
  readonly onClose: () => void
}

/** Cuenta de una mesa ocupada: detalle, agregar platos, pre-cuenta, cambio/unión de mesa y cobro. */
export function TableAccountSheet({ table, candidates, onClose }: TableAccountSheetProps) {
  const queryClient = useQueryClient()
  const session = useActiveSession()
  const { goToWaiter, goToCashier } = useOperationNavigation()
  const print = usePrintStore((state) => state.print)
  const [voiding, setVoiding] = useState<OrderItem | null>(null)
  const [moving, setMoving] = useState(false)
  const orderId = table?.order?.id ?? null
  const order = useQuery({ ...operationQueries.order(orderId ?? ""), enabled: orderId !== null })
  const canPreBill = useCan("orders.pre_bill")
  const canCharge = useCan("cashier.charge")
  const canTransfer = useCan("tables.transfer")
  const canVoid = useCan("orders.void_item")

  const preBill = useMutation({
    mutationFn: requestPreBill,
    onSuccess: (updated) => {
      queryClient.setQueryData(operationKeys.order(updated.id), updated)
      print(<PreBillTicket order={updated} company={session.user.company} />)
    },
    onError: (error) => toast.error(error.message),
  })

  const transfer = useMutation({
    mutationFn: (payload: { from: string; to: string }) => transferTable(payload.from, payload.to),
    onSuccess: (updated) => {
      toast.success(`Cuenta ${updated.order_number} ahora en ${updated.table?.code ?? "-"}.`)
      void queryClient.invalidateQueries({ queryKey: operationKeys.floorPlan() })
      setMoving(false)
      onClose()
    },
    onError: (error) => toast.error(error.message),
  })

  const data = order.data

  return (
    <Sheet open={table !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-xl">
        <SheetHeader className="border-b">
          <SheetTitle className="flex items-center gap-3">
            <span className="font-mono tabular-nums">{table?.code}</span>
            <span className="font-mono text-sm font-normal text-muted-foreground tabular-nums">{table?.order?.order_number}</span>
          </SheetTitle>
          <SheetDescription className="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span>
              Comensales <span className="font-mono tabular-nums">{table?.order?.diners_count}</span>
            </span>
            <span>Mozo {table?.order?.waiter_name ?? "-"}</span>
            {table?.opened_at === null || table?.opened_at === undefined ? null : (
              <span>
                Abierta <ElapsedTimer since={table.opened_at} />
              </span>
            )}
          </SheetDescription>
        </SheetHeader>

        <ScrollArea className="min-h-0 flex-1">
          <div className="px-4 py-3">
            {data === undefined ? (
              <div className="flex flex-col gap-2">
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-8 w-2/3" />
              </div>
            ) : (
              <OrderLines items={data.items ?? []} {...(canVoid ? { onVoid: setVoiding } : {})} />
            )}
          </div>
        </ScrollArea>

        {data === undefined ? null : (
          <div className="border-t px-4 py-3">
            <OrderTotals order={data} />
          </div>
        )}

        {moving ? (
          <div className="border-t px-4 py-3">
            <p className="mb-2 text-xs text-muted-foreground">Mesa destino: libre para cambiar, ocupada para unir cuentas.</p>
            <div className="grid grid-cols-4 gap-1.5 sm:grid-cols-6">
              {candidates
                .filter((candidate) => candidate.id !== table?.id && candidate.status !== "RESERVED")
                .map((candidate) => (
                  <Button
                    key={candidate.id}
                    variant="outline"
                    size="sm"
                    className="font-mono tabular-nums"
                    disabled={transfer.isPending}
                    onClick={() => (table === null ? undefined : transfer.mutate({ from: table.id, to: candidate.id }))}
                  >
                    {candidate.code}
                    {candidate.status === "AVAILABLE" ? null : <span className="size-1.5 rounded-full bg-status-occupied" aria-label="ocupada" />}
                  </Button>
                ))}
            </div>
          </div>
        ) : null}

        <Separator />
        <SheetFooter className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          <Button variant="outline" disabled={orderId === null} onClick={() => (orderId === null ? undefined : goToWaiter(orderId))}>
            <CookingPot aria-hidden="true" />
            Agregar platos
          </Button>
          <Button variant="outline" disabled={!canPreBill || orderId === null || preBill.isPending} onClick={() => (orderId === null ? undefined : preBill.mutate(orderId))}>
            {preBill.isPending ? <LoaderCircle className="animate-spin" aria-hidden="true" /> : <Printer aria-hidden="true" />}
            Imprimir pre-cuenta
          </Button>
          <Button variant="outline" disabled={!canTransfer} onClick={() => setMoving((value) => !value)} aria-pressed={moving}>
            <ArrowRightLeft aria-hidden="true" />
            Cambiar / unir
          </Button>
          <Button disabled={!canCharge || orderId === null} onClick={() => (orderId === null ? undefined : goToCashier(orderId))}>
            <Receipt aria-hidden="true" />
            Pasar a cobro
          </Button>
        </SheetFooter>
      </SheetContent>

      {orderId === null ? null : <VoidItemDialog orderId={orderId} item={voiding} onClose={() => setVoiding(null)} />}
    </Sheet>
  )
}
