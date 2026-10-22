import { BUSINESS_TIMEZONE } from "@/lib/format"
import type { DateRange } from "@/types/reports"

/**
 * Fechas de negocio (YYYY-MM-DD) en la zona horaria del restaurante. El
 * backend las convierte a instantes UTC [inicio, fin) para cada reporte.
 */

const isoDateFormatter = new Intl.DateTimeFormat("en-CA", {
  timeZone: BUSINESS_TIMEZONE,
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
})

/** Fecha de Lima de un instante: 2026-10-21. */
export function businessDate(date: Date = new Date()): string {
  return isoDateFormatter.format(date)
}

/** "2026-10-21" -> Date local a mediodía (estable ante cambios de zona en el calendario). */
export function parseBusinessDate(value: string): Date {
  const [year = "1970", month = "01", day = "01"] = value.split("-")

  return new Date(Number(year), Number(month) - 1, Number(day), 12)
}

/** Date del calendario (hora local del navegador) -> "2026-10-21". */
export function toBusinessDate(date: Date): string {
  return `${String(date.getFullYear())}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
}

/** "2026-10-21" -> "21/10/2026". */
export function formatBusinessDay(value: string): string {
  const [year = "", month = "", day = ""] = value.split("-")

  return `${day}/${month}/${year}`
}

export type RangePreset = "today" | "last7" | "month"

export function presetRange(preset: RangePreset, today: string = businessDate()): DateRange {
  const base = parseBusinessDate(today)

  if (preset === "today") {
    return { from: today, to: today }
  }

  if (preset === "last7") {
    const start = new Date(base)
    start.setDate(start.getDate() - 6)

    return { from: toBusinessDate(start), to: today }
  }

  return { from: `${today.slice(0, 8)}01`, to: today }
}

/** Periodo tributario AAAAMM de los últimos `count` meses (el actual primero). */
export function recentTaxPeriods(count: number, today: string = businessDate()): { value: string; label: string }[] {
  const base = parseBusinessDate(today)
  const formatter = new Intl.DateTimeFormat("es-PE", { month: "long", year: "numeric" })

  return Array.from({ length: count }, (_, index) => {
    const date = new Date(base.getFullYear(), base.getMonth() - index, 1, 12)

    return {
      value: `${String(date.getFullYear())}${String(date.getMonth() + 1).padStart(2, "0")}`,
      label: formatter.format(date),
    }
  })
}
