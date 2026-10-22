import { z } from "zod"
import { create } from "zustand"
import { createJSONStorage, persist } from "zustand/middleware"

import { WORKSPACE_VIEWS, type WorkspaceView } from "@/types/navigation"

interface WorkspaceState {
  readonly activeView: WorkspaceView
  setActiveView: (view: WorkspaceView) => void
}

type PersistedWorkspace = Pick<WorkspaceState, "activeView">

const persistedSchema = z.object({
  activeView: z.enum(WORKSPACE_VIEWS),
})

/**
 * Vista activa de la terminal. Se recuerda por dispositivo para que una
 * pantalla de cocina vuelva al KDS tras recargar.
 */
export const useWorkspaceStore = create<WorkspaceState>()(
  persist<WorkspaceState, [], [], PersistedWorkspace>(
    (set) => ({
      activeView: "floor-plan",
      setActiveView: (view) => set({ activeView: view }),
    }),
    {
      name: "kds-pro.workspace",
      version: 1,
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ activeView: state.activeView }),
      // Un valor manipulado o de una versión anterior no debe romper la terminal.
      merge: (persisted, current) => {
        const parsed = persistedSchema.safeParse(persisted)

        return parsed.success ? { ...current, ...parsed.data } : current
      },
    },
  ),
)
