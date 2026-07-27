# Manual QA checklist (Harden)

Run after deploy/activate of Zanjir ≥ 2.0.1.

## Automated
- [ ] `composer install`
- [ ] `composer test` (or `vendor/bin/phpunit`) — all green

## Happy path
- [ ] Place `[zanjir_register]` and `[zanjir_dashboard]` on pages
- [ ] Register affiliate with valid national ID → pending
- [ ] Admin → Affiliates → Approve → code generated, tree row exists
- [ ] Visit `/?ref=CODE` as another user → cookie set
- [ ] Place WooCommerce order → order meta `_zanjir_seller_id` / snapshot row
- [ ] Mark order completed → pending commissions + ledger pending credits
- [ ] After refund window (or trigger cron `zanjir_check_return_window`) → payable + ledger transfer
- [ ] Settlements → prepare → approve → withdrawable balances increase
- [ ] Dashboard withdrawal request → admin approve → mark paid

## Negative / fraud
- [ ] Buyer using own referral code → no attribution + fraud log `self_buy`
- [ ] Refund inside window with pending commissions → void + pending debit
- [ ] Refund inside window after payable → void + payable debit (clawback)
- [ ] Parent without `recruit_enabled` cannot be linked on registration

## Staff override
- [ ] Affiliates → Make staff on an approved user
- [ ] Set `staff_id` on a seller tree row (DB or future UI) OR rely on default staff
- [ ] Completed order produces `staff_override` commission when staff_rate > 0
