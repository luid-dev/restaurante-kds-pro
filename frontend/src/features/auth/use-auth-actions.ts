import { useMutation, useQuery, useQueryClient, type QueryClient } from "@tanstack/react-query"

import { loginWithCredentials, loginWithPin, logout, SESSION_QUERY_KEY, sessionQueries } from "@/api/auth"
import { defaultViewFor } from "@/components/layout/navigation"
import { useTerminalStore } from "@/stores/terminal-store"
import { useWorkspaceStore } from "@/stores/workspace-store"
import type { AuthMethod, Session } from "@/types/auth"

export function useSession() {
  return useQuery(sessionQueries.current())
}

/**
 * Al cambiar de usuario se descartan los datos en caché del anterior: el
 * siguiente mozo nunca ve información de otra sesión.
 */
function resetUserData(queryClient: QueryClient): void {
  queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== "session" && query.queryKey[0] !== "system" })
}

function useStartSession(method: AuthMethod) {
  const queryClient = useQueryClient()
  const setActiveView = useWorkspaceStore((state) => state.setActiveView)
  const linkCompany = useTerminalStore((state) => state.linkCompany)
  const preferMethod = useTerminalStore((state) => state.preferMethod)

  return (session: Session) => {
    resetUserData(queryClient)
    linkCompany(session.user.company.ruc)
    preferMethod(method)
    setActiveView(defaultViewFor(session.user.roles, session.user.permissions))
    queryClient.setQueryData(SESSION_QUERY_KEY, session)
  }
}

export function useCredentialsLogin() {
  const startSession = useStartSession("PASSWORD")

  return useMutation({ mutationFn: loginWithCredentials, onSuccess: startSession })
}

export function usePinLogin() {
  const startSession = useStartSession("PIN")

  return useMutation({ mutationFn: loginWithPin, onSuccess: startSession })
}

/**
 * Cierra la sesión. Con `lock` la terminal vuelve directamente al teclado PIN
 * para que el siguiente mozo ingrese.
 */
export function useLogout() {
  const queryClient = useQueryClient()
  const preferMethod = useTerminalStore((state) => state.preferMethod)

  return useMutation({
    mutationFn: async ({ lock }: { lock: boolean }) => {
      await logout()

      return lock
    },
    onSettled: (lock) => {
      if (lock === true) {
        preferMethod("PIN")
      }

      resetUserData(queryClient)
      queryClient.setQueryData(SESSION_QUERY_KEY, null)
    },
  })
}
