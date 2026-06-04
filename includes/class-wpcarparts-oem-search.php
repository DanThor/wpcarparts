<?php

/**
 * OEM search results on WooCommerce search / shop views.
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/includes
 */

class Wpcarparts_Oem_Search
{

	/**
	 * @var Wpcarparts_Oem_Repository
	 */
	private $repository;

	/**
	 * @var Wpcarparts_Oem_Images
	 */
	private $images;

	/**
	 * @param Wpcarparts_Oem_Repository $repository
	 * @param Wpcarparts_Oem_Images     $images
	 */
	public function __construct(Wpcarparts_Oem_Repository $repository, Wpcarparts_Oem_Images $images)
	{
		$this->repository = $repository;
		$this->images     = $images;
	}

	/**
	 * @param Wpcarparts_Loader $loader
	 */
	public function register_hooks(Wpcarparts_Loader $loader)
	{
		$loader->add_action('woocommerce_before_main_content', $this, 'render_search_results', 10);
		$loader->add_action('wp_enqueue_scripts', $this, 'enqueue_assets');
	}

	/**
	 * Enqueue third-party lookup script on search when Partshub is configured.
	 */
	public function enqueue_assets()
	{
		if (! get_search_query()) {
			return;
		}

		if (! Wpcarparts_Config::is_partshub_configured()) {
			return;
		}

		$handle = 'wpcarparts-oem-lookup';
		$path   = plugin_dir_path(dirname(__FILE__)) . 'public/js/wpcarparts-oem-lookup.js';

		wp_enqueue_style(
			'wpcarparts-oem',
			plugin_dir_url(dirname(__FILE__)) . 'public/css/wpcarparts-oem.css',
			array(),
			WPCARPARTS_VERSION,
			'all'
		);

		wp_enqueue_script(
			$handle,
			plugin_dir_url(dirname(__FILE__)) . 'public/js/wpcarparts-oem-lookup.js',
			array(),
			file_exists($path) ? (string) filemtime($path) : WPCARPARTS_VERSION,
			true
		);

		$synthetic_id = Wpcarparts_Config::get_synthetic_product_id();
		$synthetic    = function_exists('wc_get_product') ? wc_get_product($synthetic_id) : false;
		$upload_dir   = wp_upload_dir();
		$placeholder  = trailingslashit($upload_dir['baseurl']) . 'woocommerce-placeholder-247x247.png';

		$cart_config = array(
			'productId'       => $synthetic_id,
			'addToCartUrl'    => '',
			'addToCartText'   => __('Kjøp', 'wpcarparts'),
			'canAddToCart'    => false,
		);

		if (
			$synthetic
			&& $synthetic->is_type('simple')
			&& $synthetic->is_purchasable()
			&& $synthetic->is_in_stock()
			&& ! $synthetic->is_sold_individually()
		) {
			$cart_config['addToCartUrl']  = $synthetic->add_to_cart_url();
			$cart_config['addToCartText'] = $synthetic->add_to_cart_text();
			$cart_config['canAddToCart']  = true;
		}

		wp_localize_script(
			$handle,
			'wpcarpartsOemLookup',
			array(
				'restUrl'          => rest_url('wpcarparts/v1/oe-lookup'),
				'nonce'            => wp_create_nonce('wp_rest'),
				'markupMultiplier' => Wpcarparts_Partshub_Pricing::MARKUP_MULTIPLIER,
				'isB2b'            => Wpcarparts_B2b::user_has_group(),
				'placeholderImage' => $placeholder,
				'cart'             => $cart_config,
				'i18n'             => array(
					'loading'         => __('Henter bruktdeler…', 'wpcarparts'),
					'error'           => __('Kunne ikke hente bruktdeler.', 'wpcarparts'),
					'heading'         => __('Brukte deler', 'wpcarparts'),
					'kmStand'         => __('Km.stand', 'wpcarparts'),
					'model'           => __('Modell', 'wpcarparts'),
					'year'            => __('År', 'wpcarparts'),
					'priceSuffixExcl' => __(',- eks. mva', 'wpcarparts'),
					'priceSuffixIncl' => __(',- ink. mva', 'wpcarparts'),
				),
			)
		);
	}

	/**
	 * Output OEM rows above WooCommerce product loop on search.
	 */
	public function render_search_results()
	{
		if (! get_search_query()) {
			return;
		}

		$search   = preg_replace('/\s+/', '', get_search_query());
		$products = $this->repository->search_products($search, 12);

		if (empty($products)) {
			return;
		}

		remove_action('woocommerce_no_products_found', 'wc_no_products_found');

		$partshub_enabled = Wpcarparts_Config::is_partshub_configured();
		$synthetic_id     = Wpcarparts_Config::get_synthetic_product_id();
		$synthetic        = function_exists('wc_get_product') ? wc_get_product($synthetic_id) : false;

		echo '<div class="wpcarparts-oem-results">';

		foreach ($products as $product) {
			$this->render_product_row($product, $synthetic, $partshub_enabled);
		}

		echo '</div>';
	}

	/**
	 * @param object    $product
	 * @param WC_Product|false $synthetic
	 * @param bool      $partshub_enabled
	 */
	private function render_product_row($product, $synthetic, $partshub_enabled)
	{
		$mpn          = isset($product->item_mpn_number) ? $product->item_mpn_number : '';
		$manufacturer = isset($product->item_manufacture) ? $product->item_manufacture : '';
		$image_url    = $this->images->get_image_url($mpn, $manufacturer);
		$price_html   = $this->get_display_price_html($product);

		$estimated_shipping = '7 virkedager';
?>
		<div
			class="oem-product"
			data-oe-part-number="<?php echo esc_attr($mpn); ?>"
			data-manufacturer="<?php echo esc_attr($manufacturer); ?>">
			<img
				width="120"
				height="120"
				src="<?php echo esc_url($image_url); ?>"
				alt="<?php echo esc_attr($product->item_name); ?>" />
			<div class="oem-product-col">
				<p class="oem-product-name"><?php echo esc_html($product->item_name); ?></p>
				<p class="oem-product-sku"><?php esc_html_e('Varenummer:', 'wpcarparts'); ?> <?php echo esc_html($mpn); ?></p>
				<p class="oem-product-sku"><?php esc_html_e('Produsent:', 'wpcarparts'); ?> <?php echo esc_html($manufacturer); ?></p>
				<p class="oem-product-sku">
					<?php
					printf(
						/* translators: %s: estimated shipping time */
						esc_html__('Estimert sendt fra lager i Oslo: %s', 'wpcarparts'),
						esc_html($estimated_shipping)
					);
					?>
				</p>
			</div>
			<div class="oem-product-col">
				<span class="oem-product-price"><bdi><?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_html 
														?></bdi></span>
				<?php echo $this->get_add_to_cart_form_html($synthetic, $mpn); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
			</div>
			<?php if ($partshub_enabled) : ?>
				<div class="oem-third-party-slot" aria-live="polite">
					<span class="oem-third-party-loader"><?php esc_html_e('Henter tilleggsdata…', 'wpcarparts'); ?></span>
				</div>
			<?php endif; ?>
		</div>
	<?php
	}

	/**
	 * @param object $product
	 * @return string
	 */
	private function get_display_price_html($product)
	{
		$price = (float) $product->item_price;

		if (Wpcarparts_B2b::user_has_group()) {
			$price = Wpcarparts_B2b::apply_discount($price);
			return esc_html((string) $price) . '<span>,- eks. mva</span>';
		}

		return esc_html((string) $price) . '<span>,- ink. mva</span>';
	}

	/**
	 * @param WC_Product|false $synthetic
	 * @param string           $mpn
	 * @return string
	 */
	private function get_add_to_cart_form_html($synthetic, $mpn)
	{
		if (! $synthetic || (! is_search() && ! is_shop())) {
			return '';
		}

		if (
			! $synthetic->is_type('simple')
			|| ! $synthetic->is_purchasable()
			|| ! $synthetic->is_in_stock()
			|| $synthetic->is_sold_individually()
		) {
			return '';
		}

		ob_start();
	?>
		<form action="<?php echo esc_url($synthetic->add_to_cart_url()); ?>" class="oem-cart" method="post" enctype="multipart/form-data">
			<?php woocommerce_quantity_input(array(), $synthetic, false); ?>
			<input type="hidden" name="item_number" value="<?php echo esc_attr($mpn); ?>" />
			<button type="submit" class="button alt"><?php echo esc_html($synthetic->add_to_cart_text()); ?></button>
		</form>
<?php
		return ob_get_clean();
	}
}
