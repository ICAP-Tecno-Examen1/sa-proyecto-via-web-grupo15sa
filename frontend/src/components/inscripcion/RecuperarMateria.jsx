import { RotateCcw } from 'lucide-react'
import { useState } from 'react'
import api from '../../api/cliente'
import { useConsulta } from '../../hooks/useConsulta'
import { fecha, legible, porcentaje } from '../../utils/formato'
import { Alerta, Cargando } from '../ui/Basicos'
import Boton from '../ui/Boton'
import Modal from '../ui/Modal'

/**
 * Recuperación de una materia reprobada: el estudiante vuelve a cursarla en otra oferta
 * vigente que la dicte. Sin aprobarla no puede recibir el certificado.
 */
export default function RecuperarMateria({ idMatricula, materia, alCerrar, alTerminar }) {
  const consulta = useConsulta(materia ? `/matriculas/${idMatricula}/recuperacion` : null)
  const [elegida, setElegida] = useState(null)
  const [enviando, setEnviando] = useState(false)
  const [error, setError] = useState(null)

  const datos = consulta.datos?.find((m) => m.id_modulo === materia?.id_modulo)
  const opciones = datos?.opciones ?? []

  const inscribir = async () => {
    setEnviando(true)
    setError(null)

    try {
      const { data } = await api.post(`/matriculas/${idMatricula}/recuperacion`, { id_oferta_modulo: elegida })
      alTerminar?.(data)
      alCerrar()
    } catch (e) {
      setError(e.response?.data?.message ?? 'No se pudo inscribir la recuperación.')
    } finally {
      setEnviando(false)
    }
  }

  if (!materia) return null

  return (
    <Modal abierto titulo={`Recuperar «${materia.modulo}»`} alCerrar={alCerrar}
      pie={<><Boton variante="secundario" onClick={alCerrar}>Cancelar</Boton><Boton icono={RotateCcw} onClick={inscribir} cargando={enviando} disabled={!elegida}>Inscribir para recuperar</Boton></>}>
      <div className="space-y-4 text-sm">
        <Alerta tipo="alerta" titulo="Materia reprobada">
          Nota {materia.nota_final ?? '—'} y {porcentaje(materia.porcentaje_asistencia ?? 0)} de asistencia. Para el certificado debe aprobarla:
          elija una oferta que la esté dictando; su docente la calificará como a cualquier inscrito.
        </Alerta>
        {error && <Alerta tipo="peligro">{error}</Alerta>}

        {consulta.cargando ? <Cargando className="py-6" /> : opciones.length === 0 ? (
          <Alerta>Ninguna oferta vigente dicta esta materia por ahora. Cuando el Coordinador Académico abra una, podrá inscribirlo aquí.</Alerta>
        ) : (
          <fieldset className="space-y-2">
            <legend className="mb-2 font-bold">¿Dónde la volverá a cursar?</legend>
            {opciones.map((o) => (
              <label key={o.id_oferta_modulo} className={`flex cursor-pointer gap-3 rounded-tema border-2 p-3 ${elegida === o.id_oferta_modulo ? 'border-primario bg-primario-suave' : 'hover:bg-suave'}`}>
                <input type="radio" name="recuperacion" className="mt-1" checked={elegida === o.id_oferta_modulo} onChange={() => setElegida(o.id_oferta_modulo)} />
                <span>
                  <strong>{o.oferta}</strong> · {o.programa} · gestión {o.gestion}
                  <span className="block text-xs text-tenue">{legible(o.modalidad)} · {fecha(o.fecha_inicio)} – {fecha(o.fecha_fin)} · Docente: {o.docentes.join(', ') || 'por designar'}</span>
                </span>
              </label>
            ))}
          </fieldset>
        )}
      </div>
    </Modal>
  )
}
