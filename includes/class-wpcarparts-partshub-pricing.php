<?php

/**
 * Partshub retail pricing (60 % markup, whole kroner).
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/includes
 */

class Wpcarparts_Partshub_Pricing
{

	const MARKUP_MULTIPLIER = 1.6;

	/**
	 * Apply 60 % markup and drop fractional kroner (999.99 → 999).
	 *
	 * @param float|string $price_excl_vat Supplier price excluding VAT.
	 * @return int
	 */
	public static function retail_price_excl_vat( $price_excl_vat )
	{
		$price = (float) $price_excl_vat;

		return (int) floor( $price * self::MARKUP_MULTIPLIER );
	}

	/**
	 * Storefront / cart price after markup and optional wcb2b discount.
	 *
	 * @param float|string $price_excl_vat Supplier price excluding VAT.
	 * @return int
	 */
	public static function customer_price( $price_excl_vat )
	{
		$retail = self::retail_price_excl_vat( $price_excl_vat );

		if ( Wpcarparts_B2b::user_has_group() ) {
			return Wpcarparts_B2b::apply_discount_int( $retail );
		}

		return $retail;
	}
}
