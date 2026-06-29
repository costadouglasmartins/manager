ALTER TABLE internal_chat_participants
  ADD COLUMN typing_until_at DATETIME NULL AFTER last_seen_at;
