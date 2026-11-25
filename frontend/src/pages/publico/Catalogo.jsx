import { Search, X } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import TarjetaOferta from '../../components/catalogo/TarjetaOferta'
import { Estado, Vacio } from '../../components/ui/Basicos'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { legible } from '../../utils/formato'

/**
 * CU2 · Catálogo público de programas con ofertas abiertas.
 * Diseño minimalista: buscador, filtros en píldoras y tarjetas limpias.
 * Los filtros viven en la URL (?tipo=…&modalidad=…), así se pueden compartir
 * y el asistente de la portada puede abrir el catálogo ya filtrado.
 */
export default function Catalogo() {
  const [parametros, setParametros] = useSearchParams()
  const [texto, setTexto] = useState(parametros.get('q') ?? '')
  const tipo = parametros.get('tipo') ?? ''
  const modalidad = parametros.get('modalidad') ?? ''
  const catalogo = useConsulta('/publico/catalogo', { q: useRetardo(texto), tipo, modalidad })

  const filtrar = (clave, valor) => {
    const nuevos = new URLSearchParams(parametros)
    if (valor) nuevos.set(clave, valor)
    else nuevos.delete(clave)
    setParametros(nuevos, { replace: true })
  }

  const limpiar = () => {
    setTexto('')
    setParametros({}, { replace: true })
  }

  const hayFiltros = Boolean(texto || tipo || modalidad)
  const total = catalogo.datos?.ofertas.length

  return (
    <>
      <header className="mb-8 animar-subir">
        <p className="text-sm font-bold tracking-widest text-acento uppercase">Catálogo</p>
        <h1 className="mt-1 text-3xl font-black sm:text-4xl">Encuentra tu programa</h1>
        <p className="mt-2 max-w-2xl text-tenue">Ofertas con inscripción abierta. Consulta costos, duración y modalidad, y preinscríbete en línea.</p>
      </header>

      <div className="relative mb-5 max-w-2xl">
        <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-tenue" aria-hidden />
        <input
          type="search"
          value={texto}
          onChange={(e) => setTexto(e.target.value)}
          placeholder="¿Qué quieres aprender?"
          aria-label="Buscar un programa"
          className="campo-control rounded-full py-3 pl-12 text-base"
        />
      </div>

      <div className="mb-8 space-y-3">
        <Filtros etiqueta="Tipo" valores={catalogo.datos?.tipos} activo={tipo} alElegir={(v) => filtrar('tipo', v)} />
        <Filtros etiqueta="Modalidad" valores={catalogo.datos?.modalidades} activo={modalidad} alElegir={(v) => filtrar('modalidad', v)} />
      </div>

      <div className="mb-4 flex min-h-8 items-center justify-between gap-3 border-b pb-3 text-sm">
        <p className="text-tenue" aria-live="polite">
          {total == null ? 'Buscando…' : <><strong className="text-texto">{total}</strong> {total === 1 ? 'programa disponible' : 'programas disponibles'}</>}
        </p>
        {hayFiltros && (
          <button type="button" onClick={limpiar} className="flex items-center gap-1 font-semibold text-primario hover:underline">
            <X className="size-4" aria-hidden /> Quitar filtros
          </button>
        )}
      </div>

      <Estado consulta={catalogo}>
        {({ ofertas }) => ofertas.length === 0
          ? <div className="tarjeta"><Vacio titulo="No se encontraron programas">Prueba con otros filtros o vuelve más adelante.</Vacio></div>
          : (
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
              {ofertas.map((o, i) => (
                <div key={o.id_oferta} className="animar-subir flex" style={{ animationDelay: `${Math.min(i, 8) * 0.05}s` }}>
                  <TarjetaOferta oferta={o} />
                </div>
              ))}
            </div>
          )}
      </Estado>
    </>
  )
}

/** Fila de píldoras: "Todos" + un valor por opción. */
function Filtros({ etiqueta, valores = [], activo, alElegir }) {
  return (
    <div className="flex items-center gap-3">
      <span className="w-20 shrink-0 text-xs font-bold tracking-wide text-tenue uppercase">{etiqueta}</span>
      <div className="-my-1 flex gap-2 overflow-x-auto py-1" role="group" aria-label={`Filtrar por ${etiqueta.toLowerCase()}`}>
        <button type="button" className="chip" aria-pressed={!activo} onClick={() => alElegir('')}>Todos</button>
        {valores.map((v) => (
          <button key={v} type="button" className="chip" aria-pressed={activo === v} onClick={() => alElegir(activo === v ? '' : v)}>
            {legible(v)}
          </button>
        ))}
      </div>
    </div>
  )
}
