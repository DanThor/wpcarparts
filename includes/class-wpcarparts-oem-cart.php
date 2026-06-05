<?php

/**
 * WooCommerce cart integration for OEM catalog lines.
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/includes
 */

class Wpcarparts_Oem_Cart
{

	/**
	 * @var Wpcarparts_Oem_Repository
	 */
	private $repository;

	/**
	 * @param Wpcarparts_Oem_Repository $repository
	 */
	public function __construct( Wpcarparts_Oem_Repository $repository )
	{
		$this->repository = $repository;
	}

	/**
	 * Register WooCommerce hooks.
	 */
	public function register_hooks( Wpcarparts_Loader $loader )
	{
		$loader->add_filter( 'woocommerce_add_cart_item_data', $this, 'add_cart_item_data', 10, 3 );
		$loader->add_action( 'woocommerce_before_calculate_totals', $this, 'set_cart_item_totals', 10, 1 );
		$loader->add_filter( 'woocommerce_cart_item_price', $this, 'filter_cart_item_price', 10, 3 );
		$loader->add_filter( 'woocommerce_cart_item_name', $this, 'filter_cart_item_name', 10, 3 );
	}

	/**
	 * @param array $cart_item_data
	 * @param int   $product_id
	 * @param int   $variation_id
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id )
	{
		if ( (int) $product_id !== Wpcarparts_Config::get_synthetic_product_id() ) {
			return $cart_item_data;
		}

		if ( ! empty( $_POST['partshub_stock_number'] ) && isset( $_POST['partshub_price_excl_vat'] ) ) {
			return $this->add_partshub_cart_item_data( $cart_item_data );
		}

		if ( empty( $_POST['item_number'] ) ) {
			return $cart_item_data;
		}

		$article_number = sanitize_text_field( wp_unslash( $_POST['item_number'] ) );
		$row            = $this->repository->get_product_by_mpn( $article_number );

		if ( ! $row ) {
			return $cart_item_data;
		}

		$product_name = $row->item_name . ', ' . $article_number;
		$price        = (float) $row->item_price;

		$cart_item_data['oem_mpn']      = $article_number;
		$cart_item_data['custom_price'] = $price;
		$cart_item_data['custom_name']  = $product_name;
		$cart_item_data['unique_key']   = 'oem_' . $article_number;

		return $cart_item_data;
	}

	/**
	 * Partshub used-parts line: price recalculated server-side from supplier priceExclVat.
	 *
	 * @param array $cart_item_data
	 * @return array
	 */
	private function add_partshub_cart_item_data( $cart_item_data )
	{
		$stock_number    = sanitize_text_field( wp_unslash( $_POST['partshub_stock_number'] ) );
		$price_excl_vat  = (float) wp_unslash( $_POST['partshub_price_excl_vat'] );
		$model           = isset( $_POST['partshub_model'] ) ? sanitize_text_field( wp_unslash( $_POST['partshub_model'] ) ) : '';
		$year            = isset( $_POST['partshub_year'] ) ? sanitize_text_field( wp_unslash( $_POST['partshub_year'] ) ) : '';
		$kilometrage     = isset( $_POST['partshub_kilometrage'] ) && $_POST['partshub_kilometrage'] !== ''
			? (int) $_POST['partshub_kilometrage']
			: null;

		if ( $stock_number === '' || $price_excl_vat <= 0 ) {
			return $cart_item_data;
		}

		$retail_price = Wpcarparts_Partshub_Pricing::customer_price( $price_excl_vat );
		$product_name = $this->format_partshub_cart_name( $model, $year, $stock_number );

		$cart_item_data['partshub_stock_number'] = $stock_number;
		$cart_item_data['partshub_price_excl']   = $price_excl_vat;
		$cart_item_data['custom_price']          = (float) $retail_price;
		$cart_item_data['custom_name']           = $product_name;
		$cart_item_data['unique_key']            = 'partshub_' . $stock_number;

		if ( $kilometrage !== null ) {
			$cart_item_data['partshub_kilometrage'] = $kilometrage;
		}

		return $cart_item_data;
	}

	/**
	 * @param string $model
	 * @param string $year
	 * @param string $stock_number
	 * @return string
	 */
	private function format_partshub_cart_name( $model, $year, $stock_number )
	{
		$details = implode(
			' ',
			array_filter(
				array(
					trim( (string) $model ),
					trim( (string) $year ),
				)
			)
		);

		if ( $details === '' ) {
			return sprintf(
				/* translators: %s: third-party stock / part number */
				__( 'Brukt del %s', 'wpcarparts' ),
				$stock_number
			);
		}

		return sprintf(
			/* translators: 1: model and year, 2: third-party stock / part number */
			__( 'Brukt del %1$s %2$s', 'wpcarparts' ),
			$details,
			$stock_number
		);
	}

	/**
	 * @param WC_Cart $cart
	 */
	public function set_cart_item_totals( $cart )
	{
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['custom_price'], $cart_item['custom_name'] ) ) {
				$cart_item['data']->set_price( $cart_item['custom_price'] );
				$cart_item['data']->set_name( $cart_item['custom_name'] );
			}
		}
	}

	/**
	 * @param string $price
	 * @param array  $cart_item
	 * @param string $cart_item_key
	 * @return string
	 */
	public function filter_cart_item_price( $price, $cart_item, $cart_item_key )
	{
		if ( isset( $cart_item['custom_price'] ) ) {
			return wc_price( $cart_item['custom_price'] );
		}

		return $price;
	}

	/**
	 * @param string $item_name
	 * @param array  $cart_item
	 * @param string $cart_item_key
	 * @return string
	 */
	public function filter_cart_item_name( $item_name, $cart_item, $cart_item_key )
	{
		if ( isset( $cart_item['custom_name'] ) ) {
			return $cart_item['custom_name'];
		}

		return $item_name;
	}
}
