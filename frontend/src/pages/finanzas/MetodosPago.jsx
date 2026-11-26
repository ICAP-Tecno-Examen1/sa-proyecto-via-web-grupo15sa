import { Ban, Check } from 'lucide-react'
import api from '../../api/cliente'
import { Alerta, Insignia } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import CrudSimple from '../../components/ui/CrudSimple'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { maximo, minimo, requerido, requeridoSi } from '../../utils/validacion'

const TIPOS = [
  { valor: 'EFECTIVO', etiqueta: 'Efectivo (lo registra el personal)' },
  { valor: 'COMPROBANTE', etiqueta: 'Comprobante (transferencia o QR bancario)' },
  { valor: 'PASARELA', etiqueta: 'Pago en línea con tarjeta (Stripe)' },
]
const NOMBRE_TIPO = { EFECTIVO: 'Efectivo', COMPROBANTE: 'Con comprobante', PASARELA: 'Tarjeta · Stripe' }
const esPasarela = (v) => v.tipo === 'PASARELA'

// Formatos de las credenciales de Stripe (la API valida lo mismo)
const claveSecreta = (v) => (v && !/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/.test(v.trim()) ? 'Debe empezar con sk_test_ (modo prueba) o sk_live_ (modo real).' : null)
const secretoWebhook = (v) => (v && !/^whsec_[A-Za-z0-9]+$/.test(v.trim()) ? 'Debe empezar con whsec_.' : null)
const clavePublica = (v) => (v && !/^pk_(test|live)_[A-Za-z0-9]+$/.test(v.trim()) ? 'Debe empezar con pk_test_ (modo prueba) o pk_live_ (modo real).' : null)

/**
 * CU4 · Métodos de pago y credenciales de Stripe (requisito 10).
 * Las claves no se muestran después de guardarlas: para cambiarlas se escriben de nuevo.
 */
export default function MetodosPago() {
  const auth = useAuth()
  const avisar = useAvisos()
  const lista = useConsulta('/metodos-pago')
  const urlWebhook = lista.datos?.find((m) => m.url_webhook)?.url_webhook

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
      descripcion="Formas de pago del ICAP. Para cobrar con tarjeta en línea, cargue las credenciales de Stripe en el método «Pago en línea con tarjeta»."
      singular="método de pago"
      recurso="/metodos-pago"
      id="id_metodo_pago"
      buscar={false}
      permisoGestion="metodos_pago.gestionar"
      anchoModal="lg"
      nota={
        <Alerta className="mb-4" titulo="Cómo conectar Stripe">
          <ol className="mt-1 list-decimal space-y-1 pl-5">
            <li>En <strong>dashboard.stripe.com</strong> → <em>Developers → API keys</em>, copie la <strong>Secret key</strong> (<code>sk_test_…</code> para pruebas, <code>sk_live_…</code> para cobrar de verdad) y la <strong>Publishable key</strong> (<code>pk_test_…</code>): con esta última el formulario de la tarjeta aparece dentro del sistema.</li>
            <li>En <em>Developers → Webhooks → Add endpoint</em>, registre la URL {urlWebhook ? <code className="break-all">{urlWebhook}</code> : <code>…/api/pagos/stripe/webhook</code>} con el evento <code>checkout.session.completed</code> y copie su <strong>Signing secret</strong> (<code>whsec_…</code>).</li>
            <li>Edite el método de tarjeta, pegue las claves y déjelo activo.</li>
          </ol>
          <p className="mt-2 text-xs">Sin webhook (por ejemplo, en una computadora local) el pago igual se confirma cuando el estudiante vuelve de Stripe. Tarjeta de prueba: 4242 4242 4242 4242, cualquier fecha futura y CVC.</p>
        </Alerta>
      }
      columnas={[
        { clave: 'nombre', titulo: 'Método', render: (m) => (<><span className="font-semibold">{m.nombre}</span><span className="block max-w-md truncate text-xs text-tenue">{m.descripcion}</span></>) },
        { clave: 'tipo', titulo: 'Tipo', render: (m) => NOMBRE_TIPO[m.tipo] },
        {
          clave: 'electronico', titulo: 'Stripe', render: (m) => (m.electronico ? (
            <span className="flex flex-wrap gap-1">
              <Insignia estado={m.electronico.tiene_credenciales ? 'ACTIVO' : 'PENDIENTE'}>{m.electronico.tiene_credenciales ? 'Clave cargada' : 'Falta la clave'}</Insignia>
              {m.electronico.modo && <Insignia tono={m.electronico.modo === 'REAL' ? 'exito' : 'alerta'}>{m.electronico.modo === 'REAL' ? 'Modo real' : 'Modo prueba'}</Insignia>}
              {m.electronico.tiene_credenciales && <Insignia tono={m.electronico.pago_integrado ? 'informacion' : 'neutro'}>{m.electronico.pago_integrado ? 'En el sistema' : 'En la página de Stripe'}</Insignia>}
              {m.electronico.tiene_credenciales && !m.electronico.tiene_webhook && <Insignia tono="neutro">Sin webhook</Insignia>}
            </span>
          ) : '—'),
        },
        { clave: 'pagos_count', titulo: 'Pagos', alinear: 'derecha' },
        { clave: 'activo', titulo: 'Estado', render: (m) => <Insignia estado={m.activo ? 'ACTIVO' : 'INACTIVO'} /> },
      ]}
      inicial={{ nombre: '', descripcion: '', tipo: 'COMPROBANTE', activo: true, stripe_clave_secreta: '', stripe_clave_publica: '', stripe_secreto_webhook: '' }}
      reglas={{
        nombre: [requerido(), minimo(3), maximo(100)],
        descripcion: [maximo(1000)],
        tipo: [requerido()],
        stripe_clave_secreta: [requeridoSi((v) => esPasarela(v) && !v.id_metodo_pago, 'Escriba la clave secreta de Stripe (sk_test_… o sk_live_…).'), claveSecreta],
        stripe_clave_publica: [clavePublica],
        stripe_secreto_webhook: [secretoWebhook],
      }}
      campos={[
        { nombre: 'nombre', etiqueta: 'Nombre', requerido: true },
        { nombre: 'tipo', etiqueta: 'Tipo de método', tipo: 'select', opciones: TIPOS, vacio: null, requerido: true },
        { nombre: 'descripcion', etiqueta: 'Instrucciones para quien paga', tipo: 'textarea', ancho: 2, ayuda: 'Por ejemplo, los datos de la cuenta bancaria.' },
        { nombre: 'stripe_clave_secreta', etiqueta: 'Clave secreta de Stripe (Secret key)', tipo: 'password', autoComplete: 'off', ancho: 2, visible: esPasarela,
          ayuda: 'sk_test_… para pruebas o sk_live_… para cobros reales. Al editar, déjela vacía para conservar la actual.' },
        { nombre: 'stripe_clave_publica', etiqueta: 'Clave publicable de Stripe (Publishable key)', autoComplete: 'off', ancho: 2, visible: esPasarela,
          ayuda: 'pk_test_… o pk_live_…. Permite pagar con tarjeta dentro del sistema; sin ella se usa la página de Stripe. Vacía = conservar la actual.' },
        { nombre: 'stripe_secreto_webhook', etiqueta: 'Secreto del webhook (Signing secret)', tipo: 'password', autoComplete: 'off', ancho: 2, visible: esPasarela,
          ayuda: 'whsec_… del endpoint registrado en Stripe. Opcional, pero recomendado: confirma el pago aunque el estudiante cierre la página.' },
        { nombre: 'activo', etiqueta: 'Método habilitado', tipo: 'checkbox', ancho: 2 },
      ]}
      aFormulario={(m) => ({ id_metodo_pago: m.id_metodo_pago, nombre: m.nombre, descripcion: m.descripcion ?? '', tipo: m.tipo, activo: m.activo, stripe_clave_secreta: '', stripe_clave_publica: '', stripe_secreto_webhook: '' })}
      aEnvio={({ id_metodo_pago: _id, ...v }) => ({ ...v, stripe_clave_secreta: v.stripe_clave_secreta.trim() || null, stripe_clave_publica: v.stripe_clave_publica.trim() || null, stripe_secreto_webhook: v.stripe_secreto_webhook.trim() || null })}
      accionesFila={(m, recargar) => auth.puede('metodos_pago.gestionar') && (
        <Boton tamano="sm" variante="fantasma" icono={m.activo ? Ban : Check} className={m.activo ? 'text-peligro' : 'text-exito'} onClick={() => alternar(m, recargar)}>
          {m.activo ? 'Desactivar' : 'Activar'}
        </Boton>
      )}
    />
  )
}
