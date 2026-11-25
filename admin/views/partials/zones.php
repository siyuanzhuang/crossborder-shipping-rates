<?php
/**
 * 分区管理。
 *
 * @package CrossBorder_Shipping_Rates
 * @var array $zones      分区。
 * @var int   $carrier_id 线路 ID。
 */

defined( 'ABSPATH' ) || exit;

$row_index = 0;
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cbsr-form">
	<input type="hidden" name="action" value="cbsr_save_zones" />
	<input type="hidden" name="carrier_id" value="<?php echo (int) $carrier_id; ?>" />
	<?php wp_nonce_field( 'cbsr_save_zones' ); ?>

	<div class="cbsr-card">
		<h2><?php esc_html_e( '分区表', 'crossborder-shipping' ); ?></h2>
		<p class="cbsr-hint">
			<?php esc_html_e( '分区 = 报价单里的「1区 / 2区 / A区」。覆盖国家填两位国家代码，用逗号分隔，例如 US,CA,PR。填 * 表示兜底分区（所有没被其他分区命中的国家都走它）。', 'crossborder-shipping' ); ?>
		</p>

		<?php if ( empty( $zones ) ) : ?>
			<p class="cbsr-empty-inline">
				<?php esc_html_e( '还没有分区。可以先到「导入报价单」把 CSV 里的分区一起导入，或在这里手工添加。', 'crossborder-shipping' ); ?>
			</p>
		<?php endif; ?>

		<table class="widefat cbsr-table cbsr-table-editable">
			<thead>
				<tr>
					<th class="cbsr-w-90"><?php esc_html_e( '分区号', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-140"><?php esc_html_e( '名称', 'crossborder-shipping' ); ?></th>
					<th><?php esc_html_e( '覆盖国家', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-140"><?php esc_html_e( '偏远国家', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-140"><?php esc_html_e( '计价方式', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-80"><?php esc_html_e( '首重 kg', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-80"><?php esc_html_e( '首重价', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-80"><?php esc_html_e( '续重 kg', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-80"><?php esc_html_e( '续重价', 'crossborder-shipping' ); ?></th>
					<th class="cbsr-w-50"></th>
				</tr>
			</thead>
			<tbody id="cbsr-zone-rows">
				<?php foreach ( $zones as $zone ) : ?>
					<?php $n = $row_index++; ?>
					<tr>
						<td>
							<input type="text" name="zones[<?php echo (int) $n; ?>][zone_no]" class="cbsr-mono cbsr-full"
								value="<?php echo esc_attr( $zone['zone_no'] ); ?>" />
						</td>
						<td>
							<input type="text" name="zones[<?php echo (int) $n; ?>][name]" class="cbsr-full"
								value="<?php echo esc_attr( $zone['name'] ); ?>" />
						</td>
						<td>
							<input type="text" name="zones[<?php echo (int) $n; ?>][countries]" class="cbsr-mono cbsr-full"
								value="<?php echo esc_attr( $zone['countries'] ); ?>"
								placeholder="US,CA" />
							<?php if ( '' === trim( (string) $zone['countries'] ) ) : ?>
								<span class="cbsr-warn-inline"><?php esc_html_e( '未填国家，前台匹配不到', 'crossborder-shipping' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<input type="text" name="zones[<?php echo (int) $n; ?>][remote_countries]" class="cbsr-mono cbsr-full"
								value="<?php echo esc_attr( $zone['remote_countries'] ); ?>"
								placeholder="<?php esc_attr_e( '可留空', 'crossborder-shipping' ); ?>" />
						</td>
						<td>
							<select name="zones[<?php echo (int) $n; ?>][pricing_mode]" class="cbsr-full">
								<option value="bracket" <?php selected( $zone['pricing_mode'], 'bracket' ); ?>><?php esc_html_e( '重量段查表', 'crossborder-shipping' ); ?></option>
								<option value="first_add" <?php selected( $zone['pricing_mode'], 'first_add' ); ?>><?php esc_html_e( '首重 + 续重', 'crossborder-shipping' ); ?></option>
							</select>
						</td>
						<td>
							<input type="number" step="0.001" min="0" name="zones[<?php echo (int) $n; ?>][first_weight]" class="cbsr-mono cbsr-full"
								value="<?php echo esc_attr( CBSR_Engine::trim_decimal( (float) $zone['first_weight'] ) ); ?>" />
						</td>
						<td>
							<input type="number" step="0.0001" min="0" name="zones[<?php echo (int) $n; ?>][first_price]" class="cbsr-mono cbsr-full"
								value="<?php echo esc_attr( CBSR_Engine::trim_decimal( (float) $zone['first_price'] ) ); ?>" />
						</td>
						<td>
							<input type="number" step="0.001" min="0" name="zones[<?php echo (int) $n; ?>][step_weight]" class="cbsr-mono cbsr-full"
								value="<?php echo esc_attr( CBSR_Engine::trim_decimal( (float) $zone['step_weight'] ) ); ?>" />
						</td>
						<td>
							<input type="number" step="0.0001" min="0" name="zones[<?php echo (int) $n; ?>][step_price]" class="cbsr-mono cbsr-full"
								value="<?php echo esc_attr( CBSR_Engine::trim_decimal( (float) $zone['step_price'] ) ); ?>" />
						</td>
						<td>
							<button type="button" class="button cbsr-remove-row" aria-label="<?php esc_attr_e( '删除这一行', 'crossborder-shipping' ); ?>">×</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="cbsr-hint">
			<?php esc_html_e( '「首重 + 续重」适合按 0.5kg 首重、每 0.5kg 续重报价的快递/专线；选了这个方式后，该分区的重量段表格会被忽略。', 'crossborder-shipping' ); ?>
		</p>

		<template id="cbsr-zone-row-template">
			<tr>
				<td><input type="text" name="zones[__i__][zone_no]" class="cbsr-mono cbsr-full" value="" /></td>
				<td><input type="text" name="zones[__i__][name]" class="cbsr-full" value="" /></td>
				<td><input type="text" name="zones[__i__][countries]" class="cbsr-mono cbsr-full" value="" placeholder="US,CA" /></td>
				<td><input type="text" name="zones[__i__][remote_countries]" class="cbsr-mono cbsr-full" value="" /></td>
				<td>
					<select name="zones[__i__][pricing_mode]" class="cbsr-full">
						<option value="bracket"><?php esc_html_e( '重量段查表', 'crossborder-shipping' ); ?></option>
						<option value="first_add"><?php esc_html_e( '首重 + 续重', 'crossborder-shipping' ); ?></option>
					</select>
				</td>
				<td><input type="number" step="0.001" min="0" name="zones[__i__][first_weight]" class="cbsr-mono cbsr-full" value="0" /></td>
				<td><input type="number" step="0.0001" min="0" name="zones[__i__][first_price]" class="cbsr-mono cbsr-full" value="0" /></td>
				<td><input type="number" step="0.001" min="0" name="zones[__i__][step_weight]" class="cbsr-mono cbsr-full" value="0" /></td>
				<td><input type="number" step="0.0001" min="0" name="zones[__i__][step_price]" class="cbsr-mono cbsr-full" value="0" /></td>
				<td><button type="button" class="button cbsr-remove-row" aria-label="<?php esc_attr_e( '删除这一行', 'crossborder-shipping' ); ?>">×</button></td>
			</tr>
		</template>

		<p class="cbsr-actions">
			<button type="button" class="button" data-cbsr-add-row="#cbsr-zone-rows" data-cbsr-template="#cbsr-zone-row-template">
				<?php esc_html_e( '+ 添加分区', 'crossborder-shipping' ); ?>
			</button>
			<button type="submit" class="button button-primary"><?php esc_html_e( '保存分区', 'crossborder-shipping' ); ?></button>
		</p>
	</div>
</form>
