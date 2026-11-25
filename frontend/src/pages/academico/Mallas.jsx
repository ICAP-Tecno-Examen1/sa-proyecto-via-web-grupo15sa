import { ArrowDown, ArrowUp, Copy, Layers, ListOrdered, Pencil, Plus, X } from 'lucide-react'
import { useState } from 'react'
import api from '../../api/cliente'
import { Alerta, EncabezadoPagina, Estado, Insignia, Tarjeta, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal from '../../components/ui/Modal'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fecha } from '../../utils/formato'
import { maximo, requerido } from '../../utils/validacion'

const ESTADOS = [{ valor: 'ACTIVO', etiqueta: 'Activa' }, { valor: 'INACTIVO', etiqueta: 'Inactiva' }]
const MAXIMO_MATERIAS = 2 // por módulo de carrera (ModuloCarrera::MAXIMO_MATERIAS en la API)

/*
 * Dos formas de armar una malla:
 *   simple   lista ordenada de materias (cursos, talleres, programas técnicos…)
 *   carrera  módulos de la carrera; cada módulo agrupa 1 o 2 materias
 */
const inicial = { id_programa: '', version: '', fecha_vigencia: '', estado: 'ACTIVO', modo: 'simple', modulos: [], bloques: [] }
let siguienteClave = 1
const nuevoBloque = () => ({ clave: `nuevo-${siguienteClave++}`, nombre: '', materias: [] })

/** Módulos de la carrera de una malla guardada, cada uno con sus materias en orden. */
function bloquesDe(malla) {
  return [...(malla.bloques ?? [])]
    .sort((a, b) => a.orden - b.orden)
    .map((b) => ({
      ...b,
      clave: b.id_modulo_carrera,
      materias: malla.malla_modulos.filter((m) => m.id_modulo_carrera === b.id_modulo_carrera).sort((x, y) => x.orden - y.orden),
    }))
}

/**
 * CU2 · Mallas curriculares y versionado.
 * Una malla usada en una oferta queda congelada: para cambiarla se crea una
 * nueva versión (botón «Nueva versión»), que copia sus materias y módulos.
 */
export default function Mallas() {
  const auth = useAuth()
  const avisar = useAvisos()
  const gestiona = auth.puede('programas.gestionar')
  const [idPrograma, setIdPrograma] = useState('')
  const mallas = useConsulta('/mallas', { id_programa: idPrograma })
  const programas = useConsulta('/programas')
  const modulos = useConsulta('/modulos')

  const [editando, setEditando] = useState(null) // null · {} nueva · malla
  const [versionando, setVersionando] = useState(null)
  const [moduloElegido, setModuloElegido] = useState('')

  const f = useFormulario(inicial, {
    id_programa: [requerido()],
    version: [requerido(), maximo(30)],
    modulos: [(v, todo) => (todo.modo === 'simple' && !v.length ? 'Agregue al menos una materia a la malla.' : null)],
    bloques: [(v, todo) => {
      if (todo.modo !== 'carrera') return null
      if (!v.length) return 'Agregue al menos un módulo a la carrera.'
      const sinNombre = v.findIndex((b) => b.nombre.trim().length < 3)
      if (sinNombre >= 0) return `Escriba el nombre del módulo ${sinNombre + 1} (mínimo 3 letras).`
      const vacio = v.findIndex((b) => b.materias.length === 0)
      if (vacio >= 0) return `El módulo ${vacio + 1} necesita al menos una materia.`

      return null
    }],
  })
  const fVersion = useFormulario({ version: '', fecha_vigencia: '' }, { version: [requerido(), maximo(30)] })

  const esNueva = editando && !editando.id_malla
  const congelada = editando?.ofertas_count > 0
  const carrera = f.valores.modo === 'carrera'
  const materia = (id) => modulos.datos?.find((x) => x.id_modulo === id)
  const idsElegidos = carrera ? f.valores.bloques.flatMap((b) => b.materias) : f.valores.modulos.map((m) => m.id_modulo)
  const horas = idsElegidos.reduce((suma, id) => suma + (materia(id)?.horas ?? 0), 0)
  const disponibles = (modulos.datos ?? []).filter((m) => !idsElegidos.includes(m.id_modulo))
  const opcionesMateria = disponibles.map((m) => ({ valor: m.id_modulo, etiqueta: `${m.codigo} · ${m.nombre} (${m.horas} h)` }))

  const abrir = (malla) => {
    const bloques = malla ? bloquesDe(malla) : []
    f.reiniciar(malla
      ? {
          id_programa: malla.id_programa, version: malla.version, fecha_vigencia: malla.fecha_vigencia ?? '', estado: malla.estado,
          modo: bloques.length ? 'carrera' : 'simple',
          modulos: malla.malla_modulos.map((m) => ({ id_modulo: m.id_modulo, obligatorio: m.obligatorio })),
          bloques: bloques.map((b) => ({ clave: b.clave, nombre: b.nombre, materias: b.materias.map((m) => m.id_modulo) })),
        }
      : { ...inicial, id_programa: idPrograma, bloques: [nuevoBloque()] })
    setModuloElegido('')
    setEditando(malla ?? {})
  }

  /** Intercambia un elemento con su vecino dentro de una lista del formulario. */
  const mover = (campo, indice, salto) => {
    const lista = [...f.valores[campo]]
    const destino = indice + salto
    if (destino < 0 || destino >= lista.length) return
    ;[lista[indice], lista[destino]] = [lista[destino], lista[indice]]
    f.cambiar(campo, lista)
  }

  const cambiarBloque = (indice, cambios) => f.cambiar('bloques', f.valores.bloques.map((b, i) => (i === indice ? { ...b, ...cambios } : b)))

  const guardar = f.enviar(async (valores) => {
    const base = { id_programa: Number(valores.id_programa), version: valores.version, estado: valores.estado, fecha_vigencia: valores.fecha_vigencia || null }
    const cuerpo = valores.modo === 'carrera'
      ? {
          ...base,
          bloques: valores.bloques.map((b, i) => ({
            nombre: b.nombre.trim(), orden: i + 1, modulos: b.materias.map((id) => ({ id_modulo: id, obligatorio: true })),
          })),
        }
      : { ...base, modulos: valores.modulos.map((m, i) => ({ ...m, orden: i + 1 })) }

    if (esNueva) await api.post('/mallas', cuerpo)
    else await api.put(`/mallas/${editando.id_malla}`, cuerpo)

    avisar.exito(esNueva ? 'Malla curricular creada.' : 'Malla curricular actualizada.')
    setEditando(null)
    mallas.recargar()
  })

  const versionar = fVersion.enviar(async (valores) => {
    await api.post(`/mallas/${versionando.id_malla}/versionar`, { ...valores, fecha_vigencia: valores.fecha_vigencia || null })
    avisar.exito(`Se creó la versión ${valores.version} con las mismas materias. Ya puede modificarla.`)
    setVersionando(null)
    mallas.recargar()
  })

  return (
    <>
      <EncabezadoPagina titulo="Mallas curriculares" descripcion="Cada malla es una versión del plan de estudios de un programa. En una carrera, las materias se agrupan en módulos de 1 o 2 materias.">
        {gestiona && <Boton icono={Plus} onClick={() => abrir(null)}>Nueva malla</Boton>}
      </EncabezadoPagina>

      <div className="tarjeta mb-5 p-3">
        <Campo tipo="select" nombre="programa" valor={idPrograma} alCambiar={setIdPrograma} vacio="Todos los programas" aria-label="Filtrar por programa" className="max-w-md"
          opciones={(programas.datos ?? []).map((p) => ({ valor: p.id_programa, etiqueta: `${p.codigo} · ${p.nombre}` }))} />
      </div>

      <Estado consulta={mallas}>
        {(lista) => lista.length === 0 ? <div className="tarjeta"><Vacio titulo="No hay mallas curriculares">Cree la primera malla de un programa para poder ofertarlo.</Vacio></div> : (
          <div className="grid gap-4 lg:grid-cols-2">
            {lista.map((malla) => (
              <Tarjeta key={malla.id_malla}
                titulo={<span>{malla.programa.nombre} <span className="font-normal text-tenue">· versión {malla.version}</span></span>}
                acciones={gestiona && (
                  <>
                    <Boton tamano="sm" variante="secundario" icono={Copy} onClick={() => (fVersion.reiniciar(), setVersionando(malla))}>Nueva versión</Boton>
                    <Boton tamano="sm" variante="secundario" icono={Pencil} onClick={() => abrir(malla)}>Editar</Boton>
                  </>
                )}>
                <div className="mb-3 flex flex-wrap items-center gap-2 text-xs text-tenue">
                  <Insignia estado={malla.estado} />
                  <span>{malla.horas_totales} horas</span>·<span>Vigente desde {fecha(malla.fecha_vigencia)}</span>·
                  <span>{malla.ofertas_count > 0 ? `Usada en ${malla.ofertas_count} oferta(s)` : 'Sin ofertas'}</span>
                </div>
                <VistaMalla malla={malla} />
              </Tarjeta>
            ))}
          </div>
        )}
      </Estado>

      <Modal abierto={Boolean(editando)} titulo={esNueva ? 'Nueva malla curricular' : `Editar malla ${editando?.version ?? ''}`} alCerrar={() => setEditando(null)} ancho="lg"
        pie={<><Boton variante="secundario" onClick={() => setEditando(null)}>Cancelar</Boton><Boton onClick={guardar} cargando={f.enviando}>Guardar malla</Boton></>}>
        <form onSubmit={guardar} noValidate className="space-y-4">
          {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
          {congelada && <Alerta tipo="alerta">Esta malla ya se usa en una oferta académica: puede cambiar su versión, vigencia y estado, pero no sus materias ni módulos. Para cambiarlos cree una nueva versión.</Alerta>}

          <div className="grid gap-4 sm:grid-cols-2">
            <Campo {...f.campo('id_programa')} tipo="select" etiqueta="Programa o carrera" requerido disabled={!esNueva} className="sm:col-span-2"
              opciones={(programas.datos ?? []).map((p) => ({ valor: p.id_programa, etiqueta: `${p.codigo} · ${p.nombre}` }))} />
            <Campo {...f.campo('version')} etiqueta="Versión" requerido ayuda="Ejemplo: 2026-1" />
            <Campo {...f.campo('fecha_vigencia')} tipo="date" etiqueta="Vigente desde" />
            <Campo {...f.campo('estado')} tipo="select" etiqueta="Estado" opciones={ESTADOS} vacio={null} requerido />
          </div>

          <fieldset disabled={congelada} className="space-y-3">
            <legend className="mb-2 text-sm font-bold">Plan de estudios · {horas} horas en total</legend>

            <div role="radiogroup" aria-label="Forma de la malla" className="grid gap-2 sm:grid-cols-2">
              {[
                ['simple', ListOrdered, 'Lista de materias', 'Cursos, talleres y programas técnicos'],
                ['carrera', Layers, 'Carrera por módulos', 'Cada módulo agrupa 1 o 2 materias'],
              ].map(([modo, Icono, titulo, detalle]) => (
                <button key={modo} type="button" role="radio" aria-checked={f.valores.modo === modo} className="opcion-leccion"
                  onClick={() => (f.cambiar('modo', modo), modo === 'carrera' && !f.valores.bloques.length && f.cambiar('bloques', [nuevoBloque()]))}>
                  <Icono className="size-5 shrink-0" aria-hidden />
                  <span><span className="block">{titulo}</span><span className="block text-xs font-normal text-tenue">{detalle}</span></span>
                </button>
              ))}
            </div>

            {!carrera && (
              <>
                {f.errores.modulos && <p className="text-xs font-medium text-peligro" role="alert">{f.errores.modulos}</p>}
                {!congelada && (
                  <div className="flex gap-2">
                    <Campo tipo="select" nombre="modulo" valor={moduloElegido} alCambiar={setModuloElegido} vacio="Seleccione una materia para agregar…" aria-label="Materia a agregar" className="flex-1"
                      opciones={opcionesMateria} />
                    <Boton variante="secundario" icono={Plus} disabled={!moduloElegido}
                      onClick={() => (f.cambiar('modulos', [...f.valores.modulos, { id_modulo: Number(moduloElegido), obligatorio: true }]), setModuloElegido(''))}>Agregar</Boton>
                  </div>
                )}

                <ol className="space-y-1.5">
                  {f.valores.modulos.map((m, i) => (
                    <li key={m.id_modulo} className="flex flex-wrap items-center gap-2 rounded-tema border px-3 py-2 text-sm">
                      <span className="font-bold text-tenue">{i + 1}.</span>
                      <span className="min-w-40 flex-1">{materia(m.id_modulo)?.nombre ?? `Materia ${m.id_modulo}`} <span className="text-xs text-tenue">({materia(m.id_modulo)?.horas ?? '?'} h)</span></span>
                      <label className="flex items-center gap-1.5 text-xs">
                        <input type="checkbox" className="size-4 accent-[var(--primario)]" checked={m.obligatorio}
                          onChange={(e) => f.cambiar('modulos', f.valores.modulos.map((x, j) => (j === i ? { ...x, obligatorio: e.target.checked } : x)))} />
                        Obligatorio
                      </label>
                      {!congelada && (
                        <Ordenar indice={i} total={f.valores.modulos.length} alMover={(salto) => mover('modulos', i, salto)}
                          alQuitar={() => f.cambiar('modulos', f.valores.modulos.filter((_, j) => j !== i))} etiqueta="materia" />
                      )}
                    </li>
                  ))}
                </ol>
              </>
            )}

            {carrera && (
              <>
                {f.errores.bloques && <p className="text-xs font-medium text-peligro" role="alert">{f.errores.bloques}</p>}
                <ol className="space-y-3">
                  {f.valores.bloques.map((b, i) => (
                    <li key={b.clave} className="rounded-tema border-2 p-3">
                      <div className="flex items-center gap-2">
                        <span className="grid size-8 shrink-0 place-items-center rounded-full bg-primario text-sm font-bold text-sobre-primario">{i + 1}</span>
                        <input value={b.nombre} onChange={(e) => cambiarBloque(i, { nombre: e.target.value })} placeholder={`Nombre del módulo ${i + 1} (ej. Fundamentos contables)`}
                          aria-label={`Nombre del módulo ${i + 1}`} maxLength={200} className="campo-control flex-1" />
                        {!congelada && (
                          <Ordenar indice={i} total={f.valores.bloques.length} alMover={(salto) => mover('bloques', i, salto)}
                            alQuitar={() => f.cambiar('bloques', f.valores.bloques.filter((_, j) => j !== i))} etiqueta="módulo" />
                        )}
                      </div>

                      <ul className="mt-2 space-y-1.5 pl-10">
                        {b.materias.map((id) => (
                          <li key={id} className="flex items-center gap-2 rounded-lg bg-suave px-3 py-1.5 text-sm">
                            <span className="flex-1">{materia(id)?.nombre ?? `Materia ${id}`} <span className="text-xs text-tenue">({materia(id)?.horas ?? '?'} h)</span></span>
                            {!congelada && (
                              <button type="button" onClick={() => cambiarBloque(i, { materias: b.materias.filter((x) => x !== id) })} aria-label="Quitar materia"
                                className="rounded p-1 text-peligro hover:bg-peligro-suave"><X className="size-4" /></button>
                            )}
                          </li>
                        ))}
                        {!congelada && b.materias.length < MAXIMO_MATERIAS && (
                          <li>
                            <Campo tipo="select" nombre={`materia-${b.clave}`} valor="" vacio={b.materias.length ? '+ Agregar una segunda materia (opcional)…' : '+ Agregar una materia…'}
                              aria-label={`Agregar materia al módulo ${i + 1}`} opciones={opcionesMateria}
                              alCambiar={(v) => v && cambiarBloque(i, { materias: [...b.materias, Number(v)] })} />
                          </li>
                        )}
                      </ul>
                    </li>
                  ))}
                </ol>
                {!congelada && <Boton variante="secundario" icono={Plus} onClick={() => f.cambiar('bloques', [...f.valores.bloques, nuevoBloque()])}>Agregar módulo</Boton>}
              </>
            )}
          </fieldset>
        </form>
      </Modal>

      <Modal abierto={Boolean(versionando)} titulo="Nueva versión de la malla" alCerrar={() => setVersionando(null)} ancho="sm"
        pie={<><Boton variante="secundario" onClick={() => setVersionando(null)}>Cancelar</Boton><Boton onClick={versionar} cargando={fVersion.enviando}>Crear versión</Boton></>}>
        <form onSubmit={versionar} noValidate className="space-y-4">
          {fVersion.errorGeneral && <Alerta tipo="peligro">{fVersion.errorGeneral}</Alerta>}
          <p className="text-sm">Se copiarán las {versionando?.malla_modulos.length} materias (y sus módulos) de la versión <strong>{versionando?.version}</strong> de {versionando?.programa.nombre}. La versión actual no cambia.</p>
          <Campo {...fVersion.campo('version')} etiqueta="Nombre de la nueva versión" requerido ayuda="Ejemplo: 2026-2" />
          <Campo {...fVersion.campo('fecha_vigencia')} tipo="date" etiqueta="Vigente desde" />
        </form>
      </Modal>
    </>
  )
}

/** Plan de estudios de una malla guardada: por módulos de carrera o como lista simple. */
function VistaMalla({ malla }) {
  const bloques = bloquesDe(malla)

  if (bloques.length === 0) {
    return (
      <ol className="space-y-1 text-sm">
        {malla.malla_modulos.map((m) => (
          <li key={m.id_malla_modulo} className="flex items-center justify-between gap-2 rounded bg-suave px-3 py-1.5">
            <span><span className="mr-2 font-bold text-tenue">{m.orden}.</span>{m.modulo.nombre}</span>
            <span className="shrink-0 text-xs text-tenue">{m.modulo.horas} h · {m.obligatorio ? 'Obligatorio' : 'Optativo'}</span>
          </li>
        ))}
      </ol>
    )
  }

  return (
    <ol className="space-y-2.5 text-sm">
      {bloques.map((b) => (
        <li key={b.clave} className="rounded-tema border p-2.5">
          <p className="flex items-center justify-between gap-2 font-bold">
            <span><span className="mr-2 text-acento">Módulo {b.orden}</span>{b.nombre}</span>
            <span className="text-xs font-normal text-tenue">{b.materias.reduce((s, m) => s + m.modulo.horas, 0)} h</span>
          </p>
          <ul className="mt-1.5 space-y-1">
            {b.materias.map((m) => (
              <li key={m.id_malla_modulo} className="flex justify-between gap-2 rounded bg-suave px-3 py-1">
                <span>{m.modulo.nombre}</span><span className="text-xs text-tenue">{m.modulo.horas} h</span>
              </li>
            ))}
          </ul>
        </li>
      ))}
    </ol>
  )
}

/** Botones subir / bajar / quitar de un elemento de lista. */
function Ordenar({ indice, total, alMover, alQuitar, etiqueta }) {
  return (
    <span className="flex gap-0.5">
      <button type="button" onClick={() => alMover(-1)} disabled={indice === 0} aria-label={`Subir ${etiqueta}`} className="rounded p-1 hover:bg-suave disabled:opacity-30"><ArrowUp className="size-4" /></button>
      <button type="button" onClick={() => alMover(1)} disabled={indice === total - 1} aria-label={`Bajar ${etiqueta}`} className="rounded p-1 hover:bg-suave disabled:opacity-30"><ArrowDown className="size-4" /></button>
      <button type="button" onClick={alQuitar} aria-label={`Quitar ${etiqueta}`} className="rounded p-1 text-peligro hover:bg-peligro-suave"><X className="size-4" /></button>
    </span>
  )
}
