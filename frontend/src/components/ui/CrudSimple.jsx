import { Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import api from '../../api/cliente'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { Alerta, EncabezadoPagina, Tarjeta } from './Basicos'
import Boton from './Boton'
import Campo from './Campo'
import Modal, { Confirmar } from './Modal'
import Tabla, { Paginacion } from './Tabla'

/**
 * Pantalla de mantenimiento reutilizable: listado con búsqueda y filtros,
 * formulario de alta/edición en una ventana y eliminación con confirmación.
 * La usan los catálogos sencillos (programas, módulos, becas, aranceles…).
 *
 *   recurso          '/programas'  (GET lista, POST crear, PUT /{id}, DELETE /{id})
 *   id               nombre de la clave primaria
 *   columnas         columnas de <Tabla>
 *   campos           [{ nombre, etiqueta, tipo, opciones, requerido, ayuda, ancho: 1|2, visible?: (valores) => bool }]
 *   reglas           reglas de validación del cliente (pueden ser función de "editando")
 *   inicial          valores de un registro nuevo
 *   permisoGestion   permiso para crear / editar / eliminar
 *   filtros          [{ nombre, etiqueta, opciones }]  filtros tipo lista
 *   paginado         la API responde paginado (data, current_page…)
 *   aFormulario      (fila) => valores del formulario al editar
 *   aEnvio           (valores) => cuerpo que se envía
 *   accionesFila     (fila, recargar) => nodos extra en la columna de acciones
 */
export default function CrudSimple({
  titulo, descripcion, recurso, id, columnas, campos, reglas = {}, inicial, permisoGestion, filtros = [], paginado = false,
  singular = 'registro', buscar = true, puedeEliminar = true, aFormulario = (f) => f, aEnvio = (v) => v, accionesFila, nota, anchoModal = 'md',
  parametros = {}, recursoCrear,
}) {
  const auth = useAuth()
  const avisar = useAvisos()
  const gestiona = auth.puede(permisoGestion)

  const [parametrosUrl] = useSearchParams()
  const [texto, setTexto] = useState(parametrosUrl.get('q') ?? '')
  const [valoresFiltro, setValoresFiltro] = useState({})
  const [numeroPagina, setNumeroPagina] = useState(1)
  const q = useRetardo(texto)
  const consulta = useConsulta(recurso, { ...parametros, ...valoresFiltro, q: buscar ? q : null, page: paginado ? numeroPagina : null })

  const [editando, setEditando] = useState(null) // null = cerrado · {} = nuevo · fila = edición
  const [eliminando, setEliminando] = useState(null)
  const [borrando, setBorrando] = useState(false)
  const esNuevo = editando && !editando[id]
  const f = useFormulario(inicial, typeof reglas === 'function' ? reglas(!esNuevo) : reglas)

  const filas = paginado ? consulta.datos?.data : consulta.datos

  const abrir = (fila) => {
    f.reiniciar(fila ? { ...inicial, ...aFormulario(fila) } : inicial)
    setEditando(fila ?? {})
  }

  const guardar = f.enviar(async (valores) => {
    const cuerpo = aEnvio(valores)

    if (esNuevo) await api.post(recursoCrear ?? recurso, cuerpo)
    else await api.put(`${recurso}/${editando[id]}`, cuerpo)

    avisar.exito(esNuevo ? `Se registró el ${singular}.` : `Se actualizó el ${singular}.`)
    setEditando(null)
    consulta.recargar()
  })

  const eliminar = async () => {
    setBorrando(true)

    try {
      const { data } = await api.delete(`${recurso}/${eliminando[id]}`)
      avisar.exito(data.message ?? `Se eliminó el ${singular}.`)
      consulta.recargar()
    } catch (e) {
      avisar.error(e)
    } finally {
      setBorrando(false)
      setEliminando(null)
    }
  }

  const columnasConAcciones = gestiona || accionesFila
    ? [...columnas, {
        clave: '_acciones', titulo: 'Acciones', alinear: 'derecha',
        render: (fila) => (
          <div className="flex justify-end gap-1">
            {accionesFila?.(fila, consulta.recargar)}
            {gestiona && <Boton tamano="sm" variante="secundario" icono={Pencil} onClick={() => abrir(fila)} aria-label={`Editar ${singular}`}>Editar</Boton>}
            {gestiona && puedeEliminar && <Boton tamano="sm" variante="fantasma" icono={Trash2} onClick={() => setEliminando(fila)} aria-label={`Eliminar ${singular}`} className="text-peligro" />}
          </div>
        ),
      }]
    : columnas

  return (
    <>
      <EncabezadoPagina titulo={titulo} descripcion={descripcion}>
        {gestiona && <Boton icono={Plus} onClick={() => abrir(null)}>Nuevo {singular}</Boton>}
      </EncabezadoPagina>

      {nota}

      <Tarjeta relleno={false}>
        {(buscar || filtros.length > 0) && (
          <div className="flex flex-wrap items-end gap-3 border-b p-3">
            {buscar && (
              <div className="relative min-w-56 flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
                <input type="search" value={texto} onChange={(e) => (setTexto(e.target.value), setNumeroPagina(1))} placeholder="Buscar…" aria-label={`Buscar en ${titulo}`} className="campo-control pl-9" />
              </div>
            )}
            {filtros.map((filtro) => (
              <Campo key={filtro.nombre} tipo="select" nombre={filtro.nombre} opciones={filtro.opciones} vacio={filtro.etiqueta} aria-label={filtro.etiqueta}
                valor={valoresFiltro[filtro.nombre] ?? ''} alCambiar={(v) => (setValoresFiltro((p) => ({ ...p, [filtro.nombre]: v })), setNumeroPagina(1))} className="w-52" />
            ))}
          </div>
        )}

        {consulta.error ? <div className="p-4"><Alerta tipo="peligro">{consulta.error}</Alerta></div>
          : <Tabla columnas={columnasConAcciones} filas={filas} id={id} cargando={consulta.cargando} />}
        {paginado && <Paginacion pagina={consulta.datos} alCambiar={setNumeroPagina} />}
      </Tarjeta>

      <Modal
        abierto={Boolean(editando)}
        titulo={`${esNuevo ? 'Nuevo' : 'Editar'} ${singular}`}
        alCerrar={() => setEditando(null)}
        ancho={anchoModal}
        pie={
          <>
            <Boton variante="secundario" onClick={() => setEditando(null)}>Cancelar</Boton>
            <Boton onClick={guardar} cargando={f.enviando}>Guardar</Boton>
          </>
        }
      >
        <form onSubmit={guardar} noValidate className="grid gap-4 sm:grid-cols-2">
          {f.errorGeneral && <Alerta tipo="peligro" className="sm:col-span-2">{f.errorGeneral}</Alerta>}
          {campos
            .filter((c) => (c.visible ? c.visible(f.valores, !esNuevo) : true))
            .map(({ ancho = 1, visible, soloNuevo, ...c }) => (
              <Campo key={c.nombre} {...c} {...f.campo(c.nombre)} disabled={soloNuevo && !esNuevo} className={ancho === 2 ? 'sm:col-span-2' : ''} />
            ))}
          <button type="submit" hidden />
        </form>
      </Modal>

      <Confirmar
        abierto={Boolean(eliminando)}
        titulo={`Eliminar ${singular}`}
        mensaje={`¿Está seguro de eliminar este ${singular}? Esta acción no se puede deshacer.`}
        textoConfirmar="Eliminar"
        alConfirmar={eliminar}
        alCerrar={() => setEliminando(null)}
        cargando={borrando}
      />
    </>
  )
}
