-- Backfill already-sent templates once. The unique (message_id, type) key makes this idempotent.
INSERT IGNORE INTO wa_tenant_dev_fee_quotas (tenant_id)
SELECT DISTINCT c.tenant_id
FROM messages m
INNER JOIN conversations c ON c.id = m.conversation_id
WHERE m.direction = 'out' AND m.type = 'template';

INSERT IGNORE INTO wa_tenant_dev_fee_logs
  (tenant_id, message_id, template_name, user_id, team_id, channel_id, phone, source, type, created_at)
SELECT
  c.tenant_id, m.id, COALESCE(NULLIF(m.template_name, ''), '[template]'), m.sent_by_user_id,
  c.team_id, c.channel_id, c.phone, 'chat', 'consume', m.created_at
FROM messages m
INNER JOIN conversations c ON c.id = m.conversation_id
WHERE m.direction = 'out'
  AND m.type = 'template'
  AND (m.status IS NULL OR TRIM(m.status) = '' OR LOWER(m.status) IN ('sent','delivered','read','accepted','pending'));

UPDATE wa_tenant_dev_fee_quotas q
LEFT JOIN (
  SELECT tenant_id, COUNT(*) AS used_count
  FROM wa_tenant_dev_fee_logs
  WHERE type = 'consume'
  GROUP BY tenant_id
) u ON u.tenant_id = q.tenant_id
LEFT JOIN (
  SELECT tenant_id, COUNT(*) AS refund_count
  FROM wa_tenant_dev_fee_logs
  WHERE type = 'refund'
  GROUP BY tenant_id
) r ON r.tenant_id = q.tenant_id
SET q.quota_used = GREATEST(0, COALESCE(u.used_count, 0) - COALESCE(r.refund_count, 0));
