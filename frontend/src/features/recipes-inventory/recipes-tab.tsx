import { useQuery } from "@tanstack/react-query"
import { useState, type ReactNode } from "react"

import { reportQueries } from "@/api/reports"
import { Amount } from "@/components/shared/amount"
import { ReportSection } from "@/components/shared/report-section"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { cn } from "@/lib/utils"
import { PRODUCTION_AREA_LABELS } from "@/types/operations"
import type { Recipe } from "@/types/reports"

const AFFECTATION_LABELS: Record<Recipe["tax_affectation_type"], string> = { "10": "Gravado", "20": "Exonerado", "30": "Inafecto" }

/** Escandallo técnico: fracciones que descuenta cada plato en salón y en para llevar / delivery. */
export function RecipesTab() {
  const recipes = useQuery(reportQueries.recipes())
  const [selectedId, setSelectedId] = useState<string | null>(null)

  if (recipes.isPending) {
    return <Skeleton className="h-72 w-full" />
  }

  if (recipes.isError) {
    return <p className="text-sm text-status-delayed">{recipes.error.message}</p>
  }

  const selected = recipes.data.find((recipe) => recipe.id === selectedId) ?? recipes.data[0] ?? null

  return (
    <div className="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
      <ReportSection title="Carta">
        <ul className="max-h-[32rem] divide-y overflow-y-auto">
          {recipes.data.map((recipe) => (
            <li key={recipe.id}>
              <button
                type="button"
                aria-pressed={selected?.id === recipe.id}
                onClick={() => setSelectedId(recipe.id)}
                className={cn("flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm hover:bg-accent", selected?.id === recipe.id && "bg-accent")}
              >
                <span className="min-w-0">
                  <span className="block truncate">{recipe.name}</span>
                  <span className="font-mono text-[11px] text-muted-foreground">{recipe.sku}</span>
                </span>
                <span className="font-mono text-xs tabular-nums text-muted-foreground">{recipe.food_cost_percentage === null ? "-" : `${recipe.food_cost_percentage}%`}</span>
              </button>
            </li>
          ))}
        </ul>
      </ReportSection>

      {selected === null ? null : <RecipeDetail recipe={selected} />}
    </div>
  )
}

function RecipeDetail({ recipe }: { readonly recipe: Recipe }) {
  return (
    <ReportSection
      title={`Escandallo · ${recipe.name}`}
      aside={
        <span className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
          <span>{recipe.category}</span>
          <span>{PRODUCTION_AREA_LABELS[recipe.production_area]}</span>
          <span>IGV: {AFFECTATION_LABELS[recipe.tax_affectation_type]}</span>
        </span>
      }
    >
      <dl className="grid grid-cols-2 gap-px border-b bg-border sm:grid-cols-4">
        <Figure label="Precio carta">
          <Amount value={recipe.sale_price} />
        </Figure>
        <Figure label="Valor de venta (sin IGV)">
          <Amount value={recipe.net_price} />
        </Figure>
        <Figure label="Costo salón / llevar">
          <span className="font-mono tabular-nums">
            {recipe.dine_in_cost} / {recipe.takeout_cost}
          </span>
        </Figure>
        <Figure label="Food cost salón">
          <span className="font-mono tabular-nums">{recipe.food_cost_percentage === null ? "-" : `${recipe.food_cost_percentage}%`}</span>
        </Figure>
      </dl>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Insumo</TableHead>
            <TableHead className="text-right">Descuenta en salón</TableHead>
            <TableHead className="text-right">Descuenta para llevar</TableHead>
            <TableHead className="text-right">Costo unit.</TableHead>
            <TableHead className="text-right">Costo línea</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {recipe.lines.map((line) => (
            <TableRow key={line.ingredient.id}>
              <TableCell>
                <span>{line.ingredient.name}</span>
                <span className="ml-2 font-mono text-[11px] text-muted-foreground">{line.ingredient.sku}</span>
                {line.apply_only_on_takeout ? <span className="ml-2 rounded-sm border px-1 py-px font-mono text-[10px] text-muted-foreground">ENVASE</span> : null}
              </TableCell>
              <TableCell className={cn("text-right font-mono tabular-nums", line.apply_only_on_takeout && "text-muted-foreground")}>
                {line.dine_in_quantity} {line.ingredient.unit_code}
              </TableCell>
              <TableCell className="text-right font-mono tabular-nums">
                {line.takeout_quantity} {line.ingredient.unit_code}
              </TableCell>
              <TableCell className="text-right font-mono tabular-nums text-muted-foreground">{line.unit_cost}</TableCell>
              <TableCell className="text-right font-mono tabular-nums">{line.line_cost}</TableCell>
            </TableRow>
          ))}
        </TableBody>
        <TableFooter>
          <TableRow>
            <TableCell>Costo por porción (S/)</TableCell>
            <TableCell className="text-right font-mono tabular-nums">{recipe.dine_in_cost}</TableCell>
            <TableCell className="text-right font-mono tabular-nums">{recipe.takeout_cost}</TableCell>
            <TableCell colSpan={2} className="text-right text-xs font-normal text-muted-foreground">
              Empaque cobrado en llevar: <Amount value={recipe.packaging_surcharge} />
            </TableCell>
          </TableRow>
        </TableFooter>
      </Table>
    </ReportSection>
  )
}

function Figure({ label, children }: { readonly label: string; readonly children: ReactNode }) {
  return (
    <div className="bg-background px-4 py-2.5">
      <dt className="text-[11px] tracking-wide text-muted-foreground uppercase">{label}</dt>
      <dd className="mt-0.5 text-sm font-medium">{children}</dd>
    </div>
  )
}
