import { z } from "zod"
import { create } from "zustand"
import { createJSONStorage, persist } from "zustand/middleware"

import { AUTH_METHODS, type AuthMethod } from "@/types/auth"

interface TerminalState {
  /** RUC de la empresa a la que está vinculada esta terminal (dato público, no secreto). */
  readonly companyRuc: string | null
  readonly preferredMethod: AuthMethod
  linkCompany: (ruc: string) => void
  preferMethod: (method: AuthMethod) => void
}

type PersistedTerminal = Pick<TerminalState, "companyRuc" | "preferredMethod">

const persistedSchema = z.object({
  companyRuc: z.string().regex(/^\d{11}$/).nullable(),
  preferredMethod: z.enum(AUTH_METHODS),
})

/**
 * Configuración de la terminal física. Nunca guarda credenciales ni PIN:
 * solo el RUC vinculado y el método de acceso habitual.
 */
export const useTerminalStore = create<TerminalState>()(
  persist<TerminalState, [], [], PersistedTerminal>(
    (set) => ({
      companyRuc: null,
      preferredMethod: "PIN",
      linkCompany: (ruc) => set({ companyRuc: ruc }),
      preferMethod: (method) => set({ preferredMethod: method }),
    }),
    {
      name: "kds-pro.terminal",
      version: 1,
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ companyRuc: state.companyRuc, preferredMethod: state.preferredMethod }),
      merge: (persisted, current) => {
        const parsed = persistedSchema.safeParse(persisted)

        return parsed.success ? { ...current, ...parsed.data } : current
      },
    },
  ),
)
