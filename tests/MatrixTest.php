<?php
/**
 * Matrix validation and selection tests.
 *
 * @package Zanjir
 */

use PHPUnit\Framework\TestCase;

class Zanjir_Matrix_Test extends TestCase {

	public function test_defaults_match_financial_spec() {
		$defaults = Zanjir_Matrix::defaults();
		$this->assertCount( 3, $defaults );
		$this->assertSame( array( 2000 ), $defaults[0]['rates'] );
		$this->assertSame( array( 1250, 750 ), $defaults[1]['rates'] );
		$this->assertSame( array( 1000, 750, 250 ), $defaults[2]['rates'] );
	}

	public function test_validate_accepts_defaults() {
		$result = Zanjir_Matrix::validate( Zanjir_Matrix::defaults() );
		$this->assertTrue( $result );
	}

	public function test_validate_rejects_sum_mismatch() {
		$rows = array(
			array( 'depth' => 1, 'rates' => array( 1500 ), 'tree_cap' => 2000 ),
		);
		$result = Zanjir_Matrix::validate( $rows );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'sum_mismatch', $result->get_error_code() );
	}

	public function test_validate_rejects_seller_not_highest() {
		$rows = array(
			array( 'depth' => 2, 'rates' => array( 500, 1500 ), 'tree_cap' => 2000 ),
		);
		$result = Zanjir_Matrix::validate( $rows );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'seller_not_highest', $result->get_error_code() );
	}

	public function test_validate_rejects_depth_mismatch() {
		$rows = array(
			array( 'depth' => 3, 'rates' => array( 1000, 1000 ), 'tree_cap' => 2000 ),
		);
		$result = Zanjir_Matrix::validate( $rows );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'depth_mismatch', $result->get_error_code() );
	}

	public function test_get_rates_for_depth_three() {
		$rates = Zanjir_Matrix::get_rates( 3 );
		$this->assertSame( array( 1000, 750, 250 ), $rates );
	}

	public function test_get_rates_for_depth_one() {
		$rates = Zanjir_Matrix::get_rates( 1 );
		$this->assertSame( array( 2000 ), $rates );
	}
}
