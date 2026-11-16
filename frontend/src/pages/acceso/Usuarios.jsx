import { Ban, Check } from 'lucide-react'
import api from '../../api/cliente'
import { camposAcceso, camposPersonales, limpiarPersona, personaInicial, reglasConAcceso } from '../../components/acceso/persona'
import { Insignia } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import CrudSimple from '../../components/ui/CrudSimple'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { fecha } from '../../utils/formato'

export const columnasUsuario = [
  { clave: 'nombre_completo', titulo: 'Nombre', render: (u) => (<><span className="font-semibold">{u.apellidos}, {u.nombres}</span><span className="block text-xs text-tenue">{u.username}</span></>) },
  { clave: 'ci', titulo: 'Documento' },
  { clave: 'correo', titulo: 'Contacto', render: (u) => (<>{u.correo}<span className="block text-xs text-tenue">{u.telefono ?? 'Sin teléfono'}</span></>) },
  { clave: 'roles', titulo: 'Roles', render: (u) => <span className="flex flex-wrap gap-1">{u.roles.map((r) => <Insignia key={r.id_rol} tono="informacion">{r.nombre}</Insignia>)}</span> },
  { clave: 'estado', titulo: 'Estado', render: (u) => <Insignia estado={u.estado} /> },
  { clave: 'fecha_registro', titulo: 'Registro', render: (u) => fecha(u.fecha_registro) },
]

/** CU1 · Gestión de usuarios por el Director: datos, roles y activación. */
export default function Usuarios() {
  const auth = useAuth()
  const avisar = useAvisos()
  const roles = useConsulta('/roles')
  const opcionesRol = (roles.datos ?? []).map((r) => ({ valor: r.id_rol, etiqueta: r.nombre }))

  const cambiarEstado = async (usuario, recargar) => {
    try {
      await api.patch(`/usuarios/${usuario.id_usuario}/estado`, { estado: usuario.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' })
      avisar.exito(usuario.estado === 'ACTIVO' ? 'Usuario desactivado: ya no podrá ingresar.' : 'Usuario activado.')
      recargar()
    } catch (e) {
      avisar.error(e)
    }
  }

  return (
    <CrudSimple
      titulo="Usuarios"
      descripcion="Registre al personal y a los docentes, asigne sus roles y active o desactive cuentas."
      singular="usuario"
      recurso="/usuarios"
      id="id_usuario"
      paginado
      puedeEliminar={false}
      permisoGestion="usuarios.gestionar"
      columnas={columnasUsuario}
      filtros={[
        { nombre: 'id_rol', etiqueta: 'Todos los roles', opciones: opcionesRol },
        { nombre: 'estado', etiqueta: 'Todos los estados', opciones: [{ valor: 'ACTIVO', etiqueta: 'Activos' }, { valor: 'INACTIVO', etiqueta: 'Inactivos' }] },
      ]}
      inicial={{ ...personaInicial, roles: [] }}
      reglas={(editando) => ({ ...reglasConAcceso(editando), roles: [(v) => (v?.length ? null : 'Seleccione al menos un rol.')] })}
      campos={[
        ...camposPersonales,
        { nombre: 'roles', etiqueta: 'Roles', tipo: 'casillas', opciones: opcionesRol, requerido: true, ancho: 2, ayuda: 'Definen el menú y los permisos. Con más de un rol, el usuario elige con cuál trabajar.' },
        ...camposAcceso.map((c) => (c.nombre === 'password' ? { ...c, ayuda: 'Mínimo 8 caracteres, con letras y números. Al editar, déjela vacía para no cambiarla.' } : c)),
      ]}
      aFormulario={(u) => ({ ...Object.fromEntries(Object.keys(personaInicial).map((k) => [k, u[k] ?? ''])), password: '', password_confirmation: '', roles: u.roles.map((r) => String(r.id_rol)) })}
      aEnvio={(v) => ({ ...limpiarPersona(v), roles: v.roles.map(Number) })}
      anchoModal="lg"
      accionesFila={(u, recargar) => auth.puede('usuarios.gestionar') && u.id_usuario !== auth.usuario.id_usuario && (
        <Boton tamano="sm" variante="fantasma" icono={u.estado === 'ACTIVO' ? Ban : Check} onClick={() => cambiarEstado(u, recargar)} className={u.estado === 'ACTIVO' ? 'text-peligro' : 'text-exito'}>
          {u.estado === 'ACTIVO' ? 'Desactivar' : 'Activar'}
        </Boton>
      )}
    />
  )
}
