import api from '../../api/cliente'
import { camposPersonales, limpiarPersona, reglasPersonales } from '../../components/acceso/persona'
import { Alerta, Dato, EncabezadoPagina, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useFormulario } from '../../hooks/useFormulario'
import { fechaHora } from '../../utils/formato'
import { igualA, password, requerido } from '../../utils/validacion'

/** CU1 · Consulta y actualización de los datos personales y de la contraseña. */
export default function Perfil() {
  const auth = useAuth()
  const avisar = useAvisos()
  const u = auth.usuario

  const datos = useFormulario(
    Object.fromEntries(camposPersonales.map((c) => [c.nombre, u[c.nombre] ?? ''])),
    reglasPersonales,
  )

  const clave = useFormulario(
    { password_actual: '', password: '', password_confirmation: '' },
    {
      password_actual: [requerido()],
      password: [requerido(), password(), (v, t) => (v && v === t.password_actual ? 'La nueva contraseña debe ser distinta a la actual.' : null)],
      password_confirmation: [requerido(), igualA('password', 'Las contraseñas no coinciden.')],
    },
  )

  const guardarDatos = datos.enviar(async (valores) => {
    await api.put('/perfil', limpiarPersona(valores))
    await auth.refrescar()
    avisar.exito('Sus datos fueron actualizados.')
  })

  const guardarClave = clave.enviar(async (valores) => {
    await api.put('/perfil/password', valores)
    clave.reiniciar()
    avisar.exito('Contraseña actualizada.')
  })

  return (
    <>
      <EncabezadoPagina titulo="Mi perfil" descripcion="Mantenga sus datos al día: se usan en sus matrículas, pagos y certificados." />

      <div className="grid gap-5 lg:grid-cols-3">
        <Tarjeta titulo="Datos personales" className="lg:col-span-2">
          <form onSubmit={guardarDatos} noValidate>
            {datos.errorGeneral && <Alerta tipo="peligro" className="mb-4">{datos.errorGeneral}</Alerta>}
            <div className="grid gap-4 sm:grid-cols-2">
              {camposPersonales.map((c) => <Campo key={c.nombre} {...c} {...datos.campo(c.nombre)} />)}
            </div>
            <div className="mt-5 flex justify-end">
              <Boton type="submit" cargando={datos.enviando}>Guardar cambios</Boton>
            </div>
          </form>
        </Tarjeta>

        <div className="space-y-5">
          <Tarjeta titulo="Mi cuenta">
            <dl className="space-y-3">
              <Dato etiqueta="Usuario">{u.username}</Dato>
              <Dato etiqueta="Roles">
                <span className="flex flex-wrap gap-1">{auth.roles.map((r) => <Insignia key={r.id_rol} tono={r.id_rol === auth.rolActivo?.id_rol ? 'exito' : 'neutro'}>{r.nombre}</Insignia>)}</span>
              </Dato>
              <Dato etiqueta="Registrado el">{fechaHora(u.fecha_registro)}</Dato>
            </dl>
          </Tarjeta>

          <Tarjeta titulo="Cambiar contraseña">
            <form onSubmit={guardarClave} noValidate className="space-y-4">
              {clave.errorGeneral && <Alerta tipo="peligro">{clave.errorGeneral}</Alerta>}
              <Campo {...clave.campo('password_actual')} etiqueta="Contraseña actual" tipo="password" requerido autoComplete="current-password" />
              <Campo {...clave.campo('password')} etiqueta="Nueva contraseña" tipo="password" requerido autoComplete="new-password" ayuda="Mínimo 8 caracteres, con letras y números." />
              <Campo {...clave.campo('password_confirmation')} etiqueta="Repetir nueva contraseña" tipo="password" requerido autoComplete="new-password" />
              <Boton type="submit" variante="secundario" cargando={clave.enviando} className="w-full">Actualizar contraseña</Boton>
            </form>
          </Tarjeta>
        </div>
      </div>
    </>
  )
}
