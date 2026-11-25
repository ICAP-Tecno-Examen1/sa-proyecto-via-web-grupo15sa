import { ArrowLeft, Award, CircleCheck, CircleX, Download, ExternalLink } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { abrirArchivo } from '../../api/cliente'
import { Alerta, Dato, Estado, Insignia } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal, { Confirmar } from '../../components/ui/Modal'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fecha, fechaHora } from '../../utils/formato'
import { maximo, minimo, requerido, requeridoSi } from '../../utils/validacion'

const TITULOS = {
  ACADEMICO: ['Requisito académico', 'Valida el Coordinador Académico: materias obligatorias aprobadas y asistencia mínima.'],
  FINANCIERO: ['Requisito financiero', 'Valida el Coordinador Financiero: sin deudas pendientes.'],
  DOCUMENTAL: ['Requisito documental', 'Valida la Secretaria: expediente completo y datos personales correctos.'],
}

/**
 * CU6 · Validación de requisitos y emisión del certificado de una matrícula.
 * Cada tarjeta muestra la verificación automática del sistema y la decisión
 * registrada. Solo valida quien tiene el permiso de ese requisito.
 */
export default function CertificacionDetalle() {
  const { id } = useParams()
  const avisar = useAvisos()
  const consulta = useConsulta(`/certificacion/${id}`)
  const [validando, setValidando] = useState(null)
  const [emitiendo, setEmitiendo] = useState(false)
  const [procesando, setProcesando] = useState(false)
  const f = useFormulario({ estado: '', observacion: '' }, {
    estado: [requerido('Seleccione el resultado.')],
    observacion: [requeridoSi((v) => v.estado === 'OBSERVADO', 'Indique qué falta para cumplir el requisito.'), minimo(5), maximo(500)],
  })

  const validar = f.enviar(async (v) => {
    const { data } = await api.post(`/certificacion/${id}/validar`, { tipo: validando.tipo, estado: v.estado, observacion: v.observacion || null })
    consulta.setDatos(data)
    avisar.exito('Validación registrada.')
    setValidando(null)
  })

  const emitir = async () => {
    setProcesando(true)

    try {
      const { data } = await api.post(`/certificacion/${id}/emitir`)
      consulta.setDatos(data)
      avisar.exito('Certificado emitido con código único y QR de verificación.')
    } catch (e) {
      avisar.error(e)
    } finally {
      setProcesando(false)
      setEmitiendo(false)
    }
  }

  return (
    <>
      <Link to="/certificacion" className="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primario hover:underline">
        <ArrowLeft className="size-4" aria-hidden /> Volver a certificación
      </Link>

      <Estado consulta={consulta}>
        {(m) => (
          <div className="space-y-5">
            <section className="tarjeta p-5">
              <h1 className="text-2xl font-bold">{m.estudiante.nombre_completo}</h1>
              <p className="text-sm text-tenue">Matrícula {m.numero_registro} · {m.programa.nombre} · Finalizó el {fecha(m.fecha_finalizacion)}</p>
            </section>

            <div className="grid gap-4 lg:grid-cols-3">
              {m.requisitos.map((r) => (
                <section key={r.tipo} className="tarjeta flex flex-col p-5">
                  <div className="flex items-start justify-between gap-2">
                    <h2 className="font-bold">{TITULOS[r.tipo][0]}</h2>
                    <Insignia estado={r.estado} />
                  </div>
                  <p className="mt-1 text-xs text-tenue">{TITULOS[r.tipo][1]}</p>

                  <div className={`mt-3 rounded-tema border p-3 text-sm ${r.cumple ? 'border-exito/50 bg-exito-suave' : 'border-alerta/50 bg-alerta-suave'}`}>
                    <p className="mb-1 flex items-center gap-1.5 font-bold">
                      {r.cumple ? <CircleCheck className="size-4 text-exito" aria-hidden /> : <CircleX className="size-4 text-alerta" aria-hidden />}
                      Verificación del sistema: {r.cumple ? 'cumple' : 'no cumple'}
                    </p>
                    <ul className="space-y-0.5 text-xs">{r.detalle.map((linea) => <li key={linea}>{linea}</li>)}</ul>
                  </div>

                  <div className="mt-3 flex-1 text-xs text-tenue">
                    {r.validador ? <p>Validado por <strong className="text-texto">{r.validador}</strong> el {fechaHora(r.fecha_validacion)}</p> : <p>Aún sin validar.</p>}
                    {r.observacion && <p className="mt-1 rounded bg-suave p-2 text-texto">{r.observacion}</p>}
                  </div>

                  {r.puede_validar && !m.certificado && (
                    <Boton className="mt-3" variante={r.estado === 'APROBADO' ? 'secundario' : 'primario'} onClick={() => (f.reiniciar({ estado: r.cumple ? 'APROBADO' : 'OBSERVADO', observacion: '' }), setValidando(r))}>
                      {r.estado === 'PENDIENTE' ? 'Validar requisito' : 'Cambiar validación'}
                    </Boton>
                  )}
                </section>
              ))}
            </div>

            <section className="tarjeta p-5">
              <h2 className="flex items-center gap-2 text-lg font-bold"><Award className="size-5 text-primario" aria-hidden /> Certificado digital</h2>

              {m.certificado ? (
                <div className="mt-3 space-y-4">
                  <Alerta tipo="exito">Certificado emitido. El estudiante ya puede descargarlo desde su perfil y cualquier persona puede verificarlo.</Alerta>
                  <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Dato etiqueta="Código único"><span className="tabular text-base font-extrabold">{m.certificado.codigo_unico}</span></Dato>
                    <Dato etiqueta="Fecha de emisión">{fecha(m.certificado.fecha_emision)}</Dato>
                    <Dato etiqueta="Autorizado por">{m.certificado.autorizado_por}</Dato>
                    <Dato etiqueta="Promedio">{m.certificado.promedio}</Dato>
                  </dl>
                  <div className="flex flex-wrap gap-2">
                    <Boton icono={Download} onClick={() => abrirArchivo(`/certificados/${m.certificado.id_certificado}/pdf`).catch(avisar.error)}>Ver certificado (PDF)</Boton>
                    <Boton a={`/verificar/${m.certificado.codigo_unico}`} variante="secundario" icono={ExternalLink}>Página pública de verificación</Boton>
                  </div>
                </div>
              ) : m.listo_para_emitir ? (
                <div className="mt-3 space-y-3">
                  <Alerta tipo="exito">Los tres requisitos están aprobados.</Alerta>
                  {m.puede_emitir
                    ? <Boton tamano="lg" icono={Award} onClick={() => setEmitiendo(true)}>Autorizar y emitir certificado</Boton>
                    : <p className="text-sm text-tenue">El Director debe autorizar la emisión.</p>}
                </div>
              ) : <Alerta tipo="alerta" className="mt-3">El certificado se podrá emitir cuando los requisitos académico, financiero y documental estén aprobados.</Alerta>}
            </section>

            <Modal abierto={Boolean(validando)} titulo={validando ? TITULOS[validando.tipo][0] : ''} alCerrar={() => setValidando(null)} ancho="sm"
              pie={<><Boton variante="secundario" onClick={() => setValidando(null)}>Cancelar</Boton><Boton onClick={validar} cargando={f.enviando}>Registrar validación</Boton></>}>
              <form onSubmit={validar} noValidate className="space-y-4">
                {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
                {validando && !validando.cumple && <Alerta tipo="alerta">El sistema detecta que el requisito aún no se cumple: solo puede registrarse como observado.</Alerta>}
                <Campo {...f.campo('estado')} tipo="select" etiqueta="Resultado" requerido
                  opciones={[{ valor: 'APROBADO', etiqueta: 'Aprobado: cumple el requisito' }, { valor: 'OBSERVADO', etiqueta: 'Observado: no cumple todavía' }]} />
                <Campo {...f.campo('observacion')} tipo="textarea" etiqueta="Observación" requerido={f.valores.estado === 'OBSERVADO'} />
              </form>
            </Modal>

            <Confirmar abierto={emitiendo} titulo="Emitir certificado" variante="primario" textoConfirmar="Autorizar y emitir" cargando={procesando} alConfirmar={emitir} alCerrar={() => setEmitiendo(false)}
              mensaje={`Se generará el certificado digital de ${m.estudiante.nombre_completo} con un código único y un QR de verificación pública. Quedará archivado en su expediente.`} />
          </div>
        )}
      </Estado>
    </>
  )
}
