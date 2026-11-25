<?php
/**
 * 后台模板冒烟测试：用最小 WP 桩函数把每个模板渲染一遍，
 * 目的是抓出「未定义变量 / 函数名写错 / 结构错乱」这类只在页面里才炸的问题。
 *
 * 用法：php tests/test-views.php
 *
 * @package CrossBorder_Shipping_Rates
 */

define( 'CBSR_TESTING', true );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CBSR_VERSION', '0.1.0' );
define( 'CBSR_PATH', __DIR__ . '/../' );
define( 'CBSR_URL', 'https://example.test/wp-content/plugins/crossborder-shipping-rates/' );
define( 'CBSR_FILE', __DIR__ . '/../crossborder-shipping-rates.php' );

/* -------------------------------------------------------------------------
 * 最小 WordPress 桩
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

function _e( $text, $domain = '' ) {
	echo $text;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

function add_query_arg( $args, $url = '' ) {
	$query = array();
	foreach ( (array) $args as $key => $value ) {
		$query[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $query );
}

function wp_nonce_field( $action = '', $name = '_wpnonce' ) {
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />';
}

function wp_nonce_url( $url, $action = '' ) {
	return $url . '&_wpnonce=nonce';
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

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

require_once CBSR_PATH . 'includes/class-cbsr-engine.php';
require_once CBSR_PATH . 'includes/class-cbsr-admin.php';

$passed = 0;
$failed = 0;

/**
 * 断言。
 *
 * @param string $label    用例名。
 * @param mixed  $expected 期望。
 * @param mixed  $actual   实际。
 */
function check( $label, $expected, $actual ) {
	global $passed, $failed;

	if ( $expected === $actual ) {
		++$passed;
		echo "  PASS  {$label}\n";
		return;
	}

	++$failed;
	echo "  FAIL  {$label}\n";
	echo '        期望: ' . var_export( $expected, true ) . "\n"; // phpcs:ignore
	echo '        实际: ' . var_export( $actual, true ) . "\n"; // phpcs:ignore
}

/**
 * 在独立作用域里渲染模板。
 *
 * @param string $relative 相对路径。
 * @param array  $vars     模板变量。
 * @return string
 */
function render_view( $relative, $vars = array() ) {
	$file = CBSR_PATH . 'admin/views/' . $relative;

	ob_start();
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	extract( $vars );
	include $file;

	return ob_get_clean();
}

$carrier = array(
	'id'                => 1,
	'name'              => '云途挂号小包',
	'code'              => 'yunexpress-pu',
	'currency'          => 'CNY',
	'exchange_rate'     => '0.14',
	'dim_factor'        => '6000',
	'weight_round_step' => '0.01',
	'weight_round_mode' => 'up',
	'status'            => 1,
	'is_default'        => 1,
	'sort_order'        => 0,
	'settings'          => '{"fuel_rate":10,"remote_fee":35,"handling_fee":2}',
	'stats'             => array(
		'zones' => 2,
		'rates' => 24,
	),
);

$zones = array(
	array(
		'id'               => 1,
		'carrier_id'       => 1,
		'zone_no'          => '1区',
		'name'             => '美国',
		'countries'        => 'US',
		'remote_countries' => '',
		'pricing_mode'     => 'bracket',
		'first_weight'     => 0,
		'first_price'      => 0,
		'step_weight'      => 0,
		'step_price'       => 0,
		'sort_order'       => 10,
	),
	array(
		'id'               => 2,
		'carrier_id'       => 1,
		'zone_no'          => '2区',
		'name'             => '澳新',
		'countries'        => '',
		'remote_countries' => '',
		'pricing_mode'     => 'first_add',
		'first_weight'     => 0.5,
		'first_price'      => 65,
		'step_weight'      => 0.5,
		'step_price'       => 22,
		'sort_order'       => 20,
	),
);

$rates = array(
	array(
		'carrier_id'    => 1,
		'zone_no'       => '1区',
		'weight_min'    => 0.001,
		'weight_max'    => 0.1,
		'unit_price'    => 58,
		'base_fee'      => 16,
		'operation_fee' => 1.5,
		'min_charge'    => 0,
	),
	array(
		'carrier_id'    => 1,
		'zone_no'       => '1区',
		'weight_min'    => 0.101,
		'weight_max'    => 0.2,
		'unit_price'    => 55,
		'base_fee'      => 16,
		'operation_fee' => 1.5,
		'min_charge'    => 0,
	),
);

echo "== 线路列表 ==\n";

$html = render_view(
	'carriers.php',
	array(
		'carriers' => array( $carrier ),
	)
);
check( '渲染出线路名', true, false !== strpos( $html, '云途挂号小包' ) );
check( '渲染出默认标记', true, false !== strpos( $html, '默认' ) );
check( '渲染出停用状态样式', true, false !== strpos( $html, 'cbsr-status-on' ) );
check( '渲染出空状态前的表格', true, false !== strpos( $html, '<table' ) );

$html = render_view( 'carriers.php', array( 'carriers' => array() ) );
check( '无线路时显示引导', true, false !== strpos( $html, '新建第一条线路' ) );

echo "\n== 基本设置 ==\n";

$html = render_view(
	'partials/basic.php',
	array(
		'carrier'  => $carrier,
		'settings' => CBSR_Engine::carrier_settings( $carrier ),
	)
);
check( '回填线路名', true, false !== strpos( $html, 'value="云途挂号小包"' ) );
check( '回填汇率', true, false !== strpos( $html, 'value="0.14"' ) );
check( '回填燃油', true, false !== strpos( $html, 'value="10"' ) );
check( '选中进位', 1, preg_match( '/value="up"[^>]*selected=/', $html ) );

$html = render_view(
	'partials/basic.php',
	array(
		'carrier'  => null,
		'settings' => CBSR_Engine::carrier_settings( array() ),
	)
);
check( '新建时默认币种 CNY', true, false !== strpos( $html, 'value="CNY"' ) );
check( '新建时按钮是创建线路', true, false !== strpos( $html, '创建线路' ) );

echo "\n== 分区 ==\n";

$html = render_view(
	'partials/zones.php',
	array(
		'zones'      => $zones,
		'carrier_id' => 1,
	)
);
check( '分区行使用显式下标', true, false !== strpos( $html, 'name="zones[0][zone_no]"' ) );
check( '第二行下标为 1', true, false !== strpos( $html, 'name="zones[1][zone_no]"' ) );
check( '模板使用 __i__ 占位', true, false !== strpos( $html, 'name="zones[__i__][zone_no]"' ) );
check( '选中首重续重', 1, preg_match( '/value="first_add"[^>]*selected=/', $html ) );
check( '空国家给出提示', true, false !== strpos( $html, '未填国家' ) );
check( '数组下标没有空', true, false === strpos( $html, 'zones[' . '][]' ) );

echo "\n== 重量段 ==\n";

$html = render_view(
	'partials/rates.php',
	array(
		'zones'         => $zones,
		'zone_options'  => array( '1区' => $zones[0], '2区' => $zones[1] ),
		'zone_filter'   => '1区',
		'rates'         => $rates,
		'carrier_id'    => 1,
		'carrier'       => $carrier,
		'settings'      => array(),
	)
);
check( '第一行有 6 个字段', 6, substr_count( $html, 'name="rates[0][' ) );
check( '第二行有 6 个字段', 6, substr_count( $html, 'name="rates[1][' ) );
check( '显式下标 rates[0]', true, false !== strpos( $html, 'name="rates[0][weight_min]"' ) );
check( '渲染分区切换胶囊', true, false !== strpos( $html, 'cbsr-zone-chip' ) );

$html = render_view(
	'partials/rates.php',
	array(
		'zones'         => $zones,
		'zone_options'  => array( '2区' => $zones[1] ),
		'zone_filter'   => '2区',
		'rates'         => $rates,
		'carrier_id'    => 1,
		'carrier'       => $carrier,
		'settings'      => array(),
	)
);
check( '首重续重分区给出提示', true, false !== strpos( $html, '首重 + 续重' ) );

$html = render_view(
	'partials/rates.php',
	array(
		'zones'         => array(),
		'zone_options'  => array(),
		'zone_filter'   => '',
		'rates'         => array(),
		'carrier_id'    => 1,
		'carrier'       => $carrier,
		'settings'      => array(),
	)
);
check( '无分区时提示先建分区', true, false !== strpos( $html, '请先在「分区」页建立分区' ) );

echo "\n== 导入 ==\n";

$html = render_view(
	'partials/import.php',
	array(
		'preview'    => false,
		'carrier_id' => 1,
	)
);
check( '渲染上传表单', true, false !== strpos( $html, 'enctype="multipart/form-data"' ) );
check( '渲染示例下载', true, false !== strpos( $html, 'example-rates.csv' ) );

$preview = array(
	'rows'     => array(
		array(
			'zone_no'       => '1区',
			'weight_min'    => 0.001,
			'weight_max'    => 0.1,
			'unit_price'    => 58,
			'base_fee'      => 16,
			'operation_fee' => 1.5,
			'min_charge'    => 0,
			'line'          => 2,
		),
	),
	'zones'    => array(
		'1区' => array(
			'zone_no'   => '1区',
			'name'      => '美国',
			'countries' => 'US',
			'remote'    => '',
		),
		'2区' => array(
			'zone_no'   => '2区',
			'name'      => '',
			'countries' => '',
			'remote'    => '',
		),
	),
	'errors'   => array(),
	'warnings' => array( '分区「2区」没有填国家代码' ),
	'header'   => array(),
);

$html = render_view(
	'partials/import.php',
	array(
		'preview'    => $preview,
		'carrier_id' => 1,
	)
);
check( '预览显示分区数', true, false !== strpos( $html, '识别到 2 个分区、1 行报价' ) );
check( '预览显示警告', true, false !== strpos( $html, '没有填国家代码' ) );
check( '预览提供确认按钮', true, false !== strpos( $html, '确认导入' ) );
check( '空国家显示为红色提示', true, false !== strpos( $html, 'cbsr-warn-inline' ) );

$preview['errors'] = array( '没有找到「单价」列' );
$html              = render_view(
	'partials/import.php',
	array(
		'preview'    => $preview,
		'carrier_id' => 1,
	)
);
check( '有致命错误时不显示确认按钮', false, false !== strpos( $html, 'cbsr_import_commit' ) );

echo "\n== 试算 ==\n";

$result = CBSR_Engine::quote(
	$carrier,
	$zones,
	$rates,
	array(
		'weight'  => 0.2,
		'country' => 'US',
	)
);

$html = render_view(
	'tools.php',
	array(
		'carriers' => array( $carrier ),
		'result'   => $result,
		'input'    => array(
			'carrier_id' => 1,
			'country'    => 'US',
			'weight'     => 0.2,
			'length'     => 0,
			'width'      => 0,
			'height'     => 0,
		),
	)
);
check( '试算显示计费重', true, false !== strpos( $html, '计费重' ) );
check( '试算显示命中分区', true, false !== strpos( $html, '1区' ) );
check( '试算显示合计', true, false !== strpos( $html, '合计（报价币种）' ) );
check( '试算显示换算后金额', true, false !== strpos( $html, '换算后（商店币种）' ) );

$bad = CBSR_Engine::quote( $carrier, $zones, $rates, array( 'weight' => 9, 'country' => 'US' ) );
$html = render_view(
	'tools.php',
	array(
		'carriers' => array( $carrier ),
		'result'   => $bad,
		'input'    => array(
			'carrier_id' => 1,
			'country'    => 'US',
			'weight'     => 9,
			'length'     => 0,
			'width'      => 0,
			'height'     => 0,
		),
	)
);
check( '算不出价时显示中文原因', true, false !== strpos( $html, '超出该线路的最大重量' ) );

$html = render_view(
	'tools.php',
	array(
		'carriers' => array(),
		'result'   => null,
		'input'    => array(
			'carrier_id' => 0,
			'country'    => 'US',
			'weight'     => 0.5,
			'length'     => 0,
			'width'      => 0,
			'height'     => 0,
		),
	)
);
check( '无线路时给出提示', true, false !== strpos( $html, '还没有线路' ) );

echo "\n----------------------------------------\n";
echo "通过 {$passed}，失败 {$failed}\n";

exit( $failed > 0 ? 1 : 0 );
