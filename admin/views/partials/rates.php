<?php
/**
 * 重量段报价表。
 *
 * @package CrossBorder_Shipping_Rates
 * @var array  $zones        分区。
 * @var array  $zone_options 分区索引。
 * @var string $zone_filter  当前分区。
 * @var array  $rates        全部重量段。
 * @var int    $carrier_id   线路 ID。
 */

defined( 'ABSPATH' ) || exit;

$current_zone = isset( $zone_options[ $zone_filter ] ) ? $zone_options[ $zone_filter ] : null;
$current_mode = $current_zone ? $current_zone['pricing_mode'] : 'bracket';

$zone_rates = isset( $zone_options[ $zone_filter ] ) ? CBSR_Engine::filter_zone( $rates, $zone_filter ) : array();

usort(
	$zone_rates,
	function ( $a, $b ) {
		return (float) $a['weight_min'] <=> (float) $b['weight_min'];
	}
);
?>
<div class="cbsr-card">
	<h2><?php esc_html_e( '重量段报价', 'crossborder-shipping' ); ?></h2>

	<?php if ( empty( $zones ) ) : ?>
		<p class="cbsr-empty-inline"><?php esc_html_e( '请先在「分区」页建立分区，或在「导入报价单」里一起导入。', 'crossborder-shipping' ); ?></p>
	</div>
		<?php
		return;
	endif;
	?>

	<div class="cbsr-zone-picker">
		<?php foreach ( $zones as $zone ) : ?>
			<?php
			$is_active = (string) $zone['zone_no'] === (string) $zone_filter;
			$url       = add_query_arg(
				array(
					'page' => 'cbsr-carrier',
					'id'   => (int) $carrier_id,
					'tab'  => 'rates',
					'zone' => rawurlencode( (string) $zone['zone_no'] ),
				),
				admin_url( 'admin.php' )
			);
			?>
			<a class="cbsr-zone-chip <?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
				<?php echo esc_html( $zone['zone_no'] ); ?>
				<?php if ( '' !== (string) $zone['name'] ) : ?>
					<span class="cbsr-muted">· <?php echo esc_html( $zone['name'] ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( $current_zone && 'first_add' === $current_mode ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				printf(
					/* translators: 1: first weight, 2: first price, 3: step weight, 4: step price */
					esc_html__( '这个分区用的是「首重 + 续重」计价：首重 %1$s kg 收 %2$s，之后每 %3$s kg 加 %4$s。如果要改，请回「分区」页修改；这里填的表格不会生效。', 'crossborder-shipping' ),
					esc_html( CBSR_Engine::trim_decimal( (float) $current_zone['first_weight'] ) ),
					esc_html( CBSR_Engine::trim_decimal( (float) $current_zone['first_price'] ) ),
					esc_html( CBSR_Engine::trim_decimal( (float) $current_zone['step_weight'] ) ),
					esc_html( CBSR_Engine::trim_decimal( (float) $current_zone['step_price'] ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cbsr-form">
		<input type="hidden" name="action" value="cbsr_save_rates" />
		<input type="hidden" name="carrier_id" value="<?php echo (int) $carrier_id; ?>" />
		<input type="hidden" name="zone_no" value="<?php echo esc_attr( $zone_filter ); ?>" />
		<?php wp_nonce_field( 'cbsr_save_rates' ); ?>

		<p class="cbsr-hint">
			<?php esc_html_e( '区间规则：本档覆盖「上一档上限 < 计费重 ≤ 本档上限」。例如 0.001–0.1 覆盖 0.1kg，0.101–0.2 覆盖 0.2kg。', 'crossborder-shipping' ); ?>
		</p>

		<table class="widefat cbsr-table cbsr-table-editable">
			<thead>
				<tr>
					<th class="cbsr-w-90"><?php esc_html_e( '下限 kg', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-90"><?php esc_html_e( '上限 kg', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-110"><?php esc_html_e( '单价 / kg', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-110"><?php esc_html_e( '挂号费 / 件', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-110"><?php esc_html_e( '操作费 / 件', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-110"><?php esc_html_e( '最低消费', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-50"></th>
				</tr>
			</thead>
			<tbody id="cbsr-rate-rows">
				<?php foreach ( $zone_rates as $rate_index => $rate ) : ?>
					<tr>
						<td><input type="number" step="0.001" min="0" name="rates[<?php echo (int) $rate_index; ?>][weight_min]" class="cbsr-mono cbsr-full" value="<?php echo esc_attr( (float) $rate['weight_min'] ); ?>" /></td>
						<td><input type="number" step="0.001" min="0" name="rates[<?php echo (int) $rate_index; ?>][weight_max]" class="cbsr-mono cbsr-full" value="<?php echo esc_attr( (float) $rate['weight_max'] ); ?>" /></td>
						<td><input type="number" step="0.0001" min="0" name="rates[<?php echo (int) $rate_index; ?>][unit_price]" class="cbsr-mono cbsr-full" value="<?php echo esc_attr( (float) $rate['unit_price'] ); ?>" /></td>
						<td><input type="number" step="0.0001" min="0" name="rates[<?php echo (int) $rate_index; ?>][base_fee]" class="cbsr-mono cbsr-full" value="<?php echo esc_attr( (float) $rate['base_fee'] ); ?>" /></td>
						<td><input type="number" step="0.0001" min="0" name="rates[<?php echo (int) $rate_index; ?>][operation_fee]" class="cbsr-mono cbsr-full" value="<?php echo esc_attr( (float) $rate['operation_fee'] ); ?>" /></td>
						<td><input type="number" step="0.0001" min="0" name="rates[<?php echo (int) $rate_index; ?>][min_charge]" class="cbsr-mono cbsr-full" value="<?php echo esc_attr( (float) $rate['min_charge'] ); ?>" /></td>
						<td><button type="button" class="button cbsr-remove-row" aria-label="<?php esc_attr_e( '删除这一行', 'crossborder-shipping' ); ?>">×</button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<template id="cbsr-rate-row-template">
			<tr>
				<td><input type="number" step="0.001" min="0" name="rates[__i__][weight_min]" class="cbsr-mono cbsr-full" value="0.001" /></td>
				<td><input type="number" step="0.001" min="0" name="rates[__i__][weight_max]" class="cbsr-mono cbsr-full" value="0.1" /></td>
				<td><input type="number" step="0.0001" min="0" name="rates[__i__][unit_price]" class="cbsr-mono cbsr-full" value="0" /></td>
				<td><input type="number" step="0.0001" min="0" name="rates[__i__][base_fee]" class="cbsr-mono cbsr-full" value="0" /></td>
				<td><input type="number" step="0.0001" min="0" name="rates[__i__][operation_fee]" class="cbsr-mono cbsr-full" value="0" /></td>
				<td><input type="number" step="0.0001" min="0" name="rates[__i__][min_charge]" class="cbsr-mono cbsr-full" value="0" /></td>
				<td><button type="button" class="button cbsr-remove-row" aria-label="<?php esc_attr_e( '删除这一行', 'crossborder-shipping' ); ?>">×</button></td>
			</tr>
		</template>

		<p class="cbsr-actions">
			<button type="button" class="button" data-cbsr-add-row="#cbsr-rate-rows" data-cbsr-template="#cbsr-rate-row-template">
				<?php esc_html_e( '+ 添加重量段', 'crossborder-shipping' ); ?>
			</button>
			<button type="submit" class="button button-primary">
				<?php
				printf(
					/* translators: %s: zone number */
					esc_html__( '保存「%s」的报价', 'crossborder-shipping' ),
					esc_html( $zone_filter )
				);
				?>
			</button>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'cbsr-carrier', 'id' => (int) $carrier_id, 'tab' => 'import' ), admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( '改用 CSV 批量导入', 'crossborder-shipping' ); ?>
			</a>
		</p>
	</form>
</div>
