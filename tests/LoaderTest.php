<?php
/**
 * Loader registration signature tests.
 *
 * @package Zanjir
 */

use PHPUnit\Framework\TestCase;

class Zanjir_Loader_Test extends TestCase {

	protected function setUp(): void {
		$GLOBALS['zanjir_test_actions'] = array();
	}

	public function test_loader_stores_component_callback_signature() {
		$loader = new Zanjir_Loader();
		$loader->add_action( 'init', 'Zanjir_Referral_Code', 'maybe_capture_referral', 5, 1 );
		$loader->run();

		$this->assertNotEmpty( $GLOBALS['zanjir_test_actions'] );
		$first = $GLOBALS['zanjir_test_actions'][0];
		$this->assertSame( 'init', $first['hook'] );
		$this->assertSame( array( 'Zanjir_Referral_Code', 'maybe_capture_referral' ), $first['callback'] );
		$this->assertSame( 5, $first['priority'] );
		$this->assertSame( 1, $first['accepted_args'] );
	}

	public function test_loader_registers_object_methods() {
		$component = new class() {
			public function handle() {}
		};

		$loader = new Zanjir_Loader();
		$loader->add_action( 'admin_menu', $component, 'handle', 10, 0 );
		$loader->run();

		$last = end( $GLOBALS['zanjir_test_actions'] );
		$this->assertSame( 'admin_menu', $last['hook'] );
		$this->assertSame( array( $component, 'handle' ), $last['callback'] );
		$this->assertSame( 0, $last['accepted_args'] );
	}
}
