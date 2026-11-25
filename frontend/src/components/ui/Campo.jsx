import { Eye, EyeOff } from 'lucide-react'
import { useId, useState } from 'react'
import Selector from './Selector'

/**
 * Campo de formulario con etiqueta, ayuda y mensaje de error accesibles.
 * Recibe directamente lo que devuelve useFormulario().campo('nombre').
 *
 *   tipo: text | email | password | number | date | textarea | select | file | checkbox | casillas
 *   password: incluye un botón (ojito) para mostrar u ocultar lo escrito
 *   casillas: varias opciones a la vez; el valor es un arreglo
 *   opciones (select): [{ valor, etiqueta }]  o  ['A', 'B']
 *   select: lista con buscador (se escribe y se filtran las opciones); vacio = texto de la opción vacía, null para no ofrecerla
 */
export default function Campo({
  etiqueta,
  nombre,
  valor,
  error,
  alCambiar,
  alSalir,
  tipo = 'text',
  opciones = [],
  ayuda,
  requerido = false,
  vacio = 'Seleccione…',
  className = '',
  ...resto
}) {
  const id = useId()
  const [visible, setVisible] = useState(false)
  const idAyuda = `${id}-ayuda`
  const comunes = {
    id,
    name: nombre,
    onBlur: alSalir,
    'aria-invalid': error ? 'true' : undefined,
    'aria-describedby': error || ayuda ? idAyuda : undefined,
    className: 'campo-control',
    ...resto,
  }

  let control

  if (tipo === 'textarea') {
    control = <textarea rows={3} {...comunes} value={valor ?? ''} onChange={(e) => alCambiar(e.target.value)} />
  } else if (tipo === 'select') {
    // Lista con buscador: se escribe y aparecen las opciones que coinciden (ver Selector.jsx)
    const { className: _clase, onBlur: _salir, ...atributos } = comunes
    control = <Selector {...atributos} valor={valor} opciones={opciones} alCambiar={alCambiar} alSalir={alSalir} vacio={vacio} />
  } else if (tipo === 'file') {
    control = <input type="file" {...comunes} className="campo-control file:mr-3 file:rounded file:border-0 file:bg-primario-suave file:px-3 file:py-1 file:text-sm file:font-semibold file:text-primario" onChange={(e) => alCambiar(e.target.files?.[0] ?? null)} />
  } else if (tipo === 'casillas') {
    const marcados = Array.isArray(valor) ? valor.map(String) : []
    const alternar = (v) => alCambiar(marcados.includes(String(v)) ? marcados.filter((m) => m !== String(v)) : [...marcados, String(v)])

    return (
      <fieldset className={className} aria-describedby={error || ayuda ? idAyuda : undefined}>
        <legend className="mb-1 block text-sm font-semibold">
          {etiqueta}
          {requerido && <span className="text-peligro" aria-hidden> *</span>}
        </legend>
        <div className="flex flex-wrap gap-x-5 gap-y-2 rounded-tema border p-3">
          {opciones.map((o) => (
            <label key={o.valor} className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="size-4 accent-[var(--primario)]" checked={marcados.includes(String(o.valor))} onChange={() => alternar(o.valor)} />
              {o.etiqueta}
            </label>
          ))}
        </div>
        {(error || ayuda) && <p id={idAyuda} className={`mt-1 text-xs ${error ? 'font-medium text-peligro' : 'text-tenue'}`}>{error || ayuda}</p>}
      </fieldset>
    )
  } else if (tipo === 'checkbox') {
    return (
      <div className={className}>
        <label htmlFor={id} className="flex items-center gap-2 text-sm">
          <input id={id} name={nombre} type="checkbox" className="size-4 accent-[var(--primario)]" checked={Boolean(valor)} onChange={(e) => alCambiar(e.target.checked)} {...resto} />
          {etiqueta}
        </label>
        {error && <p className="mt-1 text-xs font-medium text-peligro">{error}</p>}
      </div>
    )
  } else if (tipo === 'password') {
    // Contraseña con botón de "ojito" para mostrarla u ocultarla
    control = (
      <div className="relative">
        <input type={visible ? 'text' : 'password'} {...comunes} className="campo-control pr-11" value={valor ?? ''} onChange={(e) => alCambiar(e.target.value)} />
        <button
          type="button"
          onClick={() => setVisible((v) => !v)}
          aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
          aria-pressed={visible}
          aria-controls={id}
          title={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
          className="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-tema text-tenue hover:text-primario"
        >
          {visible ? <EyeOff className="size-5" aria-hidden /> : <Eye className="size-5" aria-hidden />}
        </button>
      </div>
    )
  } else {
    control = <input type={tipo} {...comunes} value={valor ?? ''} onChange={(e) => alCambiar(e.target.value)} />
  }

  return (
    <div className={className}>
      {etiqueta && (
        <label htmlFor={id} className="mb-1 block text-sm font-semibold">
          {etiqueta}
          {requerido && <span className="text-peligro" aria-hidden> *</span>}
        </label>
      )}
      {control}
      {(error || ayuda) && (
        <p id={idAyuda} className={`mt-1 text-xs ${error ? 'font-medium text-peligro' : 'text-tenue'}`} role={error ? 'alert' : undefined}>
          {error || ayuda}
        </p>
      )}
    </div>
  )
}
