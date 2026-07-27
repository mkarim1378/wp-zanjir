<?php
/**
 * National ID validator tests.
 *
 * @package Zanjir
 */

use PHPUnit\Framework\TestCase;

class Zanjir_National_Id_Validator_Test extends TestCase {

	public function test_rejects_wrong_length() {
		$this->assertFalse( Zanjir_National_Id_Validator::validate( '123' ) );
		$this->assertFalse( Zanjir_National_Id_Validator::validate( '12345678901' ) );
	}

	public function test_rejects_all_same_digits() {
		$this->assertFalse( Zanjir_National_Id_Validator::validate( '0000000000' ) );
		$this->assertFalse( Zanjir_National_Id_Validator::validate( '1111111111' ) );
	}

	public function test_accepts_known_valid_checksum() {
		// Well-known valid Iranian national ID checksum sample.
		$this->assertTrue( Zanjir_National_Id_Validator::validate( '0013542419' ) );
	}

	public function test_process_invalid_returns_flag() {
		$result = Zanjir_National_Id_Validator::process( '1111111111' );
		$this->assertFalse( $result['valid'] );
		$this->assertSame( '', $result['hash'] );
	}

	public function test_hash_is_stable_for_same_input() {
		$a = Zanjir_National_Id_Validator::hash( '0013542419' );
		$b = Zanjir_National_Id_Validator::hash( '001-354-2419' );
		$this->assertSame( $a, $b );
		$this->assertSame( 64, strlen( $a ) );
	}

	public function test_hmac_pepper_changes_hash() {
		if ( ! defined( 'ZANJIR_NID_KEY' ) ) {
			define( 'ZANJIR_NID_KEY', 'test-pepper-key' );
		}

		$with_pepper = Zanjir_National_Id_Validator::hash( '0013542419' );
		$this->assertSame( 64, strlen( $with_pepper ) );
		$this->assertNotSame( hash( 'sha256', '0013542419' ), $with_pepper );
	}
}
