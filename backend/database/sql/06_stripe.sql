-- ============================================================
-- 06 · STRIPE COMO PASARELA DE PAGO EN LÍNEA (reemplaza a PagoFácil)
-- ------------------------------------------------------------
-- Las credenciales de Stripe se guardan en metodo_pago_electronico,
-- igual que antes las de PagoFácil (se cargan desde Finanzas > Métodos
-- de pago). Las columnas de PagoFácil se conservan sin uso para no
-- perder datos. Aditivo e idempotente. Lo ejecuta la migración 2026_11_25_000001.
-- ============================================================

-- Clave secreta de la API (sk_test_… / sk_live_…) y secreto de firma del webhook (whsec_…)
ALTER TABLE metodo_pago_electronico ADD COLUMN IF NOT EXISTS stripe_clave_secreta   TEXT;
ALTER TABLE metodo_pago_electronico ADD COLUMN IF NOT EXISTS stripe_secreto_webhook TEXT;

-- El método de pasarela creado para PagoFácil pasa a ser el de Stripe.
UPDATE metodo_pago
   SET nombre = 'Tarjeta de crédito o débito (Stripe)',
       descripcion = 'Pago en línea con tarjeta en la página segura de Stripe. Se confirma automáticamente.'
 WHERE tipo = 'PASARELA' AND nombre = 'QR PagoFácil';


-- No hace falta desactivar nada: la pasarela solo se ofrece al pagar si hay una clave
-- secreta de Stripe (en este método o en STRIPE_SECRET_KEY del .env).
