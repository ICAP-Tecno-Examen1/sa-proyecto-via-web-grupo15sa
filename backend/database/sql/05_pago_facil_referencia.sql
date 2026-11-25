-- ============================================================
-- 05 · PAGO FÁCIL COMO EN EL PROYECTO DE REFERENCIA
-- ------------------------------------------------------------
-- La configuración de la pasarela pasa a la tabla metodo_pago_electronico
-- (credenciales por método de pago, activo, callback_url), igual que en el
-- proyecto de referencia del grupo. Se quita el modo SIMULADO.
-- Aditivo e idempotente. Lo ejecuta la migración 2026_11_22_000002.
-- ============================================================

DO $$
BEGIN
    IF to_regclass('public.pasarela_pago') IS NOT NULL AND to_regclass('public.metodo_pago_electronico') IS NULL THEN
        ALTER TABLE pasarela_pago RENAME TO metodo_pago_electronico;
    END IF;
END $$;

ALTER TABLE metodo_pago_electronico DROP COLUMN IF EXISTS proveedor;
ALTER TABLE metodo_pago_electronico DROP COLUMN IF EXISTS modo;
ALTER TABLE metodo_pago_electronico DROP COLUMN IF EXISTS id_pasarela;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'metodo_pago_electronico'::regclass AND contype = 'p') THEN
        ALTER TABLE metodo_pago_electronico ADD PRIMARY KEY (id_metodo_pago);
    END IF;
END $$;
