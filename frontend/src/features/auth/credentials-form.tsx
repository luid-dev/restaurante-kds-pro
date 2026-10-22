import { zodResolver } from "@hookform/resolvers/zod"
import { LoaderCircle, LogIn } from "lucide-react"
import { useForm } from "react-hook-form"

import { Button } from "@/components/ui/button"
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { AuthError } from "@/features/auth/auth-error"
import { useCredentialsLogin } from "@/features/auth/use-auth-actions"
import { credentialsSchema, type Credentials } from "@/types/auth"

export function CredentialsForm() {
  const login = useCredentialsLogin()
  const form = useForm<Credentials>({
    resolver: zodResolver(credentialsSchema),
    defaultValues: { email: "", password: "" },
  })
  const { errors } = form.formState

  return (
    <form noValidate onSubmit={form.handleSubmit((values) => login.mutate(values))} className="flex flex-col gap-4">
      <FieldGroup className="gap-4">
        <Field data-invalid={errors.email !== undefined}>
          <FieldLabel htmlFor="login-email">Correo corporativo</FieldLabel>
          <Input
            id="login-email"
            type="email"
            autoComplete="username"
            placeholder="nombre@restaurante.pe"
            aria-invalid={errors.email !== undefined}
            {...form.register("email")}
          />
          <FieldError errors={[errors.email]} />
        </Field>
        <Field data-invalid={errors.password !== undefined}>
          <FieldLabel htmlFor="login-password">Contraseña</FieldLabel>
          <Input
            id="login-password"
            type="password"
            autoComplete="current-password"
            aria-invalid={errors.password !== undefined}
            {...form.register("password")}
          />
          <FieldError errors={[errors.password]} />
        </Field>
      </FieldGroup>

      <AuthError error={login.error} />

      <Button type="submit" className="h-10" disabled={login.isPending}>
        {login.isPending ? (
          <LoaderCircle className="size-4 animate-spin" strokeWidth={1.5} aria-hidden="true" />
        ) : (
          <LogIn className="size-4" strokeWidth={1.5} aria-hidden="true" />
        )}
        Iniciar sesión
      </Button>
    </form>
  )
}
