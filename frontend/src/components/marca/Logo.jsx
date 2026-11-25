import logoCompleto from '../../assets/logo-icap.png'
import logoMarca from '../../assets/logo-icap-marca.png'

/**
 * Logo oficial del ICAP (fondo transparente).
 *   variante="marca"     solo las letras ICAP (encabezado)
 *   variante="completo"  con "Instituto de Capacitación Popular · UAGRM" y el lema
 *   placa                lo muestra sobre una placa blanca (para fondos oscuros)
 * En modo noche se coloca en placa blanca automáticamente (ver .logo-icap en index.css).
 * Para cambiar el logo, reemplace los archivos de src/assets manteniendo el nombre.
 */
export default function Logo({ variante = 'marca', placa = false, className = '' }) {
  return (
    <img
      src={variante === 'completo' ? logoCompleto : logoMarca}
      alt="ICAP · Instituto de Capacitación Popular, UAGRM"
      className={`logo-icap ${placa ? 'rounded-tema bg-white p-2' : ''} ${className}`}
      draggable="false"
    />
  )
}
