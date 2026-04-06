CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `database_name` varchar(64) NOT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenants_code` (`code`),
  UNIQUE KEY `uq_tenants_database_name` (`database_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_users` (
  `tenant_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` enum('owner','admin','user') NOT NULL DEFAULT 'user',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`tenant_id`,`user_id`),
  KEY `idx_tenant_users_user` (`user_id`),
  CONSTRAINT `fk_tenant_users_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tenant_users_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ability` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_permissions_ability` (`ability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_user_permissions` (
  `tenant_id` int NOT NULL,
  `user_id` int NOT NULL,
  `permission_id` int unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`tenant_id`,`user_id`,`permission_id`),
  KEY `idx_tenant_user_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_tenant_user_permissions_tenant_user`
    FOREIGN KEY (`tenant_id`, `user_id`) REFERENCES `tenant_users` (`tenant_id`, `user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tenant_user_permissions_permission`
    FOREIGN KEY (`permission_id`) REFERENCES `tenant_permissions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tenant_permissions` (`ability`, `description`, `created`, `modified`) VALUES
  ('dashboard.view', 'Visualizar dashboard do tenant', NOW(), NOW()),
  ('customer.index', 'Listar clientes do tenant', NOW(), NOW()),
  ('customer.create', 'Criar clientes do tenant', NOW(), NOW()),
  ('customer.update', 'Atualizar clientes do tenant', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `modified` = VALUES(`modified`);
