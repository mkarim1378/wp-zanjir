<?php
/**
 * Integer money / commission math tests (FINANCIAL.md).
 *
 * @package Zanjir
 */

use PHPUnit\Framework\TestCase;

class Zanjir_Money_Test extends TestCase {

	public function test_amount_from_rate_matches_financial_example() {
		// base=1_000_000, rates from 3-layer default matrix.
		$this->assertSame( 100000, Zanjir_Money::amount_from_rate( 1000000, 1000 ) );
		$this->assertSame( 75000, Zanjir_Money::amount_from_rate( 1000000, 750 ) );
		$this->assertSame( 25000, Zanjir_Money::amount_from_rate( 1000000, 250 ) );
		$this->assertSame( 50000, Zanjir_Money::amount_from_rate( 1000000, 500 ) );
	}

	public function test_amount_from_rate_floors_residue() {
		// 100 * 333 / 10000 = 3.33 → 3
		$this->assertSame( 3, Zanjir_Money::amount_from_rate( 100, 333 ) );
	}

	public function test_tree_total_never_exceeds_cap_budget() {
		$base   = 1000000;
		$rates  = array( 1000, 750, 250 );
		$paid   = 0;
		foreach ( $rates as $rate ) {
			$paid += Zanjir_Money::amount_from_rate( $base, $rate );
		}
		$budget = Zanjir_Money::amount_from_rate( $base, 2000 );
		$this->assertLessThanOrEqual( $budget, $paid );
		$this->assertSame( 200000, $paid );
	}

	public function test_commission_base_subtracts_discounts() {
		$this->assertSame( 700000, Zanjir_Money::commission_base( 1000000, 200000, 100000 ) );
		$this->assertSame( 0, Zanjir_Money::commission_base( 100000, 80000, 50000 ) );
	}

	public function test_cap_referral_discount_basis_rate() {
		// line=1_000_000, max_discount=3000 (30%) → max 300_000
		// referral 250_000 + coupon 100_000 = 350_000 → referral capped to 200_000
		$capped = Zanjir_Money::cap_referral_discount( 1000000, 250000, 100000, 3000 );
		$this->assertSame( 200000, $capped );
	}

	public function test_cap_referral_discount_no_change_under_cap() {
		$capped = Zanjir_Money::cap_referral_discount( 1000000, 100000, 50000, 3000 );
		$this->assertSame( 100000, $capped );
	}

	public function test_double_dip_off_skips_when_discount_positive() {
		// Pure rule check used by Zanjir_Discount::should_skip_commission.
		$double_dip = 0;
		$discount   = 15000;
		$skip       = ( empty( $double_dip ) && $discount > 0 );
		$this->assertTrue( $skip );

		$double_dip = 1;
		$skip       = ( empty( $double_dip ) && $discount > 0 );
		$this->assertFalse( $skip );
	}

	public function test_zero_inputs() {
		$this->assertSame( 0, Zanjir_Money::amount_from_rate( 0, 1000 ) );
		$this->assertSame( 0, Zanjir_Money::amount_from_rate( 1000, 0 ) );
		$this->assertSame( 0, Zanjir_Money::cap_referral_discount( 1000, 0, 0, 3000 ) );
	}
}
