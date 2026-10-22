import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { ArrowLeft, ClipboardList } from "lucide-react"
import { useCallback, useMemo, useState } from "react"
import { toast } from "sonner"

import { operationKeys, operationQueries, sendItemsToKitchen } from "@/api/operations"
import { findNavigationItem } from "@/components/layout/navigation"
import { EmptyPanel } from "@/components/shared/empty-panel"
import { ModuleHeader } from "@/components/shared/module-header"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { VoidItemDialog } from "@/features/orders/void-item-dialog"
import { mergeLine, toPayload, type DraftLine } from "@/features/pos-waiter/draft"
import { ItemOptionsDialog } from "@/features/pos-waiter/item-options-dialog"
import { MenuBoard } from "@/features/pos-waiter/menu-board"
import { OrderPicker } from "@/features/pos-waiter/order-picker"
import { OrderTicketPanel } from "@/features/pos-waiter/order-ticket-panel"
import { useCan } from "@/hooks/use-active-session"
import { useHotkeys, type Hotkey } from "@/hooks/use-hotkeys"
import { useOrderFocusStore } from "@/stores/order-focus-store"
import type { MenuItem, OrderItem } from "@/types/operations"

/**
 * Terminal del mozo: carta técnica (65%) y comanda de la mesa (35%). Solo la
 * ronda nueva viaja a cocina; F4 la envía y el KDS la recibe por Reverb.
 */
export default function WaiterOrderPage() {
  const item = findNavigationItem("pos-waiter")
  const orderId = useOrderFocusStore((state) => state.waiterOrderId)
  const focusOrder = useOrderFocusStore((state) => state.focusWaiterOrder)

  return (
    <div className="flex h-full min-h-0 flex-col">
      <ModuleHeader
        item={item}
        actions={
          orderId === null ? null : (
            <Button variant="outline" size="sm" onClick={() => focusOrder(null)}>
              <ArrowLeft aria-hidden="true" />
              Cambiar comanda
            </Button>
          )
        }
      />
      <div className="min-h-0 flex-1 p-4 md:p-6">
        {orderId === null ? <OrderPicker onPick={focusOrder} /> : <WaiterWorkspace key={orderId} orderId={orderId} />}
      </div>
    </div>
  )
}

function WaiterWorkspace({ orderId }: { readonly orderId: string }) {
  const queryClient = useQueryClient()
  const order = useQuery(operationQueries.order(orderId))
  const menu = useQuery(operationQueries.menu())
  const canSend = useCan("orders.send_to_kitchen")
  const canVoid = useCan("orders.void_item")
  const [draft, setDraft] = useState<readonly DraftLine[]>([])
  const [picking, setPicking] = useState<MenuItem | null>(null)
  const [voiding, setVoiding] = useState<OrderItem | null>(null)

  const send = useMutation({
    mutationFn: () => sendItemsToKitchen(orderId, toPayload(draft)),
    onSuccess: (updated) => {
      queryClient.setQueryData(operationKeys.order(orderId), updated)
      void queryClient.invalidateQueries({ queryKey: operationKeys.floorPlan() })
      setDraft([])
      toast.success(`Comanda ${updated.order_number} enviada a cocina.`)
    },
    onError: (error) => toast.error(error.message),
  })

  const sendDraft = useCallback(() => {
    if (draft.length > 0 && canSend && !send.isPending) {
      send.mutate()
    }
  }, [draft.length, canSend, send])

  const hotkeys = useMemo<Hotkey[]>(() => [{ code: "F4", handler: sendDraft }], [sendDraft])
  useHotkeys(hotkeys)

  if (order.isPending || menu.isPending) {
    return (
      <div className="grid h-full gap-4 lg:grid-cols-[minmax(0,65fr)_minmax(0,35fr)]">
        <Skeleton className="h-full min-h-64" />
        <Skeleton className="h-full min-h-64" />
      </div>
    )
  }

  if (order.isError || menu.isError) {
    return <EmptyPanel icon={ClipboardList} title="No se pudo cargar la comanda" description={(order.error ?? menu.error)?.message ?? ""} />
  }

  const closed = order.data.status === "PAID" || order.data.status === "CANCELLED"

  return (
    <div className="grid h-full min-h-0 gap-4 lg:grid-cols-[minmax(0,65fr)_minmax(0,35fr)]">
      {closed ? (
        <EmptyPanel icon={ClipboardList} title={`Comanda ${order.data.order_number} cerrada`} description="Esta cuenta ya fue cobrada o unida a otra mesa. Elija otra comanda." />
      ) : (
        <MenuBoard menu={menu.data} onPick={setPicking} />
      )}
      <OrderTicketPanel
        order={order.data}
        draft={draft}
        sending={send.isPending}
        onQuantity={(key, quantity) => setDraft((lines) => lines.map((line) => (line.key === key ? { ...line, quantity: Math.min(99, Math.max(1, quantity)) } : line)))}
        onRemove={(key) => setDraft((lines) => lines.filter((line) => line.key !== key))}
        onSend={sendDraft}
        {...(canVoid && !closed ? { onVoid: setVoiding } : {})}
      />
      <ItemOptionsDialog item={picking} modifiers={menu.data.modifiers} onAdd={(line) => setDraft((lines) => mergeLine(lines, line))} onClose={() => setPicking(null)} />
      <VoidItemDialog orderId={orderId} item={voiding} onClose={() => setVoiding(null)} />
    </div>
  )
}
