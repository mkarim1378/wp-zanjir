<?php
/**
 * Iranian IBAN validation (IR + 24 digits, mod-97 check).
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Iban_Validator {

	/**
	 * Normalize raw IBAN input to uppercase IR + digits (no spaces).
	 *
	 * @param string $iban Raw input.
	 * @return string
	 */
	public static function normalize( $iban ) {
		$iban = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $iban ) ) );
		if ( 0 === strpos( $iban, 'IR' ) ) {
			return 'IR' . preg_replace( '/\D/', '', substr( $iban, 2 ) );
		}

		$digits = preg_replace( '/\D/', '', $iban );
		return '' === $digits ? '' : 'IR' . $digits;
	}

	/**
	 * Validate an Iranian IBAN.
	 *
	 * @param string $iban Raw or normalized IBAN.
	 * @return bool
	 */
	public static function validate( $iban ) {
		$iban = self::normalize( $iban );

		if ( ! preg_match( '/^IR\d{24}$/', $iban ) ) {
			return false;
		}

		$rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );
		$numeric    = '';

		for ( $i = 0, $len = strlen( $rearranged ); $i < $len; $i++ ) {
			$char = $rearranged[ $i ];
			if ( ctype_digit( $char ) ) {
				$numeric .= $char;
				continue;
			}

			if ( ! ctype_alpha( $char ) ) {
				return false;
			}

			$numeric .= (string) ( ord( $char ) - 55 );
		}

		$remainder = (int) substr( $numeric, 0, 1 );
		$length    = strlen( $numeric );

		for ( $i = 1; $i < $length; $i++ ) {
			$remainder = (int) ( $remainder . $numeric[ $i ] ) % 97;
		}

		return 1 === $remainder;
	}
}
