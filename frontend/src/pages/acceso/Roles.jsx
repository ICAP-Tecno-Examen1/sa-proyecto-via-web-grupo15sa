import { Plus, RotateCcw, Save, Trash2 } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import api from '../../api/cliente'
import { Alerta, Cargando, EncabezadoPagina, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal, { Confirmar } from '../../components/ui/Modal'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { maximo, minimo, requerido } from '../../utils/validacion'

/**
 * CU1 · Roles y MATRIZ DE ACCESO (requisitos 2 y 4).
 * A la izquierda los roles; a la derecha los permisos del rol elegido,
 * agrupados por módulo. Lo que se marque aquí define el menú y lo que la API
 * le permite hacer a cada rol.
 */
export default function Roles() {
  const auth = useAuth()
  const avisar = useAvisos()
  const gestiona = auth.puede('roles.gestionar')
  const roles = useConsulta('/roles')
  const permisos = useConsulta('/permisos')

  const [idRol, setIdRol] = useState(null)
  const [marcados, setMarcados] = useState(new Set())
  const [guardando, setGuardando] = useState(false)
  const [creando, setCreando] = useState(false)
  const [eliminando, setEliminando] = useState(false)

  const rol = roles.datos?.find((r) => r.id_rol === idRol) ?? null
  const reglas = { nombre: [requerido(), minimo(3), maximo(100)], descripcion: [maximo(300)] }
  const fNuevo = useFormulario({ nombre: '', descripcion: '' }, reglas)

  useEffect(() => {
    if (roles.datos && !rol) setIdRol(roles.datos[0]?.id_rol ?? null)
  }, [roles.datos, rol])

  useEffect(() => {
    setMarcados(new Set(rol?.permisos ?? []))
  }, [rol])

  const hayCambios = useMemo(() => {
    const originales = new Set(rol?.permisos ?? [])

    return originales.size !== marcados.size || [...marcados].some((p) => !originales.has(p))
  }, [rol, marcados])

  const alternar = (ids, marcar) => setMarcados((previos) => {
    const nuevos = new Set(previos)
    ids.forEach((id) => (marcar ? nuevos.add(id) : nuevos.delete(id)))

    return nuevos
  })

  // Rol base: vuelve a los permisos de la matriz inicial (deshace lo marcado aquí después)
  const restablecer = async () => {
    setGuardando(true)

    try {
      const { data } = await api.post(`/roles/${rol.id_rol}/restablecer`)
      avisar.exito(data.message)
      await roles.recargar()
      if (rol.id_rol === auth.rolActivo?.id_rol) await auth.refrescar()
    } catch (e) {
      avisar.error(e)
    } finally {
      setGuardando(false)
    }
  }

  const guardar = async () => {
    setGuardando(true)

    try {
      await api.put(`/roles/${rol.id_rol}`, { nombre: rol.nombre, descripcion: rol.descripcion, permisos: [...marcados] })
      avisar.exito(`Permisos del rol ${rol.nombre} actualizados.`)
      await roles.recargar()
      if (rol.id_rol === auth.rolActivo?.id_rol) await auth.refrescar()
    } catch (e) {
      avisar.error(e)
    } finally {
      setGuardando(false)
    }
  }

  const crear = fNuevo.enviar(async (valores) => {
    const { data } = await api.post('/roles', { ...valores, permisos: [] })
    avisar.exito('Rol creado. Ahora asigne sus permisos.')
    setCreando(false)
    await roles.recargar()
    setIdRol(data.id_rol)
  })

  const eliminar = async () => {
    try {
      await api.delete(`/roles/${rol.id_rol}`)
      avisar.exito('Rol eliminado.')
      setIdRol(null)
      roles.recargar()
    } catch (e) {
      avisar.error(e)
    } finally {
      setEliminando(false)
    }
  }

  if (!roles.datos || !permisos.datos) return roles.error || permisos.error ? <Alerta tipo="peligro">{roles.error || permisos.error}</Alerta> : <Cargando />

  return (
    <>
      <EncabezadoPagina titulo="Roles y permisos" descripcion="Matriz de control de acceso: cada rol solo ve en su menú y solo puede ejecutar lo que aquí se le concede.">
        {gestiona && <Boton icono={Plus} onClick={() => (fNuevo.reiniciar(), setCreando(true))}>Nuevo rol</Boton>}
      </EncabezadoPagina>

      <div className="grid gap-5 lg:grid-cols-[18rem_1fr]">
        <Tarjeta titulo="Roles" relleno={false}>
          <ul>
            {roles.datos.map((r) => (
              <li key={r.id_rol}>
                <button onClick={() => setIdRol(r.id_rol)} aria-current={r.id_rol === idRol}
                  className={`block w-full border-b px-4 py-3 text-left last:border-b-0 ${r.id_rol === idRol ? 'bg-primario-suave' : 'hover:bg-suave'}`}>
                  <span className="flex items-center justify-between gap-2">
                    <span className="font-bold">{r.nombre}</span>
                    {!r.es_base && <Insignia tono="alerta">Personalizado</Insignia>}
                  </span>
                  <span className="text-xs text-tenue">{r.permisos_count} permisos · {r.usuarios_count} usuario(s)</span>
                </button>
              </li>
            ))}
          </ul>
        </Tarjeta>

        {rol && (
          <Tarjeta
            titulo={`Permisos de ${rol.nombre}`}
            acciones={gestiona && (
              <>
                {!rol.es_base && <Boton variante="fantasma" icono={Trash2} className="text-peligro" onClick={() => setEliminando(true)}>Eliminar rol</Boton>}
                {rol.es_base && <Boton variante="secundario" icono={RotateCcw} onClick={restablecer} cargando={guardando} title="Vuelve a los permisos de la matriz de acceso inicial">Restablecer originales</Boton>}
                <Boton icono={Save} onClick={guardar} cargando={guardando} disabled={!hayCambios}>Guardar permisos</Boton>
              </>
            )}
          >
            {rol.descripcion && <p className="mb-4 text-sm text-tenue">{rol.descripcion}</p>}
            {!gestiona && <Alerta className="mb-4">Solo consulta: su rol no puede modificar la matriz de permisos.</Alerta>}

            <div className="grid gap-4 xl:grid-cols-2">
              {permisos.datos.map((grupo) => {
                const ids = grupo.permisos.map((p) => p.id_permiso)
                const todos = ids.every((id) => marcados.has(id))

                return (
                  <fieldset key={grupo.modulo} className="rounded-tema border">
                    <legend className="ml-3 flex items-center gap-2 px-2 text-sm font-bold">
                      {grupo.modulo}
                      <span className="text-xs font-normal text-tenue">({ids.filter((id) => marcados.has(id)).length}/{ids.length})</span>
                    </legend>
                    {gestiona && (
                      <button type="button" onClick={() => alternar(ids, !todos)} className="mx-3 mt-1 text-xs font-semibold text-primario hover:underline">
                        {todos ? 'Quitar todos' : 'Marcar todos'}
                      </button>
                    )}
                    <ul className="space-y-1 p-3">
                      {grupo.permisos.map((p) => (
                        <li key={p.id_permiso}>
                          <label className="flex cursor-pointer items-start gap-2 rounded px-1 py-1 hover:bg-suave">
                            <input type="checkbox" className="mt-0.5 size-4 accent-[var(--primario)]" disabled={!gestiona}
                              checked={marcados.has(p.id_permiso)} onChange={(e) => alternar([p.id_permiso], e.target.checked)} />
                            <span className="text-sm">
                              {p.descripcion}
                              <code className="block text-xs text-tenue">{p.accion}</code>
                            </span>
                          </label>
                        </li>
                      ))}
                    </ul>
                  </fieldset>
                )
              })}
            </div>
          </Tarjeta>
        )}
      </div>

      <Modal abierto={creando} titulo="Nuevo rol" alCerrar={() => setCreando(false)} ancho="sm"
        pie={<><Boton variante="secundario" onClick={() => setCreando(false)}>Cancelar</Boton><Boton onClick={crear} cargando={fNuevo.enviando}>Crear rol</Boton></>}>
        <form onSubmit={crear} noValidate className="space-y-4">
          {fNuevo.errorGeneral && <Alerta tipo="peligro">{fNuevo.errorGeneral}</Alerta>}
          <Campo {...fNuevo.campo('nombre')} etiqueta="Nombre del rol" requerido />
          <Campo {...fNuevo.campo('descripcion')} etiqueta="Descripción" tipo="textarea" />
        </form>
      </Modal>

      <Confirmar abierto={eliminando} titulo="Eliminar rol" mensaje={`¿Eliminar el rol «${rol?.nombre}»? Solo es posible si no tiene usuarios asignados.`} textoConfirmar="Eliminar" alConfirmar={eliminar} alCerrar={() => setEliminando(false)} />
    </>
  )
}
