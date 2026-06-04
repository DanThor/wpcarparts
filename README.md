# wpcarparts

WordPress plugin that gives WP-User the ability to upload a CSV-file that will be parsed and saved to a db.

## OEM search and Partshub lookup

OEM catalog search, cart shell product, and async Partshub lookup are built into the plugin (formerly `extend/oem-products.php`).

Add to `wp-config.php`:

```php
define( 'WPCARPARTS_PARTSHUB_API_KEY', 'your-secret-key' );
define( 'WPCARPARTS_PARTSHUB_LOOKUP_URL', 'https://example.com/functions/v1/api-gateway-v2/parts/oe-lookup' );

// Optional: WooCommerce shell product ID (default option fallback: 26564)
define( 'WPCARPARTS_SYNTHETIC_PRODUCT_ID', 26564 );
```

On search, OEM rows render from the `carpart` table first; each row then loads Partshub data via `GET /wp-json/wpcarparts/v1/oe-lookup?oePartNumber=…&manufacturer=…` (API key stays server-side).

Remove any theme include of `extend/oem-products.php`.
