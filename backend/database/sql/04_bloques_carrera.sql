-- ============================================================
-- 04 · MÓDULOS DE LA CARRERA (agrupación de 1 o 2 materias)
-- ------------------------------------------------------------
-- Una carrera (programa) tiene módulos; cada módulo agrupa 1 o 2 materias.
-- Las materias son la tabla MODULO (se califican una por una).
-- Esta tabla agrupa las materias DENTRO de una malla curricular.
-- Aditivo e idempotente. Lo ejecuta la migración 2026_11_22_000001.
-- ============================================================

CREATE TABLE IF NOT EXISTS MODULO_CARRERA (
    id_modulo_carrera BIGSERIAL    PRIMARY KEY,
    id_malla          BIGINT       NOT NULL REFERENCES MALLA_CURRICULAR(id_malla) ON DELETE CASCADE,
    nombre            VARCHAR(200) NOT NULL,
    orden             INT          NOT NULL,
    UNIQUE (id_malla, orden)
);

ALTER TABLE MALLA_MODULO ADD COLUMN IF NOT EXISTS id_modulo_carrera BIGINT
    REFERENCES MODULO_CARRERA(id_modulo_carrera) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_malla_modulo_carrera ON MALLA_MODULO(id_modulo_carrera);
