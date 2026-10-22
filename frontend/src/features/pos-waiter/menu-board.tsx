import { Search } from "lucide-react"
import { useMemo, useState } from "react"

import { Amount } from "@/components/shared/amount"
import { Input } from "@/components/ui/input"
import { ScrollArea } from "@/components/ui/scroll-area"
import { cn } from "@/lib/utils"
import { PRODUCTION_AREA_LABELS, type Menu, type MenuItem } from "@/types/operations"

interface MenuBoardProps {
  readonly menu: Menu
  readonly onPick: (item: MenuItem) => void
}

/** Carta técnica: acceso por categoría, búsqueda y grilla densa de platos. */
export function MenuBoard({ menu, onPick }: MenuBoardProps) {
  const [categoryId, setCategoryId] = useState<string | null>(menu.categories[0]?.id ?? null)
  const [search, setSearch] = useState("")
  const term = search.trim().toLocaleLowerCase("es")

  const items = useMemo(() => {
    if (term !== "") {
      return menu.categories.flatMap((category) => category.items).filter((item) => `${item.name} ${item.sku}`.toLocaleLowerCase("es").includes(term))
    }

    return menu.categories.find((category) => category.id === categoryId)?.items ?? []
  }, [menu, categoryId, term])

  return (
    <section aria-label="Carta" className="flex min-h-0 flex-col gap-3">
      <div className="flex flex-wrap gap-1.5">
        {menu.categories.map((category) => (
          <button
            key={category.id}
            type="button"
            onClick={() => {
              setCategoryId(category.id)
              setSearch("")
            }}
            aria-pressed={term === "" && categoryId === category.id}
            className={cn(
              "h-10 rounded-md border px-3 text-sm transition-colors hover:bg-accent",
              term === "" && categoryId === category.id && "border-foreground bg-foreground text-background hover:bg-foreground/90",
            )}
          >
            {category.name}
            <span className="ml-2 font-mono text-xs opacity-60 tabular-nums">{category.items.length}</span>
          </button>
        ))}
        <div className="relative ml-auto w-full sm:w-56">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
          <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar plato o código" className="h-10 pl-8" aria-label="Buscar en la carta" />
        </div>
      </div>

      <ScrollArea className="min-h-0 flex-1 rounded-md border">
        {items.length === 0 ? (
          <p className="px-4 py-10 text-center text-sm text-muted-foreground">Sin platos disponibles en esta selección.</p>
        ) : (
          <div className="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-px bg-border">
            {items.map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => onPick(item)}
                className="flex min-h-24 flex-col justify-between gap-2 bg-background p-3 text-left transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
              >
                <span className="text-sm leading-snug font-medium">{item.name}</span>
                <span className="flex items-end justify-between gap-2">
                  <span className="font-mono text-[11px] text-muted-foreground uppercase">
                    {item.sku}
                    <span className="block normal-case">{PRODUCTION_AREA_LABELS[item.production_area]}</span>
                  </span>
                  <Amount value={item.sale_price} className="text-sm font-semibold" />
                </span>
              </button>
            ))}
          </div>
        )}
      </ScrollArea>
    </section>
  )
}
