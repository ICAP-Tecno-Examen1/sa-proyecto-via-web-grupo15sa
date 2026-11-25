import { ArrowLeft, CircleCheck } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api from '../../api/cliente'
import { Alerta, Dato, Estado, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal from '../../components/ui/Modal'
import Tabla from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { describirPlan, fecha, legible, moneda } from '../../utils/formato'
import { maximo, requerido } from '../../utils/validacion'

/** Malla de una carrera: módulos numerados, cada uno con sus 1 o 2 materias. */
function PlanPorModulos({ modulos }) {
  const bloques = []
  modulos.forEach((m) => {
    const clave = m.bloque?.id_modulo_carrera ?? `suelta-${m.id_oferta_modulo}`
    let bloque = bloques.find((b) => b.clave === clave)
    if (!bloque) bloques.push((bloque = { clave, nombre: m.bloque?.nombre ?? m.nombre, orden: m.bloque?.orden, materias: [] }))
    bloque.materias.push(m)
  })
  bloques.sort((a, b) => (a.orden ?? 999) - (b.orden ?? 999))

  return (
    <Tarjeta titulo={`Malla curricular · ${bloques.length} módulos, ${modulos.length} materias`}>
      <ol className="relative space-y-4 border-l-4 border-dashed border-borde pl-6">
        {bloques.map((b, i) => (
          <li key={b.clave} className="relative">
            <span className="absolute top-0 -left-[2.4rem] grid size-8 place-items-center rounded-full bg-primario text-sm font-bold text-sobre-primario">{i + 1}</span>
            <p className="font-extrabold">{b.nombre}</p>
            <ul className="mt-2 space-y-1.5">
              {b.materias.map((m) => (
                <li key={m.id_oferta_modulo} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-suave px-3 py-2 text-sm">
                  <span className="font-semibold">{m.nombre}</span>
                  <span className="text-xs text-tenue">{m.horas} h · {m.docentes.join(', ') || 'Docente por designar'}</span>
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ol>
    </Tarjeta>
  )
}

/** CU2/CU3 · Detalle de una oferta y preinscripción desde la web. */
export default function DetalleOferta() {
  const { id } = useParams()
  const auth = useAuth()
  const avisar = useAvisos()
  const navegar = useNavigate()
  const consulta = useConsulta(`/publico/catalogo/${id}`)
  const [abierto, setAbierto] = useState(false)
  const f = useFormulario({ id_arancel: '', observacion: '' }, { id_arancel: [requerido('Seleccione el plan de pago que prefiere.')], observacion: [maximo(500)] })

  const preinscribir = f.enviar(async (valores) => {
    await api.post('/preinscripciones', { id_oferta: Number(id), id_arancel: Number(valores.id_arancel), observacion: valores.observacion || null })
    avisar.exito('Preinscripción enviada. La Secretaría revisará sus datos y documentos.')
    navegar('/mis-preinscripciones')
  })

  return (
    <>
      <Link to="/catalogo" className="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primario hover:underline">
        <ArrowLeft className="size-4" aria-hidden /> Volver al catálogo
      </Link>

      <Estado consulta={consulta}>
        {(o) => (
          <div className="grid gap-5 lg:grid-cols-3">
            <div className="space-y-5 lg:col-span-2">
              <section className="tarjeta p-6">
                <div className="mb-2 flex flex-wrap gap-2">
                  <Insignia tono="informacion">{legible(o.programa.tipo)}</Insignia>
                  <Insignia estado={o.estado} />
                </div>
                <h1 className="text-3xl font-extrabold">{o.programa.nombre}</h1>
                <p className="mt-3 leading-relaxed text-tenue">{o.programa.descripcion || 'Programa de capacitación del ICAP.'}</p>
                <dl className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                  <Dato etiqueta="Modalidad">{legible(o.modalidad)}</Dato>
                  <Dato etiqueta="Duración">{o.malla.horas_totales} horas</Dato>
                  <Dato etiqueta="Inicio">{fecha(o.fecha_inicio)}</Dato>
                  <Dato etiqueta="Finalización">{fecha(o.fecha_fin)}</Dato>
                  <Dato etiqueta="Oferta">{o.codigo}</Dato>
                  <Dato etiqueta="Gestión">{o.gestion}</Dato>
                  <Dato etiqueta="Malla">Versión {o.malla.version}</Dato>
                  <Dato etiqueta="Cupos disponibles">{o.cupos_disponibles} de {o.cupo_maximo}</Dato>
                </dl>
              </section>

              {o.modulos.some((m) => m.bloque) ? <PlanPorModulos modulos={o.modulos} /> : (
              <Tarjeta titulo="Módulos del programa" relleno={false}>
                <Tabla
                  id="id_oferta_modulo"
                  filas={o.modulos}
                  columnas={[
                    { clave: 'orden', titulo: 'N.º', alinear: 'centro' },
                    { clave: 'nombre', titulo: 'Módulo', render: (m) => (<><span className="font-semibold">{m.nombre}</span>{!m.obligatorio && <span className="ml-2 text-xs text-tenue">(optativo)</span>}</>) },
                    { clave: 'horas', titulo: 'Horas', alinear: 'derecha' },
                    { clave: 'docentes', titulo: 'Docente', render: (m) => m.docentes.join(', ') || 'Por designar' },
                  ]}
                />
              </Tarjeta>
              )}
            </div>

            <aside className="space-y-5">
              <Tarjeta titulo="Costos y formas de pago">
                {o.planes_pago.length === 0 ? <p className="text-sm text-tenue">Los costos se publicarán pronto.</p> : (
                  <ul className="space-y-3">
                    {o.planes_pago.map((plan) => (
                      <li key={plan.id_arancel} className="rounded-tema border p-3">
                        <p className="text-sm font-bold">{plan.concepto}</p>
                        <p className="tabular text-2xl font-extrabold text-primario">{moneda(plan.monto)}</p>
                        <p className="text-xs text-tenue">{describirPlan(plan)}</p>
                      </li>
                    ))}
                  </ul>
                )}
                <p className="mt-3 text-xs text-tenue">Si cuenta con una beca, el descuento se aplica al generar su matrícula.</p>
              </Tarjeta>

              <Tarjeta titulo="Requisitos">
                <ul className="space-y-1.5 text-sm">
                  {o.documentos_requeridos.map((d) => (
                    <li key={d} className="flex items-center gap-2"><CircleCheck className="size-4 text-exito" aria-hidden /> {d}</li>
                  ))}
                </ul>
              </Tarjeta>

              {o.estado !== 'ABIERTA' ? <Alerta tipo="alerta">Esta oferta ya no recibe preinscripciones.</Alerta>
                : o.cupos_disponibles === 0 ? <Alerta tipo="alerta">La oferta ya no tiene cupos disponibles.</Alerta>
                : !auth.autenticado ? (
                  <div className="tarjeta space-y-3 p-4">
                    <p className="text-sm">Para preinscribirte necesitas una cuenta de estudiante.</p>
                    <Boton a="/registro" state={{ desde: `/catalogo/${id}` }} className="w-full">Registrarme y preinscribirme</Boton>
                    <Boton a="/ingresar" state={{ desde: `/catalogo/${id}` }} variante="secundario" className="w-full">Ya tengo cuenta</Boton>
                  </div>
                )
                : auth.puede('preinscripciones.propias') ? <Boton tamano="lg" className="w-full" onClick={() => (f.reiniciar(), setAbierto(true))}>Preinscribirme</Boton>
                : <Alerta>La preinscripción en línea está disponible para cuentas de estudiante.</Alerta>}
            </aside>

            <Modal
              abierto={abierto}
              titulo="Confirmar preinscripción"
              alCerrar={() => setAbierto(false)}
              pie={<><Boton variante="secundario" onClick={() => setAbierto(false)}>Cancelar</Boton><Boton onClick={preinscribir} cargando={f.enviando}>Enviar preinscripción</Boton></>}
            >
              <form onSubmit={preinscribir} noValidate className="space-y-4">
                {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
                <p className="text-sm">Se preinscribirá en <strong>{o.programa.nombre}</strong> ({o.codigo}). La Secretaría revisará su solicitud y su expediente de documentos.</p>
                <Campo {...f.campo('id_arancel')} tipo="select" etiqueta="Plan de pago preferido" requerido
                  opciones={o.planes_pago.map((p) => ({ valor: p.id_arancel, etiqueta: `${p.concepto} — ${moneda(p.monto)} (${describirPlan(p)})` }))} />
                <Campo {...f.campo('observacion')} tipo="textarea" etiqueta="Comentario para la Secretaría (opcional)" maxLength={500} />
              </form>
            </Modal>
          </div>
        )}
      </Estado>
    </>
  )
}
