# Changelog

All notable changes to `laravel-tron` will be documented in this file.

## v1.6.6 - 2026-10-15

### Fixed

- Confirmed outgoing transfers were marked as dropped when the status lookup failed: a rate limit
  or server error from the node (429/5xx, usually with a non-JSON body) decoded to an empty answer,
  read as "transaction unknown", and the TTL fallback dropped the transfer. `HttpProvider` now
  throws on 429/5xx, and sync never drops a transfer whose lookup failed.
- A dropped transfer that shows up mined (sync history or status lookup) gets its block number and
  loses the drop mark.

### Added

- `tron.pending.recheck_dropped_hours` (default 24): transfers dropped within this window are
  looked up again on every sync.
- `tron:recheck-dropped {--address=} {--sleep=300} {--dry-run}` — looks up every dropped outgoing
  transfer and restores the mined ones (repairs data dropped by earlier versions).

## v1.2.0 - 2026-06-15

### Added

- Alchemy as a Tron RPC node via `Tron::createAlchemyNode()`. RPC calls (balances, resources,
  `triggerconstantcontract`, broadcast) go to Alchemy; address-history endpoints (`v1/accounts/...`,
  which Alchemy doesn't serve) are routed to a separate `index_node` (TronGrid) so deposit detection
  keeps working. New nullable `index_node` column on `tron_nodes`; `ApiManager` routes `v1/*` to it.

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

