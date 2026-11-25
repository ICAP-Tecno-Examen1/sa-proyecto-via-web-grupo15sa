import CrudSimple from '../../components/ui/CrudSimple'
import { codigo, entero, entre, maximo, minimo, requerido } from '../../utils/validacion'

/** CU2 · Materias (tabla MODULO) que luego se combinan en las mallas curriculares. */
export default function Modulos() {
  return (
    <CrudSimple
      titulo="Materias"
      descripcion="Materias que componen los programas: cada una tiene su carga horaria y se califica por separado. En una carrera, la malla curricular las agrupa en módulos de 1 o 2 materias."
      singular="materia"
      femenino
      recurso="/modulos"
      id="id_modulo"
      permisoGestion="programas.gestionar"
      columnas={[
        { clave: 'codigo', titulo: 'Código', clase: 'font-semibold' },
        { clave: 'nombre', titulo: 'Materia', render: (m) => (<><span className="font-semibold">{m.nombre}</span><span className="block max-w-md truncate text-xs text-tenue">{m.descripcion}</span></>) },
        { clave: 'horas', titulo: 'Horas', alinear: 'derecha' },
        { clave: 'malla_modulos_count', titulo: 'Mallas que la usan', alinear: 'derecha' },
      ]}
      inicial={{ codigo: '', nombre: '', horas: '', descripcion: '' }}
      reglas={{
        codigo: [requerido(), minimo(2), maximo(50), codigo()],
        nombre: [requerido(), minimo(3), maximo(200)],
        horas: [requerido(), entero(), entre(1, 1000)],
        descripcion: [maximo(2000)],
      }}
      campos={[
        { nombre: 'codigo', etiqueta: 'Código', requerido: true },
        { nombre: 'horas', etiqueta: 'Carga horaria (horas)', tipo: 'number', min: 1, requerido: true },
        { nombre: 'nombre', etiqueta: 'Nombre de la materia', requerido: true, ancho: 2 },
        { nombre: 'descripcion', etiqueta: 'Descripción', tipo: 'textarea', ancho: 2 },
      ]}
      aFormulario={(m) => ({ ...m, descripcion: m.descripcion ?? '' })}
      aEnvio={(v) => ({ ...v, horas: Number(v.horas) })}
    />
  )
}
