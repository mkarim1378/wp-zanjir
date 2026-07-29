# Zanjir

A powerful multi-tier affiliate marketing plugin for WordPress / WooCommerce.

Zanjir lets every customer become an affiliate with a unique referral code, and
distributes commissions across a configurable multi-level referral tree. Built
with fraud prevention, financial accuracy, and full admin control in mind.

**Current version:** 2.2.1

## Key Features
- **Matrix-based commissions** — depth × position payout matrix with a
  configurable tree cap; direct sellers earn the most, with shrinking shares up
  the chain. Editable in **Zanjir → Settings**.
- **3-layer referral tree** (configurable) with permanent, non-escalating
  attribution via `?ref=CODE` cookies.
- **Flexible budget model** — split the total payout budget between the
  affiliate tree, fixed staff override, and a volume-based reward pool.
- **Referral discount codes** — applied as a live WooCommerce cart fee, optional
  coupon stacking, and a global discount cap (basis-10000).
- **Anti-fraud suite** — mandatory registration with admin approval, unique
  national-ID validation (checksum + hashed storage), self-referral and
  referral-loop detection, IP monitoring, and pluggable third-party identity
  verification hooks.
- **Return-safe commissions** — payouts stay pending through a configurable
  refund window and are voided on return (all-or-nothing), including payable clawback.
- **Internal wallet & ledger** — pending / payable / withdrawable buckets with
  settlement batches and withdrawal requests.
- **Admin panel** — settings + matrix editor, affiliates, settlements,
  withdrawals, fraud queue, bonus plans, and reports (commissions,
  settlements, withdrawals, referral tree) with CSV export.
- **Affiliate dashboard** — referral link, balances, withdrawal form, recruitment status.

## Shortcodes
| Shortcode | Purpose |
|-----------|---------|
| `[zanjir_register]` | Affiliate registration form (logged-in users) |
| `[zanjir_dashboard]` | Affiliate dashboard + withdrawal request |

## Development
```bash
composer install
composer test
php bin/build-languages.php   # regenerate fa_IR po/mo
```

## Tech
WordPress · WooCommerce · custom database tables · precise financial math
(no rounding loss) · i18n-ready (Persian-first, RTL stylesheets).
