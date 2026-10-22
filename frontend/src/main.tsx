import "@fontsource-variable/geist"
import "@fontsource-variable/geist-mono"
import "./index.css"

import { QueryClientProvider } from "@tanstack/react-query"
import { StrictMode } from "react"
import { createRoot } from "react-dom/client"

import App from "@/App"
import { ThemeProvider } from "@/components/theme-provider"
import { Toaster } from "@/components/ui/sonner"
import { queryClient } from "@/lib/query-client"
import { applyStoredTheme } from "@/lib/theme"

const container = document.getElementById("root")

if (container === null) {
  throw new Error("No se encontró el contenedor #root.")
}

// Aplica el tema guardado antes del primer render (sin destello en pantallas KDS oscuras).
applyStoredTheme()

createRoot(container).render(
  <StrictMode>
    <ThemeProvider>
      <QueryClientProvider client={queryClient}>
        <App />
        <Toaster position="top-right" />
      </QueryClientProvider>
    </ThemeProvider>
  </StrictMode>,
)
