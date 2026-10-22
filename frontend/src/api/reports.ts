import { keepPreviousData, queryOptions } from "@tanstack/react-query"
import { z } from "zod"

import { http, unwrap, unwrapWithMeta } from "@/api/http"
import { downloadFile } from "@/lib/download"
import { saleSchema, saleSummarySchema, type Sale, type SaleDocumentType } from "@/types/operations"
import {
  dashboardMetricsSchema,
  ingredientSchema,
  ingredientsMetaSchema,
  kardexMetaSchema,
  kardexMovementSchema,
  paginationSchema,
  recipeSchema,
  wasteAuditSchema,
  type CreditNoteReasonCode,
  type DateRange,
  type KardexMovementType,
} from "@/types/reports"

/**
 * API de rentabilidad, inventario y comprobantes. Cada respuesta se valida con
 * Zod; el backend exige reports.view, inventory.manage, recipes.manage o sales.*.
 */

export interface SalesFilter extends DateRange {
  readonly documentType: SaleDocumentType | null
  readonly search: string
  readonly page: number
}

export interface KardexFilter extends DateRange {
  readonly ingredient: string | null
  readonly movementType: KardexMovementType | null
  readonly page: number
}

export const reportKeys = {
  all: ["reports"] as const,
  dashboard: (range: DateRange) => [...reportKeys.all, "dashboard", range] as const,
  sales: (filter: SalesFilter) => [...reportKeys.all, "sales", filter] as const,
  sale: (saleId: string) => [...reportKeys.all, "sale", saleId] as const,
  ingredients: () => [...reportKeys.all, "ingredients"] as const,
  recipes: () => [...reportKeys.all, "recipes"] as const,
  kardex: (filter: KardexFilter) => [...reportKeys.all, "kardex", filter] as const,
  waste: (range: DateRange) => [...reportKeys.all, "waste", range] as const,
}

function rangeParams(range: DateRange): Record<string, string> {
  return { from: range.from, to: range.to }
}

export const reportQueries = {
  dashboard: (range: DateRange) =>
    queryOptions({
      queryKey: reportKeys.dashboard(range),
      queryFn: async ({ signal }) => unwrap(await http.get<unknown>("/reports/dashboard-metrics", { signal, params: rangeParams(range) }), dashboardMetricsSchema),
      placeholderData: keepPreviousData,
    }),
  sales: (filter: SalesFilter) =>
    queryOptions({
      queryKey: reportKeys.sales(filter),
      queryFn: async ({ signal }) =>
        unwrapWithMeta(
          await http.get<unknown>("/sales", {
            signal,
            params: {
              ...rangeParams(filter),
              page: String(filter.page),
              ...(filter.documentType === null ? {} : { document_type: filter.documentType }),
              ...(filter.search.trim() === "" ? {} : { search: filter.search.trim() }),
            },
          }),
          z.array(saleSummarySchema),
          z.object({ pagination: paginationSchema }),
        ),
      placeholderData: keepPreviousData,
    }),
  sale: (saleId: string) =>
    queryOptions({
      queryKey: reportKeys.sale(saleId),
      queryFn: async ({ signal }) => unwrap(await http.get<unknown>(`/sales/${saleId}`, { signal }), saleSchema),
    }),
  ingredients: () =>
    queryOptions({
      queryKey: reportKeys.ingredients(),
      queryFn: async ({ signal }) => unwrapWithMeta(await http.get<unknown>("/inventory/ingredients", { signal }), z.array(ingredientSchema), ingredientsMetaSchema),
    }),
  recipes: () =>
    queryOptions({
      queryKey: reportKeys.recipes(),
      queryFn: async ({ signal }) => unwrap(await http.get<unknown>("/inventory/recipes", { signal }), z.array(recipeSchema)),
      staleTime: 5 * 60_000,
    }),
  kardex: (filter: KardexFilter) =>
    queryOptions({
      queryKey: reportKeys.kardex(filter),
      queryFn: async ({ signal }) =>
        unwrapWithMeta(
          await http.get<unknown>("/inventory/kardex", {
            signal,
            params: {
              ...rangeParams(filter),
              page: String(filter.page),
              ...(filter.ingredient === null ? {} : { ingredient: filter.ingredient }),
              ...(filter.movementType === null ? {} : { movement_type: filter.movementType }),
            },
          }),
          z.array(kardexMovementSchema),
          kardexMetaSchema,
        ),
      placeholderData: keepPreviousData,
    }),
  waste: (range: DateRange) =>
    queryOptions({
      queryKey: reportKeys.waste(range),
      queryFn: async ({ signal }) => unwrap(await http.get<unknown>("/reports/waste-audit", { signal, params: rangeParams(range) }), wasteAuditSchema),
      placeholderData: keepPreviousData,
    }),
}

export async function issueCreditNote(saleId: string, reason: CreditNoteReasonCode, description: string): Promise<Sale> {
  return unwrap(await http.post<unknown>(`/sales/${saleId}/credit-notes`, { reason, description }), saleSchema)
}

export const reportDownloads = {
  xml: (saleId: string) => downloadFile(`/sales/${saleId}/xml`),
  cdr: (saleId: string) => downloadFile(`/sales/${saleId}/cdr`),
  sire: (taxPeriod: string) => downloadFile("/reports/sales-sire", { period: taxPeriod }),
  kardex131: (range: DateRange, ingredient: string | null) =>
    downloadFile("/reports/kardex-13-1", { ...rangeParams(range), ...(ingredient === null ? {} : { ingredient }) }),
  wasteCsv: (range: DateRange) => downloadFile("/reports/waste-audit", { ...rangeParams(range), format: "csv" }),
}
