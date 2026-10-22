import { useQuery } from "@tanstack/react-query"
import { Printer } from "lucide-react"

import { reportQueries } from "@/api/reports"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Skeleton } from "@/components/ui/skeleton"
import { usePrintStore } from "@/features/printing/print-store"
import { SunatReceipt } from "@/features/printing/sunat-receipt"

interface ReceiptPreviewDialogProps {
  readonly saleId: string
  readonly onClose: () => void
}

/** Representación impresa 80 mm del comprobante (hash y QR SUNAT) con reimpresión. */
export function ReceiptPreviewDialog({ saleId, onClose }: ReceiptPreviewDialogProps) {
  const sale = useQuery(reportQueries.sale(saleId))
  const print = usePrintStore((state) => state.print)

  return (
    <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="flex max-h-[92vh] flex-col gap-0 p-0 sm:max-w-md">
        <DialogHeader className="border-b px-5 py-3.5">
          <DialogTitle className="font-mono text-sm tabular-nums">{sale.data?.document_number ?? "Comprobante"}</DialogTitle>
          <DialogDescription>Ticket térmico de 80 mm tal como sale de la impresora de caja.</DialogDescription>
        </DialogHeader>
        <ScrollArea className="min-h-0 flex-1 bg-muted/40">
          <div className="flex justify-center p-5">
            {sale.isPending ? (
              <Skeleton className="h-96 w-[80mm]" />
            ) : sale.isError ? (
              <p className="text-sm text-status-delayed">{sale.error.message}</p>
            ) : (
              <div className="border bg-white text-black">
                <SunatReceipt sale={sale.data} />
              </div>
            )}
          </div>
        </ScrollArea>
        <div className="flex justify-end gap-2 border-t px-5 py-3">
          <Button variant="outline" onClick={onClose}>
            Cerrar
          </Button>
          <Button disabled={sale.data === undefined} onClick={() => (sale.data === undefined ? undefined : print(<SunatReceipt sale={sale.data} />))}>
            <Printer className="size-4" strokeWidth={1.5} aria-hidden="true" />
            Imprimir
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
