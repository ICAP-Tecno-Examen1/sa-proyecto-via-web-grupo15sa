import { useState } from 'react'
import Grafico, { Indicador } from '../../components/graficos/Grafico'
import { EncabezadoPagina, Estado, Tarjeta } from '../../components/ui/Basicos'
import Campo from '../../components/ui/Campo'
import Tabla from '../../components/ui/Tabla'
import { useConsulta } from '../../hooks/useConsulta'

/** Requisito 8 · Flujo de accesos al sitio: visitas, sesiones, recursos e IP. */
export default function EstadisticasAccesos() {
  const [dias, setDias] = useState('30')
  const consulta = useConsulta('/estadisticas/accesos', { dias })

  return (
    <>
      <EncabezadoPagina titulo="Estadísticas de acceso" descripcion="Tráfico del sitio a partir del contador de visitas y de la bitácora de auditoría.">
        <Campo tipo="select" nombre="dias" valor={dias} alCambiar={setDias} vacio={null} aria-label="Periodo" className="w-44"
          opciones={[['7', 'Últimos 7 días'], ['14', 'Últimos 14 días'], ['30', 'Últimos 30 días'], ['90', 'Últimos 90 días']].map(([valor, etiqueta]) => ({ valor, etiqueta }))} />
      </EncabezadoPagina>

      <Estado consulta={consulta}>
        {(d) => (
          <div className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{d.tarjetas.map((t) => <Indicador key={t.titulo} tarjeta={t} />)}</div>
            <div className="grid gap-4 lg:grid-cols-2">{d.graficos.slice(0, 2).map((g) => <Grafico key={g.titulo} grafico={g} />)}</div>
            <Grafico grafico={d.graficos[2]} alto={Math.max(220, d.paginas.length * 34)} />

            <div className="grid gap-4 lg:grid-cols-2">
              <Tarjeta titulo="Recursos de la API con mayor acceso" relleno={false}>
                <Tabla id={(f) => f.metodo + f.recurso} filas={d.recursos} vacio="Sin actividad en el periodo." columnas={[
                  { clave: 'recurso', titulo: 'Recurso', render: (f) => <code className="text-xs">{f.metodo} /{f.recurso}</code> },
                  { clave: 'total', titulo: 'Accesos', alinear: 'derecha' },
                  { clave: 'denegados', titulo: 'Denegados', alinear: 'derecha' },
                ]} />
              </Tarjeta>
              <Tarjeta titulo="Direcciones IP con más actividad" relleno={false}>
                <Tabla id="ip" filas={d.ips} vacio="Sin actividad en el periodo." columnas={[
                  { clave: 'ip', titulo: 'IP', clase: 'tabular' },
                  { clave: 'exitosos', titulo: 'Sesiones', alinear: 'derecha' },
                  { clave: 'fallidos', titulo: 'Fallidos', alinear: 'derecha' },
                  { clave: 'denegados', titulo: 'Denegados', alinear: 'derecha' },
                  { clave: 'ultimo', titulo: 'Último acceso' },
                ]} />
              </Tarjeta>
              <Tarjeta titulo="Usuarios más activos" relleno={false} className="lg:col-span-2">
                <Tabla id="usuario" filas={d.usuarios} vacio="Sin actividad en el periodo." columnas={[
                  { clave: 'usuario', titulo: 'Usuario' },
                  { clave: 'sesiones', titulo: 'Sesiones iniciadas', alinear: 'derecha' },
                  { clave: 'total', titulo: 'Eventos registrados', alinear: 'derecha' },
                  { clave: 'ultimo', titulo: 'Última actividad' },
                ]} />
              </Tarjeta>
            </div>
          </div>
        )}
      </Estado>
    </>
  )
}
