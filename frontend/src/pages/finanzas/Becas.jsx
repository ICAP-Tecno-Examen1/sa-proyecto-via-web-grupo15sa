import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Alerta, Estado, Insignia, Pestanas, Tarjeta } from '../../components/ui/Basicos'
import CrudSimple from '../../components/ui/CrudSimple'
import Tabla from '../../components/ui/Tabla'
import { useConsulta } from '../../hooks/useConsulta'
import { fecha, porcentaje } from '../../utils/formato'
import { entre, maximo, minimo, numero, requerido } from '../../utils/validacion'

/** CU4 · Becas y descuentos: catálogo y becas asignadas a estudiantes. */
export default function Becas() {
  const [pestana, setPestana] = useState('catalogo')

  return (
    <>
      {pestana === 'catalogo' ? (
        <CrudSimple
          titulo="Becas y descuentos"
          descripcion="Catálogo de becas. La asignación a un estudiante se hace desde la ficha de su matrícula y recalcula sus cuotas pendientes."
          singular="beca"
          recurso="/becas"
          id="id_beca"
          buscar={false}
          permisoGestion="becas.gestionar"
          nota={<SelectorPestana pestana={pestana} alCambiar={setPestana} />}
          columnas={[
            { clave: 'nombre', titulo: 'Beca', render: (b) => (<><span className="font-semibold">{b.nombre}</span><span className="block max-w-md truncate text-xs text-tenue">{b.descripcion}</span></>) },
            { clave: 'institucion_financiadora', titulo: 'Institución financiadora' },
            { clave: 'porcentaje', titulo: 'Descuento', alinear: 'derecha', render: (b) => <strong>{porcentaje(b.porcentaje)}</strong> },
            { clave: 'asignaciones_count', titulo: 'Estudiantes becados', alinear: 'derecha' },
          ]}
          inicial={{ nombre: '', porcentaje: '', institucion_financiadora: '', descripcion: '' }}
          reglas={{
            nombre: [requerido(), minimo(3), maximo(200)],
            porcentaje: [requerido(), numero(), entre(0.01, 100)],
            institucion_financiadora: [maximo(200)],
            descripcion: [maximo(1000)],
          }}
          campos={[
            { nombre: 'nombre', etiqueta: 'Nombre de la beca', requerido: true, ancho: 2 },
            { nombre: 'porcentaje', etiqueta: 'Porcentaje de descuento', tipo: 'number', min: '0.01', max: 100, step: '0.01', requerido: true },
            { nombre: 'institucion_financiadora', etiqueta: 'Institución financiadora' },
            { nombre: 'descripcion', etiqueta: 'Descripción', tipo: 'textarea', ancho: 2 },
          ]}
          aFormulario={(b) => ({ ...b, descripcion: b.descripcion ?? '', institucion_financiadora: b.institucion_financiadora ?? '' })}
          aEnvio={(v) => ({ ...v, porcentaje: Number(v.porcentaje) })}
        />
      ) : <Asignadas pestana={pestana} alCambiar={setPestana} />}
    </>
  )
}

function SelectorPestana({ pestana, alCambiar }) {
  return <Pestanas activa={pestana} alCambiar={alCambiar} pestanas={[{ clave: 'catalogo', etiqueta: 'Catálogo de becas' }, { clave: 'asignadas', etiqueta: 'Becas asignadas' }]} />
}

function Asignadas({ pestana, alCambiar }) {
  const consulta = useConsulta('/becas-asignadas')
  const navegar = useNavigate()

  return (
    <>
      <h1 className="mb-5 text-2xl font-bold">Becas y descuentos</h1>
      <SelectorPestana pestana={pestana} alCambiar={alCambiar} />
      <Alerta className="mb-4">Para asignar una beca abra la matrícula del estudiante (Inscripciones › Matrículas) y use la pestaña «Becas».</Alerta>
      <Tarjeta relleno={false}>
        <Estado consulta={consulta}>
          {(filas) => (
            <Tabla
              id="id_beca_estudiante"
              filas={filas}
              vacio="Aún no se asignaron becas."
              alSeleccionar={(b) => navegar(`/matriculas/${b.matricula.id_matricula}`)}
              columnas={[
                { clave: 'fecha_asignacion', titulo: 'Asignada', render: (b) => fecha(b.fecha_asignacion) },
                { clave: 'estudiante', titulo: 'Estudiante', render: (b) => (<>{b.matricula.estudiante.nombre_completo}<span className="block text-xs text-tenue">{b.matricula.numero_registro}</span></>) },
                { clave: 'programa', titulo: 'Programa', render: (b) => b.matricula.programa.nombre },
                { clave: 'beca', titulo: 'Beca' },
                { clave: 'porcentaje_aplicado', titulo: 'Porcentaje', alinear: 'derecha', render: (b) => porcentaje(b.porcentaje_aplicado) },
                { clave: 'estado', titulo: 'Estado', render: (b) => <Insignia estado={b.estado} /> },
              ]}
            />
          )}
        </Estado>
      </Tarjeta>
    </>
  )
}
