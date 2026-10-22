import type { ReactNode } from "react"
import { create } from "zustand"

interface PrintState {
  /** Documento térmico pendiente de imprimir (comanda, precuenta o comprobante). */
  readonly document: ReactNode
  print: (document: ReactNode) => void
  clear: () => void
}

export const usePrintStore = create<PrintState>()((set) => ({
  document: null,
  print: (document) => set({ document }),
  clear: () => set({ document: null }),
}))
