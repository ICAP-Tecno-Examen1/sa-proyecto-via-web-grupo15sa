import { useState } from 'react'
import ProgresoAcademico from '../../components/inscripcion/ProgresoAcademico'
import { Alerta, Cargando, EncabezadoPagina, Estado, Insignia, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { useConsulta } from '../../hooks/useConsulta'
import { fecha, legible, moneda, porcentaje } from '../../utils/formato'

/** CU5 · El estudiante consulta sus programas, notas, asistencia y resultados. */
export default function MisEstudios() {
  const lista = useConsulta('/mis-estudios')
  const [idAbierta, setIdAbierta] = useState(null)
  const idActiva = idAbierta ?? lista.datos?.[0]?.id_matricula ?? null
  const detalle = useConsulta(idActiva ? `/mis-estudios/${idActiva}` : null)

  return (
    <>
      <EncabezadoPagina titulo="Mis estudios y notas" descripcion="Sus programas, calificaciones por evaluación, notas finales y asistencia." />

      <Estado consulta={lista}>
        {(matriculas) => matriculas.length === 0 ? (
          <div className="tarjeta"><Vacio titulo="Aún no tiene matrículas">Cuando la Secretaría apruebe su preinscripción, su programa aparecerá aquí.<div className="mt-3"><Boton a="/catalogo">Ver catálogo</Boton></div></Vacio></div>
        ) : (
          <div className="grid gap-5 lg:grid-cols-[20rem_1fr]">
            <ul className="space-y-2">
              {matriculas.map((m) => (
                <li key={m.id_matricula}>
                  <button onClick={() => setIdAbierta(m.id_matricula)} aria-current={m.id_matricula === idActiva}
                    className={`tarjeta block w-full p-4 text-left transition ${m.id_matricula === idActiva ? 'border-primario bg-primario-suave' : 'hover:border-primario'}`}>
                    <span className="flex items-start justify-between gap-2">
                      <strong>{m.programa.nombre}</strong>
                      <Insignia estado={m.estado} />
                    </span>
                    <span className="mt-1 block text-xs text-tenue">{m.numero_registro} · {legible(m.oferta.modalidad)} · Gestión {m.oferta.gestion}</span>
                    <span className="mt-2 block text-xs">Avance {porcentaje(m.progreso.porcentaje_avance)} · Saldo {moneda(m.cuenta.saldo_pendiente)}</span>
                  </button>
                </li>
              ))}
            </ul>

            <div className="tarjeta p-5">
              {detalle.cargando && !detalle.datos ? <Cargando /> : detalle.error ? <Alerta tipo="peligro">{detalle.error}</Alerta> : detalle.datos && (
                <>
                  <h2 className="text-xl font-bold">{detalle.datos.programa.nombre}</h2>
                  <p className="mb-4 text-sm text-tenue">
                    Matrícula {detalle.datos.numero_registro} · {fecha(detalle.datos.oferta.fecha_inicio)} – {fecha(detalle.datos.oferta.fecha_fin)}
                  </p>

                  {detalle.datos.estado === 'PENDIENTE_PAGO' && (
                    <Alerta tipo="alerta" className="mb-4">Su matrícula está pendiente de pago. Al confirmarse el pago inicial quedará inscrito en sus materias. <Boton a="/mis-pagos" tamano="sm" className="mt-2">Pagar ahora</Boton></Alerta>
                  )}
                  {detalle.datos.estado === 'FINALIZADA' && !detalle.datos.certificado && (
                    <Alerta className="mb-4">Finalizó su programa. El ICAP está validando sus requisitos académicos, financieros y documentales para emitir su certificado.</Alerta>
                  )}
                  {detalle.datos.certificado && (
                    <Alerta tipo="exito" className="mb-4">Su certificado <strong>{detalle.datos.certificado.codigo_unico}</strong> ya fue emitido. <Boton a="/mis-certificados" tamano="sm" className="mt-2">Ver mis certificados</Boton></Alerta>
                  )}

                  <ProgresoAcademico progreso={detalle.datos.progreso} />
                </>
              )}
            </div>
          </div>
        )}
      </Estado>
    </>
  )
}
