-- Recovery: isi ulang wa_key_daily_contacts dari pesan outbound sukses
-- (019 menghapus baris tenant-level jika waba_id channel belum terisi saat migrasi)

SET NAMES utf8mb4;

INSERT INTO wa_key_daily_contacts (
  tenant_id,
  waba_id,
  contact_date,
  phone,
  status,
  first_source,
  last_source,
  first_attempt_at,
  last_attempt_at
)
SELECT
  conv.tenant_id,
  TRIM(ch.waba_id) AS waba_id,
  DATE(m.created_at) AS contact_date,
  conv.phone,
  'sent' AS status,
  'backfill' AS first_source,
  'backfill' AS last_source,
  MIN(m.created_at) AS first_attempt_at,
  MAX(m.created_at) AS last_attempt_at
FROM messages m
INNER JOIN conversations conv ON conv.id = m.conversation_id
INNER JOIN wa_channels ch ON ch.id = conv.channel_id
WHERE m.direction = 'out'
  AND ch.waba_id IS NOT NULL
  AND TRIM(ch.waba_id) <> ''
  AND (
    m.status IS NULL
    OR TRIM(m.status) = ''
    OR LOWER(m.status) IN ('sent', 'delivered', 'read', 'accepted', 'pending')
  )
GROUP BY conv.tenant_id, TRIM(ch.waba_id), DATE(m.created_at), conv.phone
ON DUPLICATE KEY UPDATE
  last_attempt_at = GREATEST(wa_key_daily_contacts.last_attempt_at, VALUES(last_attempt_at)),
  status = 'sent',
  last_source = 'backfill';
