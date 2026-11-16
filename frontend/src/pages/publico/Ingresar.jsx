import { LogIn } from 'lucide-react'
import { Link, useLocation } from 'react-router-dom'
import { Alerta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import { useAuth } from '../../context/AuthContext'
import { useFormulario } from '../../hooks/useFormulario'
import { maximo, requerido } from '../../utils/validacion'

/** CU1 · Inicio de sesión. Los intentos (exitosos y fallidos) quedan en la bitácora. */
export default function Ingresar() {
  const auth = useAuth()
  const { state } = useLocation()
  const f = useFormulario(
    { usuario: '', password: '' },
    { usuario: [requerido('Ingrese su usuario o correo.'), maximo(150)], password: [requerido('Ingrese su contraseña.')] },
  )

  // Al iniciar sesión, <Guardia> redirige solo: esta página es solo para invitados.
  const ingresar = f.enviar((valores) => auth.iniciarSesion(valores))

  return (
    <div className="mx-auto max-w-md py-6">
      <div className="tarjeta p-6 sm:p-8">
        <h1 className="text-2xl font-extrabold">Iniciar sesión</h1>
        <p className="mt-1 mb-5 text-sm text-tenue">Ingrese con su usuario o correo electrónico.</p>

        {state?.desde && !f.errorGeneral && <Alerta className="mb-4">Inicie sesión para continuar.</Alerta>}
        {f.errorGeneral && <Alerta tipo="peligro" className="mb-4">{f.errorGeneral}</Alerta>}

        <form onSubmit={ingresar} noValidate className="space-y-4">
          <Campo {...f.campo('usuario')} etiqueta="Usuario o correo" requerido autoComplete="username" autoFocus />
          <Campo {...f.campo('password')} etiqueta="Contraseña" tipo="password" requerido autoComplete="current-password" />
          <Boton type="submit" icono={LogIn} cargando={f.enviando} className="w-full" tamano="lg">Ingresar</Boton>
        </form>

        <p className="mt-5 text-center text-sm text-tenue">
          ¿Aún no tiene cuenta? <Link to="/registro" state={state} className="font-bold text-primario hover:underline">Regístrese como estudiante</Link>
        </p>
      </div>
    </div>
  )
}
