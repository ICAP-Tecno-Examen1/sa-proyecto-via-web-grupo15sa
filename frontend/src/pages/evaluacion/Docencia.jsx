import { CalendarDays, Search, Users, X } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { EncabezadoPagina, Estado, Insignia, Vacio } from '../../components/ui/Basicos'
import Campo from '../../components/ui/Campo'
import { useAuth } from '../../context/AuthContext'
import { useConsulta } from '../../hooks/useConsulta'
import { fecha, legible } from '../../utils/formato'

/** Texto comparable: sin tildes ni mayúsculas («Contabilidad» ≈ «contabilidad»). */
const normalizar = (texto) => String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/**
 * CU5 · Materias asignadas al docente (la API solo le devuelve las suyas) o todas,
 * para quien supervisa. El buscador filtra al instante por materia, código, programa,
 * oferta, gestión y (al supervisar) docente.
 */
export default function Docencia() {
  const auth = useAuth()
  const supervisa = auth.puede('academico.supervisar')
  const [estado, setEstado] = useState('')
  const consulta = useConsulta('/docencia/modulos', { estado })
  const [texto, setTexto] = useState('')

  // Cada palabra escrita debe aparecer en algún dato de la materia («conta 2026» encuentra Contabilidad de 2026)
  const filtradas = useMemo(() => {
    const palabras = normalizar(texto).split(/\s+/).filter(Boolean)
    if (!palabras.length) return consulta.datos ?? []

    return (consulta.datos ?? []).filter((m) => {
      const datos = normalizar([m.nombre, m.codigo, m.programa, m.oferta, m.gestion, legible(m.modalidad), ...(supervisa ? m.docentes : [])].join(' '))

      return palabras.every((p) => datos.includes(p))
    })
  }, [consulta.datos, texto, supervisa])

  return (
    <>
      <EncabezadoPagina
        titulo={supervisa ? 'Materias y calificaciones' : 'Mis materias'}
        descripcion={supervisa
          ? 'Supervisión de todas las materias: listas, asistencia, evaluaciones y notas registradas por los docentes.'
          : 'Materias que tiene asignadas. Ingrese a cada una para pasar lista, crear evaluaciones y registrar calificaciones.'}
      >
        <Campo tipo="select" nombre="estado" valor={estado} alCambiar={setEstado} vacio="Todas las materias" aria-label="Estado de la materia" className="w-48"
          opciones={[{ valor: 'ACTIVO', etiqueta: 'En curso' }, { valor: 'CERRADO', etiqueta: 'Cerrados' }]} />
      </EncabezadoPagina>

      {consulta.datos?.length > 0 && (
        <div className="mb-5 flex flex-wrap items-center gap-3">
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" value={texto} onChange={(e) => setTexto(e.target.value)} aria-label="Buscar materia"
              placeholder={supervisa ? 'Buscar por materia, programa, oferta, gestión o docente…' : 'Buscar por materia, programa, oferta o gestión…'}
              className="campo-control rounded-full pr-10 pl-10" />
            {texto && (
              <button type="button" onClick={() => setTexto('')} aria-label="Limpiar búsqueda" className="absolute top-1/2 right-3 -translate-y-1/2 rounded-full p-1 text-tenue hover:bg-suave">
                <X className="size-4" />
              </button>
            )}
          </div>
          <p className="text-sm text-tenue" aria-live="polite">
            {texto ? <><strong className="text-texto">{filtradas.length}</strong> de {consulta.datos.length} materias</> : `${consulta.datos.length} materias`}
          </p>
        </div>
      )}

      <Estado consulta={consulta}>
        {(modulos) => modulos.length === 0 ? (
          <div className="tarjeta"><Vacio titulo="No hay materias">{supervisa ? 'Aún no hay materias ofertadas.' : 'El Coordinador Académico todavía no le asignó materias.'}</Vacio></div>
        ) : filtradas.length === 0 ? (
          <div className="tarjeta"><Vacio titulo={`Ninguna materia coincide con «${texto}»`}>Pruebe con otra palabra: nombre de la materia, programa, código de oferta o año.</Vacio></div>
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {filtradas.map((m) => (
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
