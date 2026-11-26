-- ============================================================
-- CAMBIOS DE DATOS PENDIENTES PARA LA BASE DEL GRUPO (db_grupo15sa)
-- ------------------------------------------------------------
-- Mientras el servidor tecnoweb no responde se trabaja en una base local.
-- Los cambios de ESTRUCTURA llegan solos con «php artisan migrate».
-- Aquí van los cambios de DATOS que las migraciones no hacen.
--
-- Cómo aplicarlo (cuando el servidor vuelva), en pgAdmin conectado a
-- db_grupo15sa: Query Tool > abrir este archivo > ejecutar.
-- Es idempotente: se puede ejecutar más de una vez sin problema.
-- El detalle de cada cambio está en docs/cambios-pendientes-grupo15sa.md
-- ============================================================

BEGIN;

-- [P2] Menú: las materias dejan de llamarse "Módulos"
UPDATE menu_item SET etiqueta = 'Materias'
 WHERE ruta = '/modulos' AND etiqueta = 'Módulos';
UPDATE menu_item SET etiqueta = 'Materias y calificaciones'
 WHERE ruta = '/docencia' AND etiqueta = 'Módulos y calificaciones';

-- [P3] Descripción del permiso de métodos de pago (PagoFácil -> Stripe)
UPDATE permiso SET descripcion = 'Gestionar métodos de pago y la pasarela Stripe'
 WHERE accion = 'metodos_pago.gestionar' AND descripcion LIKE '%PagoFácil%';

-- [P7] La Secretaría también concilia pagos (cobra efectivo y revisa comprobantes)
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM rol r, permiso p
 WHERE r.nombre = 'Secretaria' AND p.accion = 'pagos.conciliar'
   AND NOT EXISTS (SELECT 1 FROM rol_permiso x WHERE x.id_rol = r.id_rol AND x.id_permiso = p.id_permiso);
UPDATE permiso SET descripcion = 'Cobrar pagos en efectivo y confirmar, observar o rechazar pagos'
 WHERE accion = 'pagos.conciliar';

-- [P8] El estudiante puede elegir Efectivo (avisa que pagará en caja)
UPDATE metodo_pago SET descripcion = 'Pague en la caja del ICAP. Se avisa a Secretaría y la cuota queda pagada cuando cobran.'
 WHERE tipo = 'EFECTIVO' AND nombre = 'Efectivo';

-- [P10] Menú: «Cuotas por cobrar» en Finanzas (Secretaría, Coordinador Financiero y Director)
INSERT INTO menu_item (id_padre, id_permiso, etiqueta, ruta, icono, orden, activo)
SELECT padre.id_padre, p.id_permiso, 'Cuotas por cobrar', '/cuentas-por-cobrar', 'deuda', padre.orden + 1, TRUE
  FROM permiso p,
       (SELECT id_padre, MAX(orden) AS orden FROM menu_item WHERE ruta = '/pagos' GROUP BY id_padre) padre
 WHERE p.accion = 'pagos.ver'
   AND NOT EXISTS (SELECT 1 FROM menu_item WHERE ruta = '/cuentas-por-cobrar');

-- [P13] El Coordinador Académico también acepta, observa o rechaza preinscripciones
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM rol r, permiso p
 WHERE r.nombre = 'Coordinador Académico' AND p.accion = 'preinscripciones.revisar'
   AND NOT EXISTS (SELECT 1 FROM rol_permiso x WHERE x.id_rol = r.id_rol AND x.id_permiso = p.id_permiso);

COMMIT;
