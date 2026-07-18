# Zanjir

A powerful multi-tier affiliate marketing plugin for WordPress / WooCommerce.

Zanjir lets every customer become an affiliate with a unique referral code, and
distributes commissions across a configurable multi-level referral tree. Built
with fraud prevention, financial accuracy, and full admin control in mind.

## Key Features
- **Matrix-based commissions** — depth × position payout matrix with a
  configurable tree cap; direct sellers earn the most, with shrinking shares up
  the chain.
- **3-layer referral tree** (configurable) with permanent, non-escalating
  attribution.
- **Flexible budget model** — split the total payout budget between the
  affiliate tree, fixed staff override, and a volume-based reward pool.
- **Referral discount codes** — each affiliate gets a unique code, compatible
  with native WooCommerce coupons, with a global discount cap.
- **Anti-fraud suite** — mandatory registration with admin approval, unique
  national-ID validation (checksum + hashed storage), self-referral and
  referral-loop detection, IP monitoring, and pluggable third-party identity
  verification hooks.
- **Return-safe commissions** — payouts stay pending through a configurable
  refund window and are voided on return (all-or-nothing).
- **Internal wallet & ledger** — independent balance tracking with a
  semi-automated, accountant-approved payout flow and withdrawal requests.
- **Full admin panel** — commission matrix, security switches, plans, approvals,
  tree visualization, sales, and settlement management.
- **Affiliate dashboard** — referral link, personal & downline stats, commission
  status, withdrawable balance, and recruitment eligibility.

## Tech
WordPress · WooCommerce · custom database tables · precise financial math
(no rounding loss) · i18n-ready (Persian-first).
