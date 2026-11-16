import { useCallback, useState } from 'react'
import { erroresDeValidacion, mensajeError } from '../api/cliente'
import { obtener, validar } from '../utils/validacion'

/** Copia el objeto cambiando el valor en una ruta de puntos ('pagador.nombres'). */
function asignar(objeto, ruta, valor) {
  const [clave, ...resto] = ruta.split('.')
  if (resto.length === 0) return { ...objeto, [clave]: valor }

  return { ...objeto, [clave]: asignar(objeto?.[clave] ?? {}, resto.join('.'), valor) }
}

/**
 * Estado de un formulario con validación en el cliente y en el servidor.
 *
 *   const f = useFormulario(inicial, reglas)
 *   <Campo {...f.campo('nombre')} etiqueta="Nombre" />
 *   <form onSubmit={f.enviar(async (valores) => { await api.post(...) })}>
 *
 * Al enviar: primero valida con las reglas locales; si el servidor responde
 * 422, sus mensajes (en español) se muestran debajo de cada campo.
 */
export function useFormulario(inicial, reglas = {}) {
  const [valores, setValores] = useState(inicial)
  const [errores, setErrores] = useState({})
  const [errorGeneral, setErrorGeneral] = useState(null)
  const [enviando, setEnviando] = useState(false)

  const cambiar = useCallback((nombre, valor) => {
    setValores((previos) => asignar(previos, nombre, valor))
    setErrores((previos) => (previos[nombre] ? { ...previos, [nombre]: undefined } : previos))
  }, [])

  const reiniciar = useCallback((nuevos) => {
    setValores(nuevos ?? inicial)
    setErrores({})
    setErrorGeneral(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  /** Valida un campo al salir de él. */
  const revisar = useCallback(
    (nombre) => {
      if (!reglas[nombre]) return
      const resultado = validar(valores, { [nombre]: reglas[nombre] })
      setErrores((previos) => ({ ...previos, [nombre]: resultado[nombre] }))
    },
    [reglas, valores],
  )

  /** Propiedades listas para <Campo>. */
  const campo = (nombre) => ({
    nombre,
    valor: obtener(valores, nombre) ?? '',
    error: errores[nombre],
    alCambiar: (valor) => cambiar(nombre, valor),
    alSalir: () => revisar(nombre),
  })

  const enviar = (accion) => async (evento) => {
    evento?.preventDefault()
    setErrorGeneral(null)

    const locales = validar(valores, reglas)
    setErrores(locales)

    if (Object.keys(locales).length > 0) {
      setErrorGeneral('Revise los campos marcados antes de continuar.')

      return
    }

    setEnviando(true)

    try {
      await accion(valores)
    } catch (error) {
      const delServidor = erroresDeValidacion(error)
      setErrores(delServidor)
      setErrorGeneral(mensajeError(error))
    } finally {
      setEnviando(false)
    }
  }

  return { valores, errores, errorGeneral, enviando, cambiar, campo, enviar, reiniciar, setValores, setErrorGeneral }
}
