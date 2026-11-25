<?php
/**
 * 卸载：删除插件的表与选项。
 *
 * @package CrossBorder_Shipping_Rates
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	$wpdb->prefix . 'cbsr_rates',
	$wpdb->prefix . 'cbsr_zones',
	$wpdb->prefix . 'cbsr_carriers',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'cbsr_db_version' );

// 清理导入预览的临时缓存。
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_cbsr_import_preview_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cbsr_import_preview_' ) . '%'
	)
);
