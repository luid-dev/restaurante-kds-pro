import { useCallback } from "react"

import { useOrderFocusStore } from "@/stores/order-focus-store"
import { useWorkspaceStore } from "@/stores/workspace-store"

/** Saltos entre módulos con la comanda en foco (plano -> toma de pedidos -> caja). */
export function useOperationNavigation() {
  const setActiveView = useWorkspaceStore((state) => state.setActiveView)
  const focusWaiterOrder = useOrderFocusStore((state) => state.focusWaiterOrder)
  const focusCashierOrder = useOrderFocusStore((state) => state.focusCashierOrder)

  const goToWaiter = useCallback(
    (orderId: string) => {
      focusWaiterOrder(orderId)
      setActiveView("pos-waiter")
    },
    [focusWaiterOrder, setActiveView],
  )

  const goToCashier = useCallback(
    (orderId: string) => {
      focusCashierOrder(orderId)
      setActiveView("cashier")
    },
    [focusCashierOrder, setActiveView],
  )

  return { goToWaiter, goToCashier }
}
