import { useEffect, useRef } from "react"
import type { z } from "zod"

import { echo } from "@/api/echo"

export interface ChannelListener<TSchema extends z.ZodType> {
  /** Nombre definido con broadcastAs() en Laravel, con punto inicial (".table.updated"). */
  readonly event: string
  readonly schema: TSchema
  readonly handler: (payload: z.output<TSchema>) => void
}

// Cada listener valida su propio schema; el arreglo los guarda con el tipo base de Zod.
type AnyChannelListener = ChannelListener<z.ZodType>

/**
 * Suscribe un canal privado de Reverb y valida cada payload con Zod antes de
 * entregarlo: un evento con forma inesperada se descarta (y se avisa en consola).
 * Los handlers se leen por ref: cambiar de closure no re-suscribe el canal.
 */
export function usePrivateChannel(channelName: string | null, listeners: readonly AnyChannelListener[]): void {
  const listenersRef = useRef(listeners)

  useEffect(() => {
    listenersRef.current = listeners
  }, [listeners])

  useEffect(() => {
    if (echo === null || channelName === null) {
      return
    }

    const channel = echo.private(channelName)
    const bound = listenersRef.current.map(({ event }) => {
      const callback = (payload: unknown) => {
        const listener = listenersRef.current.find((candidate) => candidate.event === event)
        const parsed = listener?.schema.safeParse(payload)

        if (listener === undefined || parsed === undefined) {
          return
        }

        if (!parsed.success) {
          console.warn(`Evento ${event} descartado: payload inválido.`, parsed.error.issues)

          return
        }

        listener.handler(parsed.data)
      }

      channel.listen(event, callback)

      return { event, callback }
    })

    return () => {
      for (const { event, callback } of bound) {
        channel.stopListening(event, callback)
      }

      echo?.leave(channelName)
    }
  }, [channelName])
}

export function listener<TSchema extends z.ZodType>(
  event: string,
  schema: TSchema,
  handler: (payload: z.output<TSchema>) => void,
): AnyChannelListener {
  return { event, schema, handler: handler as (payload: z.output<z.ZodType>) => void }
}
