import { useState } from "react"

import { findNavigationItem } from "@/components/layout/navigation"
import { ModuleHeader } from "@/components/shared/module-header"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { KardexTab } from "@/features/recipes-inventory/kardex-tab"
import { RecipesTab } from "@/features/recipes-inventory/recipes-tab"
import { StockTab } from "@/features/recipes-inventory/stock-tab"

const TABS = [
  { value: "stock", label: "Insumos y stock crítico" },
  { value: "recipes", label: "Escandallo / recetas" },
  { value: "kardex", label: "Kardex valorizado 13.1 y mermas" },
] as const

type InventoryTab = (typeof TABS)[number]["value"]

function isInventoryTab(value: string): value is InventoryTab {
  return TABS.some((tab) => tab.value === value)
}

/** Control de inventario gastronómico: existencias, escandallo fraccionario y kardex auditable. */
export default function RecipesAndKardexPage() {
  const item = findNavigationItem("recipes-inventory")
  const [tab, setTab] = useState<InventoryTab>("stock")

  return (
    <>
      <ModuleHeader item={item} />
      <Tabs value={tab} onValueChange={(value) => (isInventoryTab(value) ? setTab(value) : undefined)} className="gap-0">
        <div className="overflow-x-auto border-b px-4 md:px-6">
          <TabsList variant="line" className="h-10">
            {TABS.map((option) => (
              <TabsTrigger key={option.value} value={option.value} className="flex-none px-3">
                {option.label}
              </TabsTrigger>
            ))}
          </TabsList>
        </div>
        <TabsContent value="stock" className="p-4 md:p-6">
          <StockTab />
        </TabsContent>
        <TabsContent value="recipes" className="p-4 md:p-6">
          <RecipesTab />
        </TabsContent>
        <TabsContent value="kardex" className="p-4 md:p-6">
          <KardexTab />
        </TabsContent>
      </Tabs>
    </>
  )
}
