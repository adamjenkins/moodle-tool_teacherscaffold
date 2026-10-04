# Changelog

## [0.1.2] - 2026-10-04

### Added

- `LICENSE` file with the full GPL-3.0 text (the plugin was already
  GPL-3.0-or-later; the file was missing).

### Changed

- Maturity is now `MATURITY_BETA` (was `MATURITY_ALPHA`).
- CI tests `MOODLE_503_STABLE` (blocking rows: PHP 8.3-8.4, PostgreSQL 17,
  MariaDB 11.4) instead of the experimental moodle.git `main` rows, now that
  Moodle 5.3 is released.
- `composer.json`: `moodle/moodle` constraint is now `^5.2` (was `>=5.2 <5.4`),
  so later 5.x releases are not excluded.

## v0.1.1

- Supports Moodle 5.2 and 5.3.
- Added composer.json, so the plugin can be installed with Composer.
- First version: stage roles that hide locked activities (and, by default, block import and
  restore), both unlock rules, the tier_unlocked event with a fallback message when tool_wizards
  is not active, the progress notice with "Show me everything", opt back in from Preferences,
  the Guided teachers report, privacy provider, PHPUnit and Behat tests.
