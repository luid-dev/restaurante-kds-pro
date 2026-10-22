import { useContext } from "react"

import { ThemeContext, type ThemeContextValue } from "@/lib/theme"

export function useTheme(): ThemeContextValue {
  const context = useContext(ThemeContext)

  if (context === null) {
    throw new Error("useTheme debe usarse dentro de <ThemeProvider>.")
  }

  return context
}
