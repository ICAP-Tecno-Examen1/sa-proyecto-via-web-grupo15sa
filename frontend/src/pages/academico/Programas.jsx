import { Insignia } from '../../components/ui/Basicos'
import CrudSimple from '../../components/ui/CrudSimple'
import { legible } from '../../utils/formato'
import { codigo, maximo, minimo, requerido } from '../../utils/validacion'

// Los mismos tipos que acepta la API (Programa::TIPOS). Las carreras son TÉCNICO MEDIO / SUPERIOR.
export const TIPOS_PROGRAMA = ['CURSO', 'TALLER', 'SEMINARIO', 'DIPLOMADO', 'TECNICO_MEDIO', 'TECNICO_SUPERIOR', 'PROGRAMA_TECNICO']
  .map((t) => ({ valor: t, etiqueta: legible(t) }))
const ESTADOS = [{ valor: 'ACTIVO', etiqueta: 'Activo' }, { valor: 'INACTIVO', etiqueta: 'Inactivo' }]

/** CU2 · Catálogo de programas académicos. */
export default function Programas() {
  return (
    <CrudSimple
      titulo="Programas académicos"
      descripcion="Primer paso del catálogo: registre el programa o carrera; luego sus materias (Módulos), su malla curricular y sus ofertas."
      singular="programa"
      recurso="/programas"
      id="id_programa"
      permisoGestion="programas.gestionar"
      filtros={[{ nombre: 'estado', etiqueta: 'Todos los estados', opciones: ESTADOS }]}
      columnas={[
        { clave: 'codigo', titulo: 'Código', clase: 'font-semibold' },
        { clave: 'nombre', titulo: 'Programa', render: (p) => (<><span className="font-semibold">{p.nombre}</span><span className="block max-w-md truncate text-xs text-tenue">{p.descripcion}</span></>) },
        { clave: 'tipo', titulo: 'Tipo', render: (p) => legible(p.tipo) },
        { clave: 'mallas_count', titulo: 'Mallas', alinear: 'derecha' },
        { clave: 'estado', titulo: 'Estado', render: (p) => <Insignia estado={p.estado} /> },
      ]}
      inicial={{ codigo: '', nombre: '', tipo: '', estado: 'ACTIVO', descripcion: '' }}
      reglas={{
        codigo: [requerido(), minimo(2), maximo(50), codigo()],
        nombre: [requerido(), minimo(3), maximo(200)],
        tipo: [requerido()],
        estado: [requerido()],
        descripcion: [maximo(2000)],
      }}
      campos={[
        { nombre: 'codigo', etiqueta: 'Código', requerido: true, ayuda: 'Ejemplo: OFI-01' },
        { nombre: 'tipo', etiqueta: 'Tipo de programa', tipo: 'select', opciones: TIPOS_PROGRAMA, requerido: true },
        { nombre: 'nombre', etiqueta: 'Nombre del programa', requerido: true, ancho: 2 },
        { nombre: 'descripcion', etiqueta: 'Descripción', tipo: 'textarea', ancho: 2, ayuda: 'Se muestra en el catálogo público.' },
        { nombre: 'estado', etiqueta: 'Estado', tipo: 'select', opciones: ESTADOS, vacio: null, requerido: true },
      ]}
      aFormulario={(p) => ({ ...p, descripcion: p.descripcion ?? '' })}
    />
  )
}
