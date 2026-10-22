import { useEffect, useState } from "react"

/**
 * Fecha actual que avanza cada `intervalMs`. Úsese solo en hojas del árbol
 * (reloj, cronómetros) para no re-renderizar el layout completo cada segundo.
 */
export function useNow(intervalMs = 1_000): Date {
  const [now, setNow] = useState(() => new Date())

  useEffect(() => {
    const id = window.setInterval(() => setNow(new Date()), intervalMs)

    return () => window.clearInterval(id)
  }, [intervalMs])

  return now
}
