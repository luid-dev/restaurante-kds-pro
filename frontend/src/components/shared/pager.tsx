import { ChevronLeft, ChevronRight } from "lucide-react"

import { Button } from "@/components/ui/button"
import type { Pagination } from "@/types/reports"

interface PagerProps {
  readonly pagination: Pagination
  readonly onPage: (page: number) => void
  readonly label: string
}

export function Pager({ pagination, onPage, label }: PagerProps) {
  return (
    <nav aria-label={label} className="flex items-center justify-between gap-3 border-t px-3 py-2 text-xs text-muted-foreground">
      <span className="font-mono tabular-nums">
        {pagination.total} registro(s) · página {pagination.current_page} de {Math.max(1, pagination.last_page)}
      </span>
      <span className="flex gap-1">
        <Button variant="outline" size="icon" className="size-7" aria-label="Página anterior" disabled={pagination.current_page <= 1} onClick={() => onPage(pagination.current_page - 1)}>
          <ChevronLeft className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
        </Button>
        <Button
          variant="outline"
          size="icon"
          className="size-7"
          aria-label="Página siguiente"
          disabled={pagination.current_page >= pagination.last_page}
          onClick={() => onPage(pagination.current_page + 1)}
        >
          <ChevronRight className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
        </Button>
      </span>
    </nav>
  )
}
