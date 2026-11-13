-- ================================================================
-- VENDIA V19 | ESQUEMA UNIFICADO + CUENTAS DEMO (SOLO PARA PRUEBAS)
-- Compatible con PHP 8.1+ / MySQL 8 / MariaDB de XAMPP
-- INSTALACION NUEVA SOLAMENTE: usa una única base llamada `Vendia`.
-- Antes de actualizar una base con usuarios reales, exporta una copia de seguridad. No modifica contraseñas existentes.
-- No crea otras bases ni elimina usuarios o bases de terceros.
-- ================================================================
CREATE DATABASE IF NOT EXISTS `Vendia` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `Vendia`;

-- 1. TABLAS DE NEGOCIO (EN ESPAÑOL)
CREATE TABLE IF NOT EXISTS `negocios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_dueno` INT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `categoria` VARCHAR(80) NOT NULL,
  `telefono` VARCHAR(30) NOT NULL,
  `correo` VARCHAR(150) NOT NULL,
  `horario_comercial` VARCHAR(160) NOT NULL DEFAULT '',
  `imagen_local` VARCHAR(255) NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `estado_suscripcion` ENUM('al_corriente','pendiente','vencida','suspendida') NOT NULL DEFAULT 'al_corriente',
  `proximo_mantenimiento` DATE NULL,
  `fecha_baja` DATETIME NULL,
  `motivo_baja` VARCHAR(255) NULL,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NULL DEFAULT NULL,
  `rol` ENUM('dueño','empleado','cliente','superadministrador') NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido_paterno` VARCHAR(100) NOT NULL,
  `apellido_materno` VARCHAR(100) DEFAULT '',
  `usuario` VARCHAR(80) NOT NULL UNIQUE,
  `correo` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(30) NOT NULL,
  `contrasena_hash` VARCHAR(255) NOT NULL,
  `edad` TINYINT UNSIGNED NULL,
  `antecedentes_medicos` TEXT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `acepto_terminos_en` DATETIME NULL COMMENT 'Fecha de aceptación de términos',
  `aviso_privacidad_visto_en` DATETIME NULL COMMENT 'Fecha de confirmación del aviso de privacidad',
  `consentimiento_salud_en` DATETIME NULL COMMENT 'Consentimiento expreso para dato médico opcional',
  CONSTRAINT `fk_usuarios_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `productos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `categoria` VARCHAR(100) NOT NULL,
  `descripcion` TEXT NULL,
  `precio` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `costo` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `existencias` INT NOT NULL DEFAULT 0,
  `existencia_minima` INT NOT NULL DEFAULT 5,
  `foto` VARCHAR(255) NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_productos_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ventas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `id_usuario` INT NOT NULL,
  `subtotal` DECIMAL(10,2) NULL,
  `descuento_aplicado` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `id_promocion` INT NULL,
  `id_pedido` INT NULL COMMENT 'Pedido asociado, si aplica',
  `total` DECIMAL(10,2) NOT NULL,
  `metodo_pago` ENUM('efectivo','transferencia') NOT NULL,
  `estado` ENUM('completada','cancelada','devuelta') NOT NULL DEFAULT 'completada',
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_ventas_pedido` (`id_pedido`),
  CONSTRAINT `fk_ventas_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`),
  CONSTRAINT `fk_ventas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `detalle_ventas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_venta` INT NOT NULL,
  `id_producto` INT NOT NULL,
  `nombre_producto` VARCHAR(150) NOT NULL,
  `cantidad` INT NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL,
  CONSTRAINT `fk_detalle_ventas_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detalle_ventas_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `id_usuario` INT NOT NULL,
  `id_cliente` INT NULL COMMENT 'Cuenta de cliente vinculada a este pedido',
  `id_oferta_cotizada` INT NULL COMMENT 'Oferta vigente cuando cliente envió el pedido',
  `subtotal_cotizado` DECIMAL(12,2) NULL,
  `descuento_cotizado` DECIMAL(12,2) NULL,
  `total_cotizado` DECIMAL(12,2) NULL,
  `nombre_cliente` VARCHAR(120) NOT NULL,
  `origen` ENUM('mostrador','llamada','whatsapp','web') NOT NULL,
  `lugar_compra` VARCHAR(180) NULL COMMENT 'Lugar donde se realizó la compra, independiente del canal',
  `fecha_entrega_prevista` DATETIME NULL,
  `fecha_reprogramada_en` DATETIME NULL,
  `foto_referencia` VARCHAR(255) NULL,
  `notas` TEXT NULL,
  `estado` ENUM('pendiente','preparacion','listo','entregado') NOT NULL DEFAULT 'pendiente',
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pedidos_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`),
  CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`),
  CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `bitacora` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `id_usuario` INT NOT NULL,
  `accion` VARCHAR(120) NOT NULL,
  `detalles` TEXT NULL,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_bitacora_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`),
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `promociones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `descripcion` TEXT NULL,
  `porcentaje_descuento` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `tipo_beneficio` ENUM('descuento','promocion') NOT NULL DEFAULT 'descuento',
  `unidades_llevas` TINYINT UNSIGNED NULL,
  `unidades_pagas` TINYINT UNSIGNED NULL,
  `fecha_inicio` DATE NULL,
  `fecha_fin` DATE NULL,
  `autorizada` TINYINT(1) NOT NULL DEFAULT 0,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_promociones_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `recuperaciones_contrasena` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `fecha_expiracion` DATETIME NOT NULL,
  `usado` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_recuperaciones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;


-- Migración idempotente: si ya existe Vendia, se agregan solo los campos faltantes.
-- No se eliminan usuarios, negocios, ventas ni fotografías.
SET @db_config = DATABASE();
SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_config AND TABLE_NAME='negocios' AND COLUMN_NAME='horario_comercial');
SET @sql_config = IF(@column_exists=0,
  'ALTER TABLE negocios ADD COLUMN horario_comercial VARCHAR(160) NOT NULL DEFAULT ''''',
  'SELECT ''horario_comercial ya existe''');
PREPARE configuracion_stmt FROM @sql_config; EXECUTE configuracion_stmt; DEALLOCATE PREPARE configuracion_stmt;
SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_config AND TABLE_NAME='negocios' AND COLUMN_NAME='imagen_local');
SET @sql_config = IF(@column_exists=0,
  'ALTER TABLE negocios ADD COLUMN imagen_local VARCHAR(255) NULL',
  'SELECT ''imagen_local ya existe''');
PREPARE configuracion_stmt FROM @sql_config; EXECUTE configuracion_stmt; DEALLOCATE PREPARE configuracion_stmt;


-- 3. AMPLIACIÓN NO DESTRUCTIVA DE PROMOCIONES Y VENTAS (si las columnas faltan)
-- Las promociones antiguas se mantienen sin fecha y sin autorizar hasta editarlas.
SET @col_0 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promociones' AND COLUMN_NAME='fecha_inicio');
SET @stmt_0 = IF(@col_0=0, 'ALTER TABLE `promociones` ADD COLUMN `fecha_inicio` DATE NULL', 'SELECT 1');
PREPARE changarro_stmt FROM @stmt_0; EXECUTE changarro_stmt; DEALLOCATE PREPARE changarro_stmt;
SET @col_1 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promociones' AND COLUMN_NAME='fecha_fin');
SET @stmt_1 = IF(@col_1=0, 'ALTER TABLE `promociones` ADD COLUMN `fecha_fin` DATE NULL', 'SELECT 1');
PREPARE changarro_stmt FROM @stmt_1; EXECUTE changarro_stmt; DEALLOCATE PREPARE changarro_stmt;
SET @col_2 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promociones' AND COLUMN_NAME='autorizada');
SET @stmt_2 = IF(@col_2=0, 'ALTER TABLE `promociones` ADD COLUMN `autorizada` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE changarro_stmt FROM @stmt_2; EXECUTE changarro_stmt; DEALLOCATE PREPARE changarro_stmt;
SET @col_3 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ventas' AND COLUMN_NAME='subtotal');
SET @stmt_3 = IF(@col_3=0, 'ALTER TABLE `ventas` ADD COLUMN `subtotal` DECIMAL(10,2) NULL', 'SELECT 1');
PREPARE changarro_stmt FROM @stmt_3; EXECUTE changarro_stmt; DEALLOCATE PREPARE changarro_stmt;
SET @col_4 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ventas' AND COLUMN_NAME='descuento_aplicado');
SET @stmt_4 = IF(@col_4=0, 'ALTER TABLE `ventas` ADD COLUMN `descuento_aplicado` DECIMAL(10,2) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE changarro_stmt FROM @stmt_4; EXECUTE changarro_stmt; DEALLOCATE PREPARE changarro_stmt;
SET @col_5 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ventas' AND COLUMN_NAME='id_promocion');
SET @stmt_5 = IF(@col_5=0, 'ALTER TABLE `ventas` ADD COLUMN `id_promocion` INT NULL', 'SELECT 1');
PREPARE changarro_stmt FROM @stmt_5; EXECUTE changarro_stmt; DEALLOCATE PREPARE changarro_stmt;

-- VENDIA 14: DOS TIPOS DE OFERTA EN LA MISMA BASE. Reimportable sin borrar datos.
-- Los descuentos anteriores mantienen el tipo "descuento".
SET @nueva=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promociones' AND COLUMN_NAME='tipo_beneficio');
SET @sql=IF(@nueva=0,'ALTER TABLE `promociones` ADD COLUMN `tipo_beneficio` ENUM(''descuento'',''promocion'') NOT NULL DEFAULT ''descuento''','SELECT 1');
PREPARE vendia_oferta FROM @sql; EXECUTE vendia_oferta; DEALLOCATE PREPARE vendia_oferta;
SET @nueva=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promociones' AND COLUMN_NAME='unidades_llevas');
SET @sql=IF(@nueva=0,'ALTER TABLE `promociones` ADD COLUMN `unidades_llevas` TINYINT UNSIGNED NULL','SELECT 1');
PREPARE vendia_oferta FROM @sql; EXECUTE vendia_oferta; DEALLOCATE PREPARE vendia_oferta;
SET @nueva=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promociones' AND COLUMN_NAME='unidades_pagas');
SET @sql=IF(@nueva=0,'ALTER TABLE `promociones` ADD COLUMN `unidades_pagas` TINYINT UNSIGNED NULL','SELECT 1');
PREPARE vendia_oferta FROM @sql; EXECUTE vendia_oferta; DEALLOCATE PREPARE vendia_oferta;

-- VENDIA: PROMOCIONES POR PRODUCTO (versión 5)
-- Se puede volver a importar sin duplicar la tabla ni borrar ventas o usuarios.
-- Antes de importar, respalda tu base de datos desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `promocion_productos` (
  `id_promocion` INT NOT NULL,
  `id_producto` INT NOT NULL,
  `fecha_asignacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_promocion`,`id_producto`),
  KEY `idx_producto_promocion` (`id_producto`),
  CONSTRAINT `fk_producto_de_promocion` FOREIGN KEY (`id_promocion`) REFERENCES `promociones`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_producto_en_promocion` FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Las promociones anteriores permanecen intactas, pero deberán editarse
-- para seleccionar sus productos antes de poder autorizarse o usarse en caja.

-- 6. VINCULACIÓN DE PEDIDOS Y COBROS: migración no destructiva e idempotente.
SET @t = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ventas' AND COLUMN_NAME='id_pedido');
SET @q = IF(@t=0, 'ALTER TABLE `ventas` ADD COLUMN `id_pedido` INT NULL', 'SELECT 1');
PREPARE stmt_pedido FROM @q; EXECUTE stmt_pedido; DEALLOCATE PREPARE stmt_pedido;
SET @t = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ventas' AND INDEX_NAME='uk_ventas_pedido');
SET @q = IF(@t=0, 'ALTER TABLE `ventas` ADD UNIQUE KEY `uk_ventas_pedido` (`id_pedido`)', 'SELECT 1');
PREPARE stmt_pedido FROM @q; EXECUTE stmt_pedido; DEALLOCATE PREPARE stmt_pedido;
SET @t = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ventas' AND CONSTRAINT_NAME='fk_ventas_pedido');
SET @q = IF(@t=0, 'ALTER TABLE `ventas` ADD CONSTRAINT `fk_ventas_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos`(`id`)', 'SELECT 1');
PREPARE stmt_pedido FROM @q; EXECUTE stmt_pedido; DEALLOCATE PREPARE stmt_pedido;


-- 7. MÓDULOS NUEVOS (SOLO ESTA BASE; NO CREAR OTRAS)
CREATE TABLE IF NOT EXISTS `pagos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `id_usuario` INT NOT NULL,
  `importe` DECIMAL(10,2) NOT NULL,
  `referencia` VARCHAR(80) NOT NULL,
  `metodo` ENUM('transferencia','efectivo','otro') NOT NULL DEFAULT 'transferencia',
  `fecha_pago` DATE NOT NULL,
  `estado` ENUM('pendiente','verificado','rechazado') NOT NULL DEFAULT 'pendiente',
  `observaciones` VARCHAR(255) NULL,
  `id_verificador` INT NULL,
  `fecha_verificacion` DATETIME NULL,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_pagos_negocio` (`id_negocio`,`estado`),
  CONSTRAINT `fk_pagos_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`),
  CONSTRAINT `fk_pagos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `solicitudes_ayuda` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `id_usuario` INT NOT NULL,
  `asunto` VARCHAR(140) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `respuesta` TEXT NULL,
  `estado` ENUM('abierta','atendida','cerrada') NOT NULL DEFAULT 'abierta',
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_respuesta` DATETIME NULL,
  KEY `idx_ayuda_negocio` (`id_negocio`,`estado`),
  CONSTRAINT `fk_ayuda_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`),
  CONSTRAINT `fk_ayuda_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `eventos_negocio` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `id_admin` INT NOT NULL,
  `accion` ENUM('alta','baja','activacion','suspension','mantenimiento','pago') NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_eventos_negocio` (`id_negocio`,`fecha_creacion`),
  CONSTRAINT `fk_eventos_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`),
  CONSTRAINT `fk_eventos_admin` FOREIGN KEY (`id_admin`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB;

-- Migración idempotente al copiar una base antigua a Vendia.
-- Las sentencias ALTER solo agregan campos faltantes y no afectan tus usuarios registrados.
SET @exist = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='negocios' AND COLUMN_NAME='activo');
SET @sql = IF(@exist=0,'ALTER TABLE negocios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1','SELECT 1');
PREPARE migracion FROM @sql; EXECUTE migracion; DEALLOCATE PREPARE migracion;
SET @exist = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='negocios' AND COLUMN_NAME='estado_suscripcion');
SET @sql = IF(@exist=0,'ALTER TABLE negocios ADD COLUMN estado_suscripcion ENUM(''al_corriente'',''pendiente'',''vencida'',''suspendida'') NOT NULL DEFAULT ''al_corriente''','SELECT 1');
PREPARE migracion FROM @sql; EXECUTE migracion; DEALLOCATE PREPARE migracion;
SET @exist = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='negocios' AND COLUMN_NAME='proximo_mantenimiento');
SET @sql = IF(@exist=0,'ALTER TABLE negocios ADD COLUMN proximo_mantenimiento DATE NULL','SELECT 1');
PREPARE migracion FROM @sql; EXECUTE migracion; DEALLOCATE PREPARE migracion;
SET @exist = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='negocios' AND COLUMN_NAME='fecha_baja');
SET @sql = IF(@exist=0,'ALTER TABLE negocios ADD COLUMN fecha_baja DATETIME NULL','SELECT 1');
PREPARE migracion FROM @sql; EXECUTE migracion; DEALLOCATE PREPARE migracion;
SET @exist = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='negocios' AND COLUMN_NAME='motivo_baja');
SET @sql = IF(@exist=0,'ALTER TABLE negocios ADD COLUMN motivo_baja VARCHAR(255) NULL','SELECT 1');
PREPARE migracion FROM @sql; EXECUTE migracion; DEALLOCATE PREPARE migracion;
SET @exist = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos' AND COLUMN_NAME='fecha_entrega_prevista');
SET @sql = IF(@exist=0,'ALTER TABLE pedidos ADD COLUMN fecha_entrega_prevista DATETIME NULL','SELECT 1');
PREPARE migracion FROM @sql; EXECUTE migracion; DEALLOCATE PREPARE migracion;

-- VENDIA V20: un Cliente no pertenece a un negocio durante su registro.
-- Los esquemas antiguos a veces dejaron id_negocio como NOT NULL.
-- Esta modificación conserva todas las relaciones y usuarios existentes.
ALTER TABLE `usuarios` MODIFY COLUMN `id_negocio` INT NULL DEFAULT NULL;

-- Cliente y relación con pedidos de manera idempotente, sin borrar registros.
SET @existe_cliente = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos' AND COLUMN_NAME='id_cliente');
SET @ddl_cliente = IF(@existe_cliente=0, 'ALTER TABLE pedidos ADD COLUMN id_cliente INT NULL', 'SELECT 1');
PREPARE crear_cliente FROM @ddl_cliente; EXECUTE crear_cliente; DEALLOCATE PREPARE crear_cliente;
SET @existe_fk_cliente = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='pedidos' AND CONSTRAINT_NAME='fk_pedidos_cliente');
SET @ddl_cliente = IF(@existe_fk_cliente=0, 'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_cliente FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE crear_cliente FROM @ddl_cliente; EXECUTE crear_cliente; DEALLOCATE PREPARE crear_cliente;

-- Unificar valores de rol; los datos históricos en inglés son convertidos al español.
ALTER TABLE `usuarios` MODIFY `rol` ENUM('owner','employee','dueño','empleado','cliente','superadministrador') NOT NULL;
UPDATE `usuarios` SET `rol`='dueño' WHERE `rol`='owner';
UPDATE `usuarios` SET `rol`='empleado' WHERE `rol`='employee';
ALTER TABLE `usuarios` MODIFY `rol` ENUM('dueño','empleado','cliente','superadministrador') NOT NULL;

-- Consentimientos de registros (solo se agregan si no existen; no alteran cuentas previas).
SET @campos_vendia=DATABASE();
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@campos_vendia AND TABLE_NAME='usuarios' AND COLUMN_NAME='acepto_terminos_en');
SET @q=IF(@c=0,'ALTER TABLE usuarios ADD COLUMN acepto_terminos_en DATETIME NULL','SELECT 1');
PREPARE consent FROM @q; EXECUTE consent; DEALLOCATE PREPARE consent;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@campos_vendia AND TABLE_NAME='usuarios' AND COLUMN_NAME='aviso_privacidad_visto_en');
SET @q=IF(@c=0,'ALTER TABLE usuarios ADD COLUMN aviso_privacidad_visto_en DATETIME NULL','SELECT 1');
PREPARE consent FROM @q; EXECUTE consent; DEALLOCATE PREPARE consent;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@campos_vendia AND TABLE_NAME='usuarios' AND COLUMN_NAME='consentimiento_salud_en');
SET @q=IF(@c=0,'ALTER TABLE usuarios ADD COLUMN consentimiento_salud_en DATETIME NULL','SELECT 1');
PREPARE consent FROM @q; EXECUTE consent; DEALLOCATE PREPARE consent;

-- 2. VISTAS TÉCNICAS DE COMPATIBILIDAD CON PHP
--    En phpMyAdmin: las TABLAS son españolas; estas VISTAS no duplican datos.
--    Sirven para que el código PHP actual siga funcionando sin reescribirlo.
--    Son vistas simples actualizables (INSERT / UPDATE / SELECT).
CREATE OR REPLACE VIEW `stores` AS
SELECT `id`, `id_dueno` AS `owner_id`, `nombre` AS `name`, `categoria` AS `category`,
       `telefono` AS `phone`, `correo` AS `email`, `activo`, `estado_suscripcion`,
       `proximo_mantenimiento`, `fecha_baja`, `motivo_baja`,
       `horario_comercial` AS `business_hours`, `imagen_local` AS `photo`,
       `fecha_creacion` AS `created_at`
FROM `negocios`;

CREATE OR REPLACE VIEW `users` AS
SELECT `id`, `id_negocio` AS `store_id`, `rol` AS `role`, `nombre` AS `first_name`,
       `apellido_paterno` AS `last_name1`, `apellido_materno` AS `last_name2`,
       `usuario` AS `username`, `correo` AS `email`, `telefono` AS `phone`,
       `contrasena_hash` AS `password_hash`, `edad` AS `age`,
       `antecedentes_medicos` AS `medical_notes`, `activo` AS `active`,
       `fecha_creacion` AS `created_at`
FROM `usuarios`;

CREATE OR REPLACE VIEW `products` AS
SELECT `id`, `id_negocio` AS `store_id`, `nombre` AS `name`, `categoria` AS `category`,
       `descripcion` AS `description`, `precio` AS `price`, `costo` AS `cost`,
       `existencias` AS `stock`, `existencia_minima` AS `min_stock`,
       `foto` AS `photo`, `activo` AS `active`, `fecha_creacion` AS `created_at`
FROM `productos`;

CREATE OR REPLACE VIEW `sales` AS
SELECT `id`, `id_negocio` AS `store_id`, `id_usuario` AS `user_id`,
       `subtotal`, `descuento_aplicado` AS `discount_amount`, `id_promocion` AS `promotion_id`,
       `id_pedido` AS `order_id`, `total`,
       `metodo_pago` AS `payment_method`, `estado` AS `status`, `fecha_creacion` AS `created_at`
FROM `ventas`;

CREATE OR REPLACE VIEW `sale_items` AS
SELECT `id`, `id_venta` AS `sale_id`, `id_producto` AS `product_id`,
       `nombre_producto` AS `product_name`, `cantidad` AS `quantity`, `precio_unitario` AS `unit_price`
FROM `detalle_ventas`;

CREATE OR REPLACE VIEW `orders` AS
SELECT `id`, `id_negocio` AS `store_id`, `id_usuario` AS `user_id`,
       `id_cliente` AS `customer_id`, `nombre_cliente` AS `customer_name`, `origen` AS `source`,
       `lugar_compra` AS `purchase_location`, `notas` AS `notes`,
       `fecha_entrega_prevista`, `estado` AS `status`, `fecha_creacion` AS `created_at`
FROM `pedidos`;

CREATE OR REPLACE VIEW `audit_logs` AS
SELECT `id`, `id_negocio` AS `store_id`, `id_usuario` AS `user_id`,
       `accion` AS `action`, `detalles` AS `details`, `fecha_creacion` AS `created_at`
FROM `bitacora`;

CREATE OR REPLACE VIEW `promotions` AS
SELECT `id`, `id_negocio` AS `store_id`, `nombre` AS `name`,
       `descripcion` AS `description`, `porcentaje_descuento` AS `discount_percent`,
       `tipo_beneficio`, `unidades_llevas`, `unidades_pagas`,
       `fecha_inicio` AS `start_date`, `fecha_fin` AS `end_date`,
       `autorizada` AS `authorized`, `activo` AS `active`, `fecha_creacion` AS `created_at`
FROM `promociones`;

CREATE OR REPLACE VIEW `password_resets` AS
SELECT `id`, `id_usuario` AS `user_id`, `token_hash`, `fecha_expiracion` AS `expires_at`,
       `usado` AS `used`, `fecha_creacion` AS `created_at`
FROM `recuperaciones_contrasena`;


-- V16: consentimiento voluntario POR NEGOCIO para recibir ofertas por WhatsApp.
-- No se asume el permiso por haber comprado ni por aceptar los términos generales.
CREATE TABLE IF NOT EXISTS `consentimientos_whatsapp` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_cliente` INT NOT NULL,
  `id_negocio` INT NOT NULL,
  `autorizado` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_autorizacion` DATETIME NULL,
  `fecha_revocacion` DATETIME NULL,
  UNIQUE KEY `un_cliente_por_negocio` (`id_cliente`,`id_negocio`),
  CONSTRAINT `fk_consentimiento_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_consentimiento_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. DATOS DE PRUEBA (NO BORRAR EN INSTALACIÓN LOCAL)
--    Estas cuentas YA ESTÁN ACTIVADAS: pueden iniciar sesión sin aprobación.
--    Las contraseñas se guardan como hash bcrypt compatible con password_verify().
--    Importar varias veces no duplica las filas de demostración.

INSERT IGNORE INTO `usuarios`
(`id`, `id_negocio`, `rol`, `nombre`, `apellido_paterno`, `apellido_materno`, `usuario`, `correo`, `telefono`, `contrasena_hash`, `edad`, `antecedentes_medicos`, `activo`)
VALUES
(101, NULL, 'dueño', 'Mariana', 'Ejemplo', 'Demo', 'dueno.demo', 'dueno.demo@outlook.com', '0000000000', '$2y$12$/MN0aR.XGrjz2o8o2DEvR.2XSWJTKrAgBgPtid2SrKk9La6It9Hz.', NULL, NULL, 1);

INSERT IGNORE INTO `negocios`
(`id`, `id_dueno`, `nombre`, `categoria`, `telefono`, `correo`)
VALUES (101, 101, 'Abarrotes La Esquina (DEMO)', 'Tiendas', '0000000000', 'negocio.demo@outlook.com');

UPDATE `usuarios` SET `id_negocio`=101 WHERE `id`=101 AND `usuario`='dueno.demo';

INSERT IGNORE INTO `usuarios`
(`id`, `id_negocio`, `rol`, `nombre`, `apellido_paterno`, `apellido_materno`, `usuario`, `correo`, `telefono`, `contrasena_hash`, `edad`, `antecedentes_medicos`, `activo`)
VALUES
(102, 101, 'empleado', 'Carlos', 'Prueba', 'Demo', 'empleado.demo', 'empleado.demo@outlook.com', '0000000000', '$2y$12$enfo/tr0WeOG8YMGIH/GceHeG433Urc5N12E/9/BC41Re5jLq3zji', 24, NULL, 1),
(103, 101, 'empleado', 'Laura', 'Pendiente', 'Demo', 'empleado.pendiente', 'pendiente.demo@outlook.com', '0000000000', '$2y$12$/cDbogAXTPGoyvjjSg9H3.YMUFVaWVcX/1QUl/g.gfQcN.c.nnptu', 22, NULL, 0);

INSERT IGNORE INTO `productos`
(`id`, `id_negocio`, `nombre`, `categoria`, `descripcion`, `precio`, `costo`, `existencias`, `existencia_minima`, `activo`)
VALUES
(201, 101, 'Agua natural 1 L', 'Bebidas', 'Botella de agua natural de 1 litro.', 15.00, 8.00, 22, 10, 1),
(202, 101, 'Leche entera 1 L', 'Lácteos', 'Leche entera de 1 litro.', 29.00, 21.00, 8, 10, 1),
(203, 101, 'Pan dulce', 'Panadería', 'Pieza individual.', 18.00, 9.00, 34, 8, 1),
(204, 101, 'Café molido 250 g', 'Abarrotes', 'Café molido, presentación de 250 gramos.', 89.00, 65.00, 12, 5, 1),
(205, 101, 'Arroz 1 kg', 'Abarrotes', 'Bolsa de arroz de un kilogramo.', 32.00, 21.00, 40, 10, 1),
(206, 101, 'Galletas 170 g', 'Abarrotes', 'Paquete de galletas.', 26.00, 16.00, 6, 10, 1);

-- Ventas de muestra con totales conciliados contra los artículos.
INSERT IGNORE INTO `ventas` (`id`, `id_negocio`, `id_usuario`, `total`, `metodo_pago`, `estado`)
VALUES (301,101,102,48.00,'efectivo','completada'),
       (302,101,101,118.00,'transferencia','completada');

INSERT IGNORE INTO `detalle_ventas` (`id`, `id_venta`, `id_producto`, `nombre_producto`, `cantidad`, `precio_unitario`)
VALUES (401,301,201,'Agua natural 1 L',2,15.00),
       (402,301,203,'Pan dulce',1,18.00),
       (403,302,202,'Leche entera 1 L',1,29.00),
       (404,302,204,'Café molido 250 g',1,89.00);

INSERT IGNORE INTO `pedidos` (`id`, `id_negocio`, `id_usuario`, `nombre_cliente`, `origen`, `lugar_compra`, `notas`, `estado`)
VALUES (501,101,102,'Cliente de demostración','mostrador','Local principal','Pedido para recoger por la tarde.','pendiente');

INSERT IGNORE INTO `promociones` (`id`, `id_negocio`, `nombre`, `descripcion`, `porcentaje_descuento`, `activo`)
VALUES (601,101,'Oferta de bienvenida','Promoción de demostración; consultar condiciones en caja.',10.00,1);

-- La oferta de demostración se limita a agua y pan dulce.
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (601,201),(601,203);

INSERT IGNORE INTO `bitacora` (`id`, `id_negocio`, `id_usuario`, `accion`, `detalles`)
VALUES (701,101,101,'Instalación demo','Base de datos de prueba con datos ficticios.');

-- 4. COMPROBACIÓN (puedes ejecutar esta consulta después de importar)
-- SELECT id, nombre, usuario, rol, activo FROM usuarios ORDER BY id;



-- ================================================================
-- 5. DATOS DEMOSTRATIVOS COMPLETOS (no alteran contraseñas existentes)
-- CINCO negocios, cada uno con 5 empleados, 5 clientes, 5 productos,
-- 5 pedidos, 5 ventas, 5 renglones de venta, 5 promociones,
-- 5 relaciones promoción/producto, 5 pagos, 5 solicitudes,
-- 5 eventos y 5 movimientos de bitácora. Todas son cuentas de PRUEBA.
-- Los correos NO son buzones verificados. Cambia las claves al publicar.
-- La importación es idempotente: INSERT IGNORE / UPDATE condicionado.
-- ================================================================

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`activo`)
VALUES (900001,NULL,'superadministrador','Super','Admin','Vendia','superadmin.vendia','superadmin.demo.vendia@outlook.com','0000000000','$2y$12$JInWC1tbyhSzwq7sAZKtmOqR043dUCKeQfs9F4Ct1UdZGXh9tA4YK',1);

-- Negocio 1: La Fondita de Lisset
-- Si superlissy ya existe con una contraseña propia, NO se cambia esa cuenta:
-- la copia DEMO se crea como superlissy.demo para no usurpar el usuario real.
SET @usuario_demo_lisset = IF(EXISTS(SELECT 1 FROM usuarios WHERE id=920001 AND usuario='superlissy'), 'superlissy',
                              IF(EXISTS(SELECT 1 FROM usuarios WHERE usuario='superlissy'), 'superlissy.demo', 'superlissy'));
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`activo`)
VALUES (920001,NULL,'dueño','Lisset','Morales','Prueba',@usuario_demo_lisset,'vendia.demo.dueno1@outlook.com','0000000000','$2y$12$OFcOcUA5EGLAAyzIRIhfbe03KB.XWw.PSYCOFoBn3.vdEZdM5GcVe',1);
SET @dueno_demo_1=(SELECT id FROM usuarios WHERE usuario=@usuario_demo_lisset AND rol='dueño' LIMIT 1);
INSERT IGNORE INTO `negocios` (`id`,`id_dueno`,`nombre`,`categoria`,`telefono`,`correo`,`estado_suscripcion`,`activo`,`proximo_mantenimiento`)
VALUES (910001,@dueno_demo_1,'La Fondita de Lisset','Fondas','0000000000','vendia.demo.local1@outlook.com','al_corriente',1,DATE_ADD(CURDATE(),INTERVAL 5 DAY));
UPDATE `usuarios` SET `id_negocio`=910001 WHERE id=@dueno_demo_1 AND id_negocio IS NULL;

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930011,910001,'empleado','Ana','Pérez','Demo','empleado1.1','vendia.demo.emp1.1@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',21,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940011,910001,'cliente','Ana','Pérez','Cliente','cliente1.1','vendia.demo.cliente1.1@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',20,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950011,910001,'Torta de pollo','Comida','Producto ficticio de demostración para La Fondita de Lisset',55.00,32.00,15,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960011,910001,930011,940011,'Ana Pérez Cliente','mostrador','Local principal, La Fondita de Lisset',DATE_ADD(NOW(),INTERVAL -2 DAY),'Pedido de prueba 1','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980011,910001,930011,55.00,0,NULL,NULL,55.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990011,980011,950011,'Torta de pollo',1,55.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970011,910001,'Oferta 1 La Fondita de Lisset','Solo sobre el producto seleccionado',6,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970011,950011);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000011,910001,@dueno_demo_1,110.00,'DEMO-1-1','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010011,910001,930011,'Consulta de prueba 1','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020011,910001,930011,'Demostración','Movimiento de muestra 1');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030011,910001,@dueno_demo_1,'alta','Evento ficticio de demostración 1');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930012,910001,'empleado','Carlos','López','Demo','empleado1.2','vendia.demo.emp1.2@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',22,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940012,910001,'cliente','Carlos','López','Cliente','cliente1.2','vendia.demo.cliente1.2@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',21,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950012,910001,'Agua de jamaica','Bebidas','Producto ficticio de demostración para La Fondita de Lisset',20.00,9.00,16,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960012,910001,930012,940012,'Carlos López Cliente','llamada','Local principal, La Fondita de Lisset',DATE_ADD(NOW(),INTERVAL -1 DAY),'Pedido de prueba 2','preparacion');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980012,910001,930012,20.00,0,NULL,NULL,20.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990012,980012,950012,'Agua de jamaica',1,20.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970012,910001,'Oferta 2 La Fondita de Lisset','Solo sobre el producto seleccionado',7,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970012,950012);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000012,910001,@dueno_demo_1,120.00,'DEMO-1-2','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010012,910001,930012,'Consulta de prueba 2','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020012,910001,930012,'Demostración','Movimiento de muestra 2');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030012,910001,@dueno_demo_1,'alta','Evento ficticio de demostración 2');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930013,910001,'empleado','Diana','García','Demo','empleado1.3','vendia.demo.emp1.3@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',23,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940013,910001,'cliente','Diana','García','Cliente','cliente1.3','vendia.demo.cliente1.3@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',22,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950013,910001,'Enchiladas verdes','Comida','Producto ficticio de demostración para La Fondita de Lisset',79.00,47.00,17,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960013,910001,930013,940013,'Diana García Cliente','whatsapp','Local principal, La Fondita de Lisset',DATE_ADD(NOW(),INTERVAL 0 DAY),'Pedido de prueba 3','listo');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980013,910001,930013,79.00,0,NULL,NULL,79.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990013,980013,950013,'Enchiladas verdes',1,79.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970013,910001,'Oferta 3 La Fondita de Lisset','Solo sobre el producto seleccionado',8,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970013,950013);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000013,910001,@dueno_demo_1,130.00,'DEMO-1-3','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010013,910001,930013,'Consulta de prueba 3','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020013,910001,930013,'Demostración','Movimiento de muestra 3');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030013,910001,@dueno_demo_1,'alta','Evento ficticio de demostración 3');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930014,910001,'empleado','Emilio','Torres','Demo','empleado1.4','vendia.demo.emp1.4@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',24,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940014,910001,'cliente','Emilio','Torres','Cliente','cliente1.4','vendia.demo.cliente1.4@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',23,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950014,910001,'Café de olla','Bebidas','Producto ficticio de demostración para La Fondita de Lisset',25.00,11.00,18,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960014,910001,930014,940014,'Emilio Torres Cliente','mostrador','Local principal, La Fondita de Lisset',DATE_ADD(NOW(),INTERVAL 1 DAY),'Pedido de prueba 4','entregado');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980014,910001,930014,25.00,0,NULL,960014,25.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990014,980014,950014,'Café de olla',1,25.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970014,910001,'Oferta 4 La Fondita de Lisset','Solo sobre el producto seleccionado',9,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970014,950014);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000014,910001,@dueno_demo_1,140.00,'DEMO-1-4','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010014,910001,930014,'Consulta de prueba 4','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020014,910001,930014,'Demostración','Movimiento de muestra 4');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030014,910001,@dueno_demo_1,'alta','Evento ficticio de demostración 4');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930015,910001,'empleado','Fátima','Santos','Demo','empleado1.5','vendia.demo.emp1.5@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',25,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940015,910001,'cliente','Fátima','Santos','Cliente','cliente1.5','vendia.demo.cliente1.5@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',24,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950015,910001,'Flan casero','Postres','Producto ficticio de demostración para La Fondita de Lisset',35.00,17.00,19,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960015,910001,930015,940015,'Fátima Santos Cliente','llamada','Local principal, La Fondita de Lisset',DATE_ADD(NOW(),INTERVAL 2 DAY),'Pedido de prueba 5','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980015,910001,930015,35.00,0,NULL,NULL,35.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990015,980015,950015,'Flan casero',1,35.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970015,910001,'Oferta 5 La Fondita de Lisset','Solo sobre el producto seleccionado',10,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970015,950015);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000015,910001,@dueno_demo_1,150.00,'DEMO-1-5','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010015,910001,930015,'Consulta de prueba 5','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020015,910001,930015,'Demostración','Movimiento de muestra 5');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030015,910001,@dueno_demo_1,'alta','Evento ficticio de demostración 5');


-- Negocio 2: Abarrotes El Faro
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`activo`)
VALUES (920002,NULL,'dueño','Mariana','Solís','Prueba','dueno.abarrotes','vendia.demo.dueno2@outlook.com','0000000000','$2y$12$5d6fyw6Juln52VPTuNJ5Leqsi0pU3R8pGf5e57bPOdMvmQhBI.lpa',1);
SET @dueno_demo_2=(SELECT id FROM usuarios WHERE usuario='dueno.abarrotes' AND rol='dueño' LIMIT 1);
INSERT IGNORE INTO `negocios` (`id`,`id_dueno`,`nombre`,`categoria`,`telefono`,`correo`,`estado_suscripcion`,`activo`,`proximo_mantenimiento`)
VALUES (910002,@dueno_demo_2,'Abarrotes El Faro','Tiendas','0000000000','vendia.demo.local2@outlook.com','al_corriente',1,DATE_ADD(CURDATE(),INTERVAL 10 DAY));
UPDATE `usuarios` SET `id_negocio`=910002 WHERE id=@dueno_demo_2 AND id_negocio IS NULL;

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930021,910002,'empleado','Ana','Pérez','Demo','empleado2.1','vendia.demo.emp2.1@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',21,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940021,910002,'cliente','Ana','Pérez','Cliente','cliente2.1','vendia.demo.cliente2.1@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',20,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950021,910002,'Leche entera','Lácteos','Producto ficticio de demostración para Abarrotes El Faro',29.00,22.00,15,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960021,910002,930021,940021,'Ana Pérez Cliente','mostrador','Local principal, Abarrotes El Faro',DATE_ADD(NOW(),INTERVAL -2 DAY),'Pedido de prueba 1','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980021,910002,930021,29.00,0,NULL,NULL,29.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990021,980021,950021,'Leche entera',1,29.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970021,910002,'Oferta 1 Abarrotes El Faro','Solo sobre el producto seleccionado',6,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970021,950021);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000021,910002,@dueno_demo_2,110.00,'DEMO-2-1','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010021,910002,930021,'Consulta de prueba 1','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020021,910002,930021,'Demostración','Movimiento de muestra 1');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030021,910002,@dueno_demo_2,'alta','Evento ficticio de demostración 1');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930022,910002,'empleado','Carlos','López','Demo','empleado2.2','vendia.demo.emp2.2@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',22,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940022,910002,'cliente','Carlos','López','Cliente','cliente2.2','vendia.demo.cliente2.2@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',21,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950022,910002,'Pan integral','Panadería','Producto ficticio de demostración para Abarrotes El Faro',42.00,30.00,16,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960022,910002,930022,940022,'Carlos López Cliente','llamada','Local principal, Abarrotes El Faro',DATE_ADD(NOW(),INTERVAL -1 DAY),'Pedido de prueba 2','preparacion');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980022,910002,930022,42.00,0,NULL,NULL,42.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990022,980022,950022,'Pan integral',1,42.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970022,910002,'Oferta 2 Abarrotes El Faro','Solo sobre el producto seleccionado',7,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970022,950022);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000022,910002,@dueno_demo_2,120.00,'DEMO-2-2','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010022,910002,930022,'Consulta de prueba 2','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020022,910002,930022,'Demostración','Movimiento de muestra 2');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030022,910002,@dueno_demo_2,'alta','Evento ficticio de demostración 2');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930023,910002,'empleado','Diana','García','Demo','empleado2.3','vendia.demo.emp2.3@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',23,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940023,910002,'cliente','Diana','García','Cliente','cliente2.3','vendia.demo.cliente2.3@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',22,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950023,910002,'Agua embotellada','Bebidas','Producto ficticio de demostración para Abarrotes El Faro',17.00,9.00,17,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960023,910002,930023,940023,'Diana García Cliente','whatsapp','Local principal, Abarrotes El Faro',DATE_ADD(NOW(),INTERVAL 0 DAY),'Pedido de prueba 3','listo');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980023,910002,930023,17.00,0,NULL,NULL,17.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990023,980023,950023,'Agua embotellada',1,17.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970023,910002,'Oferta 3 Abarrotes El Faro','Solo sobre el producto seleccionado',8,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970023,950023);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000023,910002,@dueno_demo_2,130.00,'DEMO-2-3','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010023,910002,930023,'Consulta de prueba 3','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020023,910002,930023,'Demostración','Movimiento de muestra 3');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030023,910002,@dueno_demo_2,'alta','Evento ficticio de demostración 3');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930024,910002,'empleado','Emilio','Torres','Demo','empleado2.4','vendia.demo.emp2.4@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',24,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940024,910002,'cliente','Emilio','Torres','Cliente','cliente2.4','vendia.demo.cliente2.4@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',23,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950024,910002,'Arroz integral','Despensa','Producto ficticio de demostración para Abarrotes El Faro',38.00,24.00,18,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960024,910002,930024,940024,'Emilio Torres Cliente','mostrador','Local principal, Abarrotes El Faro',DATE_ADD(NOW(),INTERVAL 1 DAY),'Pedido de prueba 4','entregado');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980024,910002,930024,38.00,0,NULL,960024,38.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990024,980024,950024,'Arroz integral',1,38.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970024,910002,'Oferta 4 Abarrotes El Faro','Solo sobre el producto seleccionado',9,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970024,950024);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000024,910002,@dueno_demo_2,140.00,'DEMO-2-4','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010024,910002,930024,'Consulta de prueba 4','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020024,910002,930024,'Demostración','Movimiento de muestra 4');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030024,910002,@dueno_demo_2,'alta','Evento ficticio de demostración 4');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930025,910002,'empleado','Fátima','Santos','Demo','empleado2.5','vendia.demo.emp2.5@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',25,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940025,910002,'cliente','Fátima','Santos','Cliente','cliente2.5','vendia.demo.cliente2.5@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',24,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950025,910002,'Galletas avena','Dulces','Producto ficticio de demostración para Abarrotes El Faro',26.00,16.00,19,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960025,910002,930025,940025,'Fátima Santos Cliente','llamada','Local principal, Abarrotes El Faro',DATE_ADD(NOW(),INTERVAL 2 DAY),'Pedido de prueba 5','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980025,910002,930025,26.00,0,NULL,NULL,26.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990025,980025,950025,'Galletas avena',1,26.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970025,910002,'Oferta 5 Abarrotes El Faro','Solo sobre el producto seleccionado',10,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970025,950025);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000025,910002,@dueno_demo_2,150.00,'DEMO-2-5','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010025,910002,930025,'Consulta de prueba 5','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020025,910002,930025,'Demostración','Movimiento de muestra 5');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030025,910002,@dueno_demo_2,'alta','Evento ficticio de demostración 5');


-- Negocio 3: Taller Rodante
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`activo`)
VALUES (920003,NULL,'dueño','Fernando','Ruiz','Prueba','dueno.taller','vendia.demo.dueno3@outlook.com','0000000000','$2y$12$5d6fyw6Juln52VPTuNJ5Leqsi0pU3R8pGf5e57bPOdMvmQhBI.lpa',1);
SET @dueno_demo_3=(SELECT id FROM usuarios WHERE usuario='dueno.taller' AND rol='dueño' LIMIT 1);
INSERT IGNORE INTO `negocios` (`id`,`id_dueno`,`nombre`,`categoria`,`telefono`,`correo`,`estado_suscripcion`,`activo`,`proximo_mantenimiento`)
VALUES (910003,@dueno_demo_3,'Taller Rodante','Talleres','0000000000','vendia.demo.local3@outlook.com','al_corriente',1,DATE_ADD(CURDATE(),INTERVAL 15 DAY));
UPDATE `usuarios` SET `id_negocio`=910003 WHERE id=@dueno_demo_3 AND id_negocio IS NULL;

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930031,910003,'empleado','Ana','Pérez','Demo','empleado3.1','vendia.demo.emp3.1@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',21,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940031,910003,'cliente','Ana','Pérez','Cliente','cliente3.1','vendia.demo.cliente3.1@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',20,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950031,910003,'Aceite sintético','Refacciones','Producto ficticio de demostración para Taller Rodante',280.00,195.00,15,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960031,910003,930031,940031,'Ana Pérez Cliente','mostrador','Local principal, Taller Rodante',DATE_ADD(NOW(),INTERVAL -2 DAY),'Pedido de prueba 1','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980031,910003,930031,280.00,0,NULL,NULL,280.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990031,980031,950031,'Aceite sintético',1,280.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970031,910003,'Oferta 1 Taller Rodante','Solo sobre el producto seleccionado',6,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970031,950031);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000031,910003,@dueno_demo_3,110.00,'DEMO-3-1','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010031,910003,930031,'Consulta de prueba 1','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020031,910003,930031,'Demostración','Movimiento de muestra 1');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030031,910003,@dueno_demo_3,'alta','Evento ficticio de demostración 1');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930032,910003,'empleado','Carlos','López','Demo','empleado3.2','vendia.demo.emp3.2@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',22,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940032,910003,'cliente','Carlos','López','Cliente','cliente3.2','vendia.demo.cliente3.2@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',21,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950032,910003,'Filtro de aire','Refacciones','Producto ficticio de demostración para Taller Rodante',170.00,100.00,16,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960032,910003,930032,940032,'Carlos López Cliente','llamada','Local principal, Taller Rodante',DATE_ADD(NOW(),INTERVAL -1 DAY),'Pedido de prueba 2','preparacion');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980032,910003,930032,170.00,0,NULL,NULL,170.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990032,980032,950032,'Filtro de aire',1,170.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970032,910003,'Oferta 2 Taller Rodante','Solo sobre el producto seleccionado',7,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970032,950032);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000032,910003,@dueno_demo_3,120.00,'DEMO-3-2','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010032,910003,930032,'Consulta de prueba 2','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020032,910003,930032,'Demostración','Movimiento de muestra 2');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030032,910003,@dueno_demo_3,'alta','Evento ficticio de demostración 2');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930033,910003,'empleado','Diana','García','Demo','empleado3.3','vendia.demo.emp3.3@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',23,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940033,910003,'cliente','Diana','García','Cliente','cliente3.3','vendia.demo.cliente3.3@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',22,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950033,910003,'Bujía estándar','Refacciones','Producto ficticio de demostración para Taller Rodante',110.00,60.00,17,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960033,910003,930033,940033,'Diana García Cliente','whatsapp','Local principal, Taller Rodante',DATE_ADD(NOW(),INTERVAL 0 DAY),'Pedido de prueba 3','listo');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980033,910003,930033,110.00,0,NULL,NULL,110.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990033,980033,950033,'Bujía estándar',1,110.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970033,910003,'Oferta 3 Taller Rodante','Solo sobre el producto seleccionado',8,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970033,950033);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000033,910003,@dueno_demo_3,130.00,'DEMO-3-3','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010033,910003,930033,'Consulta de prueba 3','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020033,910003,930033,'Demostración','Movimiento de muestra 3');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030033,910003,@dueno_demo_3,'alta','Evento ficticio de demostración 3');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930034,910003,'empleado','Emilio','Torres','Demo','empleado3.4','vendia.demo.emp3.4@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',24,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940034,910003,'cliente','Emilio','Torres','Cliente','cliente3.4','vendia.demo.cliente3.4@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',23,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950034,910003,'Anticongelante','Fluidos','Producto ficticio de demostración para Taller Rodante',145.00,90.00,18,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960034,910003,930034,940034,'Emilio Torres Cliente','mostrador','Local principal, Taller Rodante',DATE_ADD(NOW(),INTERVAL 1 DAY),'Pedido de prueba 4','entregado');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980034,910003,930034,145.00,0,NULL,960034,145.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990034,980034,950034,'Anticongelante',1,145.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970034,910003,'Oferta 4 Taller Rodante','Solo sobre el producto seleccionado',9,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970034,950034);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000034,910003,@dueno_demo_3,140.00,'DEMO-3-4','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010034,910003,930034,'Consulta de prueba 4','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020034,910003,930034,'Demostración','Movimiento de muestra 4');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030034,910003,@dueno_demo_3,'alta','Evento ficticio de demostración 4');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930035,910003,'empleado','Fátima','Santos','Demo','empleado3.5','vendia.demo.emp3.5@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',25,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940035,910003,'cliente','Fátima','Santos','Cliente','cliente3.5','vendia.demo.cliente3.5@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',24,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950035,910003,'Líquido frenos','Fluidos','Producto ficticio de demostración para Taller Rodante',120.00,72.00,19,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960035,910003,930035,940035,'Fátima Santos Cliente','llamada','Local principal, Taller Rodante',DATE_ADD(NOW(),INTERVAL 2 DAY),'Pedido de prueba 5','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980035,910003,930035,120.00,0,NULL,NULL,120.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990035,980035,950035,'Líquido frenos',1,120.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970035,910003,'Oferta 5 Taller Rodante','Solo sobre el producto seleccionado',10,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970035,950035);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000035,910003,@dueno_demo_3,150.00,'DEMO-3-5','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010035,910003,930035,'Consulta de prueba 5','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020035,910003,930035,'Demostración','Movimiento de muestra 5');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030035,910003,@dueno_demo_3,'alta','Evento ficticio de demostración 5');


-- Negocio 4: Consultorio Horizonte
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`activo`)
VALUES (920004,NULL,'dueño','Claudia','Nieto','Prueba','dueno.consultorio','vendia.demo.dueno4@outlook.com','0000000000','$2y$12$5d6fyw6Juln52VPTuNJ5Leqsi0pU3R8pGf5e57bPOdMvmQhBI.lpa',1);
SET @dueno_demo_4=(SELECT id FROM usuarios WHERE usuario='dueno.consultorio' AND rol='dueño' LIMIT 1);
INSERT IGNORE INTO `negocios` (`id`,`id_dueno`,`nombre`,`categoria`,`telefono`,`correo`,`estado_suscripcion`,`activo`,`proximo_mantenimiento`)
VALUES (910004,@dueno_demo_4,'Consultorio Horizonte','Consultorios','0000000000','vendia.demo.local4@outlook.com','al_corriente',1,DATE_ADD(CURDATE(),INTERVAL 20 DAY));
UPDATE `usuarios` SET `id_negocio`=910004 WHERE id=@dueno_demo_4 AND id_negocio IS NULL;

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930041,910004,'empleado','Ana','Pérez','Demo','empleado4.1','vendia.demo.emp4.1@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',21,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940041,910004,'cliente','Ana','Pérez','Cliente','cliente4.1','vendia.demo.cliente4.1@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',20,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950041,910004,'Curita adhesiva','Insumos','Producto ficticio de demostración para Consultorio Horizonte',12.00,4.00,15,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960041,910004,930041,940041,'Ana Pérez Cliente','mostrador','Local principal, Consultorio Horizonte',DATE_ADD(NOW(),INTERVAL -2 DAY),'Pedido de prueba 1','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980041,910004,930041,12.00,0,NULL,NULL,12.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990041,980041,950041,'Curita adhesiva',1,12.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970041,910004,'Oferta 1 Consultorio Horizonte','Solo sobre el producto seleccionado',6,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970041,950041);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000041,910004,@dueno_demo_4,110.00,'DEMO-4-1','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010041,910004,930041,'Consulta de prueba 1','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020041,910004,930041,'Demostración','Movimiento de muestra 1');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030041,910004,@dueno_demo_4,'alta','Evento ficticio de demostración 1');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930042,910004,'empleado','Carlos','López','Demo','empleado4.2','vendia.demo.emp4.2@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',22,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940042,910004,'cliente','Carlos','López','Cliente','cliente4.2','vendia.demo.cliente4.2@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',21,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950042,910004,'Gel antibacterial','Higiene','Producto ficticio de demostración para Consultorio Horizonte',75.00,30.00,16,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960042,910004,930042,940042,'Carlos López Cliente','llamada','Local principal, Consultorio Horizonte',DATE_ADD(NOW(),INTERVAL -1 DAY),'Pedido de prueba 2','preparacion');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980042,910004,930042,75.00,0,NULL,NULL,75.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990042,980042,950042,'Gel antibacterial',1,75.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970042,910004,'Oferta 2 Consultorio Horizonte','Solo sobre el producto seleccionado',7,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970042,950042);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000042,910004,@dueno_demo_4,120.00,'DEMO-4-2','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010042,910004,930042,'Consulta de prueba 2','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020042,910004,930042,'Demostración','Movimiento de muestra 2');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030042,910004,@dueno_demo_4,'alta','Evento ficticio de demostración 2');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930043,910004,'empleado','Diana','García','Demo','empleado4.3','vendia.demo.emp4.3@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',23,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940043,910004,'cliente','Diana','García','Cliente','cliente4.3','vendia.demo.cliente4.3@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',22,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950043,910004,'Cubrebocas','Insumos','Producto ficticio de demostración para Consultorio Horizonte',8.00,3.00,17,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960043,910004,930043,940043,'Diana García Cliente','whatsapp','Local principal, Consultorio Horizonte',DATE_ADD(NOW(),INTERVAL 0 DAY),'Pedido de prueba 3','listo');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980043,910004,930043,8.00,0,NULL,NULL,8.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990043,980043,950043,'Cubrebocas',1,8.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970043,910004,'Oferta 3 Consultorio Horizonte','Solo sobre el producto seleccionado',8,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970043,950043);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000043,910004,@dueno_demo_4,130.00,'DEMO-4-3','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010043,910004,930043,'Consulta de prueba 3','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020043,910004,930043,'Demostración','Movimiento de muestra 3');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030043,910004,@dueno_demo_4,'alta','Evento ficticio de demostración 3');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930044,910004,'empleado','Emilio','Torres','Demo','empleado4.4','vendia.demo.emp4.4@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',24,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940044,910004,'cliente','Emilio','Torres','Cliente','cliente4.4','vendia.demo.cliente4.4@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',23,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950044,910004,'Venda elástica','Insumos','Producto ficticio de demostración para Consultorio Horizonte',55.00,24.00,18,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960044,910004,930044,940044,'Emilio Torres Cliente','mostrador','Local principal, Consultorio Horizonte',DATE_ADD(NOW(),INTERVAL 1 DAY),'Pedido de prueba 4','entregado');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980044,910004,930044,55.00,0,NULL,960044,55.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990044,980044,950044,'Venda elástica',1,55.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970044,910004,'Oferta 4 Consultorio Horizonte','Solo sobre el producto seleccionado',9,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970044,950044);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000044,910004,@dueno_demo_4,140.00,'DEMO-4-4','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010044,910004,930044,'Consulta de prueba 4','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020044,910004,930044,'Demostración','Movimiento de muestra 4');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030044,910004,@dueno_demo_4,'alta','Evento ficticio de demostración 4');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930045,910004,'empleado','Fátima','Santos','Demo','empleado4.5','vendia.demo.emp4.5@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',25,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940045,910004,'cliente','Fátima','Santos','Cliente','cliente4.5','vendia.demo.cliente4.5@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',24,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950045,910004,'Termómetro digital','Equipo','Producto ficticio de demostración para Consultorio Horizonte',210.00,128.00,19,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960045,910004,930045,940045,'Fátima Santos Cliente','llamada','Local principal, Consultorio Horizonte',DATE_ADD(NOW(),INTERVAL 2 DAY),'Pedido de prueba 5','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980045,910004,930045,210.00,0,NULL,NULL,210.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990045,980045,950045,'Termómetro digital',1,210.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970045,910004,'Oferta 5 Consultorio Horizonte','Solo sobre el producto seleccionado',10,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970045,950045);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000045,910004,@dueno_demo_4,150.00,'DEMO-4-5','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010045,910004,930045,'Consulta de prueba 5','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020045,910004,930045,'Demostración','Movimiento de muestra 5');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030045,910004,@dueno_demo_4,'alta','Evento ficticio de demostración 5');


-- Negocio 5: Mercado Buena Cosecha
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`activo`)
VALUES (920005,NULL,'dueño','Rosa','Vega','Prueba','dueno.mercado','vendia.demo.dueno5@outlook.com','0000000000','$2y$12$5d6fyw6Juln52VPTuNJ5Leqsi0pU3R8pGf5e57bPOdMvmQhBI.lpa',1);
SET @dueno_demo_5=(SELECT id FROM usuarios WHERE usuario='dueno.mercado' AND rol='dueño' LIMIT 1);
INSERT IGNORE INTO `negocios` (`id`,`id_dueno`,`nombre`,`categoria`,`telefono`,`correo`,`estado_suscripcion`,`activo`,`proximo_mantenimiento`)
VALUES (910005,@dueno_demo_5,'Mercado Buena Cosecha','Puestos en mercados','0000000000','vendia.demo.local5@outlook.com','al_corriente',1,DATE_ADD(CURDATE(),INTERVAL 25 DAY));
UPDATE `usuarios` SET `id_negocio`=910005 WHERE id=@dueno_demo_5 AND id_negocio IS NULL;

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930051,910005,'empleado','Ana','Pérez','Demo','empleado5.1','vendia.demo.emp5.1@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',21,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940051,910005,'cliente','Ana','Pérez','Cliente','cliente5.1','vendia.demo.cliente5.1@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',20,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950051,910005,'Manzana roja kg','Fruta','Producto ficticio de demostración para Mercado Buena Cosecha',58.00,35.00,15,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960051,910005,930051,940051,'Ana Pérez Cliente','mostrador','Local principal, Mercado Buena Cosecha',DATE_ADD(NOW(),INTERVAL -2 DAY),'Pedido de prueba 1','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980051,910005,930051,58.00,0,NULL,NULL,58.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990051,980051,950051,'Manzana roja kg',1,58.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970051,910005,'Oferta 1 Mercado Buena Cosecha','Solo sobre el producto seleccionado',6,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970051,950051);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000051,910005,@dueno_demo_5,110.00,'DEMO-5-1','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010051,910005,930051,'Consulta de prueba 1','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020051,910005,930051,'Demostración','Movimiento de muestra 1');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030051,910005,@dueno_demo_5,'alta','Evento ficticio de demostración 1');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930052,910005,'empleado','Carlos','López','Demo','empleado5.2','vendia.demo.emp5.2@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',22,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940052,910005,'cliente','Carlos','López','Cliente','cliente5.2','vendia.demo.cliente5.2@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',21,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950052,910005,'Plátano kg','Fruta','Producto ficticio de demostración para Mercado Buena Cosecha',27.00,16.00,16,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960052,910005,930052,940052,'Carlos López Cliente','llamada','Local principal, Mercado Buena Cosecha',DATE_ADD(NOW(),INTERVAL -1 DAY),'Pedido de prueba 2','preparacion');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980052,910005,930052,27.00,0,NULL,NULL,27.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990052,980052,950052,'Plátano kg',1,27.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970052,910005,'Oferta 2 Mercado Buena Cosecha','Solo sobre el producto seleccionado',7,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970052,950052);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000052,910005,@dueno_demo_5,120.00,'DEMO-5-2','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010052,910005,930052,'Consulta de prueba 2','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020052,910005,930052,'Demostración','Movimiento de muestra 2');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030052,910005,@dueno_demo_5,'alta','Evento ficticio de demostración 2');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930053,910005,'empleado','Diana','García','Demo','empleado5.3','vendia.demo.emp5.3@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',23,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940053,910005,'cliente','Diana','García','Cliente','cliente5.3','vendia.demo.cliente5.3@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',22,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950053,910005,'Jitomate kg','Verdura','Producto ficticio de demostración para Mercado Buena Cosecha',32.00,22.00,17,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960053,910005,930053,940053,'Diana García Cliente','whatsapp','Local principal, Mercado Buena Cosecha',DATE_ADD(NOW(),INTERVAL 0 DAY),'Pedido de prueba 3','listo');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980053,910005,930053,32.00,0,NULL,NULL,32.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990053,980053,950053,'Jitomate kg',1,32.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970053,910005,'Oferta 3 Mercado Buena Cosecha','Solo sobre el producto seleccionado',8,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970053,950053);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000053,910005,@dueno_demo_5,130.00,'DEMO-5-3','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010053,910005,930053,'Consulta de prueba 3','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020053,910005,930053,'Demostración','Movimiento de muestra 3');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030053,910005,@dueno_demo_5,'alta','Evento ficticio de demostración 3');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930054,910005,'empleado','Emilio','Torres','Demo','empleado5.4','vendia.demo.emp5.4@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',24,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940054,910005,'cliente','Emilio','Torres','Cliente','cliente5.4','vendia.demo.cliente5.4@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',23,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950054,910005,'Cebolla blanca kg','Verdura','Producto ficticio de demostración para Mercado Buena Cosecha',29.00,18.00,18,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960054,910005,930054,940054,'Emilio Torres Cliente','mostrador','Local principal, Mercado Buena Cosecha',DATE_ADD(NOW(),INTERVAL 1 DAY),'Pedido de prueba 4','entregado');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980054,910005,930054,29.00,0,NULL,960054,29.00,'transferencia','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990054,980054,950054,'Cebolla blanca kg',1,29.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970054,910005,'Oferta 4 Mercado Buena Cosecha','Solo sobre el producto seleccionado',9,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970054,950054);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000054,910005,@dueno_demo_5,140.00,'DEMO-5-4','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010054,910005,930054,'Consulta de prueba 4','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020054,910005,930054,'Demostración','Movimiento de muestra 4');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030054,910005,@dueno_demo_5,'alta','Evento ficticio de demostración 4');

INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (930055,910005,'empleado','Fátima','Santos','Demo','empleado5.5','vendia.demo.emp5.5@outlook.com','0000000000','$2y$12$clfeublCA.b/xrZ2ESRXyuiWmw2F9BtqwAU34lamEZmZGuOaES5Gq',25,1);
INSERT IGNORE INTO `usuarios` (`id`,`id_negocio`,`rol`,`nombre`,`apellido_paterno`,`apellido_materno`,`usuario`,`correo`,`telefono`,`contrasena_hash`,`edad`,`activo`)
VALUES (940055,910005,'cliente','Fátima','Santos','Cliente','cliente5.5','vendia.demo.cliente5.5@outlook.com','0000000000','$2y$12$.oQWb0fuRZ/Y/5t/xkWWTeCrJWe1loHymuA8ugNDepOoZsiboz6z2',24,1);
INSERT IGNORE INTO `productos` (`id`,`id_negocio`,`nombre`,`categoria`,`descripcion`,`precio`,`costo`,`existencias`,`existencia_minima`,`activo`)
VALUES (950055,910005,'Limón kg','Fruta','Producto ficticio de demostración para Mercado Buena Cosecha',40.00,27.00,19,5,1);
INSERT IGNORE INTO `pedidos` (`id`,`id_negocio`,`id_usuario`,`id_cliente`,`nombre_cliente`,`origen`,`lugar_compra`,`fecha_entrega_prevista`,`notas`,`estado`)
VALUES (960055,910005,930055,940055,'Fátima Santos Cliente','llamada','Local principal, Mercado Buena Cosecha',DATE_ADD(NOW(),INTERVAL 2 DAY),'Pedido de prueba 5','pendiente');
INSERT IGNORE INTO `ventas` (`id`,`id_negocio`,`id_usuario`,`subtotal`,`descuento_aplicado`,`id_promocion`,`id_pedido`,`total`,`metodo_pago`,`estado`)
VALUES (980055,910005,930055,40.00,0,NULL,NULL,40.00,'efectivo','completada');
INSERT IGNORE INTO `detalle_ventas` (`id`,`id_venta`,`id_producto`,`nombre_producto`,`cantidad`,`precio_unitario`)
VALUES (990055,980055,950055,'Limón kg',1,40.00);
INSERT IGNORE INTO `promociones` (`id`,`id_negocio`,`nombre`,`descripcion`,`porcentaje_descuento`,`fecha_inicio`,`fecha_fin`,`autorizada`,`activo`)
VALUES (970055,910005,'Oferta 5 Mercado Buena Cosecha','Solo sobre el producto seleccionado',10,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 60 DAY),0,1);
INSERT IGNORE INTO `promocion_productos` (`id_promocion`,`id_producto`) VALUES (970055,950055);
INSERT IGNORE INTO `pagos` (`id`,`id_negocio`,`id_usuario`,`importe`,`referencia`,`metodo`,`fecha_pago`,`estado`)
VALUES (1000055,910005,@dueno_demo_5,150.00,'DEMO-5-5','transferencia',CURDATE(),'pendiente');
INSERT IGNORE INTO `solicitudes_ayuda` (`id`,`id_negocio`,`id_usuario`,`asunto`,`mensaje`,`estado`)
VALUES (1010055,910005,930055,'Consulta de prueba 5','Solicitud ficticia para demostración de atención al negocio.','abierta');
INSERT IGNORE INTO `bitacora` (`id`,`id_negocio`,`id_usuario`,`accion`,`detalles`)
VALUES (1020055,910005,930055,'Demostración','Movimiento de muestra 5');
INSERT IGNORE INTO `eventos_negocio` (`id`,`id_negocio`,`id_admin`,`accion`,`descripcion`)
VALUES (1030055,910005,@dueno_demo_5,'alta','Evento ficticio de demostración 5');


-- Consultas de verificación:
-- SELECT rol,COUNT(*) FROM usuarios GROUP BY rol;
-- SELECT n.nombre,(SELECT COUNT(*) FROM productos p WHERE p.id_negocio=n.id) AS productos,
--  (SELECT COUNT(*) FROM pedidos q WHERE q.id_negocio=n.id) AS pedidos,
--  (SELECT COUNT(*) FROM ventas v WHERE v.id_negocio=n.id) AS ventas
-- FROM negocios n WHERE n.id BETWEEN 910001 AND 910005;

-- V19: conservar el precio y la oferta confirmados al crear pedidos web.
-- Es seguro repetir esta importación: nunca elimina cuentas, pedidos ni ventas.
SET @vendia_db = DATABASE();
SET @col=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@vendia_db AND TABLE_NAME='pedidos' AND COLUMN_NAME='id_oferta_cotizada');
SET @sql=IF(@col=0,'ALTER TABLE pedidos ADD COLUMN id_oferta_cotizada INT NULL','SELECT 1');
PREPARE vendia_quote FROM @sql; EXECUTE vendia_quote; DEALLOCATE PREPARE vendia_quote;
SET @col=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@vendia_db AND TABLE_NAME='pedidos' AND COLUMN_NAME='subtotal_cotizado');
SET @sql=IF(@col=0,'ALTER TABLE pedidos ADD COLUMN subtotal_cotizado DECIMAL(12,2) NULL','SELECT 1');
PREPARE vendia_quote FROM @sql; EXECUTE vendia_quote; DEALLOCATE PREPARE vendia_quote;
SET @col=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@vendia_db AND TABLE_NAME='pedidos' AND COLUMN_NAME='descuento_cotizado');
SET @sql=IF(@col=0,'ALTER TABLE pedidos ADD COLUMN descuento_cotizado DECIMAL(12,2) NULL','SELECT 1');
PREPARE vendia_quote FROM @sql; EXECUTE vendia_quote; DEALLOCATE PREPARE vendia_quote;
SET @col=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@vendia_db AND TABLE_NAME='pedidos' AND COLUMN_NAME='total_cotizado');
SET @sql=IF(@col=0,'ALTER TABLE pedidos ADD COLUMN total_cotizado DECIMAL(12,2) NULL','SELECT 1');
PREPARE vendia_quote FROM @sql; EXECUTE vendia_quote; DEALLOCATE PREPARE vendia_quote;

-- VENDIA · Pedidos multinegocio, evidencias y notificaciones.
-- Actualización idempotente: reimportar este archivo no elimina usuarios, ventas ni pedidos.
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos' AND COLUMN_NAME='foto_referencia');
SET @q=IF(@c=0,'ALTER TABLE pedidos ADD COLUMN foto_referencia VARCHAR(255) NULL','SELECT 1'); PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos' AND COLUMN_NAME='fecha_reprogramada_en');
SET @q=IF(@c=0,'ALTER TABLE pedidos ADD COLUMN fecha_reprogramada_en DATETIME NULL','SELECT 1'); PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- Se conserva el canal histórico y se incorpora 'web' para compras desde la cuenta Cliente.
ALTER TABLE pedidos MODIFY origen ENUM('mostrador','llamada','whatsapp','web') NOT NULL;
CREATE TABLE IF NOT EXISTS `detalle_pedidos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pedido` INT NOT NULL,
  `id_producto` INT NOT NULL,
  `nombre_producto` VARCHAR(150) NOT NULL,
  `cantidad` INT UNSIGNED NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL,
  CONSTRAINT `fk_det_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_det_prod` FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`),
  UNIQUE KEY `uk_pedido_producto` (`id_pedido`,`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL,
  `id_negocio` INT NULL,
  `tipo` ENUM('pedido','estado_pedido','ayuda','pago','agenda','general') NOT NULL DEFAULT 'general',
  `titulo` VARCHAR(150) NOT NULL,
  `mensaje` VARCHAR(500) NOT NULL,
  `enlace` VARCHAR(180) NOT NULL DEFAULT 'index.php?p=notificaciones',
  `leida` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notificaciones_destino` (`id_usuario`,`leida`,`fecha_creacion`),
  CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notificacion_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VENDIA V17 · Resumen semanal único por negocio y por lunes.
-- Reimportación segura: crea la tabla si falta; no borra ventas ni cuentas.
CREATE TABLE IF NOT EXISTS `resumenes_semanales` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_negocio` INT NOT NULL,
  `fecha_lunes` DATE NOT NULL COMMENT 'Lunes en el que se entrega el resumen',
  `periodo_inicio` DATE NOT NULL COMMENT 'Lunes anterior',
  `periodo_fin` DATE NOT NULL COMMENT 'Domingo anterior',
  `ventas_cantidad` INT UNSIGNED NOT NULL DEFAULT 0,
  `ingresos` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `pedidos_pendientes` INT UNSIGNED NOT NULL DEFAULT 0,
  `recomendacion` VARCHAR(500) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_resumen_negocio_lunes` (`id_negocio`, `fecha_lunes`),
  CONSTRAINT `fk_resumen_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
