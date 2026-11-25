<?php
/**
 * 基本设置。
 *
 * @package CrossBorder_Shipping_Rates
 * @var array|null $carrier  线路。
 * @var array      $settings 附加费设置。
 */

defined( 'ABSPATH' ) || exit;

$c = $carrier;
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cbsr-form">
	<input type="hidden" name="action" value="cbsr_save_carrier" />
	<input type="hidden" name="id" value="<?php echo (int) ( $c ? $c['id'] : 0 ); ?>" />
	<?php wp_nonce_field( 'cbsr_save_carrier' ); ?>

	<div class="cbsr-card">
		<h2><?php esc_html_e( '线路', 'crossborder-shipping' ); ?></h2>

		<div class="cbsr-field">
			<label for="cbsr-name"><?php esc_html_e( '线路名称', 'crossborder-shipping' ); ?> <span class="cbsr-req">*</span></label>
			<input type="text" id="cbsr-name" name="name" class="regular-text"
				value="<?php echo esc_attr( $c ? $c['name'] : '' ); ?>"
				placeholder="<?php esc_attr_e( '例：云途挂号小包（普货）', 'crossborder-shipping' ); ?>" required />
			<p class="cbsr-hint"><?php esc_html_e( '一个物流产品建一条线路；同名不同渠道请分开建。', 'crossborder-shipping' ); ?></p>
		</div>

		<div class="cbsr-field">
			<label for="cbsr-code"><?php esc_html_e( '内部编码', 'crossborder-shipping' ); ?></label>
			<input type="text" id="cbsr-code" name="code" class="regular-text cbsr-mono"
				value="<?php echo esc_attr( $c ? $c['code'] : '' ); ?>"
				placeholder="yunexpress-pu" />
			<p class="cbsr-hint"><?php esc_html_e( '可选，方便对接 ERP 或做二次开发时区分线路。', 'crossborder-shipping' ); ?></p>
		</div>

		<div class="cbsr-field-row">
			<div class="cbsr-field">
				<label for="cbsr-currency"><?php esc_html_e( '报价币种', 'crossborder-shipping' ); ?></label>
				<input type="text" id="cbsr-currency" name="currency" class="small-text cbsr-mono" maxlength="3"
					value="<?php echo esc_attr( $c ? $c['currency'] : 'CNY' ); ?>" />
			</div>
			<div class="cbsr-field">
				<label for="cbsr-exchange"><?php esc_html_e( '汇率（1 报价币 = ? 商店币）', 'crossborder-shipping' ); ?></label>
				<input type="number" id="cbsr-exchange" name="exchange_rate" class="small-text cbsr-mono"
					step="0.000001" min="0"
					value="<?php echo esc_attr( $c ? $c['exchange_rate'] : '1' ); ?>" />
			</div>
		</div>
		<p class="cbsr-hint">
			<?php esc_html_e( '报价单是人民币、商店收美元时，这里填 0.14 这类汇率，前台会把算出的运费乘上它。填 1 表示不换算。建议定期更新。', 'crossborder-shipping' ); ?>
		</p>
	</div>

	<div class="cbsr-card">
		<h2><?php esc_html_e( '计费规则', 'crossborder-shipping' ); ?></h2>

		<div class="cbsr-field-row">
			<div class="cbsr-field">
				<label for="cbsr-dim"><?php esc_html_e( '体积重系数', 'crossborder-shipping' ); ?></label>
				<input type="number" id="cbsr-dim" name="dim_factor" class="small-text cbsr-mono" step="1" min="0"
					value="<?php echo esc_attr( $c ? $c['dim_factor'] : '6000' ); ?>" />
				<p class="cbsr-hint"><?php esc_html_e( '长×宽×高(cm)÷系数。常见：6000（专线/快递）、5000、8000。填 0 表示不计体积重。', 'crossborder-shipping' ); ?></p>
			</div>
			<div class="cbsr-field">
				<label for="cbsr-step"><?php esc_html_e( '重量进位', 'crossborder-shipping' ); ?></label>
				<select id="cbsr-step" name="weight_round_step" class="cbsr-mono">
					<?php
					$steps = array( '0.001', '0.01', '0.1', '0.5', '1' );
					$cur   = $c ? (string) (float) $c['weight_round_step'] : '0.01';
					foreach ( $steps as $step ) :
						?>
						<option value="<?php echo esc_attr( $step ); ?>" <?php selected( (string) (float) $cur, (string) (float) $step ); ?>>
							<?php echo esc_html( CBSR_Engine::trim_decimal( (float) $step ) . ' kg' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="weight_round_mode" class="cbsr-mono">
					<?php
					$modes = array(
						'up'    => __( '进位', 'crossborder-shipping' ),
						'round' => __( '四舍五入', 'crossborder-shipping' ),
						'none'  => __( '不进位', 'crossborder-shipping' ),
					);
					$cur_mode = $c ? $c['weight_round_mode'] : 'up';
					foreach ( $modes as $mode => $label ) :
						?>
						<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $cur_mode, $mode ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="cbsr-hint"><?php esc_html_e( '货代通常按 0.01kg 或 0.1kg 进位取整，报价单上写「不足 xx 按 xx 计」就选进位。', 'crossborder-shipping' ); ?></p>
			</div>
		</div>
	</div>

	<div class="cbsr-card">
		<h2><?php esc_html_e( '附加费', 'crossborder-shipping' ); ?></h2>

		<div class="cbsr-field-row">
			<div class="cbsr-field">
				<label for="cbsr-fuel"><?php esc_html_e( '燃油附加费（%）', 'crossborder-shipping' ); ?></label>
				<input type="number" id="cbsr-fuel" name="fuel_rate" class="small-text cbsr-mono" step="0.01" min="0"
					value="<?php echo esc_attr( isset( $settings['fuel_rate'] ) ? $settings['fuel_rate'] : 0 ); ?>" />
				<p class="cbsr-hint"><?php esc_html_e( '按「运费 + 挂号费 + 操作费」的百分比加收。', 'crossborder-shipping' ); ?></p>
			</div>
			<div class="cbsr-field">
				<label for="cbsr-remote"><?php esc_html_e( '偏远地区附加费（每票）', 'crossborder-shipping' ); ?></label>
				<input type="number" id="cbsr-remote" name="remote_fee" class="small-text cbsr-mono" step="0.01" min="0"
					value="<?php echo esc_attr( isset( $settings['remote_fee'] ) ? $settings['remote_fee'] : 0 ); ?>" />
				<p class="cbsr-hint"><?php esc_html_e( '在「分区」页给分区填入偏远国家代码后生效。', 'crossborder-shipping' ); ?></p>
			</div>
			<div class="cbsr-field">
				<label for="cbsr-handling"><?php esc_html_e( '包装/操作附加费（每票）', 'crossborder-shipping' ); ?></label>
				<input type="number" id="cbsr-handling" name="handling_fee" class="small-text cbsr-mono" step="0.01" min="0"
					value="<?php echo esc_attr( isset( $settings['handling_fee'] ) ? $settings['handling_fee'] : 0 ); ?>" />
				<p class="cbsr-hint"><?php esc_html_e( '固定金额，可在运费里顺手把包材费收回来。', 'crossborder-shipping' ); ?></p>
			</div>
		</div>
	</div>

	<div class="cbsr-card">
		<h2><?php esc_html_e( '状态', 'crossborder-shipping' ); ?></h2>

		<div class="cbsr-field-row">
			<div class="cbsr-field">
				<label class="cbsr-checkbox">
					<input type="checkbox" name="status" value="1" <?php checked( ! $c || (int) $c['status'] === 1 ); ?> />
					<?php esc_html_e( '启用这条线路', 'crossborder-shipping' ); ?>
				</label>
			</div>
			<div class="cbsr-field">
				<label class="cbsr-checkbox">
					<input type="checkbox" name="is_default" value="1" <?php checked( $c && (int) $c['is_default'] === 1 ); ?> />
					<?php esc_html_e( '设为默认线路', 'crossborder-shipping' ); ?>
				</label>
				<p class="cbsr-hint"><?php esc_html_e( '配送方式没有指定线路时使用默认线路；同一时间只有一条。', 'crossborder-shipping' ); ?></p>
			</div>
			<div class="cbsr-field">
				<label for="cbsr-sort"><?php esc_html_e( '排序', 'crossborder-shipping' ); ?></label>
				<input type="number" id="cbsr-sort" name="sort_order" class="small-text cbsr-mono" step="1"
					value="<?php echo esc_attr( $c ? $c['sort_order'] : 0 ); ?>" />
			</div>
		</div>
	</div>

	<p class="cbsr-actions">
		<button type="submit" class="button button-primary button-hero">
			<?php echo $c ? esc_html__( '保存设置', 'crossborder-shipping' ) : esc_html__( '创建线路', 'crossborder-shipping' ); ?>
		</button>
	</p>
</form>
