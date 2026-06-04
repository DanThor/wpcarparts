<?php

/**
 * Database access for OEM / carpart catalog rows.
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/includes
 */

class Wpcarparts_Oem_Repository
{

	/**
	 * Search carpart table (and cross-reference) for storefront display.
	 *
	 * @param string $search Normalized search term (whitespace stripped).
	 * @param int    $limit  Maximum rows.
	 * @return array<object>
	 */
	public function search_products($search, $limit = 12)
	{
		global $wpdb;

		$search = preg_replace('/\s+/', '', (string) $search);
		if ($search === '') {
			return array();
		}

		$carpart_table = $wpdb->prefix . 'carpart';
		$cross_table   = $wpdb->prefix . 'crossreference';
		$like          = $wpdb->esc_like($search) . '%';
		$limit         = max(1, min(50, (int) $limit));

		$sql = $wpdb->prepare(
			"SELECT * FROM {$carpart_table} WHERE item_mpn_number LIKE %s
			UNION
			SELECT wpcp.* FROM {$carpart_table} wpcp
			INNER JOIN {$cross_table} wpcf ON wpcp.item_mpn_number = wpcf.item_mpn_number
			WHERE wpcf.cross_reference = %s
			GROUP BY wpcp.item_mpn_number
			LIMIT %d",
			$like,
			$search,
			$limit
		);

		$results = $wpdb->get_results($sql);

		return is_array($results) ? $results : array();
	}

	/**
	 * @param string $item_mpn_number
	 * @return object|null
	 */
	public function get_product_by_mpn($item_mpn_number)
	{
		global $wpdb;

		$item_mpn_number = preg_replace('/\s+/', '', (string) $item_mpn_number);
		if ($item_mpn_number === '') {
			return null;
		}

		$carpart_table = $wpdb->prefix . 'carpart';

		$sql = $wpdb->prepare(
			"SELECT * FROM {$carpart_table} WHERE item_mpn_number = %s LIMIT 1",
			$item_mpn_number
		);

		$row = $wpdb->get_row($sql);

		return $row ? $row : null;
	}
}
