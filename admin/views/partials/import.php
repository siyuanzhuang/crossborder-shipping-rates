<?php
/**
 * 导入报价单。
 *
 * @package CrossBorder_Shipping_Rates
 * @var array|false $preview    预览结果。
 * @var int         $carrier_id 线路 ID。
 */

defined( 'ABSPATH' ) || exit;

$sample_url = CBSR_URL . 'samples/example-rates.csv';
?>
<div class="cbsr-card">
	<h2><?php esc_html_e( '导入报价单', 'crossborder-shipping' ); ?></h2>

	<div class="cbsr-two-col">
		<div>
			<h3><?php esc_html_e( '支持的格式', 'crossborder-shipping' ); ?></h3>
			<p class="cbsr-hint">
				<?php esc_html_e( '把货代的 Excel 报价单另存为 CSV（UTF-8 或 GBK 都行），表头中文英文都可以自动识别。最少需要「分区」和「单价」两列。', 'crossborder-shipping' ); ?>
			</p>
			<table class="widefat cbsr-table cbsr-table-compact">
				<thead>
					<tr>
						<th><?php esc_html_e( '列', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '可用的表头写法', 'crossborder-shipping' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td><strong><?php esc_html_e( '分区', 'crossborder-shipping' ); ?></strong></td><td class="cbsr-mono">分区 / 区域 / zone / 分区号</td></tr>
					<tr><td><?php esc_html_e( '国家', 'crossborder-shipping' ); ?></td><td class="cbsr-mono">国家 / 国家代码 / 目的国 / countries</td></tr>
					<tr><td><?php esc_html_e( '分区名称', 'crossborder-shipping' ); ?></td><td class="cbsr-mono">分区名称 / name</td></tr>
					<tr><td><strong><?php esc_html_e( '重量段', 'crossborder-shipping' ); ?></strong></td><td class="cbsr-mono">重量段 (0.1-0.2) / 重量下限 + 重量上限</td></tr>
					<tr><td><strong><?php esc_html_e( '单价', 'crossborder-shipping' ); ?></strong></td><td class="cbsr-mono">单价 / 价格 / 元/kg / price</td></tr>
					<tr><td><?php esc_html_e( '挂号费', 'crossborder-shipping' ); ?></td><td class="cbsr-mono">挂号费 / 挂号 / 票费</td></tr>
					<tr><td><?php esc_html_e( '操作费', 'crossborder-shipping' ); ?></td><td class="cbsr-mono">操作费 / 手续费 / 处理费</td></tr>
					<tr><td><?php esc_html_e( '最低消费', 'crossborder-shipping' ); ?></td><td class="cbsr-mono">最低消费 / 起步价 / 最低</td></tr>
				</tbody>
			</table>
			<p>
				<a class="button" href="<?php echo esc_url( $sample_url ); ?>" download>
					<?php esc_html_e( '下载示例 CSV', 'crossborder-shipping' ); ?>
				</a>
			</p>
			<p class="cbsr-hint">
				<?php esc_html_e( '同一个分区有多行时，只有第一行需要填国家代码；后面留空会自动沿用。合并单元格导出的空分区列也会沿用上一行。', 'crossborder-shipping' ); ?>
			</p>
		</div>

		<div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="cbsr-import-form">
				<input type="hidden" name="action" value="cbsr_import_preview" />
				<input type="hidden" name="carrier_id" value="<?php echo (int) $carrier_id; ?>" />
				<?php wp_nonce_field( 'cbsr_import' ); ?>

				<p>
					<label for="cbsr-csv"><strong><?php esc_html_e( '上传 CSV 文件', 'crossborder-shipping' ); ?></strong></label><br />
					<input type="file" id="cbsr-csv" name="cbsr_csv" accept=".csv,.txt,text/csv" />
				</p>

				<p class="cbsr-or"><?php esc_html_e( '或者直接粘贴表格内容', 'crossborder-shipping' ); ?></p>

				<p>
					<textarea name="cbsr_csv_text" rows="8" class="large-text cbsr-mono" placeholder="分区,国家,重量段,单价,挂号费&#10;1区,US,0.001-0.1,58,16&#10;1区,,0.101-0.2,55,16"></textarea>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( '解析并预览', 'crossborder-shipping' ); ?></button>
				</p>
				<p class="cbsr-hint">
					<?php esc_html_e( '解析只是预览，不会写入数据库。确认无误后再点「确认导入」。', 'crossborder-shipping' ); ?>
				</p>
			</form>
		</div>
	</div>
</div>

<?php if ( is_array( $preview ) ) : ?>
	<div class="cbsr-card cbsr-preview">
		<h2><?php esc_html_e( '导入预览', 'crossborder-shipping' ); ?></h2>

		<?php if ( ! empty( $preview['errors'] ) ) : ?>
			<div class="notice notice-error inline">
				<ul class="cbsr-list">
					<?php foreach ( $preview['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $preview['warnings'] ) ) : ?>
			<div class="notice notice-warning inline">
				<ul class="cbsr-list">
					<?php foreach ( array_slice( $preview['warnings'], 0, 30 ) as $warning ) : ?>
						<li><?php echo esc_html( $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $preview['warnings'] ) > 30 ) : ?>
					<p class="cbsr-muted">
						<?php
						printf(
							/* translators: %d: count */
							esc_html__( '还有 %d 条提示未显示。', 'crossborder-shipping' ),
							(int) ( count( $preview['warnings'] ) - 30 )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( empty( $preview['errors'] ) ) : ?>
			<h3>
				<?php
				printf(
					/* translators: 1: zone count, 2: row count */
					esc_html__( '识别到 %1$d 个分区、%2$d 行报价', 'crossborder-shipping' ),
					count( $preview['zones'] ),
					count( $preview['rows'] )
				);
				?>
			</h3>

			<table class="widefat cbsr-table cbsr-table-compact">
				<thead>
					<tr>
						<th><?php esc_html_e( '分区', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '名称', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '覆盖国家', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '行数', 'crossborder-shipping' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$counts = array();
					foreach ( $preview['rows'] as $row ) {
						$counts[ $row['zone_no'] ] = isset( $counts[ $row['zone_no'] ] ) ? $counts[ $row['zone_no'] ] + 1 : 1;
					}
					?>
					<?php foreach ( $preview['zones'] as $zone_no => $zone ) : ?>
						<tr>
							<td class="cbsr-mono"><?php echo esc_html( $zone_no ); ?></td>
							<td><?php echo esc_html( $zone['name'] ); ?></td>
							<td class="cbsr-mono">
								<?php echo '' !== $zone['countries'] ? esc_html( $zone['countries'] ) : '<span class="cbsr-warn-inline">' . esc_html__( '空', 'crossborder-shipping' ) . '</span>'; ?>
							</td>
							<td class="cbsr-mono"><?php echo isset( $counts[ $zone_no ] ) ? (int) $counts[ $zone_no ] : 0; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( '前 20 行报价', 'crossborder-shipping' ); ?></h3>
			<table class="widefat cbsr-table cbsr-table-compact">
				<thead>
					<tr>
						<th><?php esc_html_e( '分区', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '重量段', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '单价/kg', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '挂号费', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '操作费', 'crossborder-shipping' ); ?></th>
						<th><?php esc_html_e( '最低消费', 'crossborder-shipping' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $preview['rows'], 0, 20 ) as $row ) : ?>
						<tr>
							<td class="cbsr-mono"><?php echo esc_html( $row['zone_no'] ); ?></td>
							<td class="cbsr-mono">
								<?php echo esc_html( CBSR_Engine::trim_decimal( $row['weight_min'] ) . ' – ' . CBSR_Engine::trim_decimal( $row['weight_max'] ) ); ?>
							</td>
							<td class="cbsr-mono"><?php echo esc_html( CBSR_Engine::format_money( $row['unit_price'] ) ); ?></td>
							<td class="cbsr-mono"><?php echo esc_html( CBSR_Engine::format_money( $row['base_fee'] ) ); ?></td>
							<td class="cbsr-mono"><?php echo esc_html( CBSR_Engine::format_money( $row['operation_fee'] ) ); ?></td>
							<td class="cbsr-mono"><?php echo esc_html( CBSR_Engine::format_money( $row['min_charge'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cbsr-actions">
				<input type="hidden" name="action" value="cbsr_import_commit" />
				<input type="hidden" name="carrier_id" value="<?php echo (int) $carrier_id; ?>" />
				<?php wp_nonce_field( 'cbsr_import_commit' ); ?>
				<button type="submit" class="button button-primary button-hero">
					<?php esc_html_e( '确认导入', 'crossborder-shipping' ); ?>
				</button>
				<span class="cbsr-hint">
					<?php esc_html_e( '导入会覆盖同名分区的重量段；现有分区的偏远国家设置会保留。', 'crossborder-shipping' ); ?>
				</span>
			</form>
		<?php endif; ?>
	</div>
<?php endif; ?>
