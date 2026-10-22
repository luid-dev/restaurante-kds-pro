import { LoaderCircle } from "lucide-react"
import { lazy, Suspense } from "react"

import { AppShell } from "@/components/layout/app-shell"
import { useSession } from "@/features/auth/use-auth-actions"

const LoginPage = lazy(() => import("@/features/auth/LoginPage"))

function BootScreen() {
  return (
    <div className="flex min-h-svh items-center justify-center gap-2 text-sm text-muted-foreground" aria-busy="true">
      <LoaderCircle className="size-4 animate-spin" strokeWidth={1.5} aria-hidden="true" />
      Verificando sesión
    </div>
  )
}

/**
 * Con sesión válida se abre el AppShell; sin sesión (o si la API no responde)
 * la terminal muestra el acceso dual.
 */
export default function App() {
  const session = useSession()

  if (session.isPending) {
    return <BootScreen />
  }

  if (session.data === null || session.data === undefined) {
    return (
      <Suspense fallback={<BootScreen />}>
        <LoginPage />
      </Suspense>
    )
  }

  return <AppShell session={session.data} />
}
