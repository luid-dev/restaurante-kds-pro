import { useMutation, useQueryClient } from "@tanstack/react-query"
import { LoaderCircle } from "lucide-react"
import { useState, type FormEvent } from "react"
import { toast } from "sonner"

import { issueCreditNote, reportKeys } from "@/api/reports"
import { Amount } from "@/components/shared/amount"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { usePrintStore } from "@/features/printing/print-store"
import { SunatReceipt } from "@/features/printing/sunat-receipt"
import type { SaleSummary } from "@/types/operations"
import { CREDIT_NOTE_REASONS, type CreditNoteReasonCode } from "@/types/reports"

interface CreditNoteDialogProps {
  readonly sale: SaleSummary
  readonly onClose: () => void
}

function isReasonCode(value: string): value is CreditNoteReasonCode {
  return CREDIT_NOTE_REASONS.some((reason) => reason.code === value)
}

/**
 * Nota de crédito electrónica (07) que anula por completo la factura o boleta:
 * serie FC01 o BC01, motivo del catálogo SUNAT 09 y sustento auditado.
 */
export function CreditNoteDialog({ sale, onClose }: CreditNoteDialogProps) {
  const queryClient = useQueryClient()
  const print = usePrintStore((state) => state.print)
  const reasons = CREDIT_NOTE_REASONS.filter((reason) => !reason.invoiceOnly || sale.document_type === "01")
  const [reason, setReason] = useState<CreditNoteReasonCode>("01")
  const [description, setDescription] = useState("")
  const series = sale.document_type === "01" ? "FC01" : "BC01"

  const mutation = useMutation({
    mutationFn: () => issueCreditNote(sale.id, reason, description.trim()),
    onSuccess: (note) => {
      toast.success(`Nota de crédito ${note.document_number} emitida y firmada.`, {
        action: { label: "Imprimir", onClick: () => print(<SunatReceipt sale={note} />) },
      })
      void queryClient.invalidateQueries({ queryKey: reportKeys.all })
      onClose()
    },
    onError: (error) => toast.error(error.message),
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate()
  }

  return (
    <Dialog open onOpenChange={(open) => (open || mutation.isPending ? undefined : onClose())}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Emitir nota de crédito</DialogTitle>
          <DialogDescription>
            Anula por completo <span className="font-mono tabular-nums text-foreground">{sale.document_number}</span> con la serie{" "}
            <span className="font-mono text-foreground">{series}</span>. Se firma con el certificado de la empresa y queda en el kardex y la auditoría.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className="flex flex-col gap-4">
          <dl className="grid grid-cols-3 gap-px overflow-hidden rounded-md border bg-border text-sm">
            <div className="bg-background px-3 py-2">
              <dt className="text-[11px] tracking-wide text-muted-foreground uppercase">Cliente</dt>
              <dd className="truncate">{sale.customer.name ?? "CLIENTES VARIOS"}</dd>
            </div>
            <div className="bg-background px-3 py-2">
              <dt className="text-[11px] tracking-wide text-muted-foreground uppercase">Documento</dt>
              <dd className="font-mono tabular-nums">{sale.customer.document_number ?? "-"}</dd>
            </div>
            <div className="bg-background px-3 py-2">
              <dt className="text-[11px] tracking-wide text-muted-foreground uppercase">Importe</dt>
              <dd>
                <Amount value={sale.totals.total_amount} />
              </dd>
            </div>
          </dl>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="credit-note-reason">Motivo (catálogo SUNAT 09)</Label>
            <Select value={reason} onValueChange={(value) => (isReasonCode(value) ? setReason(value) : undefined)}>
              <SelectTrigger id="credit-note-reason" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {reasons.map((option) => (
                  <SelectItem key={option.code} value={option.code}>
                    <span className="font-mono text-xs text-muted-foreground">{option.code}</span> {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="credit-note-description">Sustento</Label>
            <Textarea
              id="credit-note-description"
              value={description}
              maxLength={250}
              rows={3}
              placeholder="Ej.: El cliente devolvió el pedido por demora en la entrega."
              onChange={(event) => setDescription(event.target.value)}
            />
            <p className="text-xs text-muted-foreground">
              Mínimo 5 caracteres. {reason === "02" ? "Solo corrige el documento: no afecta el kardex." : "Los insumos ya cocinados se registran como merma (tipo 13)."}
            </p>
          </div>

          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" disabled={mutation.isPending} onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" variant="destructive" disabled={mutation.isPending || description.trim().length < 5}>
              {mutation.isPending ? <LoaderCircle className="size-4 animate-spin" aria-hidden="true" /> : null}
              Emitir {series}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  )
}
