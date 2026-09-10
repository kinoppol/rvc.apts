-- Admin-toggleable "high demand" mode: when usage is unusually high, admins can switch on stricter
-- rules to spread capacity across more students (no early access, no back-to-back slots, capped
-- daily bookings). See SlotSettings::isHighDemandMode() and Booking::HIGH_DEMAND_DAILY_LIMIT.
ALTER TABLE slot_settings
  ADD COLUMN IF NOT EXISTS high_demand_mode TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_current_slot;
