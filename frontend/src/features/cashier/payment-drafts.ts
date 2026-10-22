import { fromCents, toCents } from "@/lib/money"
import type { ChargePayload, PaymentMethod } from "@/types/operations"

const AMOUNT_PATTERN = /^\d{1,6}(\.\d{1,2})?$/

/**
 * Fila de pago en edición. `amount === null` = "el resto": toma automáticamente
 * lo que falta para llegar al total (solo una fila puede estar en automático).
 */
export interface PaymentDraft {
  readonly id: string
  readonly method: PaymentMethod
  readonly amount: string | null
  readonly received: string
  readonly reference: string
}

export function newPayment(method: PaymentMethod): PaymentDraft {
  return { id: crypto.randomUUID(), method, amount: null, received: "", reference: "" }
}

export function isAmount(value: string): boolean {
  return AMOUNT_PATTERN.test(value)
}

/** Monto efectivo de cada fila en céntimos (la fila automática recibe el saldo). */
export function effectiveCents(payments: readonly PaymentDraft[], totalCents: number): number[] {
  const explicit = payments.reduce((sum, payment) => sum + (payment.amount !== null && isAmount(payment.amount) ? toCents(payment.amount) : 0), 0)

  return payments.map((payment) => {
    if (payment.amount === null) {
      return Math.max(0, totalCents - explicit)
    }

    return isAmount(payment.amount) ? toCents(payment.amount) : 0
  })
}

/** Agregar un medio fija los montos actuales y deja al nuevo como "el resto". */
export function addPayment(payments: readonly PaymentDraft[], method: PaymentMethod, totalCents: number): PaymentDraft[] {
  const amounts = effectiveCents(payments, totalCents)
  const fixed = payments.map((payment, index) => (payment.amount === null ? { ...payment, amount: fromCents(amounts[index] ?? 0) } : payment))

  return [...fixed, newPayment(method)]
}

export interface PaymentCheck {
  readonly amounts: number[]
  readonly remainingCents: number
  readonly valid: boolean
}

export function checkPayments(payments: readonly PaymentDraft[], totalCents: number): PaymentCheck {
  const amounts = effectiveCents(payments, totalCents)
  const paid = amounts.reduce((sum, amount) => sum + amount, 0)
  const cashCovered = payments.every((payment, index) => {
    if (payment.method !== "CASH" || payment.received === "") {
      return true
    }

    return isAmount(payment.received) && toCents(payment.received) >= (amounts[index] ?? 0)
  })

  return {
    amounts,
    remainingCents: totalCents - paid,
    valid: totalCents > 0 && paid === totalCents && amounts.every((amount) => amount > 0) && cashCovered,
  }
}

export function toPaymentPayload(payments: readonly PaymentDraft[], amounts: readonly number[]): ChargePayload["payments"] {
  return payments.map((payment, index) => ({
    method: payment.method,
    amount: fromCents(amounts[index] ?? 0),
    ...(payment.method === "CASH" && payment.received !== "" ? { received_amount: payment.received } : {}),
    ...(payment.method !== "CASH" && payment.reference.trim() !== "" ? { reference: payment.reference.trim() } : {}),
  }))
}
