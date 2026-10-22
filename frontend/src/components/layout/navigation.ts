import { ChartColumn, ChefHat, ClipboardList, FileText, LayoutGrid, type LucideIcon, Receipt, Scale } from "lucide-react"

import type { WorkspaceView } from "@/types/navigation"

export interface NavigationItem {
  readonly view: WorkspaceView
  readonly label: string
  readonly description: string
  readonly icon: LucideIcon
  /** KeyboardEvent.code del atajo Alt + dígito. */
  readonly shortcutCode: string
  readonly shortcutLabel: string
  /** Permiso Spatie requerido para ver el módulo (el backend lo vuelve a exigir). */
  readonly permission: string
}

export interface NavigationGroup {
  readonly label: string
  readonly items: readonly NavigationItem[]
}

export const NAVIGATION: readonly NavigationGroup[] = [
  {
    label: "Operación",
    items: [
      {
        view: "floor-plan",
        label: "Salones y Mesas",
        description: "Estado de cada mesa por salón y terraza, en tiempo real.",
        icon: LayoutGrid,
        shortcutCode: "Digit1",
        shortcutLabel: "1",
        permission: "floor.view",
      },
      {
        view: "pos-waiter",
        label: "Toma de Pedidos",
        description: "Comandas por mesa con porciones, modificadores y notas para cocina.",
        icon: ClipboardList,
        shortcutCode: "Digit2",
        shortcutLabel: "2",
        permission: "orders.create",
      },
      {
        view: "kds",
        label: "Pantalla Cocina (KDS)",
        description: "Comandas en vivo por área de producción: horno, cocina y barra.",
        icon: ChefHat,
        shortcutCode: "Digit3",
        shortcutLabel: "3",
        permission: "kds.view",
      },
    ],
  },
  {
    label: "Gestión",
    items: [
      {
        view: "cashier",
        label: "Caja y Facturación",
        description: "Precuentas, división de cuenta, cobro mixto y comprobantes SUNAT.",
        icon: Receipt,
        shortcutCode: "Digit4",
        shortcutLabel: "4",
        permission: "cashier.charge",
      },
      {
        view: "invoices",
        label: "Comprobantes SUNAT",
        description: "Facturas, boletas y notas de crédito emitidas: XML, CDR, ticket y registro SIRE.",
        icon: FileText,
        shortcutCode: "Digit5",
        shortcutLabel: "5",
        permission: "sales.issue",
      },
      {
        view: "recipes-inventory",
        label: "Recetas y Kardex",
        description: "Stock crítico, escandallo salón / para llevar, kardex valorizado 13.1 y mermas.",
        icon: Scale,
        shortcutCode: "Digit6",
        shortcutLabel: "6",
        permission: "recipes.manage",
      },
    ],
  },
  {
    label: "Análisis",
    items: [
      {
        view: "dashboard",
        label: "Rentabilidad",
        description: "Ventas, food cost teórico, mermas por anulación, ticket promedio y margen por plato.",
        icon: ChartColumn,
        shortcutCode: "Digit7",
        shortcutLabel: "7",
        permission: "reports.view",
      },
    ],
  },
]

export const NAVIGATION_ITEMS: readonly NavigationItem[] = NAVIGATION.flatMap((group) => group.items)

/** Módulos que el usuario puede abrir según sus permisos. */
export function allowedViews(permissions: readonly string[]): WorkspaceView[] {
  const granted = new Set(permissions)

  return NAVIGATION_ITEMS.filter((item) => granted.has(item.permission)).map((item) => item.view)
}

/** Vista inicial por rol: gerencia abre rentabilidad, cocina el KDS, caja la facturación y el resto el plano de mesas. */
export function defaultViewFor(roles: readonly string[], permissions: readonly string[]): WorkspaceView {
  const allowed = allowedViews(permissions)
  const preferred: WorkspaceView = roles.includes("admin") ? "dashboard" : roles.includes("kitchen") ? "kds" : roles.includes("cashier") ? "cashier" : "floor-plan"

  return allowed.includes(preferred) ? preferred : (allowed[0] ?? "floor-plan")
}

export function findNavigationItem(view: WorkspaceView): NavigationItem {
  const item = NAVIGATION_ITEMS.find((candidate) => candidate.view === view)

  if (item === undefined) {
    throw new Error(`Vista sin entrada de navegación: ${view}`)
  }

  return item
}
