# Changelog

All notable changes to the Zanjir plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.11.0] - 2026-07-27

### Added
- **Phase 16 — Settlements:** `Zanjir_Settlement_Service` with draft → reviewed → approved flow; payable commissions move to `paid` and ledger `payable → withdrawable` on approve.
- Admin **Settlements** screen to prepare monthly batches and approve them.
- **Phase 17 — Withdrawals:** `Zanjir_Withdrawal_Service` with requested → approved → rejected/paid; approve locks funds via withdrawable debit; reject releases when locked.
- Admin **Withdrawals** screen with approve / reject / mark-paid actions.
- Affiliate `admin-post` endpoint to submit withdrawal requests (nonce-protected).

### Changed
- Version bump `1.10.0` → `1.11.0` (wallet settlement & withdrawals).

## [1.10.0] - 2026-07-27

### Fixed
- **Loader API** now matches the Plugin Boilerplate signature (`hook`, `component`, `method`), so admin, registration, checkout, lifecycle, and refund hooks register correctly.
- **Referral attribution** uses the `zanjir_ref` cookie / `?ref=` code instead of the buyer’s own affiliate code; self-purchase attribution is blocked.
- Referral cookie can be set for logged-in visitors and is readable in the same request.
- Commission rows are created on **order completed** (not at checkout), aligned with `ARCHITECTURE.md`.
- Checkout only captures an immutable **order snapshot**; matrix includes the direct seller plus upline.
- `return_window_ends_at` is set when commissions are created after completion.
- Lifecycle `pending → payable` and `pending → void` updates are **idempotent** (row-level status guard) and always update the ledger.
- Refund void path reuses lifecycle voiding and **debits the pending ledger**.
- Tree loop detection argument order; upline ID type casting; stable closest-parent-first ordering.
- Settings sanitize **merges** with existing options (preserves `matrix` and hidden keys) instead of wiping them.
- Settings capability tied to `manage_zanjir`.
- Integer commission math via `intdiv`; discount cap treated as basis-10000 of line total.
- Order meta reads/writes via WooCommerce CRUD (HPOS-friendly).
- Ledger rejects non-positive amounts and overdrafts; transfer rolls back debit if credit fails.
- Affiliate approve only from `pending`; status-changed action receives real old status.
- Registration flash transients are per-user; duplicate referral code generation on re-approve avoided.
- Scheduled return-window crons cleared on plugin deactivation.

### Changed
- Explicit checkout hook priorities: attach (20) → discount (30) → snapshot (40).
- Version bump `1.9.0` → `1.10.0` (core stability for phases 1–15).

## [1.9.0] - Unreleased archive

Initial scaffold covering schema, settings, roles, registration/tree/referral stubs, commission matrix/engine, lifecycle, refunds, discount meta, and ledger (pre-stabilization).
