# Changelog

All notable changes to the School Management Calendar plugin will be documented in this file.

## [1.1.1] - 2026-02-26

### Changed
- **Payment Date Calculation**: Completely rewritten vacation-aware payment logic
  - New `smc_calculate_subscription_payment_date()` function for accurate date calculation
  - New `smc_add_months_preserve_day()` function to preserve original day of month
  - New `smc_add_vacation_days_between()` function for proper vacation day counting

### Fixed
- **Day Preservation**: Payment dates now correctly maintain the original enrollment start day
  - Example: Jan 31 → Feb 28 → Mar 31 → Apr 30 → May 31
  - Handles months with different day counts properly

- **Vacation Calculation**: Fixed vacation day calculation logic
  - If payment date falls inside vacation: uses offset from vacation start
  - If vacation is between payments: adds full vacation duration
  - Handles overlapping and multiple vacation periods correctly
  - Once vacation adjusts a date, subsequent payments follow new pattern

### Technical
- Added recursive vacation checking (max 5 iterations) to handle consecutive vacations
- Improved debug logging for payment date calculations
- Deprecated old `smc_calculate_next_payment_date()` in favor of new function

## [1.1.0] - 2026-01-28

### Added
- **Multi-Day Vacation Events**: Added support for vacation periods spanning multiple days
  - New `event_end_date` field in events table for defining vacation date ranges
  - Events form now includes "Event End Date" field with validation
  - Single-day events work as before (leave end date empty)
- **Payment Integration**: Vacation periods now integrate with subscription payment calculations
  - Helper functions to retrieve vacation periods within date ranges
  - Automatic payment date extension based on vacation overlap
  - Detailed logging for vacation adjustments

### Technical
- Database migration to version 1.2.0
- Added `event_end_date` DATE column to `smc_events` table
- Added index on `event_end_date` for query performance
- New helper functions: `smc_get_vacation_periods()` and `smc_calculate_next_payment_date()`
- Automatic migration on plugin activation for existing installations

### Documentation
- Added vacation-aware payments documentation in main plugin

## [1.0.2] - 2026-01-27

### Fixed
- French translation updates

## [1.0.1] - 2026-01-27

### Fixed
- Initial French translation improvements

## [1.0.0] - 2025-12-18

### Added
- Initial release of Calendar & Schedule plugin
- Course schedule management with recurring sessions
- Event management (exams, holidays, meetings, special events)
- Visual calendar view with week/month views
- Integration with School Management plugin
- Support for classrooms, teachers, and courses
