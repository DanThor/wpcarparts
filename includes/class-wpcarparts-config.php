<?php

/**
 * Plugin configuration helpers (wp-config constants and options).
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/includes
 */

class Wpcarparts_Config
{

	/**
	 * WooCommerce product ID used as the shell for OEM line items.
	 *
	 * @return int
	 */
	public static function get_synthetic_product_id()
	{
		if ( defined( 'WPCARPARTS_SYNTHETIC_PRODUCT_ID' ) ) {
			return (int) WPCARPARTS_SYNTHETIC_PRODUCT_ID;
		}

		return (int) get_option( 'wpcarparts_synthetic_product_id', 26564 );
	}

	/**
	 * Whether Partshub lookup credentials are defined in wp-config.php.
	 *
	 * @return bool
	 */
	public static function is_partshub_configured()
	{
		return defined( 'WPCARPARTS_PARTSHUB_API_KEY' )
			&& WPCARPARTS_PARTSHUB_API_KEY
			&& defined( 'WPCARPARTS_PARTSHUB_LOOKUP_URL' )
			&& WPCARPARTS_PARTSHUB_LOOKUP_URL;
	}

	/**
	 * @return string|null
	 */
	public static function get_partshub_api_key()
	{
		if ( ! defined( 'WPCARPARTS_PARTSHUB_API_KEY' ) || ! WPCARPARTS_PARTSHUB_API_KEY ) {
			return null;
		}

		return WPCARPARTS_PARTSHUB_API_KEY;
	}

	/**
	 * @return string|null
	 */
	public static function get_partshub_lookup_url()
	{
		if ( ! defined( 'WPCARPARTS_PARTSHUB_LOOKUP_URL' ) || ! WPCARPARTS_PARTSHUB_LOOKUP_URL ) {
			return null;
		}

		return WPCARPARTS_PARTSHUB_LOOKUP_URL;
	}
}
