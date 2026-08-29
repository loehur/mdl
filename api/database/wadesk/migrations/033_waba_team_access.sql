-- Team access is managed per WABA. One team may belong to only one WABA.

CREATE TABLE IF NOT EXISTS wa_waba_teams (
  waba_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (waba_id, team_id),
  UNIQUE KEY uq_waba_team_once (tenant_id, team_id),
  INDEX idx_waba_teams_tenant_waba (tenant_id, waba_id),
  CONSTRAINT fk_waba_team_waba FOREIGN KEY (waba_id) REFERENCES wa_wabas(id) ON DELETE CASCADE,
  CONSTRAINT fk_waba_team_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_waba_team_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
