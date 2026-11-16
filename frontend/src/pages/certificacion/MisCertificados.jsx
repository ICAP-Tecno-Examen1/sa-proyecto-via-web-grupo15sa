import { Award, Download, ExternalLink } from 'lucide-react'
import { abrirArchivo } from '../../api/cliente'
import { EncabezadoPagina, Estado, Insignia, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { fecha } from '../../utils/formato'

/** CU6 · El estudiante descarga sus certificados digitales. */
export default function MisCertificados() {
  const avisar = useAvisos()
  const consulta = useConsulta('/mis-certificados')
  const descargar = (c, guardar) => abrirArchivo(`/certificados/${c.id_certificado}/pdf`, { descargar: guardar, nombre: `certificado-${c.codigo_unico}.pdf` }).catch(avisar.error)

  return (
    <>
      <EncabezadoPagina titulo="Mis certificados" descripcion="Certificados digitales emitidos por el ICAP. Cada uno tiene un código único y un QR para comprobar su autenticidad." />

      <Estado consulta={consulta}>
        {(certificados) => certificados.length === 0 ? (
          <div className="tarjeta"><Vacio icono={Award} titulo="Aún no tiene certificados">Al finalizar un programa y cumplir los requisitos académicos, financieros y documentales, el Director autoriza la emisión de su certificado.</Vacio></div>
        ) : (
          <div className="grid gap-4 md:grid-cols-2">
            {certificados.map((c) => (
              <article key={c.id_certificado} className="tarjeta p-5">
                <div className="flex items-start gap-3">
                  <span className="grid size-12 shrink-0 place-items-center rounded-tema bg-primario-suave text-primario"><Award className="size-7" aria-hidden /></span>
                  <div className="min-w-0 flex-1">
                    <h2 className="text-lg leading-snug font-bold">{c.programa}</h2>
                    <p className="text-sm text-tenue">Gestión {c.gestion} · Emitido el {fecha(c.fecha_emision)}</p>
                  </div>
                  <Insignia estado={c.estado} />
                </div>
                <p className="mt-4 rounded-tema bg-suave px-3 py-2 text-center">
                  <span className="block text-xs text-tenue">Código único</span>
                  <strong className="tabular text-lg tracking-wide">{c.codigo_unico}</strong>
                </p>
                <div className="mt-4 flex flex-wrap gap-2">
                  {c.estado === 'EMITIDO' && (
                    <>
                      <Boton icono={Download} onClick={() => descargar(c, true)}>Descargar PDF</Boton>
                      <Boton variante="secundario" onClick={() => descargar(c, false)}>Ver</Boton>
                    </>
                  )}
                  <Boton a={`/verificar/${c.codigo_unico}`} variante="fantasma" icono={ExternalLink}>Verificación pública</Boton>
                </div>
              </article>
            ))}
          </div>
        )}
      </Estado>
    </>
  )
}
