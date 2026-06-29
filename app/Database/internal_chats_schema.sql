CREATE TABLE IF NOT EXISTS internal_chats (
  id INT NOT NULL AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  tenant_database_number VARCHAR(10) NOT NULL,
  chat_type ENUM('direct', 'group') NOT NULL DEFAULT 'direct',
  title VARCHAR(160) NULL,
  conversation_key VARCHAR(120) NULL,
  created_by_user_id INT NOT NULL,
  status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  last_message_at DATETIME NULL,
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_internal_chats_conversation_key (conversation_key),
  KEY idx_internal_chats_tenant_status (tenant_id, status),
  KEY idx_internal_chats_last_message_at (last_message_at),
  KEY fk_internal_chats_created_by_user (created_by_user_id),
  CONSTRAINT fk_internal_chats_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
  CONSTRAINT fk_internal_chats_created_by_user
    FOREIGN KEY (created_by_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_chat_participants (
  internal_chat_id INT NOT NULL,
  user_id INT NOT NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  typing_until_at DATETIME NULL,
  archived_at DATETIME NULL,
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (internal_chat_id, user_id),
  KEY idx_internal_chat_participants_user (user_id),
  CONSTRAINT fk_internal_chat_participants_chat
    FOREIGN KEY (internal_chat_id) REFERENCES internal_chats (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_internal_chat_participants_user
    FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_chat_messages (
  id INT NOT NULL AUTO_INCREMENT,
  internal_chat_id INT NOT NULL,
  sender_user_id INT NOT NULL,
  sender_name VARCHAR(255) NOT NULL,
  message_text TEXT NOT NULL,
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_internal_chat_messages_chat_id (internal_chat_id, id),
  KEY idx_internal_chat_messages_sender (sender_user_id),
  CONSTRAINT fk_internal_chat_messages_chat
    FOREIGN KEY (internal_chat_id) REFERENCES internal_chats (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_internal_chat_messages_sender
    FOREIGN KEY (sender_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
