import { FileSpreadsheet, FileText, Printer } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { abrirArchivo } from '../../api/cliente'
import { Alerta, Cargando, EncabezadoPagina, Estado, Tarjeta, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Tabla from '../../components/ui/Tabla'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { cantidad, fecha, fechaHora, legible, moneda, porcentaje } from '../../utils/formato'

const SIN_FILTROS = { gestion: '', id_programa: '', id_oferta: '', desde: '', hasta: '' }
const esCodigo = (v) => typeof v === 'string' && /^[A-Z]+(_[A-Z]+)+$|^(ACTIVO|ACTIVA|INACTIVO|PENDIENTE|APROBADO|APROBADA|REPROBADO|RECHAZADA|OBSERVADA|EMITIDO|ANULADO|ANULADA|FINALIZADA|PAGADA|VENCIDA|CONFIRMADO|OBSERVADO|INSCRITO|CERRADO|REVOCADA)$/.test(v)

/** Da formato a una celda según el tipo de columna que declara la API. */
function celda(columna, fila) {
  const valor = fila[columna.clave]
  if (valor === null || valor === undefined || valor === '') return '—'
  if (columna.tipo === 'moneda') return moneda(valor).replace('Bs. ', '')
  if (columna.tipo === 'porcentaje') return porcentaje(valor)
  if (columna.tipo === 'numero') return cantidad(valor)
  if (columna.tipo === 'fecha') return fecha(valor)

  return esCodigo(valor) ? legible(valor) : valor
}

/**
 * CU8 · Reportes administrativos, académicos y financieros.
 * La lista de reportes depende del rol. Todos admiten filtros, impresión y
 * exportación a Excel (CSV) y PDF.
 */
export default function Reportes() {
  const avisar = useAvisos()
  const catalogo = useConsulta('/reportes')
  const [clave, setClave] = useState(null)
  const [filtros, setFiltros] = useState(SIN_FILTROS)
  const [exportando, setExportando] = useState(null)

  const reportes = catalogo.datos?.reportes ?? []
  const definicion = reportes.find((r) => r.clave === clave)
  const opciones = catalogo.datos?.opciones

  useEffect(() => {
    if (!clave && reportes.length) setClave(reportes[0].clave)
  }, [clave, reportes])

  // Solo se envían los filtros que el reporte elegido admite.
  const aplicados = useMemo(
    () => Object.fromEntries(Object.entries(filtros).filter(([k, v]) => v !== '' && definicion?.filtros.includes(k))),
    [filtros, definicion],
  )
  const fechasInvalidas = aplicados.desde && aplicados.hasta && aplicados.hasta < aplicados.desde
  const reporte = useConsulta(clave && !fechasInvalidas ? `/reportes/${clave}` : null, aplicados)

  const grupos = useMemo(() => {
    const mapa = {}
    reportes.forEach((r) => (mapa[r.grupo] ??= []).push(r))

    return Object.entries(mapa)
  }, [reportes])

  const ofertas = (opciones?.ofertas ?? []).filter((o) =>
    (!filtros.gestion || String(o.gestion) === String(filtros.gestion)) && (!filtros.id_programa || String(o.id_programa) === String(filtros.id_programa)))

  const exportar = async (formato) => {
    setExportando(formato)

    try {
      await abrirArchivo(`/reportes/${clave}`, { params: { ...aplicados, formato }, descargar: true, nombre: `reporte-${clave.replaceAll('_', '-')}.${formato}` })
    } catch (e) {
      avisar.error(e)
    } finally {
      setExportando(null)
    }
  }

  const admite = (filtro) => definicion?.filtros.includes(filtro)
  const cambiar = (campo) => (valor) => setFiltros((f) => ({ ...f, [campo]: valor, ...(campo !== 'id_oferta' && campo !== 'desde' && campo !== 'hasta' ? { id_oferta: '' } : {}) }))

  return (
    <>
      <EncabezadoPagina titulo="Reportes" descripcion="Genere reportes con filtros por gestión, fechas, programa y oferta. Puede imprimirlos o exportarlos.">
        {reporte.datos && (
          <>
            <Boton variante="secundario" icono={Printer} onClick={() => window.print()}>Imprimir</Boton>
            <Boton variante="secundario" icono={FileSpreadsheet} cargando={exportando === 'xlsx'} onClick={() => exportar('xlsx')}>Excel</Boton>
            <Boton variante="secundario" icono={FileText} cargando={exportando === 'pdf'} onClick={() => exportar('pdf')}>PDF</Boton>
          </>
        )}
      </EncabezadoPagina>

      <Estado consulta={catalogo}>
        {() => reportes.length === 0 ? <div className="tarjeta"><Vacio titulo="Su rol no tiene reportes asignados" /></div> : (
          <div className="grid gap-5 lg:grid-cols-[16rem_1fr]">
            <nav className="no-imprimir tarjeta h-fit p-2" aria-label="Reportes disponibles">
              {grupos.map(([grupo, lista]) => (
                <div key={grupo} className="mb-2">
                  <p className="px-2 py-1.5 text-xs font-bold tracking-wide text-tenue uppercase">{grupo}</p>
                  {lista.map((r) => (
                    <button key={r.clave} onClick={() => setClave(r.clave)} aria-current={r.clave === clave}
                      className={`block w-full rounded-tema px-3 py-2 text-left text-sm ${r.clave === clave ? 'bg-primario font-bold text-sobre-primario' : 'hover:bg-suave'}`}>
                      {r.titulo}
                    </button>
                  ))}
                </div>
              ))}
            </nav>

            <div className="min-w-0 space-y-4">
              {definicion?.filtros.length > 0 && (
                <div className="no-imprimir tarjeta flex flex-wrap items-end gap-3 p-3">
                  {admite('gestion') && <Campo tipo="select" nombre="gestion" etiqueta="Gestión" valor={filtros.gestion} alCambiar={cambiar('gestion')} vacio="Todas" opciones={(opciones?.gestiones ?? []).map(String)} className="w-32" />}
                  {admite('id_programa') && <Campo tipo="select" nombre="id_programa" etiqueta="Programa" valor={filtros.id_programa} alCambiar={cambiar('id_programa')} vacio="Todos" className="w-60"
                    opciones={(opciones?.programas ?? []).map((p) => ({ valor: p.id_programa, etiqueta: p.nombre }))} />}
                  {admite('id_oferta') && <Campo tipo="select" nombre="id_oferta" etiqueta="Oferta" valor={filtros.id_oferta} alCambiar={cambiar('id_oferta')} vacio="Todas" className="w-56"
                    opciones={ofertas.map((o) => ({ valor: o.id_oferta, etiqueta: `${o.codigo} · ${o.programa}` }))} />}
                  {admite('desde') && <Campo tipo="date" nombre="desde" etiqueta="Desde" valor={filtros.desde} alCambiar={cambiar('desde')} className="w-40" />}
                  {admite('hasta') && <Campo tipo="date" nombre="hasta" etiqueta="Hasta" valor={filtros.hasta} alCambiar={cambiar('hasta')} className="w-40"
                    error={fechasInvalidas ? 'Debe ser igual o posterior a «Desde».' : undefined} />}
                  <Boton variante="fantasma" onClick={() => setFiltros(SIN_FILTROS)}>Limpiar filtros</Boton>
                </div>
              )}

              {reporte.error ? <Alerta tipo="peligro">{reporte.error}</Alerta> : !reporte.datos ? <Cargando /> : (
                <Tarjeta relleno={false} className={reporte.cargando ? 'opacity-60' : ''}>
                  <header className="border-b p-4">
                    <h2 className="text-lg font-bold">{reporte.datos.titulo}</h2>
                    <p className="text-xs text-tenue">
                      ICAP · UAGRM — Generado el {fechaHora(reporte.datos.generado)}
                      {Object.entries(reporte.datos.filtros_aplicados).map(([k, v]) => ` · ${k}: ${v}`)}
                    </p>
                    {reporte.datos.resumen.length > 0 && (
                      <dl className="mt-3 flex flex-wrap gap-2">
                        {reporte.datos.resumen.map((r) => (
                          <div key={r.etiqueta} className="rounded-tema bg-suave px-3 py-1.5">
                            <dt className="text-xs text-tenue">{r.etiqueta}</dt>
                            <dd className="tabular text-sm font-extrabold">{r.valor}</dd>
                          </div>
                        ))}
                      </dl>
                    )}
                  </header>
                  <Tabla
                    id={(fila) => JSON.stringify(fila)}
                    filas={reporte.datos.filas}
                    vacio="No hay datos para los filtros seleccionados."
                    columnas={reporte.datos.columnas.map((c) => ({
                      clave: c.clave,
                      titulo: c.titulo,
                      alinear: ['numero', 'moneda', 'porcentaje'].includes(c.tipo) ? 'derecha' : undefined,
                      render: (fila) => celda(c, fila),
                    }))}
                  />
                  {reporte.datos.filas.length > 0 && <p className="border-t px-4 py-2 text-xs text-tenue">{reporte.datos.filas.length} fila(s)</p>}
                </Tarjeta>
              )}
            </div>
          </div>
        )}
      </Estado>
    </>
  )
}
