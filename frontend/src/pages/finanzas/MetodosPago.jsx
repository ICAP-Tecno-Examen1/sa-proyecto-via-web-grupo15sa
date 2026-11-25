import { Ban, Check } from 'lucide-react'
import api from '../../api/cliente'
import { Alerta, Insignia } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import CrudSimple from '../../components/ui/CrudSimple'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { maximo, minimo, requerido, requeridoSi } from '../../utils/validacion'

const TIPOS = [
  { valor: 'EFECTIVO', etiqueta: 'Efectivo (lo registra el personal)' },
  { valor: 'COMPROBANTE', etiqueta: 'Comprobante (transferencia o QR bancario)' },
  { valor: 'PASARELA', etiqueta: 'Pasarela en línea (QR PagoFácil)' },
]
const NOMBRE_TIPO = { EFECTIVO: 'Efectivo', COMPROBANTE: 'Con comprobante', PASARELA: 'PagoFácil (QR)' }
const esPasarela = (v) => v.tipo === 'PASARELA'

/**
 * CU4 · Métodos de pago y credenciales de PagoFácil (requisito 10), como en el proyecto de referencia.
 * Las credenciales no se muestran después de guardarlas: para cambiarlas se escriben de nuevo.
 */
export default function MetodosPago() {
  const auth = useAuth()
  const avisar = useAvisos()

  const alternar = async (metodo, recargar) => {
    try {
      await api.patch(`/metodos-pago/${metodo.id_metodo_pago}/estado`)
      avisar.exito(metodo.activo ? 'Método desactivado: ya no se ofrece al pagar.' : 'Método activado.')
      recargar()
    } catch (e) {
      avisar.error(e)
    }
  }

  return (
    <CrudSimple
      titulo="Métodos de pago"
      descripcion="Formas de pago del ICAP. Para cobrar con QR, cargue las credenciales de PagoFácil en el método «Pasarela en línea»."
      singular="método de pago"
      recurso="/metodos-pago"
      id="id_metodo_pago"
      buscar={false}
      permisoGestion="metodos_pago.gestionar"
      nota={
        <Alerta className="mb-4" titulo="PagoFácil">
          Al escribir el <strong>Token Service</strong> y el <strong>Token Secret</strong> y dejar el método activo, los estudiantes pueden pagar con QR.
          Solo puede haber un método de PagoFácil activo a la vez.
        </Alerta>
      }
      columnas={[
        { clave: 'nombre', titulo: 'Método', render: (m) => (<><span className="font-semibold">{m.nombre}</span><span className="block max-w-md truncate text-xs text-tenue">{m.descripcion}</span></>) },
        { clave: 'tipo', titulo: 'Tipo', render: (m) => NOMBRE_TIPO[m.tipo] },
        { clave: 'electronico', titulo: 'Credenciales', render: (m) => (m.electronico
          ? <Insignia estado={m.electronico.tiene_credenciales ? 'ACTIVO' : 'PENDIENTE'}>{m.electronico.tiene_credenciales ? 'Cargadas' : 'Faltan'}</Insignia>
          : '—') },
        { clave: 'pagos_count', titulo: 'Pagos', alinear: 'derecha' },
        { clave: 'activo', titulo: 'Estado', render: (m) => <Insignia estado={m.activo ? 'ACTIVO' : 'INACTIVO'} /> },
      ]}
      inicial={{ nombre: '', descripcion: '', tipo: 'COMPROBANTE', activo: true, tc_token_service: '', tc_token_secret: '', callback_url: '' }}
      reglas={{
        nombre: [requerido(), minimo(3), maximo(100)],
        descripcion: [maximo(1000)],
        tipo: [requerido()],
        tc_token_service: [requeridoSi((v) => esPasarela(v) && !v.id_metodo_pago, 'Escriba el Token Service de PagoFácil.')],
        tc_token_secret: [requeridoSi((v) => esPasarela(v) && !v.id_metodo_pago, 'Escriba el Token Secret de PagoFácil.')],
        callback_url: [(v) => (v && !/^https?:\/\/.+/i.test(v) ? 'Debe ser una dirección web completa (https://…).' : null)],
      }}
      campos={[
        { nombre: 'nombre', etiqueta: 'Nombre', requerido: true },
        { nombre: 'tipo', etiqueta: 'Tipo de método', tipo: 'select', opciones: TIPOS, vacio: null, requerido: true },
        { nombre: 'descripcion', etiqueta: 'Instrucciones para quien paga', tipo: 'textarea', ancho: 2, ayuda: 'Por ejemplo, los datos de la cuenta bancaria.' },
        { nombre: 'tc_token_service', etiqueta: 'Token Service (tcTokenService)', tipo: 'password', autoComplete: 'off', visible: esPasarela, ayuda: 'Déjelo vacío para conservar el actual.' },
        { nombre: 'tc_token_secret', etiqueta: 'Token Secret (tcTokenSecret)', tipo: 'password', autoComplete: 'off', visible: esPasarela, ayuda: 'Déjelo vacío para conservar el actual.' },
        { nombre: 'callback_url', etiqueta: 'URL de notificación (callback)', ancho: 2, visible: esPasarela, ayuda: 'Dirección pública de …/api/pagos/pagofacil/callback. Vacío = la configurada en el servidor.' },
        { nombre: 'activo', etiqueta: 'Método habilitado', tipo: 'checkbox', ancho: 2 },
      ]}
      aFormulario={(m) => ({ nombre: m.nombre, descripcion: m.descripcion ?? '', tipo: m.tipo, activo: m.activo, tc_token_service: '', tc_token_secret: '', callback_url: m.electronico?.callback_url ?? '' })}
      aEnvio={(v) => ({ ...v, callback_url: v.callback_url || null })}
      accionesFila={(m, recargar) => auth.puede('metodos_pago.gestionar') && (
        <Boton tamano="sm" variante="fantasma" icono={m.activo ? Ban : Check} className={m.activo ? 'text-peligro' : 'text-exito'} onClick={() => alternar(m, recargar)}>
          {m.activo ? 'Desactivar' : 'Activar'}
        </Boton>
      )}
    />
  )
}
