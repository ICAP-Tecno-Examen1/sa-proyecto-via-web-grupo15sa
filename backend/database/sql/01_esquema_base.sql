-- ============================================================
-- 01 · ESQUEMA BASE - SISTEMA ICAP (PostgreSQL)
-- ------------------------------------------------------------
-- Es el script aprobado por el grupo, sin cambios de estructura.
-- Únicamente se agregó "IF NOT EXISTS" para que pueda ejecutarse
-- sobre una base que ya tenga las tablas del primer parcial sin
-- borrar ni duplicar nada.
-- Lo ejecuta la migración 2026_11_16_000001_crear_esquema_base.
-- ============================================================

-- ============================================================
-- 1. TABLAS INDEPENDIENTES (sin foreign keys)
-- ============================================================

CREATE TABLE IF NOT EXISTS ROL (
    id_rol        BIGSERIAL    PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    descripcion   TEXT
);

CREATE TABLE IF NOT EXISTS PERMISO (
    id_permiso    BIGSERIAL    PRIMARY KEY,
    accion        VARCHAR(100) NOT NULL,
    descripcion   TEXT,
    modulo        VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS MODULO (
    id_modulo     BIGSERIAL    PRIMARY KEY,
    codigo        VARCHAR(50)  NOT NULL UNIQUE,
    nombre        VARCHAR(200) NOT NULL,
    descripcion   TEXT,
    horas         INT          NOT NULL CHECK (horas > 0)
);

CREATE TABLE IF NOT EXISTS METODO_PAGO (
    id_metodo_pago BIGSERIAL   PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    descripcion    TEXT,
    activo         BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS BECA (
    id_beca                 BIGSERIAL      PRIMARY KEY,
    nombre                  VARCHAR(200)   NOT NULL,
    descripcion             TEXT,
    porcentaje              DECIMAL(5,2)   NOT NULL CHECK (porcentaje >= 0 AND porcentaje <= 100),
    institucion_financiadora VARCHAR(200)
);

CREATE TABLE IF NOT EXISTS PAGADOR (
    id_pagador        BIGSERIAL    PRIMARY KEY,
    nombres           VARCHAR(150) NOT NULL,
    apellidos         VARCHAR(150) NOT NULL,
    tipo_documento    VARCHAR(50)  NOT NULL,
    numero_documento  VARCHAR(50),
    telefono          VARCHAR(30),
    correo            VARCHAR(150)
);

CREATE TABLE IF NOT EXISTS DOCUMENTO (
    id_documento    BIGSERIAL    PRIMARY KEY,
    nombre          VARCHAR(200) NOT NULL,
    descripcion     TEXT,
    tipo            VARCHAR(50)  NOT NULL,
    url_archivo     TEXT,
    fecha_documento DATE,
    gestion         INT
);

CREATE TABLE IF NOT EXISTS PROGRAMA (
    id_programa   BIGSERIAL    PRIMARY KEY,
    codigo        VARCHAR(50)  NOT NULL UNIQUE,
    nombre        VARCHAR(200) NOT NULL,
    descripcion   TEXT,
    tipo          VARCHAR(50)  NOT NULL,
    estado        VARCHAR(30)  NOT NULL DEFAULT 'ACTIVO'
);

CREATE TABLE IF NOT EXISTS USUARIO (
    id_usuario       BIGSERIAL    PRIMARY KEY,
    nombres          VARCHAR(150) NOT NULL,
    apellidos        VARCHAR(150) NOT NULL,
    ci               VARCHAR(30)  NOT NULL,
    tipo_documento   VARCHAR(50)  NOT NULL,
    correo           VARCHAR(150) NOT NULL,
    telefono         VARCHAR(30),
    direccion        TEXT,
    fecha_nacimiento DATE,
    estado           VARCHAR(30)  NOT NULL DEFAULT 'ACTIVO',
    fecha_registro   TIMESTAMP    NOT NULL DEFAULT NOW(),
    username         VARCHAR(100) UNIQUE,
    password         VARCHAR(255),
    password_hash    VARCHAR(255)
);

-- ============================================================
-- 2. TABLAS CON DEPENDENCIA NIVEL 1
-- ============================================================

CREATE TABLE IF NOT EXISTS ROL_PERMISO (
    id_rol_permiso BIGSERIAL PRIMARY KEY,
    id_rol         BIGINT   NOT NULL REFERENCES ROL(id_rol)     ON DELETE CASCADE,
    id_permiso     BIGINT   NOT NULL REFERENCES PERMISO(id_permiso) ON DELETE CASCADE,
    UNIQUE (id_rol, id_permiso)
);

CREATE TABLE IF NOT EXISTS USUARIO_ROL (
    id_usuario_rol   BIGSERIAL PRIMARY KEY,
    id_usuario       BIGINT    NOT NULL REFERENCES USUARIO(id_usuario)  ON DELETE CASCADE,
    id_rol           BIGINT    NOT NULL REFERENCES ROL(id_rol)          ON DELETE CASCADE,
    activo           BOOLEAN   NOT NULL DEFAULT TRUE,
    fecha_asignacion TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS EXPEDIENTE_DOCUMENTO (
    id_expediente_documento BIGSERIAL PRIMARY KEY,
    id_usuario              BIGINT    NOT NULL REFERENCES USUARIO(id_usuario)     ON DELETE CASCADE,
    id_documento            BIGINT    NOT NULL REFERENCES DOCUMENTO(id_documento) ON DELETE CASCADE,
    fecha_asociacion        TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS MALLA_CURRICULAR (
    id_malla      BIGSERIAL    PRIMARY KEY,
    id_programa   BIGINT       NOT NULL REFERENCES PROGRAMA(id_programa) ON DELETE RESTRICT,
    version       VARCHAR(30)  NOT NULL,
    estado        VARCHAR(30)  NOT NULL DEFAULT 'ACTIVO',
    fecha_vigencia DATE,
    horas_totales  INT         NOT NULL CHECK (horas_totales > 0)
);

-- ============================================================
-- 3. TABLAS CON DEPENDENCIA NIVEL 2
-- ============================================================

CREATE TABLE IF NOT EXISTS MALLA_MODULO (
    id_malla_modulo BIGSERIAL PRIMARY KEY,
    id_malla        BIGINT   NOT NULL REFERENCES MALLA_CURRICULAR(id_malla) ON DELETE CASCADE,
    id_modulo       BIGINT   NOT NULL REFERENCES MODULO(id_modulo)          ON DELETE RESTRICT,
    obligatorio     BOOLEAN  NOT NULL DEFAULT TRUE,
    orden           INT      NOT NULL,
    UNIQUE (id_malla, id_modulo)
);

CREATE TABLE IF NOT EXISTS OFERTA_ACADEMICA (
    id_oferta     BIGSERIAL    PRIMARY KEY,
    codigo        VARCHAR(50)  NOT NULL UNIQUE,
    id_malla      BIGINT       NOT NULL REFERENCES MALLA_CURRICULAR(id_malla) ON DELETE RESTRICT,
    estado        VARCHAR(30)  NOT NULL DEFAULT 'ABIERTA',
    modalidad     VARCHAR(50)  NOT NULL,
    fecha_inicio  DATE         NOT NULL,
    fecha_fin     DATE,
    cupo_maximo   INT          NOT NULL CHECK (cupo_maximo > 0),
    cupo_minimo   INT          NOT NULL CHECK (cupo_minimo >= 0),
    gestion       INT          NOT NULL
);

-- ============================================================
-- 4. TABLAS CON DEPENDENCIA NIVEL 3
-- ============================================================

CREATE TABLE IF NOT EXISTS PREINSCRIPCION (
    id_preinscripcion BIGSERIAL PRIMARY KEY,
    id_usuario        BIGINT    NOT NULL REFERENCES USUARIO(id_usuario)             ON DELETE CASCADE,
    id_oferta         BIGINT    NOT NULL REFERENCES OFERTA_ACADEMICA(id_oferta)     ON DELETE RESTRICT,
    fecha             DATE      NOT NULL DEFAULT CURRENT_DATE,
    estado            VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',
    observacion       TEXT
);

CREATE TABLE IF NOT EXISTS OFERTA_MODULO (
    id_oferta_modulo BIGSERIAL PRIMARY KEY,
    id_oferta        BIGINT    NOT NULL REFERENCES OFERTA_ACADEMICA(id_oferta)  ON DELETE CASCADE,
    id_malla_modulo  BIGINT    NOT NULL REFERENCES MALLA_MODULO(id_malla_modulo) ON DELETE RESTRICT,
    estado           VARCHAR(30) NOT NULL DEFAULT 'ACTIVO',
    fecha_inicio     DATE      NOT NULL,
    fecha_fin        DATE,
    UNIQUE (id_oferta, id_malla_modulo)
);

CREATE TABLE IF NOT EXISTS MATRICULA (
    id_matricula     BIGSERIAL      PRIMARY KEY,
    id_usuario       BIGINT         NOT NULL REFERENCES USUARIO(id_usuario)           ON DELETE RESTRICT,
    id_oferta        BIGINT         NOT NULL REFERENCES OFERTA_ACADEMICA(id_oferta)   ON DELETE RESTRICT,
    fecha            DATE           NOT NULL DEFAULT CURRENT_DATE,
    estado           VARCHAR(30)    NOT NULL DEFAULT 'ACTIVA',
    numero_registro  VARCHAR(50)    NOT NULL UNIQUE,
    tipo_cobro       VARCHAR(50)    NOT NULL,
    monto_original   DECIMAL(10,2)  NOT NULL CHECK (monto_original >= 0),
    monto_descuento  DECIMAL(10,2)  NOT NULL DEFAULT 0 CHECK (monto_descuento >= 0),
    monto_final      DECIMAL(10,2)  NOT NULL CHECK (monto_final >= 0),
    UNIQUE (id_usuario, id_oferta)
);

CREATE TABLE IF NOT EXISTS ARANCEL (
    id_arancel  BIGSERIAL      PRIMARY KEY,
    id_oferta   BIGINT         NOT NULL REFERENCES OFERTA_ACADEMICA(id_oferta) ON DELETE CASCADE,
    concepto    VARCHAR(200)   NOT NULL,
    monto       DECIMAL(10,2)  NOT NULL CHECK (monto >= 0),
    tipo_cobro  VARCHAR(50)    NOT NULL,
    estado      VARCHAR(30)    NOT NULL DEFAULT 'ACTIVO'
);

-- ============================================================
-- 5. TABLAS CON DEPENDENCIA NIVEL 4
-- ============================================================

CREATE TABLE IF NOT EXISTS ASIGNACION_DOCENTE (
    id_asignacion_docente BIGSERIAL     PRIMARY KEY,
    id_oferta_modulo      BIGINT        NOT NULL REFERENCES OFERTA_MODULO(id_oferta_modulo) ON DELETE RESTRICT,
    id_usuario_docente    BIGINT        NOT NULL REFERENCES USUARIO(id_usuario)              ON DELETE RESTRICT,
    estado                VARCHAR(30)   NOT NULL DEFAULT 'ACTIVO',
    fecha_contrato        DATE          NOT NULL,
    fecha_inicio          DATE          NOT NULL,
    fecha_fin             DATE,
    monto_honorario       DECIMAL(10,2) NOT NULL CHECK (monto_honorario >= 0),
    observacion           TEXT,
    tipo_contrato         VARCHAR(50)   NOT NULL
);

CREATE TABLE IF NOT EXISTS INSCRIPCION_MODULO (
    id_inscripcion_modulo BIGSERIAL PRIMARY KEY,
    id_matricula          BIGINT    NOT NULL REFERENCES MATRICULA(id_matricula)         ON DELETE CASCADE,
    id_oferta_modulo      BIGINT    NOT NULL REFERENCES OFERTA_MODULO(id_oferta_modulo) ON DELETE RESTRICT,
    estado                VARCHAR(30) NOT NULL DEFAULT 'INSCRITO',
    UNIQUE (id_matricula, id_oferta_modulo)
);

CREATE TABLE IF NOT EXISTS MENSUALIDAD (
    id_mensualidad    BIGSERIAL      PRIMARY KEY,
    id_matricula      BIGINT         NOT NULL REFERENCES MATRICULA(id_matricula) ON DELETE CASCADE,
    id_arancel        BIGINT         NOT NULL REFERENCES ARANCEL(id_arancel)     ON DELETE RESTRICT,
    numero_cuota      INT            NOT NULL CHECK (numero_cuota > 0),
    fecha_vencimiento DATE           NOT NULL,
    monto_original    DECIMAL(10,2)  NOT NULL CHECK (monto_original >= 0),
    monto_descuento   DECIMAL(10,2)  NOT NULL DEFAULT 0 CHECK (monto_descuento >= 0),
    monto_final       DECIMAL(10,2)  NOT NULL CHECK (monto_final >= 0),
    estado            VARCHAR(30)    NOT NULL DEFAULT 'PENDIENTE'
);

CREATE TABLE IF NOT EXISTS CERTIFICADO (
    id_certificado         BIGSERIAL   PRIMARY KEY,
    id_matricula           BIGINT      NOT NULL REFERENCES MATRICULA(id_matricula) ON DELETE RESTRICT,
    id_usuario_autorizador BIGINT      REFERENCES USUARIO(id_usuario)              ON DELETE SET NULL,
    codigo_unico           VARCHAR(100) NOT NULL UNIQUE,
    codigo_qr              VARCHAR(255),
    tipo                   VARCHAR(50)  NOT NULL,
    estado                 VARCHAR(30)  NOT NULL DEFAULT 'EMITIDO',
    fecha_emision          DATE         NOT NULL DEFAULT CURRENT_DATE,
    url_archivo            TEXT
);

CREATE TABLE IF NOT EXISTS BECA_ESTUDIANTE (
    id_beca_estudiante  BIGSERIAL     PRIMARY KEY,
    id_beca             BIGINT        NOT NULL REFERENCES BECA(id_beca)           ON DELETE RESTRICT,
    id_matricula        BIGINT        NOT NULL REFERENCES MATRICULA(id_matricula) ON DELETE CASCADE,
    porcentaje_aplicado DECIMAL(5,2)  NOT NULL CHECK (porcentaje_aplicado >= 0 AND porcentaje_aplicado <= 100),
    estado              VARCHAR(30)   NOT NULL DEFAULT 'ACTIVA',
    fecha_asignacion    DATE          NOT NULL DEFAULT CURRENT_DATE
);

CREATE TABLE IF NOT EXISTS EVALUACION (
    id_evaluacion    BIGSERIAL     PRIMARY KEY,
    id_oferta_modulo BIGINT        NOT NULL REFERENCES OFERTA_MODULO(id_oferta_modulo) ON DELETE CASCADE,
    nombre           VARCHAR(200)  NOT NULL,
    tipo             VARCHAR(50)   NOT NULL,
    ponderacion      DECIMAL(5,2)  NOT NULL CHECK (ponderacion >= 0 AND ponderacion <= 100),
    fecha            DATE          NOT NULL
);

-- ============================================================
-- 6. TABLAS CON DEPENDENCIA NIVEL 5
-- ============================================================

CREATE TABLE IF NOT EXISTS NOTA (
    id_nota               BIGSERIAL     PRIMARY KEY,
    id_inscripcion_modulo BIGINT        NOT NULL REFERENCES INSCRIPCION_MODULO(id_inscripcion_modulo) ON DELETE CASCADE,
    id_evaluacion         BIGINT        NOT NULL REFERENCES EVALUACION(id_evaluacion)                 ON DELETE CASCADE,
    calificacion          DECIMAL(5,2)  NOT NULL CHECK (calificacion >= 0),
    fecha_registro        TIMESTAMP     NOT NULL DEFAULT NOW(),
    UNIQUE (id_inscripcion_modulo, id_evaluacion)
);

CREATE TABLE IF NOT EXISTS ASISTENCIA (
    id_asistencia         BIGSERIAL   PRIMARY KEY,
    id_inscripcion_modulo BIGINT      NOT NULL REFERENCES INSCRIPCION_MODULO(id_inscripcion_modulo) ON DELETE CASCADE,
    fecha                 DATE        NOT NULL,
    estado                VARCHAR(30) NOT NULL DEFAULT 'PRESENTE',
    observacion           TEXT,
    UNIQUE (id_inscripcion_modulo, fecha)
);

CREATE TABLE IF NOT EXISTS PAGO (
    id_pago              BIGSERIAL      PRIMARY KEY,
    id_matricula         BIGINT         NOT NULL REFERENCES MATRICULA(id_matricula)       ON DELETE RESTRICT,
    id_arancel           BIGINT         NOT NULL REFERENCES ARANCEL(id_arancel)            ON DELETE RESTRICT,
    id_mensualidad       BIGINT         REFERENCES MENSUALIDAD(id_mensualidad)             ON DELETE SET NULL,
    id_metodo_pago       BIGINT         NOT NULL REFERENCES METODO_PAGO(id_metodo_pago)   ON DELETE RESTRICT,
    id_pagador           BIGINT         NOT NULL REFERENCES PAGADOR(id_pagador)            ON DELETE RESTRICT,
    monto                DECIMAL(10,2)  NOT NULL CHECK (monto > 0),
    fecha                TIMESTAMP      NOT NULL DEFAULT NOW(),
    referencia           VARCHAR(100),
    comprobante_url      TEXT,
    estado_conciliacion  VARCHAR(30)    NOT NULL DEFAULT 'PENDIENTE',
    parentesco_pagador   VARCHAR(50)
);

-- ============================================================
-- 7. TABLAS CON DEPENDENCIA NIVEL 6
-- ============================================================

CREATE TABLE IF NOT EXISTS CORRECCION_NOTA (
    id_correccion          BIGSERIAL     PRIMARY KEY,
    id_nota                BIGINT        NOT NULL REFERENCES NOTA(id_nota)       ON DELETE CASCADE,
    id_usuario_solicitante BIGINT        NOT NULL REFERENCES USUARIO(id_usuario) ON DELETE RESTRICT,
    id_usuario_autorizador BIGINT        REFERENCES USUARIO(id_usuario)          ON DELETE SET NULL,
    nota_anterior          DECIMAL(5,2)  NOT NULL,
    nota_nueva             DECIMAL(5,2)  NOT NULL,
    motivo                 TEXT          NOT NULL,
    estado                 VARCHAR(30)   NOT NULL DEFAULT 'PENDIENTE',
    fecha_solicitud        TIMESTAMP     NOT NULL DEFAULT NOW(),
    fecha_autorizacion     TIMESTAMP
);

-- ============================================================
-- ÍNDICES PARA RENDIMIENTO
-- ============================================================

-- Búsquedas frecuentes por usuario
CREATE INDEX IF NOT EXISTS idx_usuario_correo           ON USUARIO(correo);
CREATE INDEX IF NOT EXISTS idx_usuario_ci               ON USUARIO(ci);
CREATE INDEX IF NOT EXISTS idx_usuario_estado           ON USUARIO(estado);

-- Relaciones usuario-rol
CREATE INDEX IF NOT EXISTS idx_usuario_rol_usuario      ON USUARIO_ROL(id_usuario);
CREATE INDEX IF NOT EXISTS idx_usuario_rol_rol          ON USUARIO_ROL(id_rol);

-- Matrículas
CREATE INDEX IF NOT EXISTS idx_matricula_usuario        ON MATRICULA(id_usuario);
CREATE INDEX IF NOT EXISTS idx_matricula_oferta         ON MATRICULA(id_oferta);
CREATE INDEX IF NOT EXISTS idx_matricula_estado         ON MATRICULA(estado);

-- Inscripciones a módulo
CREATE INDEX IF NOT EXISTS idx_inscripcion_matricula    ON INSCRIPCION_MODULO(id_matricula);
CREATE INDEX IF NOT EXISTS idx_inscripcion_oferta_mod   ON INSCRIPCION_MODULO(id_oferta_modulo);

-- Notas
CREATE INDEX IF NOT EXISTS idx_nota_inscripcion         ON NOTA(id_inscripcion_modulo);
CREATE INDEX IF NOT EXISTS idx_nota_evaluacion          ON NOTA(id_evaluacion);

-- Asistencia
CREATE INDEX IF NOT EXISTS idx_asistencia_inscripcion   ON ASISTENCIA(id_inscripcion_modulo);
CREATE INDEX IF NOT EXISTS idx_asistencia_fecha         ON ASISTENCIA(fecha);

-- Pagos
CREATE INDEX IF NOT EXISTS idx_pago_matricula           ON PAGO(id_matricula);
CREATE INDEX IF NOT EXISTS idx_pago_fecha               ON PAGO(fecha);
CREATE INDEX IF NOT EXISTS idx_pago_conciliacion        ON PAGO(estado_conciliacion);

-- Mensualidades
CREATE INDEX IF NOT EXISTS idx_mensualidad_matricula    ON MENSUALIDAD(id_matricula);
CREATE INDEX IF NOT EXISTS idx_mensualidad_vencimiento  ON MENSUALIDAD(fecha_vencimiento);
CREATE INDEX IF NOT EXISTS idx_mensualidad_estado       ON MENSUALIDAD(estado);

-- Ofertas
CREATE INDEX IF NOT EXISTS idx_oferta_estado            ON OFERTA_ACADEMICA(estado);
CREATE INDEX IF NOT EXISTS idx_oferta_malla             ON OFERTA_ACADEMICA(id_malla);

-- Preinscripción
CREATE INDEX IF NOT EXISTS idx_preinscripcion_usuario   ON PREINSCRIPCION(id_usuario);
CREATE INDEX IF NOT EXISTS idx_preinscripcion_oferta    ON PREINSCRIPCION(id_oferta);

-- Expediente
CREATE INDEX IF NOT EXISTS idx_expediente_usuario       ON EXPEDIENTE_DOCUMENTO(id_usuario);

-- Asignación docente
CREATE INDEX IF NOT EXISTS idx_asignacion_docente_mod   ON ASIGNACION_DOCENTE(id_oferta_modulo);
CREATE INDEX IF NOT EXISTS idx_asignacion_docente_usr   ON ASIGNACION_DOCENTE(id_usuario_docente);

-- Corrección de nota
CREATE INDEX IF NOT EXISTS idx_correccion_nota          ON CORRECCION_NOTA(id_nota);
CREATE INDEX IF NOT EXISTS idx_correccion_solicitante   ON CORRECCION_NOTA(id_usuario_solicitante);

-- Certificados
CREATE INDEX IF NOT EXISTS idx_certificado_matricula    ON CERTIFICADO(id_matricula);

-- Beca estudiante
CREATE INDEX IF NOT EXISTS idx_beca_est_matricula       ON BECA_ESTUDIANTE(id_matricula);
