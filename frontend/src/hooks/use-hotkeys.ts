import { useEffect, useRef } from "react"

export interface Hotkey {
  /** KeyboardEvent.code, independiente del layout de teclado (p. ej. "Digit1", "F2"). */
  readonly code: string
  readonly alt?: boolean
  readonly ctrlOrMeta?: boolean
  readonly handler: () => void
}

function isEditableTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) {
    return false
  }

  return target.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName)
}

/**
 * Atajos de teclado para terminales POS. Se ignoran mientras se escribe en un
 * campo para no interferir con notas de comanda o búsquedas.
 */
export function useHotkeys(hotkeys: readonly Hotkey[]): void {
  const hotkeysRef = useRef(hotkeys)

  useEffect(() => {
    hotkeysRef.current = hotkeys
  }, [hotkeys])

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent): void {
      if (event.repeat || isEditableTarget(event.target)) {
        return
      }

      const match = hotkeysRef.current.find(
        (hotkey) =>
          hotkey.code === event.code &&
          (hotkey.alt ?? false) === event.altKey &&
          (hotkey.ctrlOrMeta ?? false) === (event.ctrlKey || event.metaKey),
      )

      if (match !== undefined) {
        event.preventDefault()
        match.handler()
      }
    }

    window.addEventListener("keydown", onKeyDown)

    return () => window.removeEventListener("keydown", onKeyDown)
  }, [])
}
