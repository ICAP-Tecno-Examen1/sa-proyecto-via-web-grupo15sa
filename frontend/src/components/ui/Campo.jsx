import { useId } from 'react'

/**
 * Campo de formulario con etiqueta, ayuda y mensaje de error accesibles.
 * Recibe directamente lo que devuelve useFormulario().campo('nombre').
 *
 *   tipo: text | email | password | number | date | textarea | select | file | checkbox | casillas
 *   casillas: varias opciones a la vez; el valor es un arreglo
 *   opciones (select): [{ valor, etiqueta }]  o  ['A', 'B']
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
    control = (
      <select {...comunes} value={valor ?? ''} onChange={(e) => alCambiar(e.target.value)}>
        {vacio !== null && <option value="">{vacio}</option>}
        {opciones.map((o) => {
          const opcion = typeof o === 'object' ? o : { valor: o, etiqueta: o }

          return (
            <option key={opcion.valor} value={opcion.valor}>
              {opcion.etiqueta}
            </option>
          )
        })}
      </select>
    )
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
