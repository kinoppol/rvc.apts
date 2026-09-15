-- Split "high demand" mode into individually toggleable rules. high_demand_mode stays as the master
-- switch (turns every rule on); each hd_* column enables one rule on its own.
-- See SlotSettings::HIGH_DEMAND_RULES / SlotSettings::highDemandRule().
ALTER TABLE slot_settings
  ADD COLUMN IF NOT EXISTS hd_no_early    TINYINT(1) NOT NULL DEFAULT 0 AFTER high_demand_mode,
  ADD COLUMN IF NOT EXISTS hd_no_adjacent TINYINT(1) NOT NULL DEFAULT 0 AFTER hd_no_early,
  ADD COLUMN IF NOT EXISTS hd_daily_limit TINYINT(1) NOT NULL DEFAULT 0 AFTER hd_no_adjacent,
  ADD COLUMN IF NOT EXISTS hd_show_banner TINYINT(1) NOT NULL DEFAULT 0 AFTER hd_daily_limit;
