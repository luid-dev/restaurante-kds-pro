import { useSession } from "@/features/auth/use-auth-actions"
import type { Session } from "@/types/auth"

/**
 * Sesión vigente dentro del AppShell (que solo se monta con sesión válida).
 */
export function useActiveSession(): Session {
  const { data } = useSession()

  if (data === null || data === undefined) {
    throw new Error("useActiveSession requiere una sesión iniciada.")
  }

  return data
}

export function useCan(permission: string): boolean {
  return useActiveSession().user.permissions.includes(permission)
}
