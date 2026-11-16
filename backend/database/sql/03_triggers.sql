-- ============================================================
-- 03 · TRIGGERS Y CONTADORES - SISTEMA ICAP
-- ------------------------------------------------------------
-- Lo ejecuta la migración 2026_11_16_000003_crear_triggers.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Contador de visitas por página
--    Cada fila insertada en VISITA incrementa el acumulado de su
--    ruta en CONTADOR_VISITA (lo que muestra el pie de página).
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_contar_visita() RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO contador_visita (ruta, titulo, total, ultima_visita)
    VALUES (NEW.ruta, NEW.titulo, 1, NEW.fecha)
    ON CONFLICT (ruta) DO UPDATE
        SET total         = contador_visita.total + 1,
            titulo        = COALESCE(EXCLUDED.titulo, contador_visita.titulo),
            ultima_visita = EXCLUDED.ultima_visita;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_visita_contador ON visita;
CREATE TRIGGER trg_visita_contador
    AFTER INSERT ON visita
    FOR EACH ROW EXECUTE FUNCTION fn_contar_visita();

-- ------------------------------------------------------------
-- 2. Estado de la cuota según los pagos conciliados
--    Una MENSUALIDAD pasa a PAGADA cuando la suma de sus pagos
--    CONFIRMADOS cubre su monto final, y vuelve a PENDIENTE si un
--    pago confirmado deja de estarlo.
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_actualizar_estado_mensualidad() RETURNS TRIGGER AS $$
DECLARE
    v_pagado NUMERIC(12,2);
    v_final  NUMERIC(12,2);
    v_estado VARCHAR(30);
BEGIN
    IF NEW.id_mensualidad IS NULL THEN
        RETURN NEW;
    END IF;

    SELECT COALESCE(SUM(monto), 0) INTO v_pagado
      FROM pago
     WHERE id_mensualidad = NEW.id_mensualidad
       AND estado_conciliacion = 'CONFIRMADO';

    SELECT monto_final, estado INTO v_final, v_estado
      FROM mensualidad
     WHERE id_mensualidad = NEW.id_mensualidad;

    IF v_estado = 'ANULADA' THEN
        RETURN NEW;
    END IF;

    IF v_pagado >= v_final THEN
        UPDATE mensualidad SET estado = 'PAGADA'
         WHERE id_mensualidad = NEW.id_mensualidad AND estado <> 'PAGADA';
    ELSE
        UPDATE mensualidad SET estado = 'PENDIENTE'
         WHERE id_mensualidad = NEW.id_mensualidad AND estado <> 'PENDIENTE';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_pago_estado_mensualidad ON pago;
CREATE TRIGGER trg_pago_estado_mensualidad
    AFTER INSERT OR UPDATE OF estado_conciliacion, monto ON pago
    FOR EACH ROW EXECUTE FUNCTION fn_actualizar_estado_mensualidad();
