export const WORKSPACE_VIEWS = [
  "floor-plan",
  "pos-waiter",
  "kds",
  "cashier",
  "invoices",
  "recipes-inventory",
  "dashboard",
] as const

export type WorkspaceView = (typeof WORKSPACE_VIEWS)[number]

export function isWorkspaceView(value: string): value is WorkspaceView {
  return WORKSPACE_VIEWS.some((view) => view === value)
}
