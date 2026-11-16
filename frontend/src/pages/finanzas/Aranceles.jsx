import { Alerta, Insignia } from '../../components/ui/Basicos'
import CrudSimple from '../../components/ui/CrudSimple'
import { useConsulta } from '../../hooks/useConsulta'
import { describirPlan, legible, moneda } from '../../utils/formato'
import { entero, entre, maximo, minimo, noNegativo, numero, requerido, requeridoSi } from '../../utils/validacion'

const TIPOS = [{ valor: 'PAGO_UNICO', etiqueta: 'Pago único' }, { valor: 'MENSUALIDAD', etiqueta: 'Mensualidades' }]
const ESTADOS = [{ valor: 'ACTIVO', etiqueta: 'Activo' }, { valor: 'INACTIVO', etiqueta: 'Inactivo' }]

/** CU4 · Aranceles: el Coordinador Financiero define costos y modalidades de pago por oferta. */
export default function Aranceles() {
  const ofertas = useConsulta('/ofertas')
  const opcionesOferta = (ofertas.datos ?? [])
    .filter((o) => !['FINALIZADA', 'CANCELADA'].includes(o.estado))
    .map((o) => ({ valor: o.id_oferta, etiqueta: `${o.codigo} · ${o.programa.nombre} (${legible(o.estado)})` }))

  return (
    <CrudSimple
      titulo="Aranceles y planes de pago"
      descripcion="Costo de cada oferta académica y su forma de cobro. Una oferta puede tener varios planes (por ejemplo, contado y mensualidades) para que el estudiante elija."
      singular="plan de pago"
      recurso="/aranceles"
      id="id_arancel"
      buscar={false}
      permisoGestion="aranceles.gestionar"
      filtros={[{ nombre: 'id_oferta', etiqueta: 'Todas las ofertas', opciones: (ofertas.datos ?? []).map((o) => ({ valor: o.id_oferta, etiqueta: `${o.codigo} · ${o.programa.nombre}` })) }]}
      nota={<Alerta className="mb-4">Una oferta en planificación solo puede abrirse a preinscripciones cuando tiene al menos un plan de pago activo.</Alerta>}
      columnas={[
        { clave: 'oferta', titulo: 'Oferta', render: (a) => (<><span className="font-semibold">{a.oferta}</span><span className="block text-xs text-tenue">{a.programa} · Gestión {a.gestion}</span></>) },
        { clave: 'concepto', titulo: 'Concepto' },
        { clave: 'tipo_cobro', titulo: 'Forma de cobro', render: (a) => (<>{legible(a.tipo_cobro)}<span className="block text-xs text-tenue">{describirPlan(a)}</span></>) },
        { clave: 'monto', titulo: 'Monto total', alinear: 'derecha', render: (a) => <strong>{moneda(a.monto)}</strong> },
        { clave: 'mensualidades_count', titulo: 'Cuotas generadas', alinear: 'derecha' },
        { clave: 'estado', titulo: 'Estado', render: (a) => <Insignia estado={a.estado} /> },
      ]}
      inicial={{ id_oferta: '', concepto: '', monto: '', tipo_cobro: 'PAGO_UNICO', numero_cuotas: '', estado: 'ACTIVO' }}
      reglas={{
        id_oferta: [requerido('Seleccione la oferta académica.')],
        concepto: [requerido(), minimo(3), maximo(200)],
        monto: [requerido(), numero(), noNegativo()],
        tipo_cobro: [requerido()],
        numero_cuotas: [requeridoSi((v) => v.tipo_cobro === 'MENSUALIDAD', 'Indique en cuántas cuotas se paga.'), entero(), (v, t) => (t.tipo_cobro === 'MENSUALIDAD' ? entre(2, 36)(v) : null)],
        estado: [requerido()],
      }}
      campos={[
        { nombre: 'id_oferta', etiqueta: 'Oferta académica', tipo: 'select', opciones: opcionesOferta, requerido: true, ancho: 2, soloNuevo: true },
        { nombre: 'concepto', etiqueta: 'Concepto', requerido: true, ancho: 2, ayuda: 'Ejemplo: «Pago al contado» o «Plan de 4 mensualidades».' },
        { nombre: 'tipo_cobro', etiqueta: 'Forma de cobro', tipo: 'select', opciones: TIPOS, vacio: null, requerido: true },
        { nombre: 'monto', etiqueta: 'Monto total (Bs.)', tipo: 'number', min: 0, step: '0.01', requerido: true },
        { nombre: 'numero_cuotas', etiqueta: 'Número de cuotas', tipo: 'number', min: 2, max: 36, requerido: true, visible: (v) => v.tipo_cobro === 'MENSUALIDAD', ayuda: 'El monto total se divide en cuotas mensuales.' },
        { nombre: 'estado', etiqueta: 'Estado', tipo: 'select', opciones: ESTADOS, vacio: null, requerido: true },
      ]}
      aEnvio={(v) => ({ ...v, id_oferta: Number(v.id_oferta), monto: Number(v.monto), numero_cuotas: v.tipo_cobro === 'MENSUALIDAD' ? Number(v.numero_cuotas) : 1 })}
    />
  )
}
