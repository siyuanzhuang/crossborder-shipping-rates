<?php
/**
 * 生成 README 截图用的静态页面。
 *
 * 用真实的 admin 模板渲染出 HTML，套一层模拟 wp-admin 外壳，
 * 再用无头浏览器截图。这样截图里看到的就是插件真实输出，不是设计稿。
 *
 * 用法：
 *   php bin/render-screens.php            # 输出 HTML 到 dist/shots/
 *   bin/screenshots.ps1                   # 调 Edge 截图并裁切
 *
 * 注意：本脚本只用于开发，bin/ 已在 .gitattributes 中 export-ignore。
 *
 * @package CrossBorder_Shipping_Rates
 */

define( 'CBSR_TESTING', true );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CBSR_VERSION', '0.1.0' );
define( 'CBSR_PATH', __DIR__ . '/../' );
define( 'CBSR_URL', '../../' );
define( 'CBSR_FILE', __DIR__ . '/../crossborder-shipping-rates.php' );

/* -------------------------------------------------------------------------
 * 渲染模板所需的最小 WordPress 桩
 * ---------------------------------------------------------------------- */

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function esc_js( $text ) {
	return addslashes( (string) $text );
}

function esc_html__( $text, $domain = '' ) {
	return esc_html( $text );
}

function esc_attr__( $text, $domain = '' ) {
	return esc_attr( $text );
}

function esc_html_e( $text, $domain = '' ) {
	echo esc_html( $text );
}

function esc_attr_e( $text, $domain = '' ) {
	echo esc_attr( $text );
}

function __( $text, $domain = '' ) {
	return $text;
}

function admin_url( $path = '' ) {
	return '#' . ltrim( (string) $path, '/' );
}

function add_query_arg( $args, $url = '' ) {
	$query = array();
	foreach ( (array) $args as $key => $value ) {
		$query[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $query );
}

function wp_nonce_field() {
	echo '';
}

function wp_nonce_url( $url ) {
	return $url;
}

function checked( $checked, $current = true, $display = true ) {
	$result = (string) $checked === (string) $current ? " checked='checked'" : '';
	if ( $display ) {
		echo $result; // phpcs:ignore
	}
	return $result;
}

function selected( $selected, $current = true, $display = true ) {
	$result = (string) $selected === (string) $current ? " selected='selected'" : '';
	if ( $display ) {
		echo $result; // phpcs:ignore
	}
	return $result;
}

function wp_json_encode( $data ) {
	return json_encode( $data ); // phpcs:ignore
}

function get_woocommerce_currency() {
	return 'USD';
}

function cbsr_render_admin_notice() {
	echo '';
}

require_once CBSR_PATH . 'includes/class-cbsr-engine.php';

/**
 * 在独立作用域里渲染模板。
 *
 * @param string $relative 相对路径。
 * @param array  $vars     模板变量。
 * @return string
 */
function render_view( $relative, $vars = array() ) {
	ob_start();
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	extract( $vars );
	include CBSR_PATH . 'admin/views/' . $relative;
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * 演示数据
 * ---------------------------------------------------------------------- */

$carriers = array(
	array(
		'id'                => 1,
		'name'              => '云途挂号小包（普货）',
		'code'              => 'yunexpress-pu',
		'currency'          => 'CNY',
		'exchange_rate'     => '0.140000',
		'dim_factor'        => '6000.00',
		'weight_round_step' => '0.010',
		'weight_round_mode' => 'up',
		'status'            => 1,
		'is_default'        => 1,
		'sort_order'        => 0,
		'settings'          => '{"fuel_rate":10,"remote_fee":0,"handling_fee":0}',
		'stats'             => array( 'zones' => 4, 'rates' => 48 ),
	),
	array(
		'id'                => 2,
		'name'              => '燕文专线-美国',
		'code'              => 'yanwen-us-line',
		'currency'          => 'CNY',
		'exchange_rate'     => '0.140000',
		'dim_factor'        => '5000.00',
		'weight_round_step' => '0.100',
		'weight_round_mode' => 'up',
		'status'            => 1,
		'is_default'        => 0,
		'sort_order'        => 10,
		'settings'          => '{"fuel_rate":0,"remote_fee":380,"handling_fee":2}',
		'stats'             => array( 'zones' => 1, 'rates' => 24 ),
	),
	array(
		'id'                => 3,
		'name'              => '4PX 欧洲专线（带电）',
		'code'              => '4px-eu-battery',
		'currency'          => 'CNY',
		'exchange_rate'     => '0.140000',
		'dim_factor'        => '8000.00',
		'weight_round_step' => '0.010',
		'weight_round_mode' => 'round',
		'status'            => 0,
		'is_default'        => 0,
		'sort_order'        => 20,
		'settings'          => '{"fuel_rate":8,"remote_fee":0,"handling_fee":0}',
		'stats'             => array( 'zones' => 2, 'rates' => 0 ),
	),
);

$zones = array(
	array(
		'id'               => 1,
		'zone_no'          => '1区',
		'name'             => '美国',
		'countries'        => 'US,PR',
		'remote_countries' => 'PR',
		'pricing_mode'     => 'bracket',
		'first_weight'     => 0,
		'first_price'      => 0,
		'step_weight'      => 0,
		'step_price'       => 0,
	),
	array(
		'id'               => 2,
		'zone_no'          => '2区',
		'name'             => '欧洲主要国家',
		'countries'        => 'GB,DE,FR,IT,ES,NL',
		'remote_countries' => '',
		'pricing_mode'     => 'bracket',
		'first_weight'     => 0,
		'first_price'      => 0,
		'step_weight'      => 0,
		'step_price'       => 0,
	),
	array(
		'id'               => 3,
		'zone_no'          => '3区',
		'name'             => '亚太',
		'countries'        => 'AU,NZ,JP,KR,SG,MY,TH',
		'remote_countries' => '',
		'pricing_mode'     => 'bracket',
		'first_weight'     => 0,
		'first_price'      => 0,
		'step_weight'      => 0,
		'step_price'       => 0,
	),
	array(
		'id'               => 4,
		'zone_no'          => '4区',
		'name'             => '其他国家',
		'countries'        => '*',
		'remote_countries' => '',
		'pricing_mode'     => 'bracket',
		'first_weight'     => 0,
		'first_price'      => 0,
		'step_weight'      => 0,
		'step_price'       => 0,
	),
);

$brackets = array(
	array( 0.001, 0.1, 58 ),
	array( 0.101, 0.2, 55 ),
	array( 0.201, 0.3, 52 ),
	array( 0.301, 0.4, 50 ),
	array( 0.401, 0.5, 48 ),
	array( 0.501, 0.6, 46 ),
	array( 0.601, 0.7, 44 ),
	array( 0.701, 0.8, 43 ),
	array( 0.801, 0.9, 42 ),
	array( 0.901, 1.0, 41 ),
	array( 1.001, 1.5, 39 ),
	array( 1.501, 2.0, 37 ),
);

$rates = array();
foreach ( $brackets as $bracket ) {
	$rates[] = array(
		'zone_no'       => '1区',
		'weight_min'    => $bracket[0],
		'weight_max'    => $bracket[1],
		'unit_price'    => $bracket[2],
		'base_fee'      => 16,
		'operation_fee' => 1.5,
		'min_charge'    => 0,
	);
}

/* -------------------------------------------------------------------------
 * 页面内容
 * ---------------------------------------------------------------------- */

$screens = array();

$screens['carriers'] = array(
	'title'   => '物流线路',
	'content' => render_view(
		'carriers.php',
		array(
			'carriers' => $carriers,
		)
	),
);

$preview_rows = array();
foreach ( $brackets as $bracket ) {
	$preview_rows[] = array(
		'zone_no'       => '1区',
		'weight_min'    => $bracket[0],
		'weight_max'    => $bracket[1],
		'unit_price'    => $bracket[2],
		'base_fee'      => 16,
		'operation_fee' => 1.5,
		'min_charge'    => 0,
		'line'          => 2,
	);
}

$screens['import'] = array(
	'title'   => '导入报价单',
	'content' => render_view(
		'partials/import.php',
		array(
			'carrier_id' => 1,
			'preview'    => array(
				'rows'     => $preview_rows,
				'zones'    => array(
					'1区' => array(
						'zone_no'   => '1区',
						'name'      => '美国',
						'countries' => 'US',
						'remote'    => '',
					),
					'2区' => array(
						'zone_no'   => '2区',
						'name'      => '欧洲主要国家',
						'countries' => 'GB,DE,FR,IT,ES,NL',
						'remote'    => '',
					),
					'3区' => array(
						'zone_no'   => '3区',
						'name'      => '亚太',
						'countries' => 'AU,NZ,JP,KR,SG,MY,TH',
						'remote'    => '',
					),
					'4区' => array(
						'zone_no'   => '4区',
						'name'      => '其他国家',
						'countries' => '*',
						'remote'    => '',
					),
				),
				'errors'   => array(),
				'warnings' => array(),
				'header'   => array(),
			),
		)
	),
);

$screens['rates'] = array(
	'title'   => '重量段报价',
	'content' => render_view(
		'partials/rates.php',
		array(
			'zones'        => $zones,
			'zone_options' => array(
				'1区' => $zones[0],
				'2区' => $zones[1],
				'3区' => $zones[2],
				'4区' => $zones[3],
			),
			'zone_filter'  => '1区',
			'rates'        => $rates,
			'carrier_id'   => 1,
			'carrier'      => $carriers[0],
			'settings'     => array(),
		)
	),
);

$quote = CBSR_Engine::quote(
	$carriers[0],
	$zones,
	$rates,
	array(
		'weight'     => 0.25,
		'country'    => 'US',
		'dimensions' => array(
			'length' => 30,
			'width'  => 20,
			'height' => 10,
		),
	)
);

$screens['tools'] = array(
	'title'   => '运费试算',
	'content' => render_view(
		'tools.php',
		array(
			'carriers' => $carriers,
			'result'   => $quote,
			'input'    => array(
				'carrier_id' => 1,
				'country'    => 'US',
				'weight'     => 0.25,
				'length'     => 30,
				'width'      => 20,
				'height'     => 10,
			),
		)
	),
);

/* -------------------------------------------------------------------------
 * wp-admin 外壳
 * ---------------------------------------------------------------------- */

$menu = array(
	'仪表盘'   => 'dashboard',
	'文章'     => 'posts',
	'媒体'     => 'media',
	'页面'     => 'pages',
	'评论'     => 'comments',
	'产品'     => 'products',
	'订单'     => 'orders',
	'分析'     => 'analytics',
	'营销'     => 'marketing',
	'外观'     => 'themes',
	'插件'     => 'plugins',
	'用户'     => 'users',
	'工具'     => 'tools',
	'设置'     => 'settings',
);

$shell_css = <<<'CSS'
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
	background: #f0f0f1;
	color: #1d2327;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Microsoft YaHei", sans-serif;
	font-size: 13px;
	line-height: 1.4;
	-webkit-font-smoothing: antialiased;
}
a { color: #2271b1; text-decoration: none; }
a:hover { color: #135e96; }

#wpadminbar {
	height: 32px;
	background: #1d2327;
	color: #c3c4c7;
	display: flex;
	align-items: center;
	padding: 0 10px;
	font-size: 13px;
	position: relative;
	z-index: 2;
}
#wpadminbar .ab-left { display: flex; align-items: center; gap: 4px; flex: 1; }
#wpadminbar .ab-item { padding: 0 9px; height: 32px; display: flex; align-items: center; color: #c3c4c7; }
#wpadminbar .ab-item.site { color: #fff; font-weight: 600; }
#wpadminbar .dot {
	width: 16px; height: 16px; border-radius: 50%;
	background: #3858e9; color: #fff;
	font-size: 10px; font-weight: 700;
	display: inline-flex; align-items: center; justify-content: center;
	margin-right: 4px;
}

#layout { display: flex; align-items: stretch; min-height: 100vh; }
#adminmenu {
	width: 170px;
	flex: 0 0 170px;
	background: #1d2327;
	padding-bottom: 20px;
}
#adminmenu ul { margin: 0; padding: 0; list-style: none; }
#adminmenu li { margin: 0; }
#adminmenu a {
	display: block;
	padding: 9px 14px;
	color: #c3c4c7;
	font-size: 14px;
	border-left: 3px solid transparent;
}
#adminmenu a:hover { color: #72aee6; background: #2c3338; }
#adminmenu li.current > a {
	background: #2271b1;
	color: #fff;
	border-left-color: #72aee6;
	font-weight: 500;
}
#adminmenu .submenu { margin: 0 0 6px; }
#adminmenu .submenu a {
	padding: 6px 14px 6px 30px;
	font-size: 13px;
	color: #c3c4c7;
	border-left: 3px solid transparent;
}
#adminmenu .submenu li.current a { color: #72aee6; }
#adminmenu .submenu a:hover { color: #72aee6; }

#wpbody { flex: 1; min-width: 0; background: #f0f0f1; }
#wpbody-content { padding: 12px 20px 36px; }

h1.wp-heading-inline {
	font-size: 23px;
	font-weight: 400;
	margin: 8px 0 12px;
	color: #1d2327;
}
.wp-heading-inline + .page-title-action {
	display: inline-block;
	margin: 0 0 12px 8px;
	padding: 4px 10px;
	border: 1px solid #2271b1;
	border-radius: 3px;
	color: #2271b1;
	font-size: 13px;
	vertical-align: 3px;
}
hr.wp-header-end { visibility: hidden; margin: 0; }

.cbsr-card { margin: 16px 0 0; }
.cbsr-wrap h1 { font-weight: 400; }
.cbsr-hint { margin-top: 6px; }

table.cbsr-table { border-radius: 4px; }
CSS;

// 补上 WordPress 后台的表格 / 表单 / 按钮基础样式——真实环境里这些来自 wp-admin 的公共 CSS，
// 这里只渲染插件模板，所以要自己补一份。
$shell_css .= <<<'CSS'
#layout { min-height: 0; }
h1.wp-heading-inline { display: inline-block; }

table.cbsr-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	overflow: hidden;
	box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
}
table.cbsr-table thead th {
	background: #fff;
	border-bottom: 1px solid #c3c4c7;
	color: #1d2327;
	font-weight: 400;
	text-align: left;
	padding: 8px 10px;
	font-size: 13px;
	white-space: nowrap;
}
table.cbsr-table tbody td {
	padding: 9px 10px;
	border-top: 1px solid #f0f0f1;
	vertical-align: middle;
}
table.striped tbody tr:nth-child(odd) td { background: #f9f9f9; }
table.striped tbody tr:nth-child(even) td { background: #fff; }
table.cbsr-table tbody tr:hover td { background: #f6f7f7; }

.cbsr-badge {
	background: #2271b1;
	color: #fff;
	border-radius: 3px;
	padding: 1px 6px;
	font-size: 11px;
	margin-left: 6px;
	white-space: nowrap;
}

.cbsr-table-editable input[type="text"],
.cbsr-table-editable input[type="number"],
.cbsr-table-editable select {
	border: 1px solid #8c8f94;
	border-radius: 4px;
	padding: 2px 6px;
	font-size: 12px;
	line-height: 1.6;
	background: #fff;
	color: #2c3338;
	min-height: 28px;
}
.cbsr-table-editable select { padding-right: 2px; }

.button {
	display: inline-block;
	padding: 4px 10px;
	border: 1px solid #2271b1;
	border-radius: 3px;
	background: #f6f7f7;
	color: #2271b1;
	font-size: 13px;
	line-height: 1.7;
	cursor: pointer;
	text-decoration: none;
}
.button:hover { background: #f0f0f1; }
.button-primary {
	background: #2271b1;
	border-color: #2271b1;
	color: #fff;
}
.button-primary:hover { background: #135e96; border-color: #135e96; }
.button-hero { font-size: 14px; padding: 6px 18px; }
.button.cbsr-danger { color: #b32d2e; border-color: #b32d2e; }

.cbsr-zone-picker { margin-bottom: 14px; }
.cbsr-zone-chip {
	display: inline-block;
	padding: 5px 12px;
	border: 1px solid #c3c4c7;
	border-radius: 14px;
	background: #fff;
	color: #1d2327;
	font-size: 12px;
	margin-right: 6px;
}
.cbsr-zone-chip.is-active { background: #2271b1; border-color: #2271b1; color: #fff; }
.cbsr-zone-chip .cbsr-muted { display: inline; color: inherit; opacity: .75; }

.cbsr-card {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 6px;
	padding: 18px 22px;
	margin: 16px 0 0;
}
.cbsr-card > h2 {
	margin: 0 0 12px;
	font-size: 15px;
	font-weight: 600;
	padding-bottom: 10px;
	border-bottom: 1px solid #f0f0f1;
}
.cbsr-card > h3 { font-size: 13px; margin: 20px 0 8px; }

.cbsr-two-col { display: flex; gap: 20px; align-items: flex-start; }
.cbsr-two-col > * { flex: 1 1 0; min-width: 0; }

.cbsr-field { margin-bottom: 14px; }
.cbsr-field > label { display: block; font-weight: 600; margin-bottom: 4px; }
.cbsr-field-row { display: flex; gap: 20px; flex-wrap: wrap; }
.cbsr-field-row > .cbsr-field { flex: 1 1 160px; }

.cbsr-actions { display: flex; align-items: center; gap: 10px; margin-top: 16px; }
.cbsr-hint, .cbsr-muted { color: #646970; font-size: 12px; }
.cbsr-muted { display: block; }
.cbsr-mono { font-family: Menlo, Consolas, monospace; font-size: 12px; }
.cbsr-right { text-align: right; }
.cbsr-warn-inline { color: #b32d2e; font-size: 11px; display: block; margin-top: 3px; }
.notice.inline { margin: 0 0 12px; padding: 1px 12px; border-left: 4px solid #dba617; background: #fff; }
.notice-error.inline { border-left-color: #d63638; }
.notice-info.inline { border-left-color: #72aee6; }
.notice-warning.inline { border-left-color: #dba617; }

/* WordPress 后台表单控件的默认宽度 */
input[type="text"], input[type="number"], input[type="email"], input[type="file"], select, textarea {
	border: 1px solid #8c8f94;
	border-radius: 4px;
	padding: 4px 8px;
	font-size: 13px;
	line-height: 1.5;
	background: #fff;
	color: #2c3338;
	min-height: 30px;
	vertical-align: middle;
}
textarea { padding: 8px; line-height: 1.6; }
input:focus, select:focus, textarea:focus { border-color: #2271b1; outline: 1px solid #2271b1; }
.small-text { width: 6em; }
.regular-text { width: 25em; }
.large-text { width: 100%; }
.cbsr-field > label { white-space: nowrap; }
.cbsr-table-editable input,
.cbsr-table-editable select { min-height: 28px; }
.cbsr-table-editable tbody td { padding: 6px 10px; }
CSS;

$wrapper = static function ( $screen ) use ( $menu, $shell_css ) {
	$menu_html = '';
	foreach ( $menu as $label => $slug ) {
		$menu_html .= '<li><a href="#">' . esc_html( $label ) . '</a></li>';
	}
	$menu_html .= '<li class="current"><a href="#">跨境运费</a>'
		. '<ul class="submenu">'
		. '<li class="current"><a href="#">物流线路</a></li>'
		. '<li><a href="#">运费试算</a></li>'
		. '</ul></li>';

	return '<!DOCTYPE html>'
		. '<html lang="zh-CN"><head><meta charset="utf-8" />'
		. '<title>' . esc_html( $screen['title'] ) . '</title>'
		. '<link rel="stylesheet" href="' . CBSR_URL . 'assets/css/admin.css" />'
		. '<style>' . $shell_css . '</style>'
		. '</head><body class="wp-admin wp-core-ui">'
		. '<div id="wpadminbar">'
		. '<div class="ab-left">'
		. '<span class="ab-item"><span class="dot">W</span></span>'
		. '<span class="ab-item site">我的跨境小店</span>'
		. '<span class="ab-item">+ 新建</span>'
		. '</div>'
		. '<span class="ab-item">你好，siyuanzhuang</span>'
		. '</div>'
		. '<div id="layout">'
		. '<div id="adminmenu"><ul>' . $menu_html . '</ul></div>'
		. '<div id="wpbody"><div id="wpbody-content">' . $screen['content'] . '</div></div>'
		. '</div>'
		. '</body></html>';
};

$out_dir = __DIR__ . '/../dist/shots';
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0777, true );
}

foreach ( $screens as $name => $screen ) {
	$path = $out_dir . '/' . $name . '.html';
	file_put_contents( $path, $wrapper( $screen ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	echo "written {$path}\n";
}
