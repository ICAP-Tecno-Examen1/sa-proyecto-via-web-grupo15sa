-- ============================================================
-- 08 · SOLICITUDES DE BECA CON RESPALDO
-- ------------------------------------------------------------
-- El estudiante solicita una beca adjuntando un documento de respaldo;
-- el Coordinador Financiero la revisa y la aprueba (se aplica el
-- descuento a las cuotas pendientes) o la rechaza.
--   beca_estudiante.estado: SOLICITADA | ACTIVA | RECHAZADA | REVOCADA
-- Aditivo e idempotente. Lo ejecuta la migración 2026_11_26_000002.
-- ============================================================

-- Qué documento debe presentar quien solicita cada beca
ALTER TABLE beca ADD COLUMN IF NOT EXISTS requisitos TEXT;

-- Datos de la solicitud y de su revisión
ALTER TABLE beca_estudiante ADD COLUMN IF NOT EXISTS motivo               TEXT;
ALTER TABLE beca_estudiante ADD COLUMN IF NOT EXISTS respaldo_url         TEXT;
ALTER TABLE beca_estudiante ADD COLUMN IF NOT EXISTS fecha_solicitud      TIMESTAMP;
ALTER TABLE beca_estudiante ADD COLUMN IF NOT EXISTS id_usuario_revisor   BIGINT REFERENCES usuario(id_usuario) ON DELETE SET NULL;
ALTER TABLE beca_estudiante ADD COLUMN IF NOT EXISTS fecha_revision       TIMESTAMP;
ALTER TABLE beca_estudiante ADD COLUMN IF NOT EXISTS observacion_revision TEXT;
CREATE INDEX IF NOT EXISTS idx_beca_est_estado ON beca_estudiante(estado);

-- Requisitos de las becas iniciales (solo si aún no tienen)
UPDATE beca SET requisitos = 'Certificado o kárdex de notas con promedio sobresaliente.'
 WHERE nombre = 'Beca Excelencia Académica' AND requisitos IS NULL;
UPDATE beca SET requisitos = 'Declaración o informe socioeconómico con respaldo de los ingresos del hogar.'
 WHERE nombre = 'Beca Socioeconómica' AND requisitos IS NULL;
UPDATE beca SET requisitos = 'Credencial o certificado de trabajo vigente de la UAGRM.'
 WHERE nombre = 'Descuento Funcionario UAGRM' AND requisitos IS NULL;
UPDATE beca SET requisitos = 'Nota o carta del Gobierno Autónomo Municipal que respalde el convenio.'
 WHERE nombre = 'Beca Completa Convenio Municipal' AND requisitos IS NULL;
