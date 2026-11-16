import { CircleAlert, CircleCheck, Inbox, Info, LoaderCircle, TriangleAlert } from 'lucide-react'
import { legible, tonoDeEstado } from '../../utils/formato'

const TONOS = {
  exito: 'bg-exito-suave text-exito border-exito/40',
  alerta: 'bg-alerta-suave text-alerta border-alerta/40',
  peligro: 'bg-peligro-suave text-peligro border-peligro/40',
  informacion: 'bg-informacion-suave text-informacion border-informacion/40',
  neutro: 'bg-suave text-tenue border-borde',
}

/** Etiqueta de estado. Si no se indica tono, se deduce del estado. */
export function Insignia({ estado, tono, children }) {
  return (
    <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ${TONOS[tono ?? tonoDeEstado(estado)]}`}>
      {children ?? legible(estado)}
    </span>
  )
}

const ALERTAS = {
  exito: { Icono: CircleCheck, tono: 'exito' },
  alerta: { Icono: TriangleAlert, tono: 'alerta' },
  peligro: { Icono: CircleAlert, tono: 'peligro' },
  informacion: { Icono: Info, tono: 'informacion' },
}

export function Alerta({ tipo = 'informacion', titulo, children, className = '' }) {
  const { Icono, tono } = ALERTAS[tipo]

  return (
    <div role={tipo === 'peligro' ? 'alert' : 'status'} className={`flex gap-3 rounded-tema border p-3 text-sm ${TONOS[tono]} ${className}`}>
      <Icono className="mt-0.5 size-5 shrink-0" aria-hidden />
      <div className="text-texto">
        {titulo && <p className="font-bold">{titulo}</p>}
        {children}
      </div>
    </div>
  )
}

export function Cargando({ texto = 'Cargando…', className = 'py-12' }) {
  return (
    <div className={`flex items-center justify-center gap-2 text-sm text-tenue ${className}`} role="status">
      <LoaderCircle className="size-5 animate-spin" aria-hidden />
      {texto}
    </div>
  )
}

export function Vacio({ titulo = 'Sin registros', children, icono: Icono = Inbox }) {
  return (
    <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
      <Icono className="size-10 text-tenue" aria-hidden />
      <p className="font-semibold">{titulo}</p>
      {children && <div className="max-w-md text-sm text-tenue">{children}</div>}
    </div>
  )
}

export function Tarjeta({ titulo, acciones, children, className = '', relleno = true }) {
  return (
    <section className={`tarjeta ${className}`}>
      {(titulo || acciones) && (
        <header className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
          <h2 className="font-bold">{titulo}</h2>
          {acciones && <div className="flex flex-wrap gap-2">{acciones}</div>}
        </header>
      )}
      <div className={relleno ? 'p-4' : ''}>{children}</div>
    </section>
  )
}

/** Título de cada pantalla, con descripción y acciones a la derecha. */
export function EncabezadoPagina({ titulo, descripcion, children }) {
  return (
    <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold">{titulo}</h1>
        {descripcion && <p className="mt-1 max-w-3xl text-sm text-tenue">{descripcion}</p>}
      </div>
      {children && <div className="no-imprimir flex flex-wrap gap-2">{children}</div>}
    </div>
  )
}

/** Par etiqueta / valor para fichas de detalle. */
export function Dato({ etiqueta, children }) {
  return (
    <div>
      <dt className="text-xs font-semibold tracking-wide text-tenue uppercase">{etiqueta}</dt>
      <dd className="mt-0.5 text-sm font-medium break-words">{children ?? '—'}</dd>
    </div>
  )
}

/** Carga + error + contenido, para no repetir el mismo if en cada pantalla. */
export function Estado({ consulta, children }) {
  if (consulta.cargando && !consulta.datos) return <Cargando />
  if (consulta.error) return <Alerta tipo="peligro">{consulta.error}</Alerta>
  if (!consulta.datos) return null

  return children(consulta.datos)
}

export function Pestanas({ pestanas, activa, alCambiar }) {
  return (
    <div role="tablist" className="no-imprimir mb-4 flex flex-wrap gap-1 border-b">
      {pestanas.map(({ clave, etiqueta, contador }) => (
        <button
          key={clave}
          role="tab"
          aria-selected={activa === clave}
          onClick={() => alCambiar(clave)}
          className={`-mb-px border-b-2 px-4 py-2 text-sm font-semibold transition ${activa === clave ? 'border-primario text-primario' : 'border-transparent text-tenue hover:text-texto'}`}
        >
          {etiqueta}
          {contador > 0 && <span className="ml-2 rounded-full bg-primario px-2 py-0.5 text-xs text-sobre-primario">{contador}</span>}
        </button>
      ))}
    </div>
  )
}
