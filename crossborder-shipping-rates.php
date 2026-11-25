<?php
/**
 * Plugin Name:       跨境物流运费 for WooCommerce
 * Plugin URI:        https://github.com/siyuanzhuang/crossborder-shipping-rates
 * Description:       把国内货代/跨境专线的报价单（CSV）直接导入，按目的国分区、重量段、体积重与附加费自动计算跨境运费。
 * Version:           0.1.0
 * Author:            siyuanzhuang
 * Author URI:        https://github.com/siyuanzhuang
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crossborder-shipping
 * Requires PHP:      7.4
 * Requires at least: 5.9
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   9.5
 *
 * @package CrossBorder_Shipping_Rates
 */

defined( 'ABSPATH' ) || exit;

define( 'CBSR_VERSION', '0.1.0' );
define( 'CBSR_FILE', __FILE__ );
define( 'CBSR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CBSR_URL', plugin_dir_url( __FILE__ ) );
define( 'CBSR_DB_VERSION', '1' );

require_once CBSR_PATH . 'includes/class-cbsr-engine.php';
require_once CBSR_PATH . 'includes/class-cbsr-db.php';

/**
 * Activate: create / upgrade tables.
 */
function cbsr_activate() {
	CBSR_DB::install();
	update_option( 'cbsr_db_version', CBSR_DB_VERSION );
}
register_activation_hook( __FILE__, 'cbsr_activate' );

/**
 * Deactivate: nothing destructive; data is kept.
 */
function cbsr_deactivate() {
	// Intentionally left blank.
}
register_deactivation_hook( __FILE__, 'cbsr_deactivate' );

/**
 * Declare compatibility with HPOS (custom order tables).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Bootstrap the plugin after WooCommerce is loaded.
 */
function cbsr_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'cbsr_render_woocommerce_missing_notice' );
		return;
	}

	if ( version_compare( get_option( 'cbsr_db_version', '0' ), CBSR_DB_VERSION, '<' ) ) {
		CBSR_DB::install();
		update_option( 'cbsr_db_version', CBSR_DB_VERSION );
	}

	require_once CBSR_PATH . 'includes/class-cbsr-import.php';
	require_once CBSR_PATH . 'includes/class-cbsr-shipping-method.php';
	require_once CBSR_PATH . 'includes/class-cbsr-admin.php';

	CBSR_Admin::init();
}
add_action( 'plugins_loaded', 'cbsr_bootstrap', 20 );

/**
 * Register the shipping method.
 *
 * @param array $methods Existing methods.
 * @return array
 */
function cbsr_register_shipping_method( $methods ) {
	$methods['cbsr'] = 'CBSR_Shipping_Method';
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'cbsr_register_shipping_method' );

/**
 * Admin notice when WooCommerce is missing.
 */
function cbsr_render_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' .
		esc_html__( '「跨境物流运费」需要先安装并启用 WooCommerce。', 'crossborder-shipping' ) .
		'</p></div>';
}

/**
 * Small helper used by views and templates.
 *
 * @return array Carrier list for selects.
 */
function cbsr_get_carrier_choices() {
	$choices  = array();
	$carriers = CBSR_DB::get_carriers();
	foreach ( $carriers as $carrier ) {
		$choices[ (int) $carrier['id'] ] = $carrier['name'];
	}
	return $choices;
}
