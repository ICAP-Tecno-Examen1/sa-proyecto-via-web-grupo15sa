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
const NOMBRE_TIPO = { EFECTIVO: 'Efectivo', COMPROBANTE: 'Con comprobante', PASARELA: 'Pasarela QR' }
const MODOS = [
  { valor: 'SIMULADO', etiqueta: 'Simulado (demostración, sin dinero real)' },
  { valor: 'REAL', etiqueta: 'Real (PagoFácil, con credenciales)' },
]
const esPasarela = (v) => v.tipo === 'PASARELA'

/**
 * CU4 · Métodos de pago y pasarela digital (requisito 10).
 * Las credenciales de PagoFácil se guardan en la base de datos y nunca se
 * muestran: para cambiarlas se escriben de nuevo.
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
      descripcion="Formas de pago que acepta el ICAP y configuración de la pasarela digital PagoFácil para el cobro con QR."
      singular="método de pago"
      recurso="/metodos-pago"
      id="id_metodo_pago"
      buscar={false}
      permisoGestion="metodos_pago.gestionar"
      nota={
        <Alerta className="mb-4" titulo="Pasarela PagoFácil">
          En modo <strong>Real</strong> el sistema genera el QR en PagoFácil y confirma el pago cuando llega su notificación (webhook).
          En modo <strong>Simulado</strong> el QR abre una pasarela de demostración que recorre el mismo flujo sin mover dinero.
        </Alerta>
      }
      columnas={[
        { clave: 'nombre', titulo: 'Método', render: (m) => (<><span className="font-semibold">{m.nombre}</span><span className="block max-w-md truncate text-xs text-tenue">{m.descripcion}</span></>) },
        { clave: 'tipo', titulo: 'Tipo', render: (m) => NOMBRE_TIPO[m.tipo] },
        { clave: 'pasarela', titulo: 'Pasarela', render: (m) => m.pasarela ? (
          <span className="flex flex-wrap items-center gap-1">
            <Insignia estado={m.pasarela.modo_efectivo}>Modo {m.pasarela.modo_efectivo === 'REAL' ? 'real' : 'simulado'}</Insignia>
            <span className="text-xs text-tenue">{m.pasarela.tiene_credenciales ? 'Credenciales cargadas' : 'Sin credenciales'}</span>
          </span>
        ) : '—' },
        { clave: 'pagos_count', titulo: 'Pagos', alinear: 'derecha' },
        { clave: 'activo', titulo: 'Estado', render: (m) => <Insignia estado={m.activo ? 'ACTIVO' : 'INACTIVO'} /> },
      ]}
      inicial={{ nombre: '', descripcion: '', tipo: 'COMPROBANTE', activo: true, modo: 'SIMULADO', tc_token_service: '', tc_token_secret: '', callback_url: '' }}
      reglas={{
        nombre: [requerido(), minimo(3), maximo(100)],
        descripcion: [maximo(1000)],
        tipo: [requerido()],
        modo: [requeridoSi(esPasarela)],
        callback_url: [(v) => (v && !/^https?:\/\/.+/i.test(v) ? 'Debe ser una dirección web completa (https://…).' : null)],
      }}
      campos={[
        { nombre: 'nombre', etiqueta: 'Nombre', requerido: true },
        { nombre: 'tipo', etiqueta: 'Tipo de método', tipo: 'select', opciones: TIPOS, vacio: null, requerido: true },
        { nombre: 'descripcion', etiqueta: 'Instrucciones para quien paga', tipo: 'textarea', ancho: 2, ayuda: 'Por ejemplo, los datos de la cuenta bancaria.' },
        { nombre: 'modo', etiqueta: 'Modo de la pasarela', tipo: 'select', opciones: MODOS, vacio: null, ancho: 2, visible: esPasarela },
        { nombre: 'tc_token_service', etiqueta: 'Token Service (tcTokenService)', tipo: 'password', autoComplete: 'off', visible: esPasarela, ayuda: 'Déjelo vacío para conservar el actual.' },
        { nombre: 'tc_token_secret', etiqueta: 'Token Secret (tcTokenSecret)', tipo: 'password', autoComplete: 'off', visible: esPasarela, ayuda: 'Déjelo vacío para conservar el actual.' },
        { nombre: 'callback_url', etiqueta: 'URL de notificación (callback)', ancho: 2, visible: esPasarela, ayuda: 'Dirección pública de …/api/pagos/pasarela/callback. Vacío = la configurada en el servidor.' },
        { nombre: 'activo', etiqueta: 'Método habilitado', tipo: 'checkbox', ancho: 2 },
      ]}
      aFormulario={(m) => ({ nombre: m.nombre, descripcion: m.descripcion ?? '', tipo: m.tipo, activo: m.activo, modo: m.pasarela?.modo ?? 'SIMULADO', tc_token_service: '', tc_token_secret: '', callback_url: m.pasarela?.callback_url ?? '' })}
      aEnvio={(v) => ({ ...v, callback_url: v.callback_url || null, modo: v.tipo === 'PASARELA' ? v.modo : null })}
      accionesFila={(m, recargar) => auth.puede('metodos_pago.gestionar') && (
        <Boton tamano="sm" variante="fantasma" icono={m.activo ? Ban : Check} className={m.activo ? 'text-peligro' : 'text-exito'} onClick={() => alternar(m, recargar)}>
          {m.activo ? 'Desactivar' : 'Activar'}
        </Boton>
      )}
    />
  )
}
