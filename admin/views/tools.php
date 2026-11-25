<?php
/**
 * 运费试算。
 *
 * @package CrossBorder_Shipping_Rates
 * @var array      $carriers 线路。
 * @var array|null $result   试算结果。
 * @var array      $input    输入。
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cbsr-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( '运费试算', 'crossborder-shipping' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=cbsr-carriers' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '返回线路', 'crossborder-shipping' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php if ( empty( $carriers ) ) : ?>
		<div class="cbsr-empty-state">
			<p><?php esc_html_e( '还没有线路，先去建一条。', 'crossborder-shipping' ); ?></p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div class="cbsr-two-col">
		<div class="cbsr-card">
			<h2><?php esc_html_e( '输入一票货', 'crossborder-shipping' ); ?></h2>
			<form method="post" class="cbsr-form">
				<?php wp_nonce_field( 'cbsr_tools' ); ?>
				<input type="hidden" name="cbsr_quote" value="1" />

				<div class="cbsr-field">
					<label for="cbsr-t-carrier"><?php esc_html_e( '线路', 'crossborder-shipping' ); ?></label>
					<select id="cbsr-t-carrier" name="carrier_id" class="cbsr-full">
						<?php foreach ( $carriers as $carrier ) : ?>
							<option value="<?php echo (int) $carrier['id']; ?>" <?php selected( (int) $input['carrier_id'], (int) $carrier['id'] ); ?>>
								<?php echo esc_html( $carrier['name'] . '（' . $carrier['currency'] . '）' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="cbsr-field">
					<label for="cbsr-t-country"><?php esc_html_e( '目的国（两位代码）', 'crossborder-shipping' ); ?></label>
					<input type="text" id="cbsr-t-country" name="country" class="small-text cbsr-mono" maxlength="2"
						value="<?php echo esc_attr( $input['country'] ); ?>" />
				</div>

				<div class="cbsr-field">
					<label for="cbsr-t-weight"><?php esc_html_e( '实重（kg）', 'crossborder-shipping' ); ?></label>
					<input type="number" id="cbsr-t-weight" name="weight" step="0.001" min="0" class="small-text cbsr-mono"
						value="<?php echo esc_attr( $input['weight'] ); ?>" />
				</div>

				<div class="cbsr-field-row">
					<div class="cbsr-field">
						<label for="cbsr-t-l"><?php esc_html_e( '长（cm）', 'crossborder-shipping' ); ?></label>
						<input type="number" id="cbsr-t-l" name="length" step="0.1" min="0" class="small-text cbsr-mono"
							value="<?php echo esc_attr( $input['length'] ); ?>" />
					</div>
					<div class="cbsr-field">
						<label for="cbsr-t-w"><?php esc_html_e( '宽（cm）', 'crossborder-shipping' ); ?></label>
						<input type="number" id="cbsr-t-w" name="width" step="0.1" min="0" class="small-text cbsr-mono"
							value="<?php echo esc_attr( $input['width'] ); ?>" />
					</div>
					<div class="cbsr-field">
						<label for="cbsr-t-h"><?php esc_html_e( '高（cm）', 'crossborder-shipping' ); ?></label>
						<input type="number" id="cbsr-t-h" name="height" step="0.1" min="0" class="small-text cbsr-mono"
							value="<?php echo esc_attr( $input['height'] ); ?>" />
					</div>
				</div>
				<p class="cbsr-hint"><?php esc_html_e( '尺寸留 0 表示不按体积重计。', 'crossborder-shipping' ); ?></p>

				<p class="cbsr-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( '计算', 'crossborder-shipping' ); ?></button>
				</p>
			</form>
		</div>

		<div class="cbsr-card">
			<h2><?php esc_html_e( '结果', 'crossborder-shipping' ); ?></h2>

			<?php if ( null === $result ) : ?>
				<p class="cbsr-muted"><?php esc_html_e( '填左边参数后点「计算」。实际结算页的算法与这里完全一致。', 'crossborder-shipping' ); ?></p>
			<?php elseif ( ! $result['ok'] ) : ?>
				<div class="notice notice-error inline">
					<p><strong><?php esc_html_e( '算不出运费', 'crossborder-shipping' ); ?></strong></p>
					<ul class="cbsr-list">
						<?php foreach ( $result['errors'] as $code ) : ?>
							<li><?php echo esc_html( CBSR_Engine::error_message( $code ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<p class="cbsr-hint">
					<?php esc_html_e( '常见原因：分区没填这个国家、重量超出报价表最大档、报价表是空的。', 'crossborder-shipping' ); ?>
				</p>
			<?php else : ?>
				<table class="widefat cbsr-table cbsr-table-compact">
					<tbody>
						<tr>
							<td><?php esc_html_e( '命中分区', 'crossborder-shipping' ); ?></td>
							<td class="cbsr-mono">
								<?php
								$zone_name = $result['zone'] && '' !== (string) $result['zone']['name'] ? $result['zone']['name'] : '';
								echo esc_html( $result['zone_no'] . ( '' !== $zone_name ? '（' . $zone_name . '）' : '' ) );
								?>
							</td>
						</tr>
						<tr>
							<td><?php esc_html_e( '实重 / 体积重', 'crossborder-shipping' ); ?></td>
							<td class="cbsr-mono">
								<?php echo esc_html( CBSR_Engine::trim_decimal( $result['actual_weight'] ) ); ?> kg /
								<?php echo esc_html( CBSR_Engine::trim_decimal( $result['volumetric'] ) ); ?> kg
							</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( '计费重', 'crossborder-shipping' ); ?></strong></td>
							<td class="cbsr-mono"><strong><?php echo esc_html( CBSR_Engine::trim_decimal( $result['billable_weight'] ) ); ?> kg</strong></td>
						</tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( '费用明细', 'crossborder-shipping' ); ?></h3>
				<table class="widefat cbsr-table cbsr-table-compact">
					<tbody>
						<?php foreach ( $result['lines'] as $line ) : ?>
							<tr>
								<td><?php echo esc_html( $line['label'] ); ?></td>
								<td class="cbsr-mono cbsr-right"><?php echo esc_html( CBSR_Engine::format_money( $line['amount'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( '合计（报价币种）', 'crossborder-shipping' ); ?></th>
							<th class="cbsr-mono cbsr-right"><?php echo esc_html( CBSR_Engine::format_money( $result['subtotal'] ) . ' ' . $result['currency'] ); ?></th>
						</tr>
						<?php if ( (float) $result['exchange_rate'] !== 1.0 ) : ?>
							<tr>
								<th><?php esc_html_e( '换算后（商店币种）', 'crossborder-shipping' ); ?></th>
								<th class="cbsr-mono cbsr-right">
									<?php
									echo esc_html(
										CBSR_Engine::format_money( $result['total'] ) . ' ' .
										( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' )
									);
									?>
								</th>
							</tr>
						<?php endif; ?>
					</tfoot>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
