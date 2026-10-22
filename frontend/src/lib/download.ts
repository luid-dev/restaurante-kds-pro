import { ApiError, http } from "@/api/http"
import { apiErrorBodySchema } from "@/types/api"

const FILENAME_PATTERN = /filename="?([^";]+)"?/i

/**
 * Descarga un archivo de la API (XML, CDR, SIRE, CSV) con la cookie de sesión.
 * Los errores llegan como Blob: se leen y se convierten al ApiError del contrato.
 */
export async function downloadFile(path: string, params: Readonly<Record<string, string>> = {}): Promise<string> {
  const response = await http.get<Blob>(path, { params, responseType: "blob", validateStatus: () => true })

  if (response.status >= 400) {
    const body = apiErrorBodySchema.safeParse(await readJson(response.data))

    throw new ApiError({
      message: body.success ? body.data.message : "No se pudo descargar el archivo.",
      status: response.status,
      code: body.success ? body.data.code : "INVALID_RESPONSE",
      traceId: body.success ? body.data.trace_id : null,
    })
  }

  const disposition = response.headers["content-disposition"]
  const filename = (typeof disposition === "string" ? FILENAME_PATTERN.exec(disposition)?.[1] : undefined) ?? "descarga"
  const url = URL.createObjectURL(response.data)
  const anchor = document.createElement("a")

  anchor.href = url
  anchor.download = filename
  document.body.append(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)

  return filename
}

async function readJson(blob: Blob): Promise<unknown> {
  try {
    return JSON.parse(await blob.text()) as unknown
  } catch {
    return null
  }
}
