import { CalendarDays, Users } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { EncabezadoPagina, Estado, Insignia, Vacio } from '../../components/ui/Basicos'
import Campo from '../../components/ui/Campo'
import { useAuth } from '../../context/AuthContext'
import { useConsulta } from '../../hooks/useConsulta'
import { fecha, legible } from '../../utils/formato'

/** CU5 · Módulos asignados al docente (o todos, para quien supervisa). */
export default function Docencia() {
  const auth = useAuth()
  const supervisa = auth.puede('academico.supervisar')
  const [estado, setEstado] = useState('')
  const consulta = useConsulta('/docencia/modulos', { estado })

  return (
    <>
      <EncabezadoPagina
        titulo={supervisa ? 'Módulos y calificaciones' : 'Mis módulos'}
        descripcion={supervisa
          ? 'Supervisión de todos los módulos: listas, asistencia, evaluaciones y notas registradas por los docentes.'
          : 'Módulos que tiene asignados. Ingrese a cada uno para pasar lista, crear evaluaciones y registrar calificaciones.'}
      >
        <Campo tipo="select" nombre="estado" valor={estado} alCambiar={setEstado} vacio="Todos los módulos" aria-label="Estado del módulo" className="w-48"
          opciones={[{ valor: 'ACTIVO', etiqueta: 'En curso' }, { valor: 'CERRADO', etiqueta: 'Cerrados' }]} />
      </EncabezadoPagina>

      <Estado consulta={consulta}>
        {(modulos) => modulos.length === 0 ? (
          <div className="tarjeta"><Vacio titulo="No hay módulos">{supervisa ? 'Aún no hay módulos ofertados.' : 'El Coordinador Académico todavía no le asignó módulos.'}</Vacio></div>
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {modulos.map((m) => (
              <Link key={m.id_oferta_modulo} to={`/docencia/${m.id_oferta_modulo}`} className="tarjeta group flex flex-col p-5 transition hover:border-primario hover:shadow-lg">
                <div className="mb-2 flex items-center justify-between gap-2">
                  <Insignia estado={m.estado}>{m.estado === 'ACTIVO' ? 'En curso' : 'Cerrado'}</Insignia>
                  <span className="text-xs text-tenue">{m.oferta} · {m.gestion}</span>
                </div>
                <h2 className="text-lg leading-snug font-bold group-hover:text-primario">{m.nombre}</h2>
                <p className="text-sm text-tenue">{m.programa} · {legible(m.modalidad)}</p>
                {supervisa && <p className="mt-1 text-xs text-tenue">Docente: {m.docentes.join(', ') || 'sin asignar'}</p>}
                <dl className="mt-auto flex flex-wrap gap-x-4 gap-y-1 pt-4 text-xs text-tenue">
                  <div className="flex items-center gap-1.5"><Users className="size-3.5" aria-hidden /> {m.estudiantes} estudiantes</div>
                  <div className="flex items-center gap-1.5"><CalendarDays className="size-3.5" aria-hidden /> {fecha(m.fecha_inicio)} – {fecha(m.fecha_fin)}</div>
                  <div>{m.evaluaciones} evaluaciones · {m.horas} h</div>
                </dl>
              </Link>
            ))}
          </div>
        )}
      </Estado>
    </>
  )
}
