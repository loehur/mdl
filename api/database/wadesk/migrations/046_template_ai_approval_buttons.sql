-- Button configuration is frozen together with the AI-approved template draft.
ALTER TABLE wa_template_ai_approvals
  ADD COLUMN buttons_json JSON NULL AFTER body;
