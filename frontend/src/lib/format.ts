/**
 * Formateadores de presentación. Los montos llegan del backend como string
 * decimal ("12.50") y aquí solo se presentan: ningún cálculo usa floats.
 */

export const BUSINESS_TIMEZONE = "America/Lima"

const penFormatter = new Intl.NumberFormat("es-PE", {
  style: "currency",
  currency: "PEN",
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const clockFormatter = new Intl.DateTimeFormat("es-PE", {
  timeZone: BUSINESS_TIMEZONE,
  hour: "2-digit",
  minute: "2-digit",
  second: "2-digit",
  hourCycle: "h23",
})

const dateFormatter = new Intl.DateTimeFormat("es-PE", {
  timeZone: BUSINESS_TIMEZONE,
  weekday: "short",
  day: "2-digit",
  month: "short",
})

const DECIMAL_PATTERN = /^-?\d+(\.\d+)?$/

/** "12.5" -> "S/ 12.50". Rechaza valores no decimales en vez de mostrar NaN. */
export function formatPEN(amount: string): string {
  if (!DECIMAL_PATTERN.test(amount)) {
    throw new RangeError(`Monto inválido: ${amount}`)
  }

  return penFormatter.format(Number(amount))
}

/** Hora de Lima con segundos, para el reloj de turno: "14:32:05". */
export function formatClock(date: Date): string {
  return clockFormatter.format(date)
}

/** Fecha corta de Lima: "mar., 21 oct." */
export function formatBusinessDate(date: Date): string {
  return dateFormatter.format(date)
}

/** Cronómetro de preparación KDS: 525 s -> "08:45"; >= 1 h -> "1:02:10". */
export function formatElapsed(totalSeconds: number): string {
  const safe = Math.max(0, Math.floor(totalSeconds))
  const hours = Math.floor(safe / 3600)
  const minutes = Math.floor((safe % 3600) / 60)
  const seconds = safe % 60
  const mmss = `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`

  return hours > 0 ? `${hours}:${mmss}` : mmss
}

/** Código de mesa: 12 -> "M-12". */
export function formatTableCode(number: number): string {
  return `M-${String(number).padStart(2, "0")}`
}

/** Serie y correlativo SUNAT: ("B001", 450) -> "B001-00000450". */
export function formatSunatNumber(series: string, correlative: number): string {
  return `${series}-${String(correlative).padStart(8, "0")}`
}

/** Tiempo de mesa abierta: 2052 s -> "00:34:12". */
export function formatDuration(totalSeconds: number): string {
  const safe = Math.max(0, Math.floor(totalSeconds))
  const parts = [Math.floor(safe / 3600), Math.floor((safe % 3600) / 60), safe % 60]

  return parts.map((part) => String(part).padStart(2, "0")).join(":")
}

const dateTimeFormatter = new Intl.DateTimeFormat("es-PE", {
  timeZone: BUSINESS_TIMEZONE,
  day: "2-digit",
  month: "2-digit",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
  hourCycle: "h23",
})

/** Fecha y hora de Lima para tickets: "21/10/2026 14:32". */
export function formatTicketDateTime(date: Date): string {
  return dateTimeFormatter.format(date).replace(",", "")
}

/** Segundos transcurridos desde un ISO 8601 hasta `now`. */
export function secondsSince(iso: string, now: Date): number {
  return Math.max(0, Math.floor((now.getTime() - Date.parse(iso)) / 1000))
}
