import { CircleCheck, CircleX, Eye, Trash2, Upload } from 'lucide-react'
import { useState } from 'react'
import api, { abrirArchivo, aFormData } from '../../api/cliente'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fechaHora, tamanoArchivo } from '../../utils/formato'
import { archivo, maximo, requerido } from '../../utils/validacion'
import { Alerta, Estado } from '../ui/Basicos'
import Boton from '../ui/Boton'
import Campo from '../ui/Campo'
import { Confirmar } from '../ui/Modal'
import Tabla from '../ui/Tabla'

/**
 * CU3 · Expediente único del estudiante.
 * Sirve para el propio estudiante (/mi-expediente) y para la Secretaría
 * (/expedientes/{usuario}). Los documentos se entregan una sola vez y valen
 * para todas las inscripciones.
 */
export default function Expediente({ recurso, puedeSubir = false, puedeEliminar = false, alCambiar }) {
  const avisar = useAvisos()
  const consulta = useConsulta(recurso)
  const [eliminando, setEliminando] = useState(null)
  const f = useFormulario(
    { tipo: '', archivo: null, descripcion: '' },
    { tipo: [requerido('Seleccione el tipo de documento.')], archivo: [requerido('Seleccione el archivo.'), archivo()], descripcion: [maximo(500)] },
  )

  const subir = f.enviar(async (valores) => {
    const { data } = await api.post(recurso, aFormData(valores))
    consulta.setDatos(data)
    f.reiniciar()
    avisar.exito('Documento agregado al expediente.')
    alCambiar?.()
  })

  const eliminar = async () => {
    try {
      const { data } = await api.delete(`${recurso}/${eliminando.id_documento}`)
      consulta.setDatos(data)
      avisar.exito('Documento eliminado.')
      alCambiar?.()
    } catch (e) {
      avisar.error(e)
    } finally {
      setEliminando(null)
    }
  }

  const ver = (d) => abrirArchivo(`/documentos/${d.id_documento}/descargar`, { params: { ver: 1 } }).catch(avisar.error)

  return (
    <Estado consulta={consulta}>
      {(exp) => (
        <div className="space-y-5">
          <div>
            <h3 className="mb-2 text-sm font-bold">Documentos requeridos</h3>
            <ul className="grid gap-2 sm:grid-cols-3">
              {exp.requeridos.map((r) => (
                <li key={r.tipo} className={`flex items-center gap-2 rounded-tema border p-3 text-sm ${r.presentado ? 'border-exito/50 bg-exito-suave' : 'border-alerta/50 bg-alerta-suave'}`}>
                  {r.presentado ? <CircleCheck className="size-5 shrink-0 text-exito" aria-hidden /> : <CircleX className="size-5 shrink-0 text-alerta" aria-hidden />}
                  <span><strong className="block">{r.etiqueta}</strong>{r.presentado ? 'Presentado' : 'Falta presentar'}</span>
                </li>
              ))}
            </ul>
            {exp.completo
              ? <Alerta tipo="exito" className="mt-3">El expediente está completo.</Alerta>
              : <Alerta tipo="alerta" className="mt-3">El expediente está incompleto: la preinscripción no podrá aprobarse hasta presentar todos los documentos requeridos.</Alerta>}
          </div>

          {puedeSubir && (
            <form onSubmit={subir} noValidate className="rounded-tema border bg-suave p-4">
              <h3 className="mb-3 text-sm font-bold">Agregar documento</h3>
              {f.errorGeneral && <Alerta tipo="peligro" className="mb-3">{f.errorGeneral}</Alerta>}
              <div className="grid gap-3 sm:grid-cols-2">
                <Campo {...f.campo('tipo')} tipo="select" etiqueta="Tipo de documento" requerido opciones={exp.tipos.map((t) => ({ valor: t.tipo, etiqueta: t.etiqueta }))} />
                <Campo {...f.campo('archivo')} key={consulta.datos?.documentos.length} tipo="file" etiqueta="Archivo" requerido accept=".pdf,.jpg,.jpeg,.png,.webp" ayuda="PDF o imagen, máximo 4 MB." valor={undefined} />
                <Campo {...f.campo('descripcion')} etiqueta="Descripción (opcional)" className="sm:col-span-2" />
              </div>
              <div className="mt-3 flex justify-end">
                <Boton type="submit" icono={Upload} cargando={f.enviando}>Subir documento</Boton>
              </div>
            </form>
          )}

          <div>
            <h3 className="mb-2 text-sm font-bold">Documentos del expediente ({exp.documentos.length})</h3>
            <div className="rounded-tema border">
              <Tabla
                id="id_documento"
                filas={exp.documentos}
                vacio="El expediente aún no tiene documentos."
                columnas={[
                  { clave: 'nombre', titulo: 'Documento', render: (d) => (<><span className="font-semibold">{d.nombre}</span><span className="block text-xs text-tenue">{exp.tipos.find((t) => t.tipo === d.tipo)?.etiqueta ?? d.tipo} · {tamanoArchivo(d.tamano_bytes)}</span></>) },
                  { clave: 'fecha_registro', titulo: 'Registrado', render: (d) => fechaHora(d.fecha_registro) },
                  { clave: 'acciones', titulo: 'Acciones', alinear: 'derecha', render: (d) => (
                    <div className="flex justify-end gap-1">
                      {d.tiene_archivo && <Boton tamano="sm" variante="secundario" icono={Eye} onClick={() => ver(d)}>Ver</Boton>}
                      {puedeEliminar && d.categoria === 'PERSONAL' && <Boton tamano="sm" variante="fantasma" icono={Trash2} className="text-peligro" onClick={() => setEliminando(d)} aria-label="Eliminar documento" />}
                    </div>
                  ) },
                ]}
              />
            </div>
          </div>

          <Confirmar abierto={Boolean(eliminando)} titulo="Eliminar documento" mensaje={`¿Eliminar «${eliminando?.nombre}» de su expediente?`} textoConfirmar="Eliminar" alConfirmar={eliminar} alCerrar={() => setEliminando(null)} />
        </div>
      )}
    </Estado>
  )
}
