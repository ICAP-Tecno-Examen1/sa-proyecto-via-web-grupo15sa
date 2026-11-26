import { Ban, Check, Eye, Search, UserPlus, X } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import api, { abrirArchivo } from '../../api/cliente'
import { Alerta, Cargando, Dato, Estado, Insignia, Pestanas, Tarjeta, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import CrudSimple from '../../components/ui/CrudSimple'
import Modal from '../../components/ui/Modal'
import Tabla from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fecha, fechaHora, moneda, porcentaje } from '../../utils/formato'
import { entre, maximo, minimo, numero, requerido } from '../../utils/validacion'

/**
 * CU4 · Becas y descuentos.
 *  - Solicitudes: el estudiante pidió una beca con su documento de respaldo; el
 *    Coordinador Financiero lo revisa y la aprueba (se descuenta de las cuotas
 *    pendientes) o la rechaza con el motivo.
 *  - Catálogo (tabla beca): cada beca o convenio, la institución que la financia, el
 *    porcentaje, los cursos que cubre, el documento que piden y su estado.
 *  - Asignadas (tabla beca_estudiante): qué beca tiene cada matrícula; desde aquí
 *    también se asigna directamente.
 */
export default function Becas() {
  const [parametros, setParametros] = useSearchParams()
  const pestana = ['solicitudes', 'catalogo', 'asignadas'].includes(parametros.get('pestana')) ? parametros.get('pestana') : 'solicitudes'
  const cambiar = (clave) => setParametros(clave === 'solicitudes' ? {} : { pestana: clave }, { replace: true })
  const pendientes = useConsulta('/becas/solicitudes')
  const selector = (
    <Pestanas activa={pestana} alCambiar={cambiar} pestanas={[
      { clave: 'solicitudes', etiqueta: 'Solicitudes por revisar', contador: pendientes.datos?.length },
      { clave: 'catalogo', etiqueta: 'Catálogo de becas' },
      { clave: 'asignadas', etiqueta: 'Becas asignadas' },
    ]} />
  )

  if (pestana === 'catalogo') return <Catalogo selector={selector} />
  if (pestana === 'asignadas') return <Asignadas selector={selector} />

  return <Solicitudes selector={selector} consulta={pendientes} />
}

function Encabezado({ selector }) {
  return (
    <>
      <h1 className="mb-1 text-2xl font-bold">Becas y descuentos</h1>
      <p className="mb-5 text-sm text-tenue">Un estudiante solo recibe el descuento cuando su solicitud, con el documento que pide la beca, es aprobada.</p>
      {selector}
    </>
  )
}

function Solicitudes({ selector, consulta }) {
  const auth = useAuth()
  const avisar = useAvisos()
  const navegar = useNavigate()
  const revisa = auth.puede('becas.asignar')
  const [revision, setRevision] = useState(null) // { solicitud, decision }
  const f = useFormulario({ porcentaje_aplicado: '', texto: '' }, {
    porcentaje_aplicado: [numero(), entre(0.01, 100)],
    texto: [(v) => (revision?.decision === 'rechazar' && (!v || v.trim().length < 5) ? 'Explique al estudiante el motivo (mínimo 5 letras).' : null), maximo(500)],
  })

  const abrir = (solicitud, decision) => {
    f.reiniciar({ porcentaje_aplicado: solicitud.porcentaje_beca, texto: '' })
    setRevision({ solicitud, decision })
  }

  const decidir = f.enviar(async (v) => {
    const { solicitud, decision } = revision
    const { data } = decision === 'aprobar'
      ? await api.post(`/becas/solicitudes/${solicitud.id_beca_estudiante}/aprobar`, { porcentaje_aplicado: Number(v.porcentaje_aplicado), observacion: v.texto || null })
      : await api.post(`/becas/solicitudes/${solicitud.id_beca_estudiante}/rechazar`, { motivo: v.texto })
    avisar.exito(data.message)
    setRevision(null)
    consulta.recargar()
  })

  return (
    <>
      <Encabezado selector={selector} />
      <Estado consulta={consulta}>
        {(lista) => lista.length === 0 ? (
          <div className="tarjeta"><Vacio titulo="No hay solicitudes de beca por revisar">Cuando un estudiante solicite una beca desde «Mis pagos», aparecerá aquí con su documento.</Vacio></div>
        ) : (
          <div className="grid gap-4 lg:grid-cols-2">
            {lista.map((s) => (
              <article key={s.id_beca_estudiante} className="tarjeta p-5">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <h2 className="font-bold">{s.matricula.estudiante.nombre_completo}</h2>
                    <p className="text-xs text-tenue">CI {s.matricula.estudiante.ci} · {s.matricula.programa.nombre} · {s.matricula.numero_registro}</p>
                  </div>
                  <span className="text-right">
                    <Insignia tono="informacion">{s.beca} · {porcentaje(s.porcentaje_beca)}</Insignia>
                    {s.institucion && <span className="mt-1 block text-xs text-tenue">Convenio con: {s.institucion}</span>}
                  </span>
                </div>

                <dl className="mt-4 grid grid-cols-2 gap-3">
                  <Dato etiqueta="Solicitada">{fechaHora(s.fecha_solicitud)}</Dato>
                  <Dato etiqueta="Total de la matrícula">{moneda(s.matricula.monto_final)}</Dato>
                </dl>
                {s.motivo && <p className="mt-3 rounded-tema bg-suave p-3 text-sm">«{s.motivo}»</p>}
                <p className="mt-3 text-xs text-tenue"><strong>Debe presentar:</strong> {s.requisitos || 'Documento que respalde la beca.'}</p>

                <div className="mt-4 flex flex-wrap gap-2">
                  <Boton tamano="sm" variante="secundario" icono={Eye} onClick={() => abrirArchivo(`/becas/solicitudes/${s.id_beca_estudiante}/respaldo`).catch(avisar.error)}>Ver documento</Boton>
                  <Boton tamano="sm" variante="fantasma" onClick={() => navegar(`/matriculas/${s.matricula.id_matricula}`)}>Ver matrícula</Boton>
                  {revisa && (
                    <span className="ml-auto flex gap-2">
                      <Boton tamano="sm" variante="secundario" icono={X} className="text-peligro" onClick={() => abrir(s, 'rechazar')}>Rechazar</Boton>
                      <Boton tamano="sm" variante="exito" icono={Check} onClick={() => abrir(s, 'aprobar')}>Aprobar</Boton>
                    </span>
                  )}
                </div>
              </article>
            ))}
          </div>
        )}
      </Estado>

      <Modal abierto={Boolean(revision)} ancho="sm" alCerrar={() => setRevision(null)}
        titulo={revision?.decision === 'aprobar' ? `Aprobar ${revision?.solicitud.beca}` : `Rechazar ${revision?.solicitud.beca ?? ''}`}
        pie={<><Boton variante="secundario" onClick={() => setRevision(null)}>Cancelar</Boton><Boton variante={revision?.decision === 'aprobar' ? 'exito' : 'peligro'} onClick={decidir} cargando={f.enviando}>{revision?.decision === 'aprobar' ? 'Aprobar y aplicar descuento' : 'Rechazar solicitud'}</Boton></>}>
        <form onSubmit={decidir} noValidate className="space-y-4">
          {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
          <p className="text-sm">Estudiante: <strong>{revision?.solicitud.matricula.estudiante.nombre_completo}</strong></p>
          {revision?.decision === 'aprobar' ? (
            <>
              <Campo {...f.campo('porcentaje_aplicado')} tipo="number" min="0.01" max="100" step="0.01" etiqueta="Porcentaje a aplicar" ayuda={`La beca indica ${porcentaje(revision.solicitud.porcentaje_beca)}; puede ajustarlo si corresponde.`} />
              <Campo {...f.campo('texto')} tipo="textarea" etiqueta="Nota (opcional)" />
              <Alerta>Se recalcularán solo las cuotas que el estudiante aún no pagó.</Alerta>
            </>
          ) : (
            <Campo {...f.campo('texto')} tipo="textarea" etiqueta="Motivo del rechazo" requerido ayuda="El estudiante lo verá en «Mis pagos» y podrá volver a solicitarla." />
          )}
        </form>
      </Modal>
    </>
  )
}

const ESTADOS_BECA = [{ valor: 'ACTIVA', etiqueta: 'Activa (se puede solicitar y aplicar)' }, { valor: 'INACTIVA', etiqueta: 'Inactiva (no se ofrece)' }]

/**
 * Catálogo (tabla beca). La beca ES el convenio: «Institución financiadora» es la
 * institución o empresa del convenio (vacía = beca propia del ICAP).
 */
function Catalogo({ selector }) {
  const auth = useAuth()
  const avisar = useAvisos()
  const ofertas = useConsulta('/ofertas')
  const opcionesOferta = (ofertas.datos ?? [])
    .filter((o) => !['FINALIZADA', 'CANCELADA'].includes(o.estado))
    .map((o) => ({ valor: String(o.id_oferta), etiqueta: `${o.codigo} · ${o.programa.nombre}` }))

  const alternar = async (b, recargar) => {
    try {
      await api.patch(`/becas/${b.id_beca}/estado`)
      avisar.exito(b.estado === 'ACTIVA' ? 'Beca desactivada: ya no se ofrece. Las ya aplicadas se mantienen.' : 'Beca activada.')
      recargar()
    } catch (e) {
      avisar.error(e)
    }
  }

  return (
    <CrudSimple
      titulo="Becas y descuentos"
      descripcion="Registre aquí cada beca o convenio: la institución que la financia, el porcentaje, los cursos que cubre y si está activa. Las becas de una institución exigen su documento al solicitarlas."
      singular="beca"
      femenino
      recurso="/becas"
      id="id_beca"
      buscar={false}
      permisoGestion="becas.gestionar"
      anchoModal="lg"
      nota={selector}
      columnas={[
        { clave: 'nombre', titulo: 'Beca', render: (b) => (<><span className="font-semibold">{b.nombre}</span><span className="block max-w-md truncate text-xs text-tenue">{b.requisitos ? `Requisito: ${b.requisitos}` : b.descripcion}</span></>) },
        { clave: 'institucion_financiadora', titulo: 'Institución (convenio)', render: (b) => b.institucion_financiadora ?? <span className="text-tenue">Propia del ICAP</span> },
        { clave: 'ofertas', titulo: 'Aplica a', render: (b) => (b.ofertas.length ? <span title={b.ofertas.map((o) => o.codigo).join(', ')}>{b.ofertas.length === 1 ? b.ofertas[0].codigo : `${b.ofertas.length} cursos`}</span> : 'Todos los cursos') },
        { clave: 'porcentaje', titulo: 'Descuento', alinear: 'derecha', render: (b) => <strong>{porcentaje(b.porcentaje)}</strong> },
        { clave: 'asignaciones_count', titulo: 'Becados', alinear: 'derecha' },
        { clave: 'estado', titulo: 'Estado', render: (b) => <Insignia estado={b.estado === 'ACTIVA' ? 'ACTIVO' : 'INACTIVO'}>{b.estado === 'ACTIVA' ? 'Activa' : 'Inactiva'}</Insignia> },
      ]}
      inicial={{ nombre: '', porcentaje: '', estado: 'ACTIVA', institucion_financiadora: '', ofertas: [], descripcion: '', requisitos: '' }}
      reglas={{
        nombre: [requerido(), minimo(3), maximo(200)],
        porcentaje: [requerido(), numero(), entre(0.01, 100)],
        estado: [requerido()],
        institucion_financiadora: [maximo(200)],
        descripcion: [maximo(1000)],
        requisitos: [(v, t) => (t.institucion_financiadora?.trim() && !v?.trim() ? 'Indique qué documento debe presentar el beneficiario de la institución.' : null), maximo(1000)],
      }}
      campos={[
        { nombre: 'nombre', etiqueta: 'Nombre de la beca', requerido: true, ancho: 2, ayuda: 'Por ejemplo: «Convenio Constructora Sur».' },
        { nombre: 'institucion_financiadora', etiqueta: 'Institución financiadora (convenio)', ancho: 2, ayuda: 'Empresa o institución con la que el ICAP tiene el convenio. Vacío = beca propia del ICAP.' },
        { nombre: 'porcentaje', etiqueta: 'Porcentaje de descuento', tipo: 'number', min: '0.01', max: 100, step: '0.01', requerido: true },
        { nombre: 'estado', etiqueta: 'Estado', tipo: 'select', opciones: ESTADOS_BECA, vacio: null, requerido: true },
        { nombre: 'ofertas', etiqueta: 'Cursos que cubre (vacío = todos)', tipo: 'casillas', opciones: opcionesOferta, ancho: 2, visible: () => opcionesOferta.length > 0 },
        { nombre: 'requisitos', etiqueta: 'Documento que debe presentar', tipo: 'textarea', ancho: 2, ayuda: 'Obligatorio si la beca es de una institución. Ej.: «Credencial de empleado o carta de la empresa».' },
        { nombre: 'descripcion', etiqueta: 'Descripción', tipo: 'textarea', ancho: 2 },
      ]}
      aFormulario={(b) => ({
        ...b, descripcion: b.descripcion ?? '', requisitos: b.requisitos ?? '', institucion_financiadora: b.institucion_financiadora ?? '',
        ofertas: b.ofertas.map((o) => String(o.id_oferta)),
      })}
      aEnvio={({ id_beca: _id, asignaciones_count: _a, requiere_respaldo: _r, ...v }) => ({
        ...v, porcentaje: Number(v.porcentaje), institucion_financiadora: v.institucion_financiadora.trim() || null, ofertas: v.ofertas.map(Number),
      })}
      accionesFila={(b, recargar) => auth.puede('becas.gestionar') && (
        <Boton tamano="sm" variante="fantasma" icono={b.estado === 'ACTIVA' ? Ban : Check} className={b.estado === 'ACTIVA' ? 'text-peligro' : 'text-exito'} onClick={() => alternar(b, recargar)}>
          {b.estado === 'ACTIVA' ? 'Desactivar' : 'Activar'}
        </Boton>
      )}
    />
  )
}

/** Becas asignadas (tabla beca_estudiante: beca ↔ matrícula del estudiante). */
function Asignadas({ selector }) {
  const auth = useAuth()
  const consulta = useConsulta('/becas-asignadas')
  const navegar = useNavigate()
  const [asignando, setAsignando] = useState(false)

  return (
    <>
      <Encabezado selector={selector} />
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-tenue">Cada fila vincula una beca con la matrícula de un estudiante. Al asignarla se rebajan sus cuotas pendientes.</p>
        {auth.puede('becas.asignar') && <Boton icono={UserPlus} onClick={() => setAsignando(true)}>Asignar beca a un estudiante</Boton>}
      </div>
      <Tarjeta relleno={false}>
        <Estado consulta={consulta}>
          {(filas) => (
            <Tabla
              id="id_beca_estudiante"
              filas={filas}
              vacio="Aún no se asignaron becas."
              alSeleccionar={(b) => navegar(`/matriculas/${b.matricula.id_matricula}`)}
              columnas={[
                { clave: 'fecha_asignacion', titulo: 'Asignada', render: (b) => fecha(b.fecha_asignacion) },
                { clave: 'estudiante', titulo: 'Estudiante', render: (b) => (<>{b.matricula.estudiante.nombre_completo}<span className="block text-xs text-tenue">{b.matricula.numero_registro}</span></>) },
                { clave: 'programa', titulo: 'Programa', render: (b) => b.matricula.programa.nombre },
                { clave: 'beca', titulo: 'Beca', render: (b) => (<>{b.beca}{b.institucion && <span className="block text-xs text-tenue">{b.institucion}</span>}</>) },
                { clave: 'porcentaje_aplicado', titulo: 'Porcentaje', alinear: 'derecha', render: (b) => porcentaje(b.porcentaje_aplicado) },
                { clave: 'revisado_por', titulo: 'Aprobada por', render: (b) => b.revisado_por ?? '—' },
                { clave: 'estado', titulo: 'Estado', render: (b) => <Insignia estado={b.estado} /> },
              ]}
            />
          )}
        </Estado>
      </Tarjeta>

      {asignando && <AsignarBeca alCerrar={() => setAsignando(false)} alTerminar={consulta.recargar} />}
    </>
  )
}

/**
 * Asignación directa: 1) buscar al estudiante y elegir su matrícula, 2) elegir una beca
 * que aplique a esa matrícula. Crea el registro en beca_estudiante (estado ACTIVA).
 */
function AsignarBeca({ alCerrar, alTerminar }) {
  const avisar = useAvisos()
  const [texto, setTexto] = useState('')
  const q = useRetardo(texto.trim())
  const [matricula, setMatricula] = useState(null)
  const lista = useConsulta(!matricula && q.length >= 2 ? '/matriculas' : null, { q, por_pagina: 8 })
  const becas = useConsulta(matricula ? '/becas/disponibles' : null, { id_matricula: matricula?.id_matricula })
  const f = useFormulario({ id_beca: '', porcentaje_aplicado: '' }, { id_beca: [requerido('Elija la beca.')], porcentaje_aplicado: [numero(), entre(0.01, 100)] })
  const beca = becas.datos?.find((b) => String(b.id_beca) === String(f.valores.id_beca))

  const asignar = f.enviar(async (v) => {
    const { data } = await api.post(`/matriculas/${matricula.id_matricula}/becas`, { id_beca: Number(v.id_beca), porcentaje_aplicado: v.porcentaje_aplicado ? Number(v.porcentaje_aplicado) : null })
    avisar.exito(data.message)
    alTerminar?.()
    alCerrar()
  })

  return (
    <Modal abierto titulo="Asignar beca a un estudiante" alCerrar={alCerrar}
      pie={<><Boton variante="secundario" onClick={alCerrar}>Cancelar</Boton>{matricula && <Boton icono={Check} onClick={asignar} cargando={f.enviando}>Asignar beca</Boton>}</>}>
      {!matricula ? (
        <>
          <p className="mb-3 text-sm">1. Busque al estudiante por nombre, documento o N.º de registro y elija su matrícula.</p>
          <div className="relative mb-3">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" autoFocus value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Nombre, documento o N.º de registro…" aria-label="Buscar matrícula" className="campo-control pl-9" />
          </div>
          {q.length < 2 ? <p className="text-sm text-tenue">Escriba al menos 2 caracteres.</p> : lista.cargando ? <Cargando className="py-6" /> : (
            <ul className="space-y-1.5">
              {(lista.datos?.data ?? []).length === 0 && <li className="text-sm text-tenue">No se encontraron matrículas.</li>}
              {(lista.datos?.data ?? []).map((m) => {
                const vigente = ['PENDIENTE_PAGO', 'ACTIVA'].includes(m.estado)

                return (
                  <li key={m.id_matricula}>
                    <button type="button" disabled={!vigente} onClick={() => (f.reiniciar(), setMatricula(m))}
                      className="flex w-full flex-wrap items-center justify-between gap-2 rounded-tema border px-3 py-2 text-left hover:bg-primario-suave disabled:cursor-not-allowed disabled:opacity-50">
                      <span>
                        <strong>{m.estudiante.nombre_completo}</strong>
                        <span className="block text-xs text-tenue">{m.numero_registro} · {m.programa.nombre}{!vigente ? ' · no admite becas' : ''}</span>
                      </span>
                      <Insignia estado={m.estado} />
                    </button>
                  </li>
                )
              })}
            </ul>
          )}
        </>
      ) : (
        <form onSubmit={asignar} noValidate className="space-y-4">
          {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
          <div className="flex items-center justify-between gap-2 rounded-tema bg-suave px-3 py-2 text-sm">
            <span><strong>{matricula.estudiante.nombre_completo}</strong> · {matricula.numero_registro} · {matricula.programa.nombre}</span>
            <Boton tamano="sm" variante="fantasma" onClick={() => setMatricula(null)}>Cambiar</Boton>
          </div>
          <p className="text-sm">2. Elija la beca (solo aparecen las activas que cubren su curso).</p>
          <Campo {...f.campo('id_beca')} tipo="select" etiqueta="Beca" requerido
            opciones={(becas.datos ?? []).map((b) => ({ valor: b.id_beca, etiqueta: `${b.nombre}${b.institucion_financiadora ? ` (${b.institucion_financiadora})` : ''} · ${porcentaje(b.porcentaje)}` }))} />
          {becas.datos?.length === 0 && <Alerta tipo="alerta">No hay becas activas que cubran el curso de esta matrícula.</Alerta>}
          <Campo {...f.campo('porcentaje_aplicado')} tipo="number" min="0.01" max="100" step="0.01" etiqueta="Porcentaje a aplicar (opcional)"
            ayuda={beca ? `Vacío = el de la beca (${porcentaje(beca.porcentaje)}).` : 'Vacío = el porcentaje de la beca.'} />
          {beca?.institucion_financiadora && <Alerta>Verifique antes el documento del estudiante con «{beca.institucion_financiadora}» (credencial o carta).</Alerta>}
        </form>
      )}
    </Modal>
  )
}
