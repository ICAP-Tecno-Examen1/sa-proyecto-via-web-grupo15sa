import Grafico, { Indicador } from '../../components/graficos/Grafico'
import { ArrowRight } from 'lucide-react'
import { useLocation } from 'react-router-dom'
import { Alerta, EncabezadoPagina, Estado, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { useAuth } from '../../context/AuthContext'
import { useConsulta } from '../../hooks/useConsulta'

/**
 * CU8 · Panel de estadísticas (requisito 8).
 * La API decide qué bloques mostrar según los permisos del rol activo; aquí
 * solo se dibujan indicadores y gráficos tal como llegan.
 */
export default function Panel() {
  const auth = useAuth()
  const consulta = useConsulta('/panel')
  // Página que la persona quería abrir antes de iniciar sesión (ver Guardia en App.jsx)
  const continuar = useLocation().state?.continuar

  return (
    <>
      <EncabezadoPagina titulo={`Hola, ${auth.usuario.nombres}`} descripcion={`Panel de ${auth.rolActivo?.nombre} · Gestión ${new Date().getFullYear()}`}>
        {auth.puede('reportes.administrativos|reportes.academicos|reportes.financieros|reportes.docente') && <Boton a="/reportes" variante="secundario">Ir a reportes</Boton>}
      </EncabezadoPagina>

      {continuar && (
        <Alerta className="mb-5" titulo="¿Seguir donde estaba?">
          <span className="flex flex-wrap items-center justify-between gap-2">
            Antes de iniciar sesión estaba abriendo otra página.
            <Boton a={continuar} tamano="sm" variante="secundario">Continuar <ArrowRight className="size-4" aria-hidden /></Boton>
          </span>
        </Alerta>
      )}

      <Estado consulta={consulta}>
        {({ bloques }) => bloques.length === 0
          ? <div className="tarjeta"><Vacio titulo="Su rol aún no tiene indicadores asignados">Use el menú lateral para acceder a sus funciones.</Vacio></div>
          : (
            <div className="space-y-8">
              {bloques.map((bloque) => (
                <section key={bloque.titulo} aria-label={bloque.titulo}>
                  <h2 className="mb-3 text-lg font-bold">{bloque.titulo}</h2>
                  <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {bloque.tarjetas.map((t) => <Indicador key={t.titulo} tarjeta={t} />)}
                  </div>
                  {bloque.graficos.length > 0 && (
                    <div className={`mt-4 grid gap-4 ${bloque.graficos.length > 1 ? 'lg:grid-cols-2' : ''}`}>
                      {bloque.graficos.map((g) => <Grafico key={g.titulo} grafico={g} />)}
                    </div>
                  )}
                </section>
              ))}
            </div>
          )}
      </Estado>
    </>
  )
}
