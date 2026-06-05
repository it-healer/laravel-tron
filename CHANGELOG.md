# Changelog

All notable changes to `laravel-tron` will be documented in this file.

## v1.0.6 - 2026-06-05

### Changed

- `Transfer::preview()` keeps calculating bandwidth/activation fees when the balance is insufficient, so callers can show how much TRX is required instead of failing with a bare «Insufficient balance».

## v1.0.5 - 2026-06-05

### Changed

- Allow Laravel 13 (`illuminate/contracts: ^13.0`).

