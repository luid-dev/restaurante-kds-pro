import { queryOptions } from "@tanstack/react-query"

import { http, unwrap } from "@/api/http"
import { healthSchema, type Health } from "@/types/api"

export interface HealthProbe extends Health {
  readonly latencyMs: number
}

async function fetchHealth(signal: AbortSignal): Promise<HealthProbe> {
  const startedAt = performance.now()
  const response = await http.get<unknown>("/health", { signal })
  const health = unwrap(response, healthSchema)

  return { ...health, latencyMs: Math.round(performance.now() - startedAt) }
}

export const systemQueries = {
  health: () =>
    queryOptions({
      queryKey: ["system", "health"] as const,
      queryFn: ({ signal }) => fetchHealth(signal),
      refetchInterval: 30_000,
      refetchIntervalInBackground: true,
      retry: false,
      staleTime: 10_000,
    }),
}
