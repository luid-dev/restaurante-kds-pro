import { FlaskConical, LoaderCircle } from "lucide-react"

import { Button } from "@/components/ui/button"
import { useCredentialsLogin, usePinLogin } from "@/features/auth/use-auth-actions"

/**
 * Accesos de evaluación en un clic (modo portafolio). Este módulo solo entra al
 * bundle cuando VITE_DEMO_MODE=true: con el modo apagado Vite lo elimina por
 * completo y las credenciales de prueba no se distribuyen.
 */

const DEMO_COMPANY_RUC = "20609876543"
const DEMO_PASSWORD = "Password123!"

type DemoAccount =
  | { readonly label: string; readonly method: "PASSWORD"; readonly email: string }
  | { readonly label: string; readonly method: "PIN"; readonly pin: string }

const DEMO_ACCOUNTS: readonly DemoAccount[] = [
  { label: "Rol: Gerente Admin", method: "PASSWORD", email: "admin@restaurante.pe" },
  { label: "Rol: Cajero Facturación", method: "PASSWORD", email: "caja@restaurante.pe" },
  { label: "Rol: Mozo Comandas (PIN 333333)", method: "PIN", pin: "333333" },
  { label: "Rol: Pantalla Cocina KDS", method: "PASSWORD", email: "cocina@restaurante.pe" },
]

export default function DemoAccessBar() {
  const credentialsLogin = useCredentialsLogin()
  const pinLogin = usePinLogin()
  const pending = credentialsLogin.isPending || pinLogin.isPending
  const pendingLabel = credentialsLogin.isPending
    ? DEMO_ACCOUNTS.find((account) => account.method === "PASSWORD" && account.email === credentialsLogin.variables?.email)?.label
    : pinLogin.isPending
      ? DEMO_ACCOUNTS.find((account) => account.method === "PIN")?.label
      : undefined

  function signIn(account: DemoAccount): void {
    if (account.method === "PIN") {
      pinLogin.mutate({ company_ruc: DEMO_COMPANY_RUC, pin_code: account.pin })
    } else {
      credentialsLogin.mutate({ email: account.email, password: DEMO_PASSWORD })
    }
  }

  return (
    <section aria-label="Accesos de evaluación" className="rounded-md border border-dashed bg-background px-4 py-3">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div className="flex shrink-0 items-start gap-2 lg:w-56">
          <FlaskConical className="mt-0.5 size-4 shrink-0 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
          <div>
            <p className="text-sm font-medium">Modo evaluación</p>
            <p className="text-xs text-muted-foreground">Ingrese con un rol de prueba en un clic.</p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {DEMO_ACCOUNTS.map((account) => (
            <Button
              key={account.label}
              type="button"
              variant="outline"
              size="sm"
              className="h-8 font-mono text-xs"
              disabled={pending}
              onClick={() => signIn(account)}
            >
              {pendingLabel === account.label ? (
                <LoaderCircle className="size-3.5 animate-spin" strokeWidth={1.5} aria-hidden="true" />
              ) : null}
              [{account.label}]
            </Button>
          ))}
        </div>
      </div>
    </section>
  )
}
