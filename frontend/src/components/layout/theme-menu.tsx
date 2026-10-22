import { Monitor, Moon, Sun, type LucideIcon } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useTheme } from "@/hooks/use-theme"
import { isTheme, THEMES, type Theme } from "@/lib/theme"

const THEME_OPTIONS: Record<Theme, { label: string; icon: LucideIcon }> = {
  light: { label: "Claro", icon: Sun },
  dark: { label: "Oscuro (cocina)", icon: Moon },
  system: { label: "Según el sistema", icon: Monitor },
}

export function ThemeMenu() {
  const { theme, setTheme } = useTheme()

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="size-8" aria-label="Cambiar tema de pantalla">
          <Sun className="size-4 dark:hidden" strokeWidth={1.5} aria-hidden="true" />
          <Moon className="hidden size-4 dark:block" strokeWidth={1.5} aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-48">
        <DropdownMenuLabel className="text-xs text-muted-foreground">Tema de pantalla</DropdownMenuLabel>
        <DropdownMenuRadioGroup value={theme} onValueChange={(value) => isTheme(value) && setTheme(value)}>
          {THEMES.map((value) => {
            const { label, icon: Icon } = THEME_OPTIONS[value]

            return (
              <DropdownMenuRadioItem key={value} value={value}>
                <Icon className="size-4" strokeWidth={1.5} aria-hidden="true" />
                {label}
              </DropdownMenuRadioItem>
            )
          })}
        </DropdownMenuRadioGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
