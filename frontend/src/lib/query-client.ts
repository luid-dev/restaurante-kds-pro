import { MutationCache, QueryCache, QueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { SESSION_QUERY_KEY } from "@/api/auth"
import { ApiError } from "@/api/http"

declare module "@tanstack/react-query" {
  // Todo error de query/mutation pasa por toApiError (interceptor de Axios).
  interface Register {
    defaultError: ApiError
  }
}

const MAX_RETRIES = 2

/**
 * Un 401 en cualquier llamada (sesión cerrada o PIN expirado por inactividad)
 * devuelve la terminal a la pantalla de acceso.
 */
function handleSessionLoss(error: ApiError): void {
  if (error.status !== 401 || error.code === "INVALID_CREDENTIALS") {
    return
  }

  if (queryClient.getQueryData(SESSION_QUERY_KEY) !== null) {
    queryClient.setQueryData(SESSION_QUERY_KEY, null)

    if (error.code === "SESSION_EXPIRED") {
      toast.warning(error.message)
    }
  }
}

export const queryClient: QueryClient = new QueryClient({
  queryCache: new QueryCache({ onError: handleSessionLoss }),
  mutationCache: new MutationCache({ onError: handleSessionLoss }),
  defaultOptions: {
    queries: {
      staleTime: 15_000,
      refetchOnWindowFocus: false,
      // Los 4xx son definitivos (validación, permisos, sesión): reintentar no los corrige.
      retry: (failureCount, error) => !(error instanceof ApiError && error.isClientError) && failureCount < MAX_RETRIES,
    },
    mutations: {
      retry: false,
    },
  },
})
