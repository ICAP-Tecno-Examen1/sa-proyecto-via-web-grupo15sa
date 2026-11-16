import { BadgeCheck, CircleX, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import api from '../../api/cliente'
import { Cargando, Dato } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { fecha, legible } from '../../utils/formato'

/**
 * CU6 · Verificación pública de certificados.
 * Llega aquí quien escanea el QR del certificado (/verificar/CODIGO) o quien
 * escribe el código único. No requiere iniciar sesión.
 */
export default function VerificarCertificado() {
  const { codigo } = useParams()
  const navegar = useNavigate()
  const [texto, setTexto] = useState(codigo ?? '')
  const [estado, setEstado] = useState({ cargando: false, certificado: null, noExiste: false, error: null })

  useEffect(() => {
    setTexto(codigo ?? '')
    if (!codigo) return setEstado({ cargando: false, certificado: null, noExiste: false, error: null })

    let vigente = true
    setEstado({ cargando: true, certificado: null, noExiste: false, error: null })

    api
      .get(`/publico/certificados/${encodeURIComponent(codigo)}`)
      .then(({ data }) => vigente && setEstado({ cargando: false, certificado: data, noExiste: false, error: null }))
      .catch((e) => vigente && setEstado({
        cargando: false, certificado: null, noExiste: e.response?.status === 404,
        error: e.response?.status === 404 ? null : 'No se pudo consultar el certificado. Intente nuevamente.',
      }))

    return () => {
      vigente = false
    }
  }, [codigo])

  const buscar = (e) => {
    e.preventDefault()
    const limpio = texto.trim().toUpperCase()
    if (limpio.length >= 6) navegar(`/verificar/${encodeURIComponent(limpio)}`)
  }

  const c = estado.certificado

  return (
    <div className="mx-auto max-w-2xl space-y-5">
      <div className="tarjeta p-6">
        <h1 className="text-2xl font-extrabold">Verificar certificado</h1>
        <p className="mt-1 mb-4 text-sm text-tenue">Escriba el código único impreso en el certificado (por ejemplo, ICAP-2026-AB12CD34) o escanee su código QR.</p>
        <form onSubmit={buscar} className="flex flex-wrap gap-2" role="search">
          <label htmlFor="codigo" className="sr-only">Código único del certificado</label>
          <input id="codigo" value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="ICAP-0000-XXXXXXXX" className="campo-control min-w-56 flex-1 uppercase" autoComplete="off" maxLength={100} />
          <Boton type="submit" icono={Search} disabled={texto.trim().length < 6}>Verificar</Boton>
        </form>
      </div>

      {estado.cargando && <Cargando texto="Consultando…" />}

      {estado.error && <div className="tarjeta border-peligro p-5 text-sm text-peligro" role="alert">{estado.error}</div>}

      {estado.noExiste && (
        <div className="tarjeta border-peligro bg-peligro-suave p-6 text-center" role="alert">
          <CircleX className="mx-auto size-12 text-peligro" aria-hidden />
          <h2 className="mt-2 text-xl font-extrabold">Certificado no encontrado</h2>
          <p className="mt-1 text-sm">No existe ningún certificado del ICAP con el código <strong>{codigo}</strong>. Revise que esté escrito correctamente.</p>
        </div>
      )}

      {c && (
        <div className={`tarjeta overflow-hidden ${c.valido ? 'border-exito' : 'border-peligro'}`}>
          <div className={`flex items-center gap-3 p-5 ${c.valido ? 'bg-exito-suave' : 'bg-peligro-suave'}`} role="status">
            {c.valido ? <BadgeCheck className="size-12 shrink-0 text-exito" aria-hidden /> : <CircleX className="size-12 shrink-0 text-peligro" aria-hidden />}
            <div>
              <h2 className="text-xl font-extrabold">{c.valido ? 'Certificado auténtico' : 'Certificado anulado'}</h2>
              <p className="text-sm">{c.valido ? 'Este certificado fue emitido por el ICAP - UAGRM y está vigente.' : 'Este certificado fue emitido por el ICAP, pero ya no tiene validez.'}</p>
            </div>
          </div>
          <dl className="grid gap-4 p-5 sm:grid-cols-2">
            <Dato etiqueta="Código único">{c.codigo_unico}</Dato>
            <Dato etiqueta="Fecha de emisión">{fecha(c.fecha_emision)}</Dato>
            <Dato etiqueta="Otorgado a">{c.estudiante}</Dato>
            <Dato etiqueta="Documento">{c.documento}</Dato>
            <div className="sm:col-span-2"><Dato etiqueta="Programa">{c.programa}</Dato></div>
            <Dato etiqueta="Modalidad">{legible(c.modalidad)}</Dato>
            <Dato etiqueta="Carga horaria">{c.horas_totales} horas</Dato>
            <Dato etiqueta="Gestión">{c.gestion}</Dato>
            <Dato etiqueta="Autorizado por">{c.autorizado_por}</Dato>
          </dl>
        </div>
      )}
    </div>
  )
}
