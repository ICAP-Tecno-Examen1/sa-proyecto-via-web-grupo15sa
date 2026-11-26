import { Eye, ImageUp, Save, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import api, { abrirArchivo, aFormData } from '../../api/cliente'
import { Alerta, EncabezadoPagina, Estado, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { maximo, requerido } from '../../utils/validacion'

const TEXTOS = {
  encabezado_1: '', encabezado_2: '', texto_otorgamiento: '', texto_habilitacion: '', texto_curso: '', texto_pie: '',
  ciudad: '', lugar_pie: '', firmante_1_nombre: '', firmante_1_cargo: '', firmante_2_nombre: '', firmante_2_cargo: '', mostrar_foto: true,
}

const REGLAS = {
  encabezado_1: [requerido(), maximo(200)],
  encabezado_2: [requerido(), maximo(200)],
  texto_otorgamiento: [requerido(), maximo(2000)],
  texto_habilitacion: [requerido(), maximo(2000)],
  texto_curso: [requerido(), maximo(2000)],
  texto_pie: [requerido(), maximo(2000)],
  ciudad: [requerido(), maximo(100)],
  lugar_pie: [requerido(), maximo(100)],
  firmante_1_nombre: [maximo(150)],
  firmante_1_cargo: [requerido(), maximo(150)],
  firmante_2_nombre: [maximo(150)],
  firmante_2_cargo: [requerido(), maximo(150)],
}

const TIPOS_VISTA = [
  { valor: 'TECNICO_AUXILIAR', etiqueta: 'Título · Técnico Auxiliar' },
  { valor: 'TECNICO_MEDIO', etiqueta: 'Título · Técnico Medio' },
  { valor: 'TECNICO_SUPERIOR', etiqueta: 'Título · Técnico Superior' },
  { valor: 'CURSO', etiqueta: 'Certificado · Curso o taller' },
]

/**
 * CU6 · Plantilla del certificado / título (formato oficial del ICAP).
 * El Director edita los textos, los firmantes y sube sus firmas y los escudos.
 * El sello no es digital: queda su espacio en blanco para estamparlo a mano.
 */
export default function PlantillaCertificado() {
  const consulta = useConsulta('/certificacion/plantilla')

  return (
    <>
      <EncabezadoPagina titulo="Plantilla del certificado"
        descripcion="Formato oficial con que se emiten los títulos y certificados. Los cambios rigen para los certificados que se emitan desde ahora; los ya emitidos conservan su PDF." />
      <Estado consulta={consulta}>{(datos) => <Editor inicial={datos} />}</Estado>
    </>
  )
}

function Editor({ inicial }) {
  const avisar = useAvisos()
  const f = useFormulario({ ...TEXTOS, ...soloTextos(inicial) }, REGLAS)
  const [imagenes, setImagenes] = useState(inicial.imagenes)
  const [tipoVista, setTipoVista] = useState('TECNICO_AUXILIAR')
  const [abriendo, setAbriendo] = useState(false)

  const guardar = f.enviar(async (valores) => {
    const datos = { ...valores }
    for (const campo of ['firmante_1_nombre', 'firmante_2_nombre']) if (datos[campo] === '') datos[campo] = null
    const { data } = await api.put('/certificacion/plantilla', datos)
    f.reiniciar({ ...TEXTOS, ...soloTextos(data) })
    avisar.exito('Plantilla guardada. Los próximos certificados saldrán con este formato.')
  })

  const vistaPrevia = async () => {
    setAbriendo(true)
    try {
      await abrirArchivo('/certificacion/plantilla/vista-previa', { params: { tipo: tipoVista } })
    } catch (e) {
      avisar.error(e)
    } finally {
      setAbriendo(false)
    }
  }

  const campoTexto = (nombre, etiqueta, extra = {}) => <Campo {...f.campo(nombre)} etiqueta={etiqueta} requerido={REGLAS[nombre]?.length > 1} {...extra} />

  return (
    <form onSubmit={guardar} className="space-y-6" noValidate>
      {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}

      <Tarjeta titulo="Vista previa">
        <div className="flex flex-wrap items-end gap-3">
          <div className="min-w-64">
            <Campo nombre="tipo_vista" etiqueta="Ver como" tipo="select" vacio={null} opciones={TIPOS_VISTA} valor={tipoVista} alCambiar={setTipoVista} />
          </div>
          <Boton variante="secundario" icono={Eye} onClick={vistaPrevia} cargando={abriendo}>Ver PDF de ejemplo</Boton>
          <p className="text-xs text-tenue">Muestra la plantilla <strong>guardada</strong> con datos ficticios y la marca «VISTA PREVIA».</p>
        </div>
      </Tarjeta>

      <Tarjeta titulo="Firmas digitales">
        <Alerta className="mb-4">
          Suba la firma escaneada de cada autoridad en <strong>PNG con fondo transparente</strong> (o JPG con fondo blanco). Se imprime sobre la línea de firma.
          El <strong>sello no se digitaliza</strong>: el espacio central del certificado queda libre para sellarlo a mano.
        </Alerta>
        <div className="grid gap-6 md:grid-cols-2">
          <div className="space-y-3">
            <h3 className="font-bold">Firmante izquierdo</h3>
            {campoTexto('firmante_1_nombre', 'Nombre', { ayuda: 'Opcional. Ej.: Lic. María Pérez' })}
            {campoTexto('firmante_1_cargo', 'Cargo')}
            <Imagen campo="firmante_1_firma" etiqueta="Firma" hay={imagenes.firmante_1_firma} alCambiar={setImagenes} />
          </div>
          <div className="space-y-3">
            <h3 className="font-bold">Firmante derecho</h3>
            {campoTexto('firmante_2_nombre', 'Nombre', { ayuda: 'Opcional. Ej.: Ing. Juan Rojas' })}
            {campoTexto('firmante_2_cargo', 'Cargo')}
            <Imagen campo="firmante_2_firma" etiqueta="Firma" hay={imagenes.firmante_2_firma} alCambiar={setImagenes} />
          </div>
        </div>
      </Tarjeta>

      <Tarjeta titulo="Encabezado y escudos">
        <div className="grid gap-4 md:grid-cols-2">
          {campoTexto('encabezado_1', 'Primera línea (letra gótica)')}
          {campoTexto('encabezado_2', 'Segunda línea (letra gótica)')}
          <Imagen campo="logo_izquierdo" etiqueta="Escudo izquierdo (UAGRM)" ayuda="Si no sube uno, se usa el logo del ICAP." hay={imagenes.logo_izquierdo} alCambiar={setImagenes} />
          <Imagen campo="logo_derecho" etiqueta="Escudo derecho (Bolivia)" ayuda="Si no sube uno, queda vacío." hay={imagenes.logo_derecho} alCambiar={setImagenes} />
        </div>
      </Tarjeta>

      <Tarjeta titulo="Textos">
        <div className="space-y-4">
          {campoTexto('texto_otorgamiento', 'Párrafo de otorgamiento', { tipo: 'textarea', rows: 4, ayuda: '{documento} se reemplaza por «Título» (carreras técnicas) o «Certificado» (cursos y talleres).' })}
          {campoTexto('texto_habilitacion', 'Cierre para títulos (carreras técnicas)', { tipo: 'textarea', rows: 3 })}
          {campoTexto('texto_curso', 'Cierre para certificados (cursos y talleres)', { tipo: 'textarea', rows: 3, ayuda: '{horas} se reemplaza por la carga horaria del programa.' })}
          <div className="grid gap-4 md:grid-cols-2">
            {campoTexto('ciudad', 'Ciudad de emisión', { ayuda: 'Va antes de la fecha: «Santa Cruz de la Sierra 24 de Junio de 2025».' })}
            {campoTexto('lugar_pie', 'Lugar al pie')}
          </div>
          {campoTexto('texto_pie', 'Nota legal al pie', { tipo: 'textarea', rows: 3 })}
          <Campo {...f.campo('mostrar_foto')} tipo="checkbox" etiqueta="Incluir la fotografía del estudiante (la toma de su expediente)" />
        </div>
      </Tarjeta>

      <div className="flex justify-end">
        <Boton type="submit" icono={Save} cargando={f.enviando}>Guardar plantilla</Boton>
      </div>
    </form>
  )
}

/** Subida, vista y quitado de una imagen de la plantilla (firma o escudo). */
function Imagen({ campo, etiqueta, ayuda, hay, alCambiar }) {
  const avisar = useAvisos()
  const [url, setUrl] = useState(null)
  const [ocupado, setOcupado] = useState(false)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    if (!hay) return undefined
    let enlace = null
    let vigente = true
    api.get(`/certificacion/plantilla/imagen/${campo}`, { responseType: 'blob' })
      .then(({ data }) => {
        if (!vigente) return
        enlace = URL.createObjectURL(data)
        setUrl(enlace)
      })
      .catch(() => vigente && setUrl(null))

    return () => {
      vigente = false
      if (enlace) URL.revokeObjectURL(enlace)
    }
  }, [campo, hay, version])

  const subir = async (archivo) => {
    if (!archivo) return
    setOcupado(true)
    try {
      const { data } = await api.post(`/certificacion/plantilla/imagen/${campo}`, aFormData({ archivo }))
      alCambiar(data.imagenes)
      setVersion((v) => v + 1)
      avisar.exito(`${etiqueta}: imagen actualizada.`)
    } catch (e) {
      avisar.error(e)
    } finally {
      setOcupado(false)
    }
  }

  const quitar = async () => {
    setOcupado(true)
    try {
      const { data } = await api.delete(`/certificacion/plantilla/imagen/${campo}`)
      alCambiar(data.imagenes)
      setUrl(null)
      avisar.exito(`${etiqueta}: imagen quitada.`)
    } catch (e) {
      avisar.error(e)
    } finally {
      setOcupado(false)
    }
  }

  return (
    <div className="space-y-2">
      <span className="block text-sm font-semibold">{etiqueta}</span>
      <div className="flex min-h-24 items-center justify-center rounded-tema border-2 border-dashed bg-white p-2">
        {hay && url ? <img src={url} alt={etiqueta} className="max-h-24 max-w-full object-contain" /> : <span className="text-xs text-tenue">Sin imagen</span>}
      </div>
      <div className="flex flex-wrap gap-2">
        <label className={`inline-flex cursor-pointer items-center gap-2 rounded-tema border-2 px-3 py-1.5 text-sm font-semibold hover:bg-suave ${ocupado ? 'pointer-events-none opacity-55' : ''}`}>
          <ImageUp className="size-4" aria-hidden />
          {hay ? 'Cambiar' : 'Subir'} imagen
          <input type="file" accept="image/png,image/jpeg" className="sr-only" onChange={(e) => { subir(e.target.files?.[0]); e.target.value = '' }} />
        </label>
        {hay && <Boton variante="secundario" tamano="sm" icono={Trash2} onClick={quitar} cargando={ocupado}>Quitar</Boton>}
      </div>
      {ayuda && <p className="text-xs text-tenue">{ayuda}</p>}
    </div>
  )
}

function soloTextos(datos) {
  return Object.fromEntries(Object.keys(TEXTOS).map((k) => [k, datos[k] ?? (k === 'mostrar_foto' ? true : '')]))
}
