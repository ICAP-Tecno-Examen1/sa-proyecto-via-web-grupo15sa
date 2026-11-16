import { Search } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { Alerta, EncabezadoPagina, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Campo from '../../components/ui/Campo'
import Tabla, { Paginacion } from '../../components/ui/Tabla'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { fecha, legible, moneda } from '../../utils/formato'

const ESTADOS = ['PENDIENTE_PAGO', 'ACTIVA', 'FINALIZADA', 'ANULADA']

/** CU3 · Matrículas: listado para Secretaría, coordinadores y Director. */
export default function Matriculas() {
  const navegar = useNavigate()
  const [parametros] = useSearchParams()
  const [filtros, setFiltros] = useState({ q: parametros.get('q') ?? '', estado: '', gestion: '' })
  const [pagina, setPagina] = useState(1)
  const lista = useConsulta('/matriculas', { ...filtros, q: useRetardo(filtros.q), page: pagina })
  const cambiar = (campo) => (valor) => (setFiltros((f) => ({ ...f, [campo]: valor })), setPagina(1))

  return (
    <>
      <EncabezadoPagina titulo="Matrículas" descripcion="Seguimiento de cada estudiante: estado de la matrícula, pagos, módulos inscritos y avance académico." />

      <Tarjeta relleno={false}>
        <div className="flex flex-wrap items-end gap-3 border-b p-3">
          <div className="relative min-w-56 flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" value={filtros.q} onChange={(e) => cambiar('q')(e.target.value)} placeholder="Buscar por estudiante, documento o N.º de registro…" aria-label="Buscar matrícula" className="campo-control pl-9" />
          </div>
          <Campo tipo="select" nombre="estado" valor={filtros.estado} alCambiar={cambiar('estado')} vacio="Todos los estados" aria-label="Estado" className="w-48"
            opciones={ESTADOS.map((e) => ({ valor: e, etiqueta: legible(e) }))} />
          <Campo tipo="number" nombre="gestion" valor={filtros.gestion} alCambiar={cambiar('gestion')} placeholder="Gestión" aria-label="Gestión" className="w-32" />
        </div>

        {lista.error ? <div className="p-4"><Alerta tipo="peligro">{lista.error}</Alerta></div> : (
          <Tabla
            id="id_matricula"
            filas={lista.datos?.data}
            cargando={lista.cargando}
            alSeleccionar={(m) => navegar(`/matriculas/${m.id_matricula}`)}
            columnas={[
              { clave: 'numero_registro', titulo: 'N.º registro', clase: 'font-semibold' },
              { clave: 'estudiante', titulo: 'Estudiante', render: (m) => (<>{m.estudiante.nombre_completo}<span className="block text-xs text-tenue">Doc. {m.estudiante.ci}</span></>) },
              { clave: 'programa', titulo: 'Programa', render: (m) => (<>{m.programa.nombre}<span className="block text-xs text-tenue">{m.oferta.codigo} · Gestión {m.oferta.gestion}</span></>) },
              { clave: 'fecha', titulo: 'Fecha', render: (m) => fecha(m.fecha) },
              { clave: 'tipo_cobro', titulo: 'Plan', render: (m) => legible(m.tipo_cobro) },
              { clave: 'monto_final', titulo: 'Total', alinear: 'derecha', render: (m) => moneda(m.monto_final) },
              { clave: 'estado', titulo: 'Estado', render: (m) => <Insignia estado={m.estado} /> },
            ]}
          />
        )}
        <Paginacion pagina={lista.datos} alCambiar={setPagina} />
      </Tarjeta>
    </>
  )
}
