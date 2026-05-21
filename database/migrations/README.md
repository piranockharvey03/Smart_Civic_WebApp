# Database Migrations

## 2026_05_21_backfill_role_profiles.sql

Backfills existing `citizen_profiles` and `staff_profiles` rows from the current `users` table.

Run this once after applying the updated schema in `database/schema.sql`.

## 2026_05_21_clear_role_specific_columns.sql

Clears duplicated `phone` and `division` values from `users` after profile rows are populated.

Run this after the backfill migration.
