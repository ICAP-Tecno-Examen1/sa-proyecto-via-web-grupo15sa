import { Link, useLocation } from 'react-router-dom'
import { camposAcceso, camposPersonales, limpiarPersona, personaInicial, reglasConAcceso } from '../../components/acceso/persona'
import { Alerta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import { useAuth } from '../../context/AuthContext'
import { useFormulario } from '../../hooks/useFormulario'

/** CU1 · Registro del estudiante desde la página web. */
export default function Registro() {
  const auth = useAuth()
  const { state } = useLocation()
  const f = useFormulario(personaInicial, reglasConAcceso(false))
  const registrar = f.enviar((valores) => auth.registrarse(limpiarPersona(valores)))

  return (
    <div className="mx-auto max-w-3xl py-2">
      <div className="tarjeta p-6 sm:p-8">
        <h1 className="text-2xl font-extrabold">Registro de estudiantes</h1>
        <p className="mt-1 mb-5 text-sm text-tenue">Cree su cuenta para preinscribirse, pagar en línea y consultar sus notas y certificados.</p>

        {f.errorGeneral && <Alerta tipo="peligro" className="mb-4">{f.errorGeneral}</Alerta>}

        <form onSubmit={registrar} noValidate>
          <h2 className="mb-3 text-sm font-bold tracking-wide text-tenue uppercase">Datos personales</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            {camposPersonales.map((c) => <Campo key={c.nombre} {...c} {...f.campo(c.nombre)} />)}
          </div>

          <h2 className="mt-6 mb-3 text-sm font-bold tracking-wide text-tenue uppercase">Datos de acceso</h2>
          <div className="grid gap-4 sm:grid-cols-3">
            {camposAcceso.map((c) => <Campo key={c.nombre} {...c} {...f.campo(c.nombre)} requerido />)}
          </div>

          <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-tenue">
              ¿Ya tiene cuenta? <Link to="/ingresar" state={state} className="font-bold text-primario hover:underline">Inicie sesión</Link>
            </p>
            <Boton type="submit" tamano="lg" cargando={f.enviando}>Crear mi cuenta</Boton>
          </div>
        </form>
      </div>
    </div>
  )
}
