# Manual del Coordinador Financiero

← [Volver al índice](README.md)

Usted define cuánto y cómo se paga cada oferta, administra las becas, configura los métodos de pago, concilia los pagos, controla las deudas y valida el requisito **financiero** del certificado.

**Su menú:**

- Oferta académica › Ofertas (consulta)
- Inscripciones › Matrículas (consulta)
- Finanzas › **Aranceles** · **Becas** · **Métodos de pago** · **Pagos y conciliación** · **Cuotas por cobrar**
- Certificación › **Requisitos y certificados**
- Repositorio documental · **Reportes** financieros

---

## 1. Aranceles (planes de pago)

Cada oferta necesita **al menos un arancel activo** antes de abrir preinscripciones.

En **Aranceles** → nuevo:

- **Oferta académica**
- **Concepto** (ejemplo: «Colegiatura completa»)
- **Monto total (Bs.)**
- **Forma de cobro**:
  - **Pago único**: una sola cuota.
  - **Mensualidades**: indique el **número de cuotas**; el sistema divide el monto.
- **Estado**: Activo o Inactivo.

Puede ofrecer varios planes por oferta (por ejemplo, contado con descuento y en cuotas). El estudiante elige uno al preinscribirse.

## 2. Becas

**Becas** tiene tres pestañas.

### Catálogo de becas

Cada beca es un descuento o convenio. Registre:

- **Nombre de la beca** y **descripción**
- **Porcentaje de descuento**
- **Institución financiadora (convenio)**: déjela vacía si la beca es propia del ICAP
- **Documento que debe presentar** el estudiante (por ejemplo, la carta de la empresa)
- **Cursos que cubre**: vacío = todos
- **Estado**: **Activa** (se puede solicitar y aplicar) o **Inactiva** (no se ofrece)

### Solicitudes por revisar

Los estudiantes solicitan becas desde **Mis pagos**, con su motivo y documento. Abra cada solicitud, revise el respaldo y elija **Aprobar** o **Rechazar**.

### Becas asignadas

Para dar una beca directamente, use **Asignar beca a un estudiante**: elija la matrícula y la beca. Desde la matrícula también puede **Revocar beca**.

El descuento se aplica a las cuotas **pendientes** de la matrícula.

## 3. Métodos de pago

En **Métodos de pago** se configura cómo pueden pagar los estudiantes:

| Método | Tipo |
|---|---|
| Tarjeta de crédito o débito (Stripe) | Pago en línea. Se cargan la **clave secreta**, la **clave publicable** y el **secreto del webhook** de Stripe. Si no se cargan, el sistema usa las del servidor. |
| Transferencia o QR bancario | Comprobante. Escriba en **Instrucciones para quien paga** los datos de la cuenta o el QR. |
| Efectivo | Pago en caja del ICAP. |

Con **Método habilitado** puede ocultar un método sin borrarlo. La guía **Cómo conectar Stripe** de esa pantalla explica de dónde sacar las claves.

## 4. Conciliar pagos

**Pagos y conciliación** reúne todos los pagos. Pestañas:

- **Comprobantes por revisar** (transferencias y QR)
- **Efectivo por cobrar**
- **Confirmados**
- **Todos**

Para cada pago pendiente, ábralo y elija:

- **Confirmar el pago**
- **Observar (falta información)**: el estudiante corrige
- **Rechazar el pago**

Para efectivo, use **Cobrado: registrar el pago en efectivo** o **No se presentó / anular el aviso**.

Los pagos con **tarjeta** se confirman solos.

**Registrar pago** sirve para un cobro que usted recibe directamente: queda confirmado al instante.

Al confirmar un pago:

1. la cuota queda **Pagada**;
2. si era el primer pago, la matrícula se **activa** y el estudiante queda inscrito en sus materias;
3. el recibo se archiva en el repositorio.

La **campana 🔔** de la cabecera le avisa de los pagos en efectivo pendientes.

## 5. Cuotas por cobrar

Vea quién debe y cuánto. Filtre por:

- **Todas las deudas**
- **Con cuotas vencidas**
- **Vencen en 7 días**

## 6. Validar el requisito financiero

1. **Requisitos y certificados** → abra al estudiante que finalizó.
2. El sistema muestra el **saldo pendiente**, las **cuotas sin pagar** y los pagos en espera de conciliación.
3. Si todo está pagado, elija **Aprobado** y pulse **Registrar validación**. Si debe algo, solo puede **Observar**.

## 7. Reportes financieros

- Ingresos por pagos confirmados
- Pagos pendientes de conciliación
- Mensualidades y cuotas
- Deudas por estudiante
- Becas asignadas

Se pueden filtrar por gestión, programa, oferta y fechas, imprimir y descargar en **PDF** o **Excel**.
