-- Store the detailed phone states returned by Meta during WABA sync.

ALTER TABLE wa_channels
  ADD COLUMN meta_verification_status VARCHAR(32) NULL AFTER meta_phone_number_id,
  ADD COLUMN meta_quality_rating VARCHAR(32) NULL AFTER meta_verification_status;
