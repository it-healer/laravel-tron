# Changelog

All notable changes to `laravel-tron` will be documented in this file.

## v1.11.2 - 2026-11-26

### Fixed

- `TronWallet::transactions()` hides an incoming row of a transfer between the wallet's own addresses only
  when the outgoing row is of the same asset: the token received by a TRX → token swap was hidden as well.

## v1.11.1 - 2026-11-26

### Fixed

- TRX a contract returns to the address within the address's own call (a refund) is no longer a deposit:
  calls are handled before internal transactions, and an internal deposit of the same transaction is removed.
- An internal transfer whose transaction the node reported failed stays failed on later syncs.

## v1.11.0 - 2026-11-26

### Added

- The history of an address includes TRX that moves through contracts: TRX sent with a contract call
  (`call_value`, for example a TRX → token swap or a bridge deposit; from the address to the contract) and
  TRX received from a contract by an internal transaction (a token → TRX swap). `TransferDTO::fromTransaction()`
  parses all three kinds, `Transfers` returns them (internal transactions only to or from the address,
  `TransferDTO::$internal`). `TransferDTO::fromArray()` still parses plain transfers only.
- `AddressSync` reads internal transactions (`search_internal`), asks the node once for the block of an
  internal transfer (its item has none) and makes a deposit of TRX received from a contract.

### Changed

- A row of `tron_transactions` is unique by `txid`, `address` and asset: a TRX → token swap keeps both the
  TRX row and the token row. **Publish and run the new migration** `add_asset_to_tron_transactions_unique_table`
  (stored column `asset` = TRC-20 contract or an empty string for TRX, unique `txid, address, asset`).
- A failed incoming transfer no longer creates a deposit. When one transaction brings the address both TRX
  and a token, only the first one becomes a deposit (a deposit is unique by address and txid).

## v1.10.0 - 2026-11-25

### Changed

- Hidden addresses (`available = false`) keep their money in the wallet: an address that has been
  synchronized before is still synchronized and counted in the wallet TRX and TRC-20 balances and
  pending outgoing transfers. A hidden address that has never been synchronized (a reserve created in
  advance) is still skipped. Scope `TronAddress::syncable()`, `TronAddress::isSyncable()`.

### Added

- `TronWallet::recalculateBalances()` — recalculate the wallet balances from its addresses
  (for example, after synchronizing single addresses).

## v1.9.2 - 2026-10-19

### Fixed

- A node request is limited to 60 s (`tron.http.timeout`, env `TRON_HTTP_TIMEOUT`). The old default
  `30000` was read as seconds, so a node that accepted the connection and went silent held the sync for
  hours. `HttpProvider::$timeout` is now `?int` (null — the config value).

## v1.9.1 - 2026-10-19

### Fixed

- Node requests: 20 s to resolve the host and connect instead of Laravel's default 10 s
  (`tron.http.connect_timeout`, env `TRON_HTTP_CONNECT_TIMEOUT`). A request that failed before reaching the
  server — cURL 6, 7, or 28 while resolving or connecting — is repeated up to `tron.http.retries` times
  (default 2, 500 ms apart). A timeout after the request was sent is never repeated. A repeated
  broadcast is the same signed transaction, so it cannot be sent twice.

## v1.9.0 - 2026-10-18

### Added

- Change detection for scheduled syncs (`tron.sync.change_detection`, on by default): the transfer
  history is skipped when the TRX and TRC20 balances are unchanged since the last history read.
  It is still read on forced syncs, for `settle_seconds` after a change, while a recent deposit is
  below `confirmations`, and at least every `max_skip_seconds`. **New migration**
  `add_scan_state_to_tron_addresses_table` — publish and run it.

### Changed

- A TRC20 balance read reuses the stored `tron_trc20.decimals` instead of calling `decimals()`
  every time (one request instead of two).

### Fixed

- The daily `requests` counter of a node counted most requests twice: every HTTP request is
  already counted by `TronNode::recordRequest()`, and the old manual `increment('requests', N)`
  calls were added on top. They are removed.

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

