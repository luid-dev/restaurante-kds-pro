import { create } from "zustand"

interface OrderFocusState {
  /** Comanda abierta en la terminal del mozo. */
  readonly waiterOrderId: string | null
  /** Comanda que caja tiene en el modal de cobro. */
  readonly cashierOrderId: string | null
  focusWaiterOrder: (orderId: string | null) => void
  focusCashierOrder: (orderId: string | null) => void
}

/**
 * Comanda en foco entre módulos (plano -> toma de pedidos -> caja). No se
 * persiste: al recargar, la terminal vuelve al plano de mesas.
 */
export const useOrderFocusStore = create<OrderFocusState>()((set) => ({
  waiterOrderId: null,
  cashierOrderId: null,
  focusWaiterOrder: (orderId) => set({ waiterOrderId: orderId }),
  focusCashierOrder: (orderId) => set({ cashierOrderId: orderId }),
}))
