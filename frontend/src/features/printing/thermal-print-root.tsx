import { useEffect } from "react"
import { createPortal } from "react-dom"

import { usePrintStore } from "@/features/printing/print-store"

/**
 * Destino de impresión 80 mm. Solo es visible en @media print (ver index.css):
 * el navegador imprime el ticket y nada más de la terminal.
 */
export function ThermalPrintRoot() {
  const document = usePrintStore((state) => state.document)
  const clear = usePrintStore((state) => state.clear)

  useEffect(() => {
    if (document === null) {
      return
    }

    const frame = window.requestAnimationFrame(() => window.print())
    window.addEventListener("afterprint", clear, { once: true })

    return () => {
      window.cancelAnimationFrame(frame)
      window.removeEventListener("afterprint", clear)
    }
  }, [document, clear])

  return createPortal(<div id="thermal-print-root">{document}</div>, window.document.body)
}
