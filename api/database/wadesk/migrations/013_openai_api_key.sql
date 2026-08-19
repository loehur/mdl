-- OpenAI API key per tenant (fungsi dipakai di step berikutnya)

ALTER TABLE tenants
  ADD COLUMN openai_api_key VARCHAR(255) NULL
  AFTER daily_unique_limit;
