import { CalendarRange } from "lucide-react"
import { useState } from "react"
import type { DateRange as DayPickerRange } from "react-day-picker"
import { es } from "react-day-picker/locale"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { businessDate, formatBusinessDay, parseBusinessDate, presetRange, toBusinessDate, type RangePreset } from "@/lib/dates"
import type { DateRange } from "@/types/reports"

const PRESETS: readonly { readonly preset: RangePreset; readonly label: string }[] = [
  { preset: "today", label: "Hoy" },
  { preset: "last7", label: "7 días" },
  { preset: "month", label: "Mes en curso" },
]

interface DateRangePickerProps {
  readonly value: DateRange
  readonly onChange: (range: DateRange) => void
}

/** Rango de fechas de negocio (Lima). Solo confirma cuando ambos extremos están elegidos. */
export function DateRangePicker({ value, onChange }: DateRangePickerProps) {
  const [open, setOpen] = useState(false)
  const [draft, setDraft] = useState<DayPickerRange | undefined>(undefined)
  const today = businessDate()
  const selected: DayPickerRange = draft ?? { from: parseBusinessDate(value.from), to: parseBusinessDate(value.to) }

  function apply(range: DateRange) {
    onChange(range)
    setDraft(undefined)
    setOpen(false)
  }

  function select(range: DayPickerRange | undefined) {
    if (range?.from !== undefined && range.to !== undefined && draft !== undefined) {
      apply({ from: toBusinessDate(range.from), to: toBusinessDate(range.to) })

      return
    }

    setDraft(range)
  }

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        setDraft(undefined)
      }}
    >
      <PopoverTrigger asChild>
        <Button variant="outline" size="sm" className="font-mono text-xs tabular-nums">
          <CalendarRange className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
          {value.from === value.to ? formatBusinessDay(value.from) : `${formatBusinessDay(value.from)} - ${formatBusinessDay(value.to)}`}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-auto p-0">
        <div className="flex gap-1 border-b p-2">
          {PRESETS.map((option) => (
            <Button key={option.preset} variant="ghost" size="sm" className="h-7 font-mono text-xs" onClick={() => apply(presetRange(option.preset, today))}>
              [{option.label}]
            </Button>
          ))}
        </div>
        <Calendar
          mode="range"
          locale={es}
          numberOfMonths={2}
          defaultMonth={selected.from ?? parseBusinessDate(value.from)}
          selected={selected}
          onSelect={select}
          disabled={{ after: parseBusinessDate(today) }}
          resetOnSelect
        />
        <p className="border-t px-3 py-2 text-xs text-muted-foreground">Elija la fecha inicial y luego la final (zona horaria America/Lima).</p>
      </PopoverContent>
    </Popover>
  )
}
