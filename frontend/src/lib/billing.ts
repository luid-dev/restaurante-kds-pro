/**
 * Validaciones tributarias del lado del cliente (el backend vuelve a validarlas).
 */

const RUC_WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as const
const RUC_PREFIXES = ["10", "15", "16", "17", "20"]

/** RUC de 11 dígitos con prefijo válido y dígito verificador (módulo 11). */
export function isValidRuc(ruc: string): boolean {
  if (!/^\d{11}$/.test(ruc) || !RUC_PREFIXES.includes(ruc.slice(0, 2))) {
    return false
  }

  const sum = RUC_WEIGHTS.reduce((total, weight, index) => total + Number(ruc.charAt(index)) * weight, 0)
  const check = 11 - (sum % 11)
  const expected = check === 10 ? 0 : check === 11 ? 1 : check

  return Number(ruc.charAt(10)) === expected
}

export function isValidDni(dni: string): boolean {
  return /^\d{8}$/.test(dni)
}

/** Boletas mayores a S/ 700.00 deben identificar al adquirente. */
export const RECEIPT_IDENTIFICATION_THRESHOLD_CENTS = 70_000
