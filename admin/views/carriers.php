<?php
/**
 * 线路列表。
 *
 * @package CrossBorder_Shipping_Rates
 * @var array $carriers 线路列表。
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cbsr-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( '物流线路', 'crossborder-shipping' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=cbsr-carrier' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '新建线路', 'crossborder-shipping' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php cbsr_render_admin_notice(); ?>

	<?php if ( empty( $carriers ) ) : ?>
		<div class="cbsr-empty-state">
			<h2><?php esc_html_e( '先把一张报价单变成可用的运费规则', 'crossborder-shipping' ); ?></h2>
			<p class="cbsr-muted">
				<?php esc_html_e( '一条「线路」= 一个物流产品，例如「云途挂号小包」「燕文专线-美国」「4PX 欧洲专线」。每条线路有自己的分区表、重量段报价、体积重系数和附加费。', 'crossborder-shipping' ); ?>
			</p>
			<ol class="cbsr-steps">
				<li><?php esc_html_e( '新建线路，填好报价币种和体积重系数。', 'crossborder-shipping' ); ?></li>
				<li><?php esc_html_e( '把货代给的报价单另存为 CSV，上传导入，预览确认后写入。', 'crossborder-shipping' ); ?></li>
				<li><?php esc_html_e( '到 WooCommerce 配送区域里添加「跨境物流运费」方式并选择这条线路。', 'crossborder-shipping' ); ?></li>
			</ol>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cbsr-carrier' ) ); ?>">
					<?php esc_html_e( '新建第一条线路', 'crossborder-shipping' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped cbsr-table">
			<thead>
				<tr>
					<th scope="col" class="cbsr-col-name"><?php esc_html_e( '线路', 'crossborder-shipping' ); ?></th>
					<th scope="col"><?php esc_html_e( '报价币种', 'crossborder-shipping' ); ?></th>
					<th scope="col"><?php esc_html_e( '汇率', 'crossborder-shipping' ); ?></th>
					<th scope="col"><?php esc_html_e( '体积重系数', 'crossborder-shipping' ); ?></th>
					<th scope="col"><?php esc_html_e( '分区 / 报价条数', 'crossborder-shipping' ); ?></th>
					<th scope="col"><?php esc_html_e( '状态', 'crossborder-shipping' ); ?></th>
					<th scope="col" class="cbsr-col-actions"><?php esc_html_e( '操作', 'crossborder-shipping' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $carriers as $carrier ) : ?>
					<?php
					$edit_url = admin_url( 'admin.php?page=cbsr-carrier&id=' . (int) $carrier['id'] );
					$del_url  = wp_nonce_url(
						admin_url( 'admin-post.php?action=cbsr_delete_carrier&id=' . (int) $carrier['id'] ),
						'cbsr_delete_carrier'
					);
					?>
					<tr>
						<td class="cbsr-col-name">
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $carrier['name'] ); ?></a></strong>
							<?php if ( (int) $carrier['is_default'] === 1 ) : ?>
								<span class="cbsr-badge"><?php esc_html_e( '默认', 'crossborder-shipping' ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== (string) $carrier['code'] ) : ?>
								<div class="cbsr-muted cbsr-mono"><?php echo esc_html( $carrier['code'] ); ?></div>
							<?php endif; ?>
						</td>
						<td class="cbsr-mono"><?php echo esc_html( $carrier['currency'] ); ?></td>
						<td class="cbsr-mono">
							<?php if ( (float) $carrier['exchange_rate'] !== 1.0 ) : ?>
								× <?php echo esc_html( CBSR_Engine::trim_decimal( (float) $carrier['exchange_rate'] ) ); ?>
								<div class="cbsr-muted"><?php esc_html_e( '换算为商店币种', 'crossborder-shipping' ); ?></div>
							<?php else : ?>
								<span class="cbsr-muted"><?php esc_html_e( '不换算', 'crossborder-shipping' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="cbsr-mono">
							<?php
							$dim_factor = (float) $carrier['dim_factor'];
							echo $dim_factor > 0 ? esc_html( CBSR_Engine::trim_decimal( $dim_factor ) ) : '<span class="cbsr-muted">—</span>';
							?>
						</td>
						<td>
							<?php echo (int) $carrier['stats']['zones']; ?> / <?php echo (int) $carrier['stats']['rates']; ?>
							<?php if ( (int) $carrier['stats']['zones'] === 0 ) : ?>
								<div class="cbsr-warn-inline"><?php esc_html_e( '还没有分区，前台算不出价', 'crossborder-shipping' ); ?></div>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( (int) $carrier['status'] === 1 ) : ?>
								<span class="cbsr-status cbsr-status-on"><?php esc_html_e( '启用', 'crossborder-shipping' ); ?></span>
							<?php else : ?>
								<span class="cbsr-status cbsr-status-off"><?php esc_html_e( '停用', 'crossborder-shipping' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="cbsr-col-actions">
							<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( '编辑', 'crossborder-shipping' ); ?></a>
							<a class="button button-small cbsr-danger"
								href="<?php echo esc_url( $del_url ); ?>"
								onclick="return confirm('<?php echo esc_js( __( '删除后该线路的分区和报价会一起删除，确定吗？', 'crossborder-shipping' ) ); ?>');">
								<?php esc_html_e( '删除', 'crossborder-shipping' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="cbsr-muted">
			<?php
			printf(
				/* translators: %s: link */
				esc_html__( '想先验证报价是否正确？去 %s 输入目的国和重量试算。', 'crossborder-shipping' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=cbsr-tools' ) ) . '">' . esc_html__( '运费试算', 'crossborder-shipping' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>
</div>
