import { Send } from 'lucide-react'
import api from '../../api/cliente'
import { Alerta, EncabezadoPagina, Estado, Insignia, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { describirPlan, fecha, legible, moneda } from '../../utils/formato'

const AYUDA = {
  PENDIENTE: 'La Secretaría está revisando sus datos y documentos.',
  OBSERVADA: 'Debe corregir lo indicado y volver a enviar su solicitud.',
  APROBADA: 'Se generó su matrícula. Realice el pago inicial para activarla.',
  RECHAZADA: 'Su solicitud no fue aceptada.',
}

/** CU3 · El estudiante consulta el estado de sus preinscripciones. */
export default function MisPreinscripciones() {
  const avisar = useAvisos()
  const consulta = useConsulta('/mis-preinscripciones')

  const reenviar = async (p) => {
    try {
      await api.post(`/preinscripciones/${p.id_preinscripcion}/reenviar`)
      avisar.exito('Solicitud reenviada a la Secretaría.')
      consulta.recargar()
    } catch (e) {
      avisar.error(e)
    }
  }

  return (
    <>
      <EncabezadoPagina titulo="Mis preinscripciones" descripcion="Estado de sus solicitudes de inscripción.">
        <Boton a="/catalogo">Ver catálogo de programas</Boton>
      </EncabezadoPagina>

      <Estado consulta={consulta}>
        {({ preinscripciones, documentos_faltantes: faltantes }) => (
          <div className="space-y-4">
            {faltantes.length > 0 && (
              <Alerta tipo="alerta" titulo="Su expediente está incompleto">
                Falta presentar: {faltantes.join(', ')}. Sin estos documentos la Secretaría no podrá aprobar su preinscripción.{' '}
                <Boton a="/mi-expediente" tamano="sm" variante="secundario" className="mt-2">Completar mi expediente</Boton>
              </Alerta>
            )}

            {preinscripciones.length === 0 ? (
              <div className="tarjeta"><Vacio titulo="Aún no tiene preinscripciones">Elija un programa del catálogo y preinscríbase en línea.</Vacio></div>
            ) : preinscripciones.map((p) => (
              <article key={p.id_preinscripcion} className="tarjeta p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h2 className="text-lg font-bold">{p.oferta.programa.nombre}</h2>
                    <p className="text-sm text-tenue">Oferta {p.oferta.codigo} · {legible(p.oferta.modalidad)} · Inicia {fecha(p.oferta.fecha_inicio)} · Solicitada el {fecha(p.fecha)}</p>
                  </div>
                  <Insignia estado={p.estado} />
                </div>

                <p className="mt-3 text-sm">{AYUDA[p.estado]}</p>
                {p.plan_preferido && <p className="mt-1 text-sm text-tenue">Plan elegido: {p.plan_preferido.concepto} — {moneda(p.plan_preferido.monto)} ({describirPlan(p.plan_preferido)})</p>}
                {p.observacion && p.estado !== 'APROBADA' && <Alerta tipo={p.estado === 'RECHAZADA' ? 'peligro' : 'alerta'} titulo="Observación de la Secretaría" className="mt-3">{p.observacion}</Alerta>}

                <div className="mt-4 flex flex-wrap gap-2">
                  {p.estado === 'OBSERVADA' && (
                    <>
                      <Boton a="/mi-expediente" variante="secundario">Revisar mi expediente</Boton>
                      <Boton icono={Send} onClick={() => reenviar(p)}>Ya corregí: reenviar</Boton>
                    </>
                  )}
                  {p.estado === 'APROBADA' && p.matricula && (
                    <>
                      <span className="self-center text-sm">Matrícula <strong>{p.matricula.numero_registro}</strong> <Insignia estado={p.matricula.estado} /></span>
                      <Boton a="/mis-pagos">Ir a mis pagos</Boton>
                    </>
                  )}
                </div>
              </article>
            ))}
          </div>
        )}
      </Estado>
    </>
  )
}
