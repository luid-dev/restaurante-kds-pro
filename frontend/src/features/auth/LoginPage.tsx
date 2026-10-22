import { Grid3x3, KeyRound, UtensilsCrossed } from "lucide-react"
import { lazy, Suspense, useState } from "react"

import { LiveClock } from "@/components/layout/live-clock"
import { ThemeMenu } from "@/components/layout/theme-menu"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { CredentialsForm } from "@/features/auth/credentials-form"
import { PinLoginPanel } from "@/features/auth/pin-login-panel"
import { TerminalOverview } from "@/features/auth/terminal-overview"
import { env } from "@/lib/env"
import { useTerminalStore } from "@/stores/terminal-store"
import type { AuthMethod } from "@/types/auth"

// Comparación estática: con VITE_DEMO_MODE=false Vite elimina el módulo demo del bundle.
const DemoAccessBar = import.meta.env.VITE_DEMO_MODE === "true" ? lazy(() => import("@/features/auth/demo-access-bar")) : null

function isAuthMethod(value: string): value is AuthMethod {
  return value === "PIN" || value === "PASSWORD"
}

/**
 * Acceso dual de la terminal: PIN rápido para el salón y credenciales para la
 * administración. Ambos abren la misma sesión por cookie (Sanctum SPA).
 */
export default function LoginPage() {
  const preferredMethod = useTerminalStore((state) => state.preferredMethod)
  const [method, setMethod] = useState<AuthMethod>(preferredMethod)

  return (
    <div className="flex min-h-svh flex-col bg-muted/40">
      <header className="flex h-12 shrink-0 items-center gap-3 border-b bg-background px-4">
        <span className="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
          <UtensilsCrossed className="size-3.5" strokeWidth={1.5} aria-hidden="true" />
        </span>
        <span className="text-sm font-semibold">{env.appName}</span>
        <div className="ml-auto flex items-center gap-3">
          <LiveClock />
          <ThemeMenu />
        </div>
      </header>

      <main className="flex flex-1 flex-col items-center justify-center gap-3 p-4 md:p-8">
        <div className="grid w-full max-w-4xl overflow-hidden rounded-md border bg-background md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
          <section className="border-b p-6 md:border-r md:border-b-0">
            <TerminalOverview />
          </section>

          <section className="p-6">
            <Tabs value={method} onValueChange={(value) => isAuthMethod(value) && setMethod(value)} className="gap-5">
              <TabsList className="grid h-9 w-full grid-cols-2 rounded-md">
                <TabsTrigger value="PIN" className="rounded-sm">
                  <Grid3x3 className="size-4" strokeWidth={1.5} aria-hidden="true" />
                  Terminal de Salón
                </TabsTrigger>
                <TabsTrigger value="PASSWORD" className="rounded-sm">
                  <KeyRound className="size-4" strokeWidth={1.5} aria-hidden="true" />
                  Acceso Corporativo
                </TabsTrigger>
              </TabsList>

              <TabsContent value="PIN" className="flex flex-col gap-4">
                <div>
                  <h2 className="text-sm font-medium">PIN rápido</h2>
                  <p className="text-sm text-muted-foreground">Digite su PIN de 6 dígitos. Se valida al completar el último dígito.</p>
                </div>
                <PinLoginPanel active={method === "PIN"} />
              </TabsContent>

              <TabsContent value="PASSWORD" className="flex flex-col gap-4">
                <div>
                  <h2 className="text-sm font-medium">Credenciales</h2>
                  <p className="text-sm text-muted-foreground">Gerencia, caja y administración del restaurante.</p>
                </div>
                <CredentialsForm />
              </TabsContent>
            </Tabs>
          </section>
        </div>

        {DemoAccessBar === null || !env.demoMode ? null : (
          <div className="w-full max-w-4xl">
            <Suspense fallback={null}>
              <DemoAccessBar />
            </Suspense>
          </div>
        )}
      </main>
    </div>
  )
}
