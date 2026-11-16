import { ArrowDown, ArrowUp, Copy, Pencil, Plus, X } from 'lucide-react'
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
const inicial = { id_programa: '', version: '', fecha_vigencia: '', estado: 'ACTIVO', modulos: [] }

/**
 * CU2 · Mallas curriculares y versionado.
 * Una malla usada en una oferta queda congelada: para cambiarla se crea una
 * nueva versión (botón «Nueva versión»), que copia sus módulos.
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
    modulos: [(v) => (v.length ? null : 'Agregue al menos un módulo a la malla.')],
  })
  const fVersion = useFormulario({ version: '', fecha_vigencia: '' }, { version: [requerido(), maximo(30)] })

  const esNueva = editando && !editando.id_malla
  const congelada = editando?.ofertas_count > 0
  const horas = f.valores.modulos.reduce((suma, m) => suma + (modulos.datos?.find((x) => x.id_modulo === m.id_modulo)?.horas ?? 0), 0)

  const abrir = (malla) => {
    f.reiniciar(malla
      ? { id_programa: malla.id_programa, version: malla.version, fecha_vigencia: malla.fecha_vigencia ?? '', estado: malla.estado,
          modulos: malla.malla_modulos.map((m) => ({ id_modulo: m.id_modulo, obligatorio: m.obligatorio })) }
      : { ...inicial, id_programa: idPrograma })
    setModuloElegido('')
    setEditando(malla ?? {})
  }

  const mover = (indice, salto) => {
    const lista = [...f.valores.modulos]
    const destino = indice + salto
    if (destino < 0 || destino >= lista.length) return
    ;[lista[indice], lista[destino]] = [lista[destino], lista[indice]]
    f.cambiar('modulos', lista)
  }

  const guardar = f.enviar(async (valores) => {
    const cuerpo = {
      ...valores,
      id_programa: Number(valores.id_programa),
      fecha_vigencia: valores.fecha_vigencia || null,
      modulos: valores.modulos.map((m, i) => ({ ...m, orden: i + 1 })),
    }

    if (esNueva) await api.post('/mallas', cuerpo)
    else await api.put(`/mallas/${editando.id_malla}`, cuerpo)

    avisar.exito(esNueva ? 'Malla curricular creada.' : 'Malla curricular actualizada.')
    setEditando(null)
    mallas.recargar()
  })

  const versionar = fVersion.enviar(async (valores) => {
    await api.post(`/mallas/${versionando.id_malla}/versionar`, { ...valores, fecha_vigencia: valores.fecha_vigencia || null })
    avisar.exito(`Se creó la versión ${valores.version} con los mismos módulos. Ya puede modificarla.`)
    setVersionando(null)
    mallas.recargar()
  })

  const disponibles = (modulos.datos ?? []).filter((m) => !f.valores.modulos.some((x) => x.id_modulo === m.id_modulo))

  return (
    <>
      <EncabezadoPagina titulo="Mallas curriculares" descripcion="Cada malla es una versión del plan de estudios de un programa: sus módulos, el orden y cuáles son obligatorios.">
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
                <ol className="space-y-1 text-sm">
                  {malla.malla_modulos.map((m) => (
                    <li key={m.id_malla_modulo} className="flex items-center justify-between gap-2 rounded bg-suave px-3 py-1.5">
                      <span><span className="mr-2 font-bold text-tenue">{m.orden}.</span>{m.modulo.nombre}</span>
                      <span className="shrink-0 text-xs text-tenue">{m.modulo.horas} h · {m.obligatorio ? 'Obligatorio' : 'Optativo'}</span>
                    </li>
                  ))}
                </ol>
              </Tarjeta>
            ))}
          </div>
        )}
      </Estado>

      <Modal abierto={Boolean(editando)} titulo={esNueva ? 'Nueva malla curricular' : `Editar malla ${editando?.version ?? ''}`} alCerrar={() => setEditando(null)} ancho="lg"
        pie={<><Boton variante="secundario" onClick={() => setEditando(null)}>Cancelar</Boton><Boton onClick={guardar} cargando={f.enviando}>Guardar malla</Boton></>}>
        <form onSubmit={guardar} noValidate className="space-y-4">
          {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
          {congelada && <Alerta tipo="alerta">Esta malla ya se usa en una oferta académica: puede cambiar su versión, vigencia y estado, pero no sus módulos. Para cambiar módulos cree una nueva versión.</Alerta>}

          <div className="grid gap-4 sm:grid-cols-2">
            <Campo {...f.campo('id_programa')} tipo="select" etiqueta="Programa" requerido disabled={!esNueva} className="sm:col-span-2"
              opciones={(programas.datos ?? []).map((p) => ({ valor: p.id_programa, etiqueta: `${p.codigo} · ${p.nombre}` }))} />
            <Campo {...f.campo('version')} etiqueta="Versión" requerido ayuda="Ejemplo: 2026-1" />
            <Campo {...f.campo('fecha_vigencia')} tipo="date" etiqueta="Vigente desde" />
            <Campo {...f.campo('estado')} tipo="select" etiqueta="Estado" opciones={ESTADOS} vacio={null} requerido />
          </div>

          <fieldset disabled={congelada}>
            <legend className="mb-2 text-sm font-bold">Módulos de la malla · {horas} horas en total</legend>
            {f.errores.modulos && <p className="mb-2 text-xs font-medium text-peligro" role="alert">{f.errores.modulos}</p>}

            {!congelada && (
              <div className="mb-3 flex gap-2">
                <Campo tipo="select" nombre="modulo" valor={moduloElegido} alCambiar={setModuloElegido} vacio="Seleccione un módulo para agregar…" aria-label="Módulo a agregar" className="flex-1"
                  opciones={disponibles.map((m) => ({ valor: m.id_modulo, etiqueta: `${m.codigo} · ${m.nombre} (${m.horas} h)` }))} />
                <Boton variante="secundario" icono={Plus} disabled={!moduloElegido}
                  onClick={() => (f.cambiar('modulos', [...f.valores.modulos, { id_modulo: Number(moduloElegido), obligatorio: true }]), setModuloElegido(''))}>Agregar</Boton>
              </div>
            )}

            <ol className="space-y-1.5">
              {f.valores.modulos.map((m, i) => {
                const modulo = modulos.datos?.find((x) => x.id_modulo === m.id_modulo)

                return (
                  <li key={m.id_modulo} className="flex flex-wrap items-center gap-2 rounded-tema border px-3 py-2 text-sm">
                    <span className="font-bold text-tenue">{i + 1}.</span>
                    <span className="min-w-40 flex-1">{modulo?.nombre ?? `Módulo ${m.id_modulo}`} <span className="text-xs text-tenue">({modulo?.horas ?? '?'} h)</span></span>
                    <label className="flex items-center gap-1.5 text-xs">
                      <input type="checkbox" className="size-4 accent-[var(--primario)]" checked={m.obligatorio}
                        onChange={(e) => f.cambiar('modulos', f.valores.modulos.map((x, j) => (j === i ? { ...x, obligatorio: e.target.checked } : x)))} />
                      Obligatorio
                    </label>
                    {!congelada && (
                      <span className="flex gap-0.5">
                        <button type="button" onClick={() => mover(i, -1)} disabled={i === 0} aria-label="Subir" className="rounded p-1 hover:bg-suave disabled:opacity-30"><ArrowUp className="size-4" /></button>
                        <button type="button" onClick={() => mover(i, 1)} disabled={i === f.valores.modulos.length - 1} aria-label="Bajar" className="rounded p-1 hover:bg-suave disabled:opacity-30"><ArrowDown className="size-4" /></button>
                        <button type="button" onClick={() => f.cambiar('modulos', f.valores.modulos.filter((_, j) => j !== i))} aria-label="Quitar módulo" className="rounded p-1 text-peligro hover:bg-peligro-suave"><X className="size-4" /></button>
                      </span>
                    )}
                  </li>
                )
              })}
            </ol>
          </fieldset>
        </form>
      </Modal>

      <Modal abierto={Boolean(versionando)} titulo="Nueva versión de la malla" alCerrar={() => setVersionando(null)} ancho="sm"
        pie={<><Boton variante="secundario" onClick={() => setVersionando(null)}>Cancelar</Boton><Boton onClick={versionar} cargando={fVersion.enviando}>Crear versión</Boton></>}>
        <form onSubmit={versionar} noValidate className="space-y-4">
          {fVersion.errorGeneral && <Alerta tipo="peligro">{fVersion.errorGeneral}</Alerta>}
          <p className="text-sm">Se copiarán los {versionando?.malla_modulos.length} módulos de la versión <strong>{versionando?.version}</strong> de {versionando?.programa.nombre}. La versión actual no cambia.</p>
          <Campo {...fVersion.campo('version')} etiqueta="Nombre de la nueva versión" requerido ayuda="Ejemplo: 2026-2" />
          <Campo {...fVersion.campo('fecha_vigencia')} tipo="date" etiqueta="Vigente desde" />
        </form>
      </Modal>
    </>
  )
}
