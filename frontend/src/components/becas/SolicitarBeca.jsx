import { FileCheck, Send } from 'lucide-react'
import api, { aFormData } from '../../api/cliente'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { moneda, porcentaje } from '../../utils/formato'
import { archivo, maximo, requerido } from '../../utils/validacion'
import { Alerta } from '../ui/Basicos'
import Boton from '../ui/Boton'
import Campo from '../ui/Campo'
import Modal from '../ui/Modal'

/**
 * CU4 · El estudiante solicita una beca para su matrícula. Cada beca indica qué
 * documento hay que adjuntar; el Coordinador Financiero lo revisa y, si corresponde,
 * la aprueba: recién entonces se descuenta de las cuotas pendientes.
 */
export default function SolicitarBeca({ matricula, alCerrar, alTerminar }) {
  const avisar = useAvisos()
  // Solo las becas que aplican a esta matrícula: activas y que cubren su curso
  const becas = useConsulta(matricula ? '/becas/disponibles' : null, { id_matricula: matricula?.id_matricula })
  const requiereRespaldo = (v) => Boolean(becas.datos?.find((b) => String(b.id_beca) === String(v.id_beca))?.requiere_respaldo)
  const f = useFormulario({ id_beca: '', motivo: '', respaldo: null }, {
    id_beca: [requerido('Elija la beca que solicita.')],
    motivo: [maximo(1000)],
    respaldo: [(v, t) => (requiereRespaldo(t) && !v ? 'Las becas de una institución exigen su documento (credencial, carta o constancia).' : null), archivo()],
  })

  const beca = becas.datos?.find((b) => String(b.id_beca) === String(f.valores.id_beca))
  const saldo = matricula?.cuenta.resumen.saldo_pendiente ?? 0

  const enviar = f.enviar(async (v) => {
    await api.post(`/matriculas/${matricula.id_matricula}/solicitar-beca`, aFormData(v))
    avisar.exito('Solicitud enviada. El Coordinador Financiero revisará su documento; verá el resultado en «Mis pagos».')
    alTerminar?.()
    alCerrar()
  })

  if (!matricula) return null

  return (
    <Modal abierto titulo="Solicitar una beca" alCerrar={alCerrar}
      pie={<><Boton variante="secundario" onClick={alCerrar}>Cancelar</Boton><Boton icono={Send} onClick={enviar} cargando={f.enviando}>Enviar solicitud</Boton></>}>
      <form onSubmit={enviar} noValidate className="space-y-4">
        {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
        <p className="text-sm text-tenue">
          {matricula.programa.nombre} · matrícula {matricula.numero_registro}. La beca se aplica a las cuotas que aún no pagó ({moneda(saldo)} pendientes).
        </p>

        <Campo {...f.campo('id_beca')} tipo="select" etiqueta="Beca que solicita" requerido
          opciones={(becas.datos ?? []).map((b) => ({ valor: b.id_beca, etiqueta: `${b.nombre}${b.institucion_financiadora ? ` (${b.institucion_financiadora})` : ''} · ${porcentaje(b.porcentaje)}` }))} />
        {becas.datos?.length === 0 && <Alerta tipo="alerta">No hay becas disponibles para este curso. Si su institución o empresa tiene convenio con el ICAP, pida que registren su beca.</Alerta>}

        {beca && (
          <Alerta titulo="Qué debe presentar">
            <span className="flex items-start gap-2">
              <FileCheck className="mt-0.5 size-4 shrink-0" aria-hidden />
              <span>
                {beca.requisitos || 'Un documento que respalde que cumple las condiciones de la beca.'}
                {saldo > 0 && <> Si la aprueban, ahorraría hasta <strong>{moneda((saldo * beca.porcentaje) / 100)}</strong>.</>}
              </span>
            </span>
          </Alerta>
        )}

        <Campo {...f.campo('respaldo')} valor={undefined} tipo="file" etiqueta={beca?.requiere_respaldo ? 'Documento de la institución' : 'Documento de respaldo (opcional)'}
          requerido={Boolean(beca?.requiere_respaldo)} accept=".pdf,.jpg,.jpeg,.png,.webp"
          ayuda={beca?.institucion_financiadora ? `Credencial, carta o constancia de ${beca.institucion_financiadora}. PDF o foto, máximo 4 MB.` : 'PDF o foto, máximo 4 MB.'} />
        <Campo {...f.campo('motivo')} tipo="textarea" etiqueta="¿Por qué la solicita? (opcional)" maxLength={1000} />
      </form>
    </Modal>
  )
}
