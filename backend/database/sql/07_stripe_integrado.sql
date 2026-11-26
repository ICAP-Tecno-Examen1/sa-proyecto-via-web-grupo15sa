-- ============================================================
-- 07 · STRIPE INTEGRADO EN LA PÁGINA (Embedded Checkout)
-- ------------------------------------------------------------
-- El formulario de tarjeta se muestra dentro del sistema; para eso el
-- navegador necesita la clave PUBLICABLE de Stripe (pk_test_… / pk_live_…).
-- No es secreta (Stripe la diseña para usarse en el navegador).
-- Aditivo e idempotente. Lo ejecuta la migración 2026_11_26_000001.
-- ============================================================

ALTER TABLE metodo_pago_electronico ADD COLUMN IF NOT EXISTS stripe_clave_publica TEXT;
