import { ChevronRight } from 'lucide-react'
import { Cargando, Vacio } from './Basicos'

/**
 * Tabla de datos del sistema.
 *
 *   columnas: [{ clave, titulo, alinear?: 'derecha' | 'centro', render?: (fila) => nodo, clase? }]
 *   filas:    arreglo de objetos
 *   id:       nombre del campo clave, o función (fila) => clave
 */
export default function Tabla({ columnas, filas, id = 'id', cargando = false, vacio = 'No hay registros para mostrar.', alSeleccionar, pie }) {
  const clave = typeof id === 'function' ? id : (fila) => fila[id]
  const alineacion = { derecha: 'text-right', centro: 'text-center' }

  if (cargando && !filas?.length) return <Cargando />
  if (!filas?.length) return <Vacio titulo={vacio} />

  return (
    <div className={`overflow-x-auto ${cargando ? 'opacity-60' : ''}`}>
      <table className="w-full min-w-max border-collapse text-sm">
        <thead>
          <tr className="bg-suave text-left">
            {columnas.map((c) => (
              <th key={c.clave} scope="col" className={`px-3 py-2.5 text-xs font-bold tracking-wide whitespace-nowrap uppercase ${alineacion[c.alinear] ?? ''}`}>
                {c.titulo}
              </th>
            ))}
            {alSeleccionar && <th className="w-8" />}
          </tr>
        </thead>
        <tbody>
          {filas.map((fila, i) => (
            <tr
              key={clave(fila) ?? i}
              className={`border-t ${alSeleccionar ? 'cursor-pointer hover:bg-primario-suave' : 'hover:bg-suave/60'}`}
              onClick={alSeleccionar ? () => alSeleccionar(fila) : undefined}
              onKeyDown={alSeleccionar ? (e) => e.key === 'Enter' && alSeleccionar(fila) : undefined}
              tabIndex={alSeleccionar ? 0 : undefined}
            >
              {columnas.map((c) => (
                <td key={c.clave} className={`px-3 py-2.5 align-middle ${alineacion[c.alinear] ?? ''} ${c.alinear === 'derecha' ? 'tabular' : ''} ${c.clase ?? ''}`}>
                  {c.render ? c.render(fila) : (fila[c.clave] ?? '—')}
                </td>
              ))}
              {alSeleccionar && (
                <td className="pr-2 text-tenue">
                  <ChevronRight className="size-4" aria-hidden />
                </td>
              )}
            </tr>
          ))}
        </tbody>
        {pie && <tfoot className="border-t-2 bg-suave font-semibold">{pie}</tfoot>}
      </table>
    </div>
  )
}

/** Paginación para las respuestas paginadas de la API (current_page, last_page, total). */
export function Paginacion({ pagina, alCambiar }) {
  if (!pagina || pagina.last_page <= 1) {
    return pagina?.total ? <p className="px-4 py-3 text-xs text-tenue">{pagina.total} registro(s)</p> : null
  }

  const boton = 'rounded-tema border px-3 py-1.5 text-sm font-semibold hover:bg-suave disabled:cursor-not-allowed disabled:opacity-40'

  return (
    <nav className="no-imprimir flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3" aria-label="Paginación">
      <p className="text-xs text-tenue">
        Mostrando {pagina.from}–{pagina.to} de {pagina.total}
      </p>
      <div className="flex items-center gap-2">
        <button className={boton} disabled={pagina.current_page <= 1} onClick={() => alCambiar(pagina.current_page - 1)}>
          Anterior
        </button>
        <span className="text-sm">
          Página {pagina.current_page} de {pagina.last_page}
        </span>
        <button className={boton} disabled={pagina.current_page >= pagina.last_page} onClick={() => alCambiar(pagina.current_page + 1)}>
          Siguiente
        </button>
      </div>
    </nav>
  )
}
