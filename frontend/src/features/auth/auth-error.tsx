import { CircleAlert } from "lucide-react"

import type { ApiError } from "@/api/http"

/** Error de acceso con el trace_id del backend para reportarlo a soporte. */
export function AuthError({ error }: { readonly error: ApiError | null }) {
  if (error === null) {
    return null
  }

  return (
    <div role="alert" className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm">
      <CircleAlert className="mt-0.5 size-4 shrink-0 text-destructive" strokeWidth={1.5} aria-hidden="true" />
      <div className="min-w-0">
        <p className="text-destructive">{error.message}</p>
        {error.traceId === null ? null : (
          <p className="mt-0.5 truncate font-mono text-[11px] tabular-nums text-muted-foreground">Ref. {error.traceId}</p>
        )}
      </div>
    </div>
  )
}
