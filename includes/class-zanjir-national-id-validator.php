<?php
/**
 * National ID (Iran) validation with checksum.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_National_Id_Validator {

	/**
	 * Validate an Iranian national ID number.
	 *
	 * Rules:
	 * - Exactly 10 digits.
	 * - Cannot be all the same digit (e.g. 1111111111).
	 * - Valid checksum: weighted sum mod 11.
	 *
	 * @param string $id Raw national ID string.
	 * @return bool
	 */
	public static function validate( $id ) {
		$id = preg_replace( '/\D/', '', $id );

		if ( strlen( $id ) !== 10 ) {
			return false;
		}

		if ( preg_match( '/^(\d)\1{9}$/', $id ) ) {
			return false;
		}

		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $id[ $i ] * ( 10 - $i );
		}

		$remainder = $sum % 11;
		$check     = (int) $id[9];

		if ( $remainder < 2 ) {
			return $check === $remainder;
		}

		return $check === ( 11 - $remainder );
	}

	/**
	 * Generate a SHA-256 / HMAC hash of the national ID (for uniqueness check).
	 *
	 * When a pepper is available (ZANJIR_NID_KEY / zanjir_nid_key / wp_salt),
	 * uses HMAC. Otherwise falls back to legacy unsalted SHA-256 for BC.
	 *
	 * @param string $id Raw national ID.
	 * @return string Hex hash.
	 */
	public static function hash( $id ) {
		$clean  = preg_replace( '/\D/', '', $id );
		$pepper = self::get_pepper();

		if ( $pepper ) {
			return hash_hmac( 'sha256', $clean, $pepper );
		}

		return hash( 'sha256', $clean );
	}

	/**
	 * Encrypt the national ID for optional storage.
	 *
	 * @param string $id Raw national ID.
	 * @return string|false Base64-encoded encrypted value, or false on failure.
	 */
	public static function encrypt( $id ) {
		$key = self::get_encryption_key();
		if ( ! $key ) {
			return false;
		}

		$iv        = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt(
			$id,
			'AES-256-CBC',
			hash( 'sha256', $key, true ),
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $encrypted ) {
			return false;
		}

		return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored national ID value.
	 *
	 * @param string $encrypted Base64-encoded encrypted value.
	 * @return string|false Decrypted raw ID, or false on failure.
	 */
	public static function decrypt( $encrypted ) {
		$key = self::get_encryption_key();
		if ( ! $key ) {
			return false;
		}

		$decoded = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return false;
		}

		$iv         = substr( $decoded, 0, 16 );
		$ciphertext = substr( $decoded, 16 );

		$raw = openssl_decrypt(
			$ciphertext,
			'AES-256-CBC',
			hash( 'sha256', $key, true ),
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false !== $raw ) {
			return $raw;
		}

		// Legacy fallback: ciphertext stored as openssl base64 (options=0).
		return openssl_decrypt(
			$ciphertext,
			'AES-256-CBC',
			hash( 'sha256', $key, true ),
			0,
			$iv
		);
	}

	/**
	 * Normalize: strip non-digits, validate, and return hash + optional encrypted value.
	 *
	 * @param string $id Raw national ID.
	 * @return array{valid: bool, hash: string, encrypted: string|false}
	 */
	public static function process( $id ) {
		$clean = preg_replace( '/\D/', '', $id );

		if ( ! self::validate( $clean ) ) {
			return array(
				'valid'     => false,
				'hash'      => '',
				'encrypted' => false,
			);
		}

		return array(
			'valid'     => true,
			'hash'      => self::hash( $clean ),
			'encrypted' => self::encrypt( $clean ),
		);
	}

	/**
	 * Pepper for HMAC uniqueness hashing.
	 *
	 * @return string
	 */
	private static function get_pepper() {
		$key = self::get_encryption_key();
		if ( $key ) {
			return (string) $key;
		}

		if ( function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( 'auth' );
		}

		return '';
	}

	/**
	 * Get the encryption key from constant or options.
	 *
	 * @return string|false
	 */
	private static function get_encryption_key() {
		if ( defined( 'ZANJIR_NID_KEY' ) && ZANJIR_NID_KEY ) {
			return ZANJIR_NID_KEY;
		}

		if ( function_exists( 'get_option' ) ) {
			$key = get_option( 'zanjir_nid_key', '' );
			return $key ? $key : false;
		}

		return false;
	}
}
