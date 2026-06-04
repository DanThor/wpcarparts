<?php

/**
 * Resolve OEM product image URLs from uploads (no HTTP header probes).
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/includes
 */

class Wpcarparts_Oem_Images
{

	/**
	 * @param string $item_mpn_number
	 * @param string $item_manufacture
	 * @return string
	 */
	public function get_image_url( $item_mpn_number, $item_manufacture )
	{
		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );
		$base_url   = trailingslashit( $upload_dir['baseurl'] );

		$placeholder = $base_url . 'woocommerce-placeholder-247x247.png';

		$mpn_file = $base_dir . sanitize_file_name( $item_mpn_number ) . '.jpg';
		if ( file_exists( $mpn_file ) ) {
			return $base_url . sanitize_file_name( $item_mpn_number ) . '.jpg';
		}

		$manufacturer_file = $base_dir . sanitize_file_name( strtolower( $item_manufacture ) ) . '.jpg';
		if ( file_exists( $manufacturer_file ) ) {
			return $base_url . sanitize_file_name( strtolower( $item_manufacture ) ) . '.jpg';
		}

		return $placeholder;
	}
}
