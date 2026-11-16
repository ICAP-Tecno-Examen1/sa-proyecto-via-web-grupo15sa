import { FolderOpen } from 'lucide-react'
import { useState } from 'react'
import { camposAcceso, camposPersonales, limpiarPersona, personaInicial, reglasConAcceso } from '../../components/acceso/persona'
import Expediente from '../../components/inscripcion/Expediente'
import Boton from '../../components/ui/Boton'
import CrudSimple from '../../components/ui/CrudSimple'
import Modal from '../../components/ui/Modal'
import { useAuth } from '../../context/AuthContext'
import { columnasUsuario } from './Usuarios'

/** CU1 / CU3 · La Secretaria registra estudiantes, actualiza sus datos y gestiona su expediente. */
export default function Estudiantes() {
  const auth = useAuth()
  const [expediente, setExpediente] = useState(null)

  return (
    <>
      <CrudSimple
        titulo="Estudiantes"
        descripcion="Registre estudiantes que se inscriben en ventanilla, actualice sus datos y complete su expediente único."
        singular="estudiante"
        recurso="/estudiantes"
        id="id_usuario"
        paginado
        puedeEliminar={false}
        permisoGestion="estudiantes.gestionar"
        columnas={columnasUsuario.filter((c) => c.clave !== 'roles')}
        inicial={personaInicial}
        reglas={reglasConAcceso}
        campos={[...camposPersonales, ...camposAcceso]}
        aFormulario={(u) => ({ ...Object.fromEntries(Object.keys(personaInicial).map((k) => [k, u[k] ?? ''])), password: '', password_confirmation: '' })}
        aEnvio={limpiarPersona}
        anchoModal="lg"
        accionesFila={(u) => auth.puede('expedientes.gestionar') && (
          <Boton tamano="sm" variante="fantasma" icono={FolderOpen} onClick={() => setExpediente(u)}>Expediente</Boton>
        )}
      />

      <Modal abierto={Boolean(expediente)} titulo={`Expediente de ${expediente?.nombre_completo ?? ''}`} alCerrar={() => setExpediente(null)} ancho="lg">
        {expediente && <Expediente recurso={`/expedientes/${expediente.id_usuario}`} puedeSubir />}
      </Modal>
    </>
  )
}
