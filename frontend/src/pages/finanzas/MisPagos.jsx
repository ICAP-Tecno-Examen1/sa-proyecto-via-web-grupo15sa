import { Percent } from 'lucide-react'
import { useState } from 'react'
import SolicitarBeca from '../../components/becas/SolicitarBeca'
import EstadoCuenta from '../../components/pagos/EstadoCuenta'
import ModalPago from '../../components/pagos/ModalPago'
import { Alerta, EncabezadoPagina, Estado, Insignia, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { useAuth } from '../../context/AuthContext'
import { useConsulta } from '../../hooks/useConsulta'
import { fecha, legible, moneda, porcentaje } from '../../utils/formato'

/**
 * CU4 · Mis pagos: el estudiante ve cuánto debe, sus mensualidades y su historial;
 * paga con tarjeta, avisa que pagará en efectivo o adjunta su comprobante, y puede
 * solicitar una beca con su documento de respaldo.
 */
export default function MisPagos() {
  const auth = useAuth()
  const consulta = useConsulta('/mis-pagos')
  const [cuota, setCuota] = useState(null)
  const [becaPara, setBecaPara] = useState(null)

  return (
    <>
      <EncabezadoPagina titulo="Mis pagos" descripcion="Consulte sus deudas, cuotas y pagos. Pague con tarjeta, en efectivo en caja o adjuntando el comprobante de su transferencia." />

      <Estado consulta={consulta}>
        {(matriculas) => matriculas.length === 0 ? (
          <div className="tarjeta"><Vacio titulo="No tiene pagos pendientes ni matrículas">Cuando se apruebe su preinscripción, aquí verá su plan de pagos.<div className="mt-3"><Boton a="/mis-preinscripciones" variante="secundario">Ver mis preinscripciones</Boton></div></Vacio></div>
        ) : (
          <div className="space-y-5">
            {matriculas.map((m) => {
              const proxima = m.cuenta.resumen.proxima_cuota
              const vigente = ['PENDIENTE_PAGO', 'ACTIVA'].includes(m.estado)
              const enRevisionBeca = m.solicitudes_beca.some((s) => s.estado === 'SOLICITADA')

              return (
                <section key={m.id_matricula} className="tarjeta p-5">
                  <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <h2 className="flex flex-wrap items-center gap-2 text-lg font-bold">{m.programa.nombre} <Insignia estado={m.estado} /></h2>
                      <p className="text-sm text-tenue">Matrícula {m.numero_registro} · Plan: {legible(m.tipo_cobro)}</p>
                      {m.becas.map((b) => <p key={b.beca} className="mt-1 text-sm font-semibold text-exito">Beca aplicada: {b.beca} ({porcentaje(b.porcentaje)})</p>)}
                      {vigente && m.cuenta.resumen.saldo_pendiente > 0 && (
                        <Boton tamano="sm" variante="fantasma" icono={Percent} className="mt-1 -ml-2" onClick={() => setBecaPara(m)}>¿Tiene una beca? Solicítela</Boton>
                      )}
                    </div>
                    {proxima && proxima.monto_en_revision === 0 && (
                      <div className="text-right">
                        <p className="text-xs text-tenue">Próxima cuota · vence {fecha(proxima.fecha_vencimiento)}</p>
                        <p className="tabular text-2xl font-extrabold text-primario">{moneda(proxima.saldo)}</p>
                        {proxima.monto_descuento > 0 && <p className="text-xs text-exito">Incluye descuento de beca: −{moneda(proxima.monto_descuento)}</p>}
                        <Boton className="mt-1" onClick={() => setCuota(proxima)}>Pagar ahora</Boton>
                      </div>
                    )}
                  </div>

                  {m.solicitudes_beca.map((s) => (
                    <Alerta key={s.id_beca_estudiante} className="mb-3" tipo={s.estado === 'RECHAZADA' ? 'peligro' : 'informacion'}
                      titulo={s.estado === 'RECHAZADA' ? `Beca no aprobada: ${s.beca}` : `Beca en revisión: ${s.beca} (${porcentaje(s.porcentaje_aplicado)})`}>
                      {s.estado === 'RECHAZADA'
                        ? <>Motivo: {s.observacion_revision}. Puede volver a solicitarla con el documento correcto.</>
                        : <>El Coordinador Financiero está revisando su documento. Si la aprueba, sus cuotas pendientes bajarán solas.{enRevisionBeca && proxima ? ' Si prefiere esperar, pague después de la respuesta.' : ''}</>}
                    </Alerta>
                  ))}

                  {m.estado === 'PENDIENTE_PAGO' && <Alerta tipo="alerta" className="mb-4">Realice el pago inicial (cuota 1) para activar su matrícula y quedar inscrito en sus materias.</Alerta>}
                  {m.cuenta.resumen.monto_en_revision > 0 && <Alerta className="mb-4">Tiene {moneda(m.cuenta.resumen.monto_en_revision)} esperando confirmación (efectivo por pagar en caja o comprobante en revisión).</Alerta>}
                  {m.cuenta.resumen.saldo_pendiente === 0 && <Alerta tipo="exito" className="mb-4">No tiene deudas en este programa.</Alerta>}

                  <EstadoCuenta cuenta={m.cuenta} alPagar={setCuota} />
                </section>
              )
            })}
          </div>
        )}
      </Estado>

      <ModalPago cuota={cuota} titular={auth.usuario} alCerrar={() => setCuota(null)} alTerminar={consulta.recargar} />
      <SolicitarBeca matricula={becaPara} alCerrar={() => setBecaPara(null)} alTerminar={consulta.recargar} />
    </>
  )
}
