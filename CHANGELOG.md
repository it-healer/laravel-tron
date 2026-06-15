# Changelog

All notable changes to `laravel-tron` will be documented in this file.

## v1.1.0 - 2026-06-15

### Added

- Adaptive (touch-based) synchronization. `tron.touch` now supports `fast_interval` (max sync
  frequency while an address is active) and `slow_interval` (while idle; `null` = skip idle
  addresses). `waiting_seconds` is the active window after the last `touch_at`. Addresses are
  polled often while in use and rarely while idle. Defaults preserve the previous behavior.

## v1.0.6 - 2026-06-05

### Changed

- `Transfer::preview()` keeps calculating bandwidth/activation fees when the balance is insufficient, so callers can show how much TRX is required instead of failing with a bare «Insufficient balance».

## v1.0.5 - 2026-06-05

### Changed

- Allow Laravel 13 (`illuminate/contracts: ^13.0`).

