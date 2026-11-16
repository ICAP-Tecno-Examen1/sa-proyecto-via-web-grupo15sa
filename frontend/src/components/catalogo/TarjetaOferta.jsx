import { CalendarDays, Clock, MapPin, Users } from 'lucide-react'
import { Link } from 'react-router-dom'
import { fecha, legible, moneda } from '../../utils/formato'
import { Insignia } from '../ui/Basicos'

/** Ficha de una oferta en el catálogo: programa, duración, modalidad, cupos y costo. */
export default function TarjetaOferta({ oferta }) {
  const sinCupo = oferta.cupos_disponibles === 0

  return (
    <Link to={`/catalogo/${oferta.id_oferta}`} className="tarjeta group flex flex-col p-5 transition hover:border-primario hover:shadow-lg">
      <div className="mb-2 flex flex-wrap items-center gap-2">
        <Insignia tono="informacion">{legible(oferta.programa.tipo)}</Insignia>
        {sinCupo ? <Insignia tono="peligro">Sin cupos</Insignia> : <Insignia tono="exito">{oferta.cupos_disponibles} cupos</Insignia>}
      </div>

      <h3 className="text-lg leading-snug font-bold group-hover:text-primario">{oferta.programa.nombre}</h3>
      <p className="mt-1 line-clamp-2 flex-1 text-sm text-tenue">{oferta.programa.descripcion || 'Programa de capacitación del ICAP.'}</p>

      <dl className="mt-4 grid grid-cols-2 gap-2 text-xs text-tenue">
        <div className="flex items-center gap-1.5"><MapPin className="size-3.5" aria-hidden /> {legible(oferta.modalidad)}</div>
        <div className="flex items-center gap-1.5"><Clock className="size-3.5" aria-hidden /> {oferta.malla.horas_totales} horas</div>
        <div className="flex items-center gap-1.5"><CalendarDays className="size-3.5" aria-hidden /> Inicia {fecha(oferta.fecha_inicio)}</div>
        <div className="flex items-center gap-1.5"><Users className="size-3.5" aria-hidden /> {oferta.malla.cantidad_modulos} módulos</div>
      </dl>

      <div className="mt-4 flex items-end justify-between border-t pt-3">
        <div>
          <p className="text-xs text-tenue">Desde</p>
          <p className="tabular text-xl font-extrabold text-primario">{oferta.costo_desde != null ? moneda(oferta.costo_desde) : 'Consultar'}</p>
        </div>
        <span className="text-sm font-bold text-primario">Ver detalle →</span>
      </div>
    </Link>
  )
}
