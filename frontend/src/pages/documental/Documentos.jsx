import { Archive, ArchiveRestore, Download, Eye, Pencil, Plus, Search } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import api, { abrirArchivo, aFormData } from '../../api/cliente'
import { Alerta, EncabezadoPagina, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal from '../../components/ui/Modal'
import Tabla, { Paginacion } from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fecha, hoy, legible, tamanoArchivo } from '../../utils/formato'
import { archivo, entero, entre, fechaNoFutura, maximo, minimo, requerido } from '../../utils/validacion'

const inicial = () => ({ nombre: '', tipo: '', categoria: '', gestion: new Date().getFullYear(), fecha_documento: hoy(), descripcion: '', archivo: null, id_titular: '' })

/**
 * CU7 · Repositorio documental histórico.
 * Cada rol ve solo lo que le corresponde (lo decide la API): el Director y la
 * Secretaria todo; cada coordinador su categoría; docentes y estudiantes, sus
 * propios documentos.
 */
export default function Documentos() {
  const auth = useAuth()
  const avisar = useAvisos()
  const [parametros] = useSearchParams()
  const registra = auth.puede('documentos.registrar')
  const gestiona = auth.puede('documentos.gestionar')

  const [filtros, setFiltros] = useState({ q: parametros.get('q') ?? '', tipo: '', categoria: '', gestion: '', estado: '' })
  const [pagina, setPagina] = useState(1)
  const consulta = useConsulta('/documentos', { ...filtros, q: useRetardo(filtros.q), page: pagina })
  const cambiar = (campo) => (valor) => (setFiltros((f) => ({ ...f, [campo]: valor })), setPagina(1))
  const opciones = consulta.datos?.opciones
  const tipos = Object.entries(opciones?.tipos ?? {}).map(([valor, etiqueta]) => ({ valor, etiqueta }))
  const categoria = (lista = []) => lista.map((c) => ({ valor: c, etiqueta: legible(c) }))

  const [editando, setEditando] = useState(null) // null · {} nuevo · documento
  const esNuevo = editando && !editando.id_documento
  const estudiantes = useConsulta(editando && esNuevo && registra ? '/personas/estudiantes' : null)

  const f = useFormulario(inicial(), {
    nombre: [requerido(), minimo(3), maximo(200)],
    tipo: [requerido()],
    categoria: [requerido()],
    gestion: [requerido(), entero(), entre(1990, new Date().getFullYear() + 1)],
    fecha_documento: [fechaNoFutura()],
    descripcion: [maximo(1000)],
    archivo: [(v) => (esNuevo && !v ? 'Seleccione el archivo del documento.' : null), archivo()],
  })

  const puedeEditar = (d) => gestiona || d.id_usuario_registro === auth.usuario.id_usuario

  const abrir = (d) => {
    f.reiniciar(d
      ? { nombre: d.nombre, tipo: d.tipo, categoria: d.categoria, gestion: d.gestion ?? '', fecha_documento: d.fecha_documento ?? '', descripcion: d.descripcion ?? '', archivo: null, id_titular: '' }
      : { ...inicial(), categoria: opciones?.categorias_registro.length === 1 ? opciones.categorias_registro[0] : '' })
    setEditando(d ?? {})
  }

  const guardar = f.enviar(async ({ id_titular, ...valores }) => {
    const cuerpo = aFormData({ ...valores, titulares: esNuevo && id_titular ? [id_titular] : [] })

    if (esNuevo) await api.post('/documentos', cuerpo)
    else await api.post(`/documentos/${editando.id_documento}`, cuerpo)

    avisar.exito(esNuevo ? 'Documento registrado en el repositorio.' : 'Documento actualizado.')
    setEditando(null)
    consulta.recargar()
  })

  const archivar = async (d) => {
    try {
      await api.patch(`/documentos/${d.id_documento}/archivar`)
      avisar.exito(d.estado === 'VIGENTE' ? 'Documento enviado al archivo histórico.' : 'Documento restituido como vigente.')
      consulta.recargar()
    } catch (e) {
      avisar.error(e)
    }
  }

  const abrirArchivoDe = (d, descargar) =>
    abrirArchivo(`/documentos/${d.id_documento}/descargar`, { params: descargar ? {} : { ver: 1 }, descargar, nombre: d.nombre_archivo }).catch(avisar.error)

  return (
    <>
      <EncabezadoPagina titulo="Repositorio documental" descripcion="Documentos digitales del ICAP clasificados por tipo, categoría y gestión. La información de gestiones anteriores se conserva y puede consultarse en cualquier momento.">
        {registra && <Boton icono={Plus} onClick={() => abrir(null)}>Registrar documento</Boton>}
      </EncabezadoPagina>

      {opciones && opciones.categorias_consulta.length === 0 && (
        <Alerta className="mb-4">Su rol accede únicamente a los documentos que usted registró o que forman parte de su expediente.</Alerta>
      )}

      <Tarjeta relleno={false}>
        <div className="flex flex-wrap items-end gap-3 border-b p-3">
          <div className="relative min-w-56 flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" value={filtros.q} onChange={(e) => cambiar('q')(e.target.value)} placeholder="Buscar por nombre, descripción o titular…" aria-label="Buscar documento" className="campo-control pl-9" />
          </div>
          <Campo tipo="select" nombre="tipo" valor={filtros.tipo} alCambiar={cambiar('tipo')} opciones={tipos} vacio="Todos los tipos" aria-label="Tipo" className="w-52" />
          <Campo tipo="select" nombre="categoria" valor={filtros.categoria} alCambiar={cambiar('categoria')} opciones={categoria(['PERSONAL', 'ACADEMICO', 'FINANCIERO', 'INSTITUCIONAL'])} vacio="Todas las categorías" aria-label="Categoría" className="w-48" />
          <Campo tipo="select" nombre="gestion" valor={filtros.gestion} alCambiar={cambiar('gestion')} opciones={(opciones?.gestiones ?? []).map(String)} vacio="Todas las gestiones" aria-label="Gestión" className="w-44" />
          <Campo tipo="select" nombre="estado" valor={filtros.estado} alCambiar={cambiar('estado')} vacio="Vigentes y archivados" aria-label="Estado" className="w-48"
            opciones={[{ valor: 'VIGENTE', etiqueta: 'Vigentes' }, { valor: 'ARCHIVADO', etiqueta: 'Archivo histórico' }]} />
        </div>

        {consulta.error ? <div className="p-4"><Alerta tipo="peligro">{consulta.error}</Alerta></div> : (
          <Tabla
            id="id_documento"
            filas={consulta.datos?.data}
            cargando={consulta.cargando}
            vacio="No se encontraron documentos con estos filtros."
            columnas={[
              { clave: 'nombre', titulo: 'Documento', render: (d) => (<><span className="font-semibold">{d.nombre}</span><span className="block max-w-sm truncate text-xs text-tenue">{opciones?.tipos[d.tipo] ?? d.tipo} · {tamanoArchivo(d.tamano_bytes)}{d.descripcion ? ` · ${d.descripcion}` : ''}</span></>) },
              { clave: 'categoria', titulo: 'Categoría', render: (d) => legible(d.categoria) },
              { clave: 'gestion', titulo: 'Gestión', alinear: 'centro' },
              { clave: 'fecha_documento', titulo: 'Fecha', render: (d) => fecha(d.fecha_documento) },
              { clave: 'titulares', titulo: 'Titular (expediente)', render: (d) => d.titulares.map((t) => `${t.nombres} ${t.apellidos}`).join(', ') || '—' },
              { clave: 'registrador', titulo: 'Registró', render: (d) => (d.registrador ? `${d.registrador.nombres} ${d.registrador.apellidos}` : 'Sistema') },
              { clave: 'estado', titulo: 'Estado', render: (d) => <Insignia estado={d.estado} /> },
              { clave: 'acciones', titulo: 'Acciones', alinear: 'derecha', render: (d) => (
                <div className="flex justify-end gap-1">
                  {d.tiene_archivo && <Boton tamano="sm" variante="secundario" icono={Eye} onClick={() => abrirArchivoDe(d, false)}>Ver</Boton>}
                  {d.tiene_archivo && <Boton tamano="sm" variante="fantasma" icono={Download} aria-label="Descargar" onClick={() => abrirArchivoDe(d, true)} />}
                  {puedeEditar(d) && <Boton tamano="sm" variante="fantasma" icono={Pencil} aria-label="Editar" onClick={() => abrir(d)} />}
                  {puedeEditar(d) && <Boton tamano="sm" variante="fantasma" icono={d.estado === 'VIGENTE' ? Archive : ArchiveRestore} aria-label={d.estado === 'VIGENTE' ? 'Archivar' : 'Restituir'} title={d.estado === 'VIGENTE' ? 'Enviar al archivo histórico' : 'Restituir como vigente'} onClick={() => archivar(d)} />}
                </div>
              ) },
            ]}
          />
        )}
        <Paginacion pagina={consulta.datos} alCambiar={setPagina} />
      </Tarjeta>

      <Modal abierto={Boolean(editando)} titulo={esNuevo ? 'Registrar documento' : 'Editar documento'} alCerrar={() => setEditando(null)}
        pie={<><Boton variante="secundario" onClick={() => setEditando(null)}>Cancelar</Boton><Boton onClick={guardar} cargando={f.enviando}>Guardar</Boton></>}>
        <form onSubmit={guardar} noValidate className="grid gap-4 sm:grid-cols-2">
          {f.errorGeneral && <Alerta tipo="peligro" className="sm:col-span-2">{f.errorGeneral}</Alerta>}
          <Campo {...f.campo('nombre')} etiqueta="Nombre del documento" requerido className="sm:col-span-2" />
          <Campo {...f.campo('tipo')} tipo="select" etiqueta="Tipo" opciones={tipos} requerido />
          <Campo {...f.campo('categoria')} tipo="select" etiqueta="Categoría" requerido opciones={categoria(opciones?.categorias_registro)} ayuda="Define qué roles pueden consultarlo." />
          <Campo {...f.campo('gestion')} tipo="number" etiqueta="Gestión" requerido />
          <Campo {...f.campo('fecha_documento')} tipo="date" etiqueta="Fecha del documento" max={hoy()} />
          <Campo {...f.campo('archivo')} valor={undefined} tipo="file" etiqueta={esNuevo ? 'Archivo' : 'Reemplazar archivo (opcional)'} requerido={esNuevo} accept=".pdf,.jpg,.jpeg,.png,.webp" ayuda="PDF o imagen, máximo 4 MB." className="sm:col-span-2" />
          {esNuevo && (
            <Campo {...f.campo('id_titular')} tipo="select" etiqueta="Asociar al expediente de un estudiante (opcional)" vacio="Documento institucional, sin titular" className="sm:col-span-2"
              opciones={(estudiantes.datos ?? []).map((e) => ({ valor: e.id_usuario, etiqueta: `${e.apellidos}, ${e.nombres} · ${e.ci}` }))} />
          )}
          <Campo {...f.campo('descripcion')} tipo="textarea" etiqueta="Descripción" className="sm:col-span-2" />
        </form>
      </Modal>
    </>
  )
}
