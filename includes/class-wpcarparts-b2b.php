<?php

/**
 * Wholesale (wcb2b) customer detection and pricing.
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/includes
 */

class Wpcarparts_B2b
{

	/**
	 * @return bool
	 */
	public static function user_has_group()
	{
		return is_user_logged_in() && (bool) get_the_author_meta( 'wcb2b_group', get_current_user_id() );
	}

	/**
	 * Same factors as legacy OEM search display: 20 % + 15 % off.
	 *
	 * @param float $price
	 * @return float
	 */
	public static function apply_discount( $price )
	{
		return ( (float) $price * 0.8 ) * 0.85;
	}

	/**
	 * @param float $price
	 * @return int
	 */
	public static function apply_discount_int( $price )
	{
		return (int) floor( self::apply_discount( $price ) );
	}
}
