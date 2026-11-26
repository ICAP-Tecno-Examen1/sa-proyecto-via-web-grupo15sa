-- ============================================================
-- 10 · PLANTILLA DEL CERTIFICADO / TÍTULO
-- ------------------------------------------------------------
-- El certificado reproduce el formato oficial del ICAP. Sus textos, los
-- firmantes (nombre, cargo e imagen de la firma) y los escudos se editan
-- desde el sistema (Certificación > Plantilla). El sello NO es digital:
-- se deja su espacio para sellarlo a mano.
--   {documento} = Título (carreras técnicas) o Certificado (cursos, talleres…)
-- Aditivo e idempotente. Lo ejecuta la migración 2026_11_26_000004.
-- ============================================================

CREATE TABLE IF NOT EXISTS PLANTILLA_CERTIFICADO (
    id_plantilla        BIGSERIAL PRIMARY KEY,
    encabezado_1        VARCHAR(200) NOT NULL DEFAULT 'Universidad Autónoma Gabriel René Moreno',
    encabezado_2        VARCHAR(200) NOT NULL DEFAULT 'Instituto de Capacitación Popular',
    texto_otorgamiento  TEXT NOT NULL DEFAULT 'En nombre de la Universidad Autónoma Gabriel René Moreno, de conformidad con lo establecido por el Artículo 92 inciso III de la Constitución Política del Estado y la Resolución ICU 001/89, como público testimonio de su idoneidad, otorga el presente {documento} de:',
    texto_habilitacion  TEXT NOT NULL DEFAULT 'Por tanto queda habilitado(a) para el ejercicio de la profesión en todo el territorio del Estado Plurinacional de Bolivia, con todos los derechos y obligaciones que la ley señala.',
    texto_curso         TEXT NOT NULL DEFAULT 'Por haber aprobado satisfactoriamente todas las materias del programa, con una carga horaria de {horas} horas académicas.',
    texto_pie           TEXT NOT NULL DEFAULT 'Art. 95 CPE establece que las "Universidades deberán crear y sostener centros interculturales de Capacitación Técnica y Cultural de libre acceso al pueblo", en concordancia con los principios y fines del sistema educativo.',
    ciudad              VARCHAR(100) NOT NULL DEFAULT 'Santa Cruz de la Sierra',
    lugar_pie           VARCHAR(100) NOT NULL DEFAULT 'Santa Cruz - Bolivia',
    firmante_1_nombre   VARCHAR(150),
    firmante_1_cargo    VARCHAR(150) NOT NULL DEFAULT 'Coordinadora General ICAP - UAGRM',
    firmante_1_firma    TEXT,       -- imagen de la firma (PNG con fondo transparente)
    firmante_2_nombre   VARCHAR(150),
    firmante_2_cargo    VARCHAR(150) NOT NULL DEFAULT 'Director ICAP - UAGRM',
    firmante_2_firma    TEXT,
    logo_izquierdo      TEXT,       -- escudo de la UAGRM (vacío = logo del ICAP)
    logo_derecho        TEXT,       -- escudo de Bolivia (vacío = sin imagen)
    mostrar_foto        BOOLEAN NOT NULL DEFAULT TRUE
);

INSERT INTO PLANTILLA_CERTIFICADO (id_plantilla)
SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM PLANTILLA_CERTIFICADO);

-- «Nacido en … en Santa Cruz – Bolivia»
ALTER TABLE usuario ADD COLUMN IF NOT EXISTS lugar_nacimiento VARCHAR(150);
