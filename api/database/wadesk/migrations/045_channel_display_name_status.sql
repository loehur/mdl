-- Meta display-name review state for each WhatsApp phone number.
ALTER TABLE wa_channels
  ADD COLUMN meta_display_name_status VARCHAR(64) NULL AFTER meta_verification_status;
