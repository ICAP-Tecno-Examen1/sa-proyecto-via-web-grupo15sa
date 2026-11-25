import { CalendarDays, Clock, MapPin, Users } from 'lucide-react'
import { Link } from 'react-router-dom'
import { fecha, legible, moneda } from '../../utils/formato'

/** Ficha de una oferta en el catálogo: programa, duración, modalidad, cupos y costo. */
export default function TarjetaOferta({ oferta }) {
  const sinCupo = oferta.cupos_disponibles === 0

  return (
    <Link to={`/catalogo/${oferta.id_oferta}`} className="tarjeta-minima group flex h-full w-full flex-col p-5">
      <div className="flex items-center justify-between gap-2 text-xs font-semibold tracking-wide text-tenue uppercase">
        <span>{legible(oferta.programa.tipo)}</span>
        <span className={sinCupo ? 'text-peligro' : 'text-exito'}>{sinCupo ? 'Sin cupos' : `${oferta.cupos_disponibles} cupos`}</span>
      </div>

      <h3 className="mt-2 text-lg leading-snug font-extrabold transition-colors group-hover:text-primario">{oferta.programa.nombre}</h3>
      <p className="mt-1.5 line-clamp-2 flex-1 text-sm text-tenue">{oferta.programa.descripcion || 'Programa de capacitación del ICAP.'}</p>

      <p className="mt-4 flex flex-wrap gap-x-3 gap-y-1 text-xs text-tenue">
        <span className="flex items-center gap-1"><MapPin className="size-3.5" aria-hidden /> {legible(oferta.modalidad)}</span>
        <span className="flex items-center gap-1"><Clock className="size-3.5" aria-hidden /> {oferta.malla.horas_totales} h</span>
        <span className="flex items-center gap-1"><CalendarDays className="size-3.5" aria-hidden /> {fecha(oferta.fecha_inicio)}</span>
        <span className="flex items-center gap-1"><Users className="size-3.5" aria-hidden /> {oferta.malla.cantidad_bloques
          ? `${oferta.malla.cantidad_bloques} módulos · ${oferta.malla.cantidad_modulos} materias`
          : `${oferta.malla.cantidad_modulos} ${oferta.malla.cantidad_modulos === 1 ? 'materia' : 'materias'}`}</span>
      </p>

      <div className="mt-4 flex items-center justify-between border-t border-borde pt-3">
        <p className="tabular text-lg font-extrabold text-texto">
          {oferta.costo_desde != null ? moneda(oferta.costo_desde) : <span className="text-sm font-semibold text-tenue">Consultar</span>}
        </p>
        <span aria-hidden className="grid size-8 place-items-center rounded-full bg-primario-suave text-primario transition-transform group-hover:translate-x-1">→</span>
      </div>
    </Link>
  )
}
