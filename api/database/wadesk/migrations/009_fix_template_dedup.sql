-- WaDesk: backfill tenant_id on global Kirimin templates + dedupe by name+language
-- Run on mdl_wadesk after 008_kirimin_channels.sql (safe to re-run)

SET NAMES utf8mb4;

-- Backfill tenant_id from linked channel (legacy YCloud rows)
UPDATE wa_templates t
INNER JOIN wa_channels c ON c.id = t.ycloud_key_id
SET t.tenant_id = c.tenant_id
WHERE t.tenant_id IS NULL;

-- Assign orphan global templates to the only tenant (single-tenant installs)
UPDATE wa_templates t
SET t.tenant_id = (SELECT MIN(id) FROM tenants)
WHERE t.tenant_id IS NULL
  AND (t.ycloud_key_id IS NULL OR t.ycloud_key_id = 0)
  AND (SELECT COUNT(*) FROM tenants) = 1;

-- Remove duplicate params then templates (keep lowest id per tenant+name+language)
DELETE p FROM wa_template_params p
INNER JOIN wa_templates t ON t.id = p.template_id
INNER JOIN wa_templates keeper ON keeper.tenant_id <=> t.tenant_id
  AND keeper.template_name = t.template_name
  AND keeper.language = t.language
  AND keeper.id < t.id;

DELETE t FROM wa_templates t
INNER JOIN wa_templates keeper ON keeper.tenant_id <=> t.tenant_id
  AND keeper.template_name = t.template_name
  AND keeper.language = t.language
  AND keeper.id < t.id;

-- Ensure unique constraint exists (ignore error if already present)
-- ALTER TABLE wa_templates ADD UNIQUE KEY uq_tpl_tenant_name_lang (tenant_id, template_name, language);
