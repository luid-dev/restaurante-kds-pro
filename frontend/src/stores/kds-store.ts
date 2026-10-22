import { z } from "zod"
import { create } from "zustand"
import { createJSONStorage, persist } from "zustand/middleware"

import { PRODUCTION_AREAS, type ProductionArea } from "@/types/operations"

interface KdsState {
  /** null = todas las áreas. Cada monitor de cocina recuerda su estación. */
  readonly area: ProductionArea | null
  setArea: (area: ProductionArea | null) => void
}

const persistedSchema = z.object({ area: z.enum(PRODUCTION_AREAS).nullable() })

export const useKdsStore = create<KdsState>()(
  persist<KdsState, [], [], Pick<KdsState, "area">>(
    (set) => ({
      area: null,
      setArea: (area) => set({ area }),
    }),
    {
      name: "kds-pro.kds",
      version: 1,
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ area: state.area }),
      merge: (persisted, current) => {
        const parsed = persistedSchema.safeParse(persisted)

        return parsed.success ? { ...current, ...parsed.data } : current
      },
    },
  ),
)
