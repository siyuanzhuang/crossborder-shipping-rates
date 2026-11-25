<?php
/**
 * 线路编辑页。
 *
 * @package CrossBorder_Shipping_Rates
 * @var array|null $carrier     线路数据。
 * @var int        $carrier_id  线路 ID。
 * @var string     $tab         当前标签。
 * @var array      $zones       分区。
 * @var array      $rates       重量段。
 * @var string     $zone_filter 当前筛选的分区。
 * @var array|false $preview    导入预览。
 */

defined( 'ABSPATH' ) || exit;

$is_new     = ! $carrier;
$base_url   = admin_url( 'admin.php?page=cbsr-carrier' );
$settings   = $carrier ? CBSR_Engine::carrier_settings( $carrier ) : CBSR_Engine::carrier_settings( array() );
$tabs       = array(
	'basic'  => __( '基本设置', 'crossborder-shipping' ),
	'zones'  => __( '分区', 'crossborder-shipping' ),
	'rates'  => __( '重量段报价', 'crossborder-shipping' ),
	'import' => __( '导入报价单', 'crossborder-shipping' ),
);

$zone_options = array();
foreach ( $zones as $zone ) {
	$zone_options[ (string) $zone['zone_no'] ] = $zone;
}

if ( '' === $zone_filter && ! empty( $zone_options ) ) {
	$zone_filter = (string) key( $zone_options );
}
?>
<div class="wrap cbsr-wrap">
	<h1 class="wp-heading-inline">
		<?php echo $is_new ? esc_html__( '新建线路', 'crossborder-shipping' ) : esc_html( $carrier['name'] ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=cbsr-carriers' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '返回列表', 'crossborder-shipping' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php cbsr_render_admin_notice(); ?>

	<?php if ( ! $is_new ) : ?>
		<nav class="nav-tab-wrapper cbsr-tabs">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<?php
				$class = 'nav-tab' . ( $tab === $key ? ' nav-tab-active' : '' );
				$url   = add_query_arg(
					array(
						'page' => 'cbsr-carrier',
						'id'   => (int) $carrier_id,
						'tab'  => $key,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( $is_new || 'basic' === $tab ) : ?>
		<?php include CBSR_PATH . 'admin/views/partials/basic.php'; ?>
	<?php elseif ( 'zones' === $tab ) : ?>
		<?php include CBSR_PATH . 'admin/views/partials/zones.php'; ?>
	<?php elseif ( 'rates' === $tab ) : ?>
		<?php include CBSR_PATH . 'admin/views/partials/rates.php'; ?>
	<?php elseif ( 'import' === $tab ) : ?>
		<?php include CBSR_PATH . 'admin/views/partials/import.php'; ?>
	<?php endif; ?>
</div>
