-- ============================================================
-- 02 · AJUSTES DEL SEGUNDO PARCIAL - SISTEMA ICAP
-- ------------------------------------------------------------
-- Columnas y tablas nuevas que el sistema web necesita además
-- del esquema base. Todo es ADITIVO (no se elimina ni se
-- renombra nada del script aprobado) e idempotente.
-- Lo ejecuta la migración 2026_11_16_000002_aplicar_ajustes_parcial2.
-- ============================================================

-- ------------------------------------------------------------
-- CU1 · Menú dinámico (se arma desde la BD según los permisos)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS MENU_ITEM (
    id_menu_item BIGSERIAL    PRIMARY KEY,
    id_padre     BIGINT       REFERENCES MENU_ITEM(id_menu_item) ON DELETE CASCADE,
    id_permiso   BIGINT       REFERENCES PERMISO(id_permiso)     ON DELETE SET NULL,
    etiqueta     VARCHAR(100) NOT NULL,
    ruta         VARCHAR(150),
    icono        VARCHAR(50),
    orden        INT          NOT NULL DEFAULT 0,
    activo       BOOLEAN      NOT NULL DEFAULT TRUE
);
CREATE INDEX IF NOT EXISTS idx_menu_item_padre ON MENU_ITEM(id_padre);

-- ------------------------------------------------------------
-- Requisito 4 · Bitácora de auditoría
-- tipo: LOGIN_EXITOSO | LOGIN_FALLIDO | LOGOUT | ACCION | ACCESO_DENEGADO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS BITACORA (
    id_bitacora      BIGSERIAL    PRIMARY KEY,
    id_usuario       BIGINT       REFERENCES USUARIO(id_usuario) ON DELETE SET NULL,
    tipo             VARCHAR(30)  NOT NULL,
    descripcion      TEXT,
    recurso          VARCHAR(200),
    metodo           VARCHAR(10),
    codigo_respuesta INT,
    identificador    VARCHAR(150),
    ip               VARCHAR(45)  NOT NULL,
    agente_usuario   TEXT,
    fecha            TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_bitacora_fecha   ON BITACORA(fecha);
CREATE INDEX IF NOT EXISTS idx_bitacora_tipo    ON BITACORA(tipo);
CREATE INDEX IF NOT EXISTS idx_bitacora_usuario ON BITACORA(id_usuario);
CREATE INDEX IF NOT EXISTS idx_bitacora_recurso ON BITACORA(recurso);

-- ------------------------------------------------------------
-- Requisito 7 · Contador de visitas por página
-- VISITA guarda cada acceso; CONTADOR_VISITA es el acumulado que
-- mantiene el trigger trg_visita_contador (ver 03_triggers.sql).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS VISITA (
    id_visita  BIGSERIAL    PRIMARY KEY,
    ruta       VARCHAR(200) NOT NULL,
    titulo     VARCHAR(150),
    id_usuario BIGINT       REFERENCES USUARIO(id_usuario) ON DELETE SET NULL,
    ip         VARCHAR(45),
    fecha      TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_visita_ruta  ON VISITA(ruta);
CREATE INDEX IF NOT EXISTS idx_visita_fecha ON VISITA(fecha);

CREATE TABLE IF NOT EXISTS CONTADOR_VISITA (
    ruta          VARCHAR(200) PRIMARY KEY,
    titulo        VARCHAR(150),
    total         BIGINT       NOT NULL DEFAULT 0,
    ultima_visita TIMESTAMP
);

-- ------------------------------------------------------------
-- CU2 / CU4 · Plan de pago de cada arancel
-- numero_cuotas = 1 para PAGO_UNICO; > 1 para MENSUALIDAD
-- ------------------------------------------------------------
ALTER TABLE ARANCEL ADD COLUMN IF NOT EXISTS numero_cuotas INT NOT NULL DEFAULT 1;

-- ------------------------------------------------------------
-- CU3 · Preinscripción, matrícula y seguimiento
-- ------------------------------------------------------------
ALTER TABLE PREINSCRIPCION ADD COLUMN IF NOT EXISTS id_arancel         BIGINT REFERENCES ARANCEL(id_arancel) ON DELETE SET NULL;
ALTER TABLE PREINSCRIPCION ADD COLUMN IF NOT EXISTS id_usuario_revisor BIGINT REFERENCES USUARIO(id_usuario) ON DELETE SET NULL;
ALTER TABLE PREINSCRIPCION ADD COLUMN IF NOT EXISTS fecha_revision     TIMESTAMP;

ALTER TABLE MATRICULA ADD COLUMN IF NOT EXISTS id_preinscripcion  BIGINT REFERENCES PREINSCRIPCION(id_preinscripcion) ON DELETE SET NULL;
ALTER TABLE MATRICULA ADD COLUMN IF NOT EXISTS fecha_finalizacion DATE;

-- Resultado del módulo (se fija cuando el docente cierra el módulo)
ALTER TABLE INSCRIPCION_MODULO ADD COLUMN IF NOT EXISTS nota_final            DECIMAL(5,2);
ALTER TABLE INSCRIPCION_MODULO ADD COLUMN IF NOT EXISTS porcentaje_asistencia DECIMAL(5,2);

-- ------------------------------------------------------------
-- CU4 · Métodos de pago, pasarela digital y conciliación
-- METODO_PAGO.tipo: EFECTIVO | COMPROBANTE | PASARELA
-- ------------------------------------------------------------
ALTER TABLE METODO_PAGO ADD COLUMN IF NOT EXISTS tipo VARCHAR(30) NOT NULL DEFAULT 'COMPROBANTE';

-- Configuración de la pasarela (PagoFácil). modo: SIMULADO | REAL
CREATE TABLE IF NOT EXISTS PASARELA_PAGO (
    id_pasarela      BIGSERIAL   PRIMARY KEY,
    id_metodo_pago   BIGINT      NOT NULL UNIQUE REFERENCES METODO_PAGO(id_metodo_pago) ON DELETE CASCADE,
    proveedor        VARCHAR(50) NOT NULL DEFAULT 'PAGOFACIL',
    modo             VARCHAR(20) NOT NULL DEFAULT 'SIMULADO',
    tc_token_service TEXT,
    tc_token_secret  TEXT,
    callback_url     TEXT,
    activo           BOOLEAN     NOT NULL DEFAULT TRUE
);

-- Trazabilidad de la conciliación y de la transacción en la pasarela
ALTER TABLE PAGO ADD COLUMN IF NOT EXISTS id_usuario_registro      BIGINT REFERENCES USUARIO(id_usuario) ON DELETE SET NULL;
ALTER TABLE PAGO ADD COLUMN IF NOT EXISTS id_usuario_conciliador   BIGINT REFERENCES USUARIO(id_usuario) ON DELETE SET NULL;
ALTER TABLE PAGO ADD COLUMN IF NOT EXISTS fecha_conciliacion       TIMESTAMP;
ALTER TABLE PAGO ADD COLUMN IF NOT EXISTS observacion_conciliacion TEXT;
ALTER TABLE PAGO ADD COLUMN IF NOT EXISTS numero_transaccion       VARCHAR(100);
ALTER TABLE PAGO ADD COLUMN IF NOT EXISTS pasarela_transaccion_id  VARCHAR(100);
CREATE INDEX IF NOT EXISTS idx_pago_mensualidad        ON PAGO(id_mensualidad);
CREATE INDEX IF NOT EXISTS idx_pago_numero_transaccion ON PAGO(numero_transaccion);

-- ------------------------------------------------------------
-- CU5 · Respuesta del Coordinador Académico a una corrección de nota
-- ------------------------------------------------------------
ALTER TABLE CORRECCION_NOTA ADD COLUMN IF NOT EXISTS observacion_autorizador TEXT;

-- ------------------------------------------------------------
-- CU6 · Validación de requisitos previa al certificado
-- tipo: ACADEMICO | FINANCIERO | DOCUMENTAL
-- estado: PENDIENTE | APROBADO | OBSERVADO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS VALIDACION_REQUISITO (
    id_validacion        BIGSERIAL   PRIMARY KEY,
    id_matricula         BIGINT      NOT NULL REFERENCES MATRICULA(id_matricula) ON DELETE CASCADE,
    tipo                 VARCHAR(30) NOT NULL,
    estado               VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',
    id_usuario_validador BIGINT      REFERENCES USUARIO(id_usuario) ON DELETE SET NULL,
    observacion          TEXT,
    fecha_validacion     TIMESTAMP,
    UNIQUE (id_matricula, tipo)
);

-- ------------------------------------------------------------
-- CU7 · Repositorio documental histórico
-- categoria: PERSONAL | ACADEMICO | FINANCIERO | INSTITUCIONAL
-- estado: VIGENTE | ARCHIVADO
-- ------------------------------------------------------------
ALTER TABLE DOCUMENTO ADD COLUMN IF NOT EXISTS categoria           VARCHAR(30)  NOT NULL DEFAULT 'PERSONAL';
ALTER TABLE DOCUMENTO ADD COLUMN IF NOT EXISTS estado              VARCHAR(30)  NOT NULL DEFAULT 'VIGENTE';
ALTER TABLE DOCUMENTO ADD COLUMN IF NOT EXISTS nombre_archivo      VARCHAR(255);
ALTER TABLE DOCUMENTO ADD COLUMN IF NOT EXISTS tipo_mime           VARCHAR(100);
ALTER TABLE DOCUMENTO ADD COLUMN IF NOT EXISTS tamano_bytes        BIGINT;
ALTER TABLE DOCUMENTO ADD COLUMN IF NOT EXISTS id_usuario_registro BIGINT REFERENCES USUARIO(id_usuario) ON DELETE SET NULL;
ALTER TABLE DOCUMENTO ADD COLUMN IF NOT EXISTS fecha_registro      TIMESTAMP    NOT NULL DEFAULT NOW();
CREATE INDEX IF NOT EXISTS idx_documento_tipo      ON DOCUMENTO(tipo);
CREATE INDEX IF NOT EXISTS idx_documento_categoria ON DOCUMENTO(categoria);
CREATE INDEX IF NOT EXISTS idx_documento_gestion   ON DOCUMENTO(gestion);
