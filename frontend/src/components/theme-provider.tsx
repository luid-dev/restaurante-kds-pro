import { useCallback, useEffect, useMemo, useState, type ReactNode } from "react"

import { applyTheme, readStoredTheme, storeTheme, subscribeSystemTheme, ThemeContext, type Theme } from "@/lib/theme"

interface ThemeProviderProps {
  readonly children: ReactNode
  readonly defaultTheme?: Theme
}

export function ThemeProvider({ children, defaultTheme = "system" }: ThemeProviderProps) {
  const [theme, setThemeState] = useState<Theme>(() => readStoredTheme(defaultTheme))

  useEffect(() => {
    applyTheme(theme)

    return theme === "system" ? subscribeSystemTheme(() => applyTheme("system")) : undefined
  }, [theme])

  const setTheme = useCallback((next: Theme) => {
    storeTheme(next)
    setThemeState(next)
  }, [])

  const value = useMemo(() => ({ theme, setTheme }), [theme, setTheme])

  return <ThemeContext value={value}>{children}</ThemeContext>
}
