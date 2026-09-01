ALTER TABLE messages
  ADD INDEX idx_msg_agent_template_visibility (conversation_id, sent_by_user_id, type, direction);
