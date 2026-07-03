-- Manager tasks module schema.
-- Execute this file in each tenant database (for example, manager_0001).
-- Tenant isolation follows the Manager tenant-database model: each manager_XXXX
-- database owns its task, category, assignee, execution and comment records.

CREATE TABLE IF NOT EXISTS task_categories (
  id int NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL,
  description varchar(255) DEFAULT NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_task_categories_name (name),
  KEY idx_task_categories_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id int NOT NULL AUTO_INCREMENT,
  task_category_id int DEFAULT NULL,
  title varchar(160) NOT NULL,
  status varchar(40) NOT NULL DEFAULT 'open',
  third_party_person_id int DEFAULT NULL,
  third_party_name varchar(160) DEFAULT NULL,
  description mediumtext NOT NULL,
  created_by_user_id int NOT NULL,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_category (task_category_id),
  KEY idx_tasks_status (status),
  KEY idx_tasks_third_party_person (third_party_person_id),
  KEY idx_tasks_created_by_user (created_by_user_id),
  KEY idx_tasks_created (created),
  CONSTRAINT fk_tasks_task_categories
    FOREIGN KEY (task_category_id) REFERENCES task_categories (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_manager_main_people_third_party
    FOREIGN KEY (third_party_person_id) REFERENCES manager_main.people (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_manager_main_users_created_by
    FOREIGN KEY (created_by_user_id) REFERENCES manager_main.users (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_assignees (
  id int NOT NULL AUTO_INCREMENT,
  task_id int NOT NULL,
  person_id int NOT NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_task_assignees_task_person (task_id, person_id),
  KEY idx_task_assignees_task_active (task_id, active),
  KEY idx_task_assignees_person (person_id),
  CONSTRAINT fk_task_assignees_tasks
    FOREIGN KEY (task_id) REFERENCES tasks (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_task_assignees_manager_main_people
    FOREIGN KEY (person_id) REFERENCES manager_main.people (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_executions (
  id int NOT NULL AUTO_INCREMENT,
  task_assignee_id int NOT NULL,
  started_by_user_id int NOT NULL,
  paused_by_user_id int DEFAULT NULL,
  started_at datetime NOT NULL,
  paused_at datetime DEFAULT NULL,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task_executions_assignee (task_assignee_id),
  KEY idx_task_executions_open (task_assignee_id, paused_at),
  KEY idx_task_executions_started_by_user (started_by_user_id),
  KEY idx_task_executions_paused_by_user (paused_by_user_id),
  CONSTRAINT fk_task_executions_task_assignees
    FOREIGN KEY (task_assignee_id) REFERENCES task_assignees (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_task_executions_manager_main_users_started_by
    FOREIGN KEY (started_by_user_id) REFERENCES manager_main.users (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_task_executions_manager_main_users_paused_by
    FOREIGN KEY (paused_by_user_id) REFERENCES manager_main.users (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_comments (
  id int NOT NULL AUTO_INCREMENT,
  task_id int NOT NULL,
  user_id int NOT NULL,
  comment text NOT NULL,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task_comments_task_created (task_id, created),
  KEY idx_task_comments_user (user_id),
  CONSTRAINT fk_task_comments_tasks
    FOREIGN KEY (task_id) REFERENCES tasks (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_task_comments_manager_main_users
    FOREIGN KEY (user_id) REFERENCES manager_main.users (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (`version`, `description`, `created`, `modified`)
VALUES ('tasks-001', 'Tasks categories, assignments, executions and comments module', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  modified = NOW();
