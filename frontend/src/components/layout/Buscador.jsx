import { LoaderCircle, Search } from 'lucide-react'
import { useEffect, useId, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../../api/cliente'
import { useRetardo } from '../../hooks/useConsulta'

/**
 * Búsqueda global predictiva del encabezado (requisito 9).
 * Consulta /api/busqueda mientras el usuario escribe; la API devuelve solo lo
 * que su rol puede ver (o el catálogo público si no hay sesión).
 * Teclado: ↑ ↓ para moverse, Enter para abrir, Escape para cerrar.
 */
export default function Buscador() {
  const [texto, setTexto] = useState('')
  const [grupos, setGrupos] = useState([])
  const [abierto, setAbierto] = useState(false)
  const [buscando, setBuscando] = useState(false)
  const [activo, setActivo] = useState(-1)
  const consulta = useRetardo(texto.trim(), 300)
  const contenedor = useRef(null)
  const navegar = useNavigate()
  const idLista = useId()

  const resultados = grupos.flatMap((g) => g.resultados)

  useEffect(() => {
    if (consulta.length < 2) {
      setGrupos([])

      return undefined
    }

    let vigente = true
    setBuscando(true)

    api
      .get('/busqueda', { params: { q: consulta } })
      .then(({ data }) => vigente && (setGrupos(data.grupos), setActivo(-1)))
      .catch(() => vigente && setGrupos([]))
      .finally(() => vigente && setBuscando(false))

    return () => {
      vigente = false
    }
  }, [consulta])

  useEffect(() => {
    const alPulsarFuera = (e) => !contenedor.current?.contains(e.target) && setAbierto(false)
    document.addEventListener('mousedown', alPulsarFuera)

    return () => document.removeEventListener('mousedown', alPulsarFuera)
  }, [])

  const ir = (resultado) => {
    setAbierto(false)
    setTexto('')
    navegar(resultado.ruta)
  }

  const alTeclear = (e) => {
    if (e.key === 'Escape') setAbierto(false)
    if (e.key === 'ArrowDown') (e.preventDefault(), setActivo((i) => Math.min(i + 1, resultados.length - 1)))
    if (e.key === 'ArrowUp') (e.preventDefault(), setActivo((i) => Math.max(i - 1, 0)))
    if (e.key === 'Enter' && resultados.length) (e.preventDefault(), ir(resultados[Math.max(activo, 0)]))
  }

  let indice = -1

  return (
    <div ref={contenedor} className="relative w-full max-w-xl" role="search">
      <label className="sr-only" htmlFor={`${idLista}-campo`}>Buscar en el sistema</label>
      <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
      <input
        id={`${idLista}-campo`}
        type="search"
        value={texto}
        onChange={(e) => (setTexto(e.target.value), setAbierto(true))}
        onFocus={() => setAbierto(true)}
        onKeyDown={alTeclear}
        placeholder="Buscar programas, estudiantes, matrículas, documentos…"
        autoComplete="off"
        role="combobox"
        aria-expanded={abierto && consulta.length >= 2}
        aria-controls={idLista}
        className="campo-control pr-9 pl-9"
      />
      {buscando && <LoaderCircle className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-tenue" aria-hidden />}

      {abierto && consulta.length >= 2 && (
        <div id={idLista} role="listbox" className="tarjeta absolute top-full right-0 left-0 z-40 mt-1 max-h-[70vh] overflow-y-auto shadow-xl">
          {!buscando && resultados.length === 0 && (
            <p className="p-4 text-sm text-tenue">No se encontraron resultados para «{consulta}».</p>
          )}
          {grupos.map((grupo) => (
            <div key={grupo.grupo}>
              <p className="bg-suave px-3 py-1.5 text-xs font-bold tracking-wide text-tenue uppercase">{grupo.grupo}</p>
              {grupo.resultados.map((r) => {
                indice += 1
                const miIndice = indice

                return (
                  <button
                    key={`${r.ruta}-${r.titulo}`}
                    role="option"
                    aria-selected={activo === miIndice}
                    onMouseEnter={() => setActivo(miIndice)}
                    onClick={() => ir(r)}
                    className={`block w-full px-3 py-2 text-left ${activo === miIndice ? 'bg-primario-suave' : ''}`}
                  >
                    <span className="block text-sm font-semibold">{r.titulo}</span>
                    <span className="block text-xs text-tenue">{r.detalle}</span>
                  </button>
                )
              })}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
