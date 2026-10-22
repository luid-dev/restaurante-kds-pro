/**
 * Aritmética monetaria en céntimos enteros. El backend (BCMath) es la fuente
 * de verdad; aquí solo se estiman totales de la comanda en edición y se reparten
 * partes iguales sin floats: "145.50" -> 14550.
 */

const DECIMAL_PATTERN = /^(-?)(\d+)(?:\.(\d+))?$/

/** "145.5" -> 14550, "0.2500" -> 25 (half-up desde el tercer decimal). */
export function toCents(amount: string): number {
  const match = DECIMAL_PATTERN.exec(amount.trim())

  if (match === null) {
    throw new RangeError(`Monto inválido: ${amount}`)
  }

  const [, sign = "", integer = "0", fraction = ""] = match
  const padded = fraction.padEnd(3, "0")
  const roundUp = Number(padded.charAt(2)) >= 5 ? 1 : 0
  const cents = Number(integer) * 100 + Number(padded.slice(0, 2)) + roundUp

  return sign === "-" ? -cents : cents
}

/** 14550 -> "145.50" */
export function fromCents(cents: number): string {
  if (!Number.isSafeInteger(cents)) {
    throw new RangeError(`Céntimos inválidos: ${String(cents)}`)
  }

  const sign = cents < 0 ? "-" : ""
  const absolute = Math.abs(cents)

  return `${sign}${String(Math.floor(absolute / 100))}.${String(absolute % 100).padStart(2, "0")}`
}

/** Precio por cantidad con 2 decimales ("21.00" x "1.50"), redondeo half-up a céntimos. */
export function lineCents(unitPrice: string, quantity: string): number {
  const hundredths = toCents(quantity)
  const product = toCents(unitPrice) * hundredths

  return Math.floor((product + 50) / 100)
}

/**
 * Reparte un total en partes iguales: los céntimos sobrantes van a las primeras
 * partes (8400 / 3 -> [2800, 2800, 2800]; 10000 / 3 -> [3334, 3333, 3333]).
 */
export function splitEvenly(totalCents: number, parts: number): number[] {
  if (!Number.isInteger(parts) || parts < 1) {
    throw new RangeError("La cuenta debe dividirse en al menos una parte.")
  }

  const base = Math.floor(totalCents / parts)
  const remainder = totalCents - base * parts

  return Array.from({ length: parts }, (_, index) => base + (index < remainder ? 1 : 0))
}

/** "2.00" -> "2", "0.50" -> "0.5": cantidades compactas para tickets y KDS. */
export function formatQuantity(quantity: string): string {
  const [integer = "0", fraction = ""] = quantity.split(".")
  const trimmed = fraction.replace(/0+$/, "")

  return trimmed === "" ? integer : `${integer}.${trimmed}`
}
