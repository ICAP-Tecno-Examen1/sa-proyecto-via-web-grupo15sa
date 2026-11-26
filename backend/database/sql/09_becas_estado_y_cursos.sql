-- ============================================================
-- 09 · ESTADO DE LAS BECAS Y CURSOS QUE CUBREN
-- ------------------------------------------------------------
-- Diseño original: la beca ES el convenio. La institución con la que el
-- ICAP tiene el convenio se guarda en beca.institucion_financiadora (vacía
-- = beca propia del ICAP) y beca_estudiante vincula la beca con la
-- matrícula del estudiante. Aquí solo se agrega lo que faltaba:
--   beca.estado: ACTIVA | INACTIVA   (solo una beca activa se solicita o asigna)
--   beca_oferta: cursos a los que se limita una beca (sin filas = todos)
-- Aditivo e idempotente. Lo ejecuta la migración 2026_11_26_000003.
-- ============================================================

ALTER TABLE beca ADD COLUMN IF NOT EXISTS estado VARCHAR(20) NOT NULL DEFAULT 'ACTIVA';

CREATE TABLE IF NOT EXISTS BECA_OFERTA (
    id_beca_oferta BIGSERIAL PRIMARY KEY,
    id_beca        BIGINT NOT NULL REFERENCES beca(id_beca)               ON DELETE CASCADE,
    id_oferta      BIGINT NOT NULL REFERENCES oferta_academica(id_oferta) ON DELETE CASCADE,
    UNIQUE (id_beca, id_oferta)
);
