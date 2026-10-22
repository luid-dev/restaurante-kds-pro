import { createContext } from "react"

/**
 * Tema claro/oscuro para Vite (guía oficial de shadcn/ui para Vite), sin
 * dependencias ni scripts inline: compatible con una CSP estricta.
 */

export const THEMES = ["light", "dark", "system"] as const

export type Theme = (typeof THEMES)[number]

const STORAGE_KEY = "kds-pro.theme"
const DARK_QUERY = "(prefers-color-scheme: dark)"

export function isTheme(value: string | null): value is Theme {
  return THEMES.some((theme) => theme === value)
}

export function readStoredTheme(fallback: Theme): Theme {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)

    return isTheme(stored) ? stored : fallback
  } catch {
    return fallback
  }
}

export function storeTheme(theme: Theme): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, theme)
  } catch {
    // Almacenamiento bloqueado (modo privado): el tema aplica solo a esta sesión.
  }
}

/** Aplica la clase en <html>. Se llama también antes del primer render para evitar destellos. */
export function applyTheme(theme: Theme): void {
  const resolved = theme === "system" ? (window.matchMedia(DARK_QUERY).matches ? "dark" : "light") : theme
  const root = window.document.documentElement

  root.classList.remove("light", "dark")
  root.classList.add(resolved)
  root.style.colorScheme = resolved
}

export function applyStoredTheme(fallback: Theme = "system"): void {
  applyTheme(readStoredTheme(fallback))
}

export function subscribeSystemTheme(onChange: () => void): () => void {
  const media = window.matchMedia(DARK_QUERY)
  media.addEventListener("change", onChange)

  return () => media.removeEventListener("change", onChange)
}

export interface ThemeContextValue {
  readonly theme: Theme
  setTheme: (theme: Theme) => void
}

export const ThemeContext = createContext<ThemeContextValue | null>(null)
