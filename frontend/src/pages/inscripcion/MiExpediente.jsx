import Expediente from '../../components/inscripcion/Expediente'
import { EncabezadoPagina, Tarjeta } from '../../components/ui/Basicos'

/** CU3 · Expediente único: el estudiante sube sus documentos una sola vez. */
export default function MiExpediente() {
  return (
    <>
      <EncabezadoPagina titulo="Mi expediente" descripcion="Sus documentos personales se guardan una sola vez y sirven para todas sus inscripciones en el ICAP." />
      <Tarjeta>
        <Expediente recurso="/mi-expediente" puedeSubir puedeEliminar />
      </Tarjeta>
    </>
  )
}
