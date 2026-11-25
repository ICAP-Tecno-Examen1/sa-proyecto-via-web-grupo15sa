import { Plus, Search } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../../api/cliente'
import { Alerta, EncabezadoPagina, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal from '../../components/ui/Modal'
import Tabla from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fecha, legible, moneda } from '../../utils/formato'
import { codigo, entero, entre, maximo, minimo, posteriorA, requerido } from '../../utils/validacion'

export const ESTADOS_OFERTA = ['PLANIFICADA', 'ABIERTA', 'EN_CURSO', 'FINALIZADA', 'CANCELADA']
export const MODALIDADES = ['PRESENCIAL', 'VIRTUAL', 'SEMIPRESENCIAL'].map((m) => ({ valor: m, etiqueta: legible(m) }))

export const reglasOferta = {
  codigo: [requerido(), minimo(3), maximo(50), codigo()],
  modalidad: [requerido()],
  fecha_inicio: [requerido()],
  fecha_fin: [requerido(), posteriorA('fecha_inicio', 'la fecha de inicio')],
  cupo_maximo: [requerido(), entero(), entre(1, 1000)],
  cupo_minimo: [requerido(), entero(), entre(0, 1000), (v, t) => (Number(v) > Number(t.cupo_maximo) ? 'No puede superar el cupo máximo.' : null)],
  gestion: [requerido(), entero(), entre(2000, 2100)],
}

/*
 * Carreras: cada módulo dura MESES_POR_MODULO y sus materias se dictan a la vez.
 * Debe coincidir con ICAP_MESES_POR_MODULO del backend (config/icap.php), que es
 * quien genera el calendario definitivo al crear la oferta.
 */
const MESES_POR_MODULO = 1

/** Fecha de fin de una carrera: inicio + (módulos × meses) − 1 día. Formato AAAA-MM-DD. */
function finDeCarrera(inicio, cantidadModulos) {
  if (!inicio || !cantidadModulos) return null
  const [anio, mes, dia] = inicio.split('-').map(Number)
  const total = mes - 1 + cantidadModulos * MESES_POR_MODULO
  const anioFin = anio + Math.floor(total / 12)
  const mesFin = total % 12
  const ultimoDia = new Date(Date.UTC(anioFin, mesFin + 1, 0)).getUTCDate()
  const fin = new Date(Date.UTC(anioFin, mesFin, Math.min(dia, ultimoDia) - 1))

  return fin.toISOString().slice(0, 10)
}

export function CamposOferta({ f, finCalculado, modulosCarrera }) {
  return (
    <>
      <Campo {...f.campo('codigo')} etiqueta="Código de la oferta" requerido ayuda="Ejemplo: OFI-2026-A" />
      <Campo {...f.campo('modalidad')} tipo="select" etiqueta="Modalidad" opciones={MODALIDADES} requerido />
      <Campo {...f.campo('fecha_inicio')} tipo="date" etiqueta="Fecha de inicio" requerido />
      {modulosCarrera
        ? <Campo nombre="fecha_fin" tipo="date" etiqueta="Fecha de finalización" valor={finCalculado ?? ''} alCambiar={() => {}} disabled
            ayuda={`Se calcula sola: ${modulosCarrera} módulos × ${MESES_POR_MODULO} mes. Cada módulo se dicta en su mes, con sus materias a la vez.`} />
        : <Campo {...f.campo('fecha_fin')} tipo="date" etiqueta="Fecha de finalización" requerido />}
      <Campo {...f.campo('cupo_minimo')} tipo="number" min={0} etiqueta="Cupo mínimo" requerido />
      <Campo {...f.campo('cupo_maximo')} tipo="number" min={1} etiqueta="Cupo máximo" requerido />
      <Campo {...f.campo('gestion')} tipo="number" etiqueta="Gestión" requerido />
    </>
  )
}

export const cuerpoOferta = (v) => ({ ...v, cupo_maximo: Number(v.cupo_maximo), cupo_minimo: Number(v.cupo_minimo), gestion: Number(v.gestion) })

/** CU2 · Ofertas académicas: una oferta dicta una versión de malla en fechas y cupos concretos. */
export default function Ofertas() {
  const auth = useAuth()
  const avisar = useAvisos()
  const navegar = useNavigate()
  const [filtros, setFiltros] = useState({ q: '', estado: '', gestion: '' })
  const ofertas = useConsulta('/ofertas', { ...filtros, q: useRetardo(filtros.q) })
  const [creando, setCreando] = useState(false)
  const mallas = useConsulta(creando ? '/mallas' : null, { estado: 'ACTIVO' })

  // Si la malla elegida es de una carrera (con módulos), la fecha de fin se calcula sola.
  const modulosDe = (idMalla) => mallas.datos?.find((m) => String(m.id_malla) === String(idMalla))?.bloques?.length ?? 0

  const f = useFormulario(
    { id_malla: '', codigo: '', modalidad: '', fecha_inicio: '', fecha_fin: '', cupo_minimo: 5, cupo_maximo: 25, gestion: new Date().getFullYear() },
    {
      id_malla: [requerido('Seleccione la malla curricular que se dictará.')],
      ...reglasOferta,
      fecha_fin: reglasOferta.fecha_fin.map((regla) => (v, todos) => (modulosDe(todos.id_malla) ? null : regla(v, todos))),
    },
  )
  const modulosCarrera = modulosDe(f.valores.id_malla)
  const finCalculado = finDeCarrera(f.valores.fecha_inicio, modulosCarrera)

  const crear = f.enviar(async (valores) => {
    const fechaFin = modulosCarrera ? finCalculado : valores.fecha_fin
    const { data } = await api.post('/ofertas', { ...cuerpoOferta(valores), fecha_fin: fechaFin, id_malla: Number(valores.id_malla) })
    avisar.exito('Oferta creada en estado «Planificada». Asigne docentes y solicite la configuración de costos.')
    navegar(`/ofertas/${data.id_oferta}`)
  })

  return (
    <>
      <EncabezadoPagina titulo="Ofertas académicas" descripcion="Cada oferta dicta una versión de malla con fechas, modalidad y cupos. Se abre a preinscripciones cuando ya tiene costos configurados.">
        {auth.puede('ofertas.gestionar') && <Boton icono={Plus} onClick={() => (f.reiniciar(), setCreando(true))}>Nueva oferta</Boton>}
      </EncabezadoPagina>

      <Tarjeta relleno={false}>
        <div className="flex flex-wrap items-end gap-3 border-b p-3">
          <div className="relative min-w-56 flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" value={filtros.q} onChange={(e) => setFiltros((p) => ({ ...p, q: e.target.value }))} placeholder="Buscar por código o programa…" aria-label="Buscar oferta" className="campo-control pl-9" />
          </div>
          <Campo tipo="select" nombre="estado" valor={filtros.estado} alCambiar={(v) => setFiltros((p) => ({ ...p, estado: v }))} vacio="Todos los estados" aria-label="Estado" className="w-48"
            opciones={ESTADOS_OFERTA.map((e) => ({ valor: e, etiqueta: legible(e) }))} />
          <Campo tipo="number" nombre="gestion" valor={filtros.gestion} alCambiar={(v) => setFiltros((p) => ({ ...p, gestion: v }))} placeholder="Gestión" aria-label="Gestión" className="w-32" />
        </div>

        {ofertas.error ? <div className="p-4"><Alerta tipo="peligro">{ofertas.error}</Alerta></div> : (
          <Tabla
            id="id_oferta"
            filas={ofertas.datos}
            cargando={ofertas.cargando}
            alSeleccionar={(o) => navegar(`/ofertas/${o.id_oferta}`)}
            columnas={[
              { clave: 'codigo', titulo: 'Oferta', render: (o) => (<><span className="font-semibold">{o.codigo}</span><span className="block text-xs text-tenue">Gestión {o.gestion}</span></>) },
              { clave: 'programa', titulo: 'Programa', render: (o) => (<>{o.programa.nombre}<span className="block text-xs text-tenue">Malla {o.malla.version} · {o.malla.horas_totales} h</span></>) },
              { clave: 'modalidad', titulo: 'Modalidad', render: (o) => legible(o.modalidad) },
              { clave: 'fechas', titulo: 'Periodo', render: (o) => `${fecha(o.fecha_inicio)} – ${fecha(o.fecha_fin)}` },
              { clave: 'cupos', titulo: 'Cupos', alinear: 'derecha', render: (o) => `${o.cupos_ocupados} / ${o.cupo_maximo}` },
              { clave: 'costo', titulo: 'Costo desde', alinear: 'derecha', render: (o) => (o.costo_desde != null ? moneda(o.costo_desde) : <span className="text-alerta">Sin costos</span>) },
              { clave: 'estado', titulo: 'Estado', render: (o) => <Insignia estado={o.estado} /> },
            ]}
          />
        )}
      </Tarjeta>

      <Modal abierto={creando} titulo="Nueva oferta académica" alCerrar={() => setCreando(false)}
        pie={<><Boton variante="secundario" onClick={() => setCreando(false)}>Cancelar</Boton><Boton onClick={crear} cargando={f.enviando}>Crear oferta</Boton></>}>
        <form onSubmit={crear} noValidate className="grid gap-4 sm:grid-cols-2">
          {f.errorGeneral && <Alerta tipo="peligro" className="sm:col-span-2">{f.errorGeneral}</Alerta>}
          <Campo {...f.campo('id_malla')} tipo="select" etiqueta="Programa y versión de malla" requerido className="sm:col-span-2"
            ayuda={modulosCarrera ? 'Carrera por módulos: un módulo por mes; las materias de un mismo módulo se dictan ese mes.' : 'La oferta heredará todas las materias de esta malla.'}
            opciones={(mallas.datos ?? []).map((m) => ({
              valor: m.id_malla,
              etiqueta: `${m.programa.nombre} — versión ${m.version} (${m.bloques?.length ? `${m.bloques.length} módulos · ${m.malla_modulos.length} materias` : `${m.malla_modulos.length} materias`}, ${m.horas_totales} h)`,
            }))} />
          <CamposOferta f={f} finCalculado={finCalculado} modulosCarrera={modulosCarrera} />
        </form>
      </Modal>
    </>
  )
}
