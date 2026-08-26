-- Template ↔ team assignment (wajib jika 1 WABA dipakai >1 team)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS wa_template_teams (
  template_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (template_id, team_id),
  INDEX idx_template_teams_team (team_id),
  INDEX idx_template_teams_tenant (tenant_id),
  CONSTRAINT fk_template_teams_template FOREIGN KEY (template_id) REFERENCES wa_templates(id) ON DELETE CASCADE,
  CONSTRAINT fk_template_teams_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_template_teams_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
