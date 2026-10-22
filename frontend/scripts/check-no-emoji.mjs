// Regla de diseño: cero emojis en código y textos de interfaz (solo iconos lucide-react).
// Uso: node scripts/check-no-emoji.mjs [directorios...]
import { readdirSync, readFileSync, statSync } from "node:fs"
import { extname, join } from "node:path"

const EMOJI = /\p{Extended_Pictographic}/u
const EXTENSIONS = new Set([".ts", ".tsx", ".css", ".html", ".json", ".md"])
const roots = process.argv.length > 2 ? process.argv.slice(2) : ["src", "index.html"]

function* files(path) {
  if (statSync(path).isDirectory()) {
    for (const entry of readdirSync(path)) {
      yield* files(join(path, entry))
    }
  } else if (EXTENSIONS.has(extname(path))) {
    yield path
  }
}

const findings = []

for (const root of roots) {
  for (const file of files(root)) {
    readFileSync(file, "utf8")
      .split("\n")
      .forEach((line, index) => {
        if (EMOJI.test(line)) {
          findings.push(`${file}:${index + 1}`)
        }
      })
  }
}

if (findings.length > 0) {
  console.error(`Emojis encontrados (use iconos lucide-react):\n${findings.join("\n")}`)
  process.exit(1)
}

process.stdout.write(`Sin emojis en: ${roots.join(", ")}\n`)
