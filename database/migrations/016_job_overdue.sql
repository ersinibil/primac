-- Migration: 016_job_overdue — geciken iş bildirimi takibi
ALTER TABLE jobs ADD COLUMN overdue_notified_at DATE NULL;
