<?php
/**
 * 计价引擎单元测试（不需要 WordPress）。
 *
 * 用法：php tests/test-engine.php
 *
 * @package CrossBorder_Shipping_Rates
 */

define( 'CBSR_TESTING', true );
define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../includes/class-cbsr-engine.php';

$passed = 0;
$failed = 0;

/**
 * 断言相等。
 *
 * @param string $label    用例名。
 * @param mixed  $expected 期望值。
 * @param mixed  $actual   实际值。
 * @param float  $delta    浮点容差。
 */
function check( $label, $expected, $actual, $delta = 0.0001 ) {
	global $passed, $failed;

	if ( is_float( $expected ) || is_float( $actual ) ) {
		$ok = abs( (float) $expected - (float) $actual ) <= $delta;
	} else {
		$ok = $expected === $actual;
	}

	if ( $ok ) {
		++$passed;
		echo "  PASS  {$label}\n";
		return;
	}

	++$failed;
	echo "  FAIL  {$label}\n";
	echo '        期望: ' . var_export( $expected, true ) . "\n"; // phpcs:ignore
	echo '        实际: ' . var_export( $actual, true ) . "\n"; // phpcs:ignore
}

$zones = array(
	array(
		'zone_no'          => '1',
		'name'             => '美国',
		'countries'        => 'US,PR',
		'remote_countries' => 'PR',
		'pricing_mode'     => 'bracket',
	),
	array(
		'zone_no'      => '2',
		'name'         => '欧洲',
		'countries'    => 'GB,DE,FR',
		'pricing_mode' => 'bracket',
	),
	array(
		'zone_no'          => '3',
		'name'             => '首重续重区',
		'countries'        => 'AU',
		'pricing_mode'     => 'first_add',
		'first_weight'     => 0.5,
		'first_price'      => 65,
		'step_weight'      => 0.5,
		'step_price'       => 22,
		'remote_countries' => '',
	),
	array(
		'zone_no'      => '9',
		'name'         => '兜底',
		'countries'    => '*',
		'pricing_mode' => 'bracket',
	),
);

$rates = array();
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

foreach ( $brackets as $bracket ) {
	$rates[] = array(
		'zone_no'       => '1',
		'weight_min'    => $bracket[0],
		'weight_max'    => $bracket[1],
		'unit_price'    => $bracket[2],
		'base_fee'      => 16,
		'operation_fee' => 1.5,
		'min_charge'    => 0,
	);
}

$rates[] = array(
	'zone_no'       => '9',
	'weight_min'    => 0.001,
	'weight_max'    => 2.0,
	'unit_price'    => 75,
	'base_fee'      => 16,
	'operation_fee' => 1.5,
	'min_charge'    => 0,
);

$carrier = array(
	'currency'          => 'CNY',
	'exchange_rate'     => 1,
	'dim_factor'        => 6000,
	'weight_round_step' => 0.01,
	'weight_round_mode' => 'up',
	'settings'          => wp_json_encode_mock( array( 'fuel_rate' => 0 ) ),
);

/**
 * 模拟 wp_json_encode。
 *
 * @param mixed $value 值。
 * @return string
 */
function wp_json_encode_mock( $value ) {
	return json_encode( $value ); // phpcs:ignore
}

echo "== 体积重与计费重 ==\n";

check( '实重 0.12kg 不进位 → 0.12', 0.12, CBSR_Engine::billable_weight( 0.12, array(), 6000, 0.01, 'up' ) );
check(
	'30×20×10cm ÷ 6000 = 1.0kg 体积重',
	1.0,
	CBSR_Engine::volumetric_weight( 0.2, array( 'length' => 30, 'width' => 20, 'height' => 10 ), 6000 )
);
check(
	'体积重大于实重时取体积重',
	1.0,
	CBSR_Engine::billable_weight( 0.2, array( 'length' => 30, 'width' => 20, 'height' => 10 ), 6000, 0.01, 'up' )
);
check( '0.101kg 按 0.01 进位 → 0.11', 0.11, CBSR_Engine::billable_weight( 0.101, array(), 6000, 0.01, 'up' ) );
check( '0.101kg 按 0.1 进位 → 0.2', 0.2, CBSR_Engine::billable_weight( 0.101, array(), 6000, 0.1, 'up' ) );
check( '0.30000000001 不被误进位成 0.31', 0.3, CBSR_Engine::billable_weight( 0.1 + 0.2, array(), 6000, 0.01, 'up' ) );
check( '不启用体积重时系数 0 → 0', 0.0, CBSR_Engine::volumetric_weight( 1, array( 'length' => 30, 'width' => 20, 'height' => 10 ), 0 ) );

echo "\n== 分区匹配 ==\n";

check( 'US 命中 1 区', '1', CBSR_Engine::find_zone( $zones, 'US' )['zone_no'] );
check( '小写 us 也能命中', '1', CBSR_Engine::find_zone( $zones, 'us' )['zone_no'] );
check( '未列出的国家走 * 兜底', '9', CBSR_Engine::find_zone( $zones, 'BR' )['zone_no'] );
check( '找不到分区返回 null', null, CBSR_Engine::find_zone( array( $zones[0] ), 'BR' ) );
check( '国家列表解析', array( 'US', 'CA' ), CBSR_Engine::zone_countries( 'us, ca' ) );

echo "\n== 重量段边界 ==\n";

check( '0.1kg 落在 0.001-0.1 档', 58.0, (float) CBSR_Engine::find_rate( CBSR_Engine::filter_zone( $rates, '1' ), 0.1 )['unit_price'] );
check( '0.2kg 落在 0.101-0.2 档', 55.0, (float) CBSR_Engine::find_rate( CBSR_Engine::filter_zone( $rates, '1' ), 0.2 )['unit_price'] );
check( '0.101kg 落在 0.101-0.2 档', 55.0, (float) CBSR_Engine::find_rate( CBSR_Engine::filter_zone( $rates, '1' ), 0.101 )['unit_price'] );
check( '2.0kg 落在最后一档', 37.0, (float) CBSR_Engine::find_rate( CBSR_Engine::filter_zone( $rates, '1' ), 2.0 )['unit_price'] );
check( '2.5kg 超重返回 null', null, CBSR_Engine::find_rate( CBSR_Engine::filter_zone( $rates, '1' ), 2.5 ) );

echo "\n== 整票报价 ==\n";

$quote = CBSR_Engine::quote(
	$carrier,
	$zones,
	$rates,
	array(
		'weight'  => 0.5,
		'country' => 'US',
	)
);
check( '0.5kg 到美国：48×0.5 + 16 + 1.5 = 41.5', 41.5, $quote['total'] );
check( '命中分区 1', '1', $quote['zone_no'] );
check( '计费重 0.5', 0.5, $quote['billable_weight'] );
check( '明细含运费/挂号费/操作费', 3, count( $quote['lines'] ) );

$carrier_fuel = $carrier;
$carrier_fuel['settings'] = wp_json_encode_mock( array( 'fuel_rate' => 10 ) );
$quote_fuel = CBSR_Engine::quote( $carrier_fuel, $zones, $rates, array( 'weight' => 0.5, 'country' => 'US' ) );
check( '燃油 10%：41.5 × 1.1 = 45.65', 45.65, $quote_fuel['total'] );

$quote_remote = CBSR_Engine::quote(
	array_merge( $carrier, array( 'settings' => wp_json_encode_mock( array( 'remote_fee' => 35 ) ) ) ),
	$zones,
	$rates,
	array( 'weight' => 0.5, 'country' => 'PR' )
);
check( '偏远地区 PR 加收 35 → 41.5 + 35', 76.5, $quote_remote['total'] );

$quote_fx = CBSR_Engine::quote(
	array_merge( $carrier, array( 'exchange_rate' => 0.14 ) ),
	$zones,
	$rates,
	array( 'weight' => 0.5, 'country' => 'US' )
);
check( '汇率 0.14 → 41.5 × 0.14 = 5.81', 5.81, $quote_fx['total'] );

$quote_vol = CBSR_Engine::quote(
	$carrier,
	$zones,
	$rates,
	array(
		'weight'     => 0.2,
		'country'    => 'US',
		'dimensions' => array( 'length' => 30, 'width' => 20, 'height' => 10 ),
	)
);
check( '体积重 1.0kg → 41 + 16 + 1.5 = 58.5', 58.5, $quote_vol['total'] );

echo "\n== 首重续重 ==\n";

$quote_fa = CBSR_Engine::quote( $carrier, $zones, $rates, array( 'weight' => 1.2, 'country' => 'AU' ) );
check( '首重 0.5kg/65 + 续重 2×22 = 109', 109.0, $quote_fa['subtotal'] );
check( '首重续重计费重 1.2', 1.2, $quote_fa['billable_weight'] );

$quote_fa2 = CBSR_Engine::quote( $carrier, $zones, $rates, array( 'weight' => 0.4, 'country' => 'AU' ) );
check( '0.4kg 未超首重，只收 65', 65.0, $quote_fa2['subtotal'] );

echo "\n== 异常 ==\n";

$quote_over = CBSR_Engine::quote( $carrier, $zones, $rates, array( 'weight' => 5, 'country' => 'US' ) );
check( '5kg 超重', false, $quote_over['ok'] );
check( '错误码 over_weight', 'over_weight', $quote_over['errors'][0] );

$quote_now = CBSR_Engine::quote( $carrier, $zones, $rates, array( 'weight' => 0.5, 'country' => 'ZZ' ) );
check( 'ZZ 走兜底分区不算错', true, $quote_now['ok'] );

$no_fallback_zones = array( $zones[0] );
$quote_nozone = CBSR_Engine::quote( $carrier, $no_fallback_zones, $rates, array( 'weight' => 0.5, 'country' => 'BR' ) );
check( '没有兜底时未匹配到分区', 'no_zone', $quote_nozone['errors'][0] );

$quote_zero = CBSR_Engine::quote( $carrier, $zones, $rates, array( 'weight' => 0, 'country' => 'US' ) );
check( '0 重量报错', false, $quote_zero['ok'] );

echo "\n== 最低消费 ==\n";

$rates_mc   = $rates;
$rates_mc[] = array(
	'zone_no'       => '2',
	'weight_min'    => 0.001,
	'weight_max'    => 0.1,
	'unit_price'    => 100,
	'base_fee'      => 0,
	'operation_fee' => 0,
	'min_charge'    => 50,
);
$quote_mc = CBSR_Engine::quote( $carrier, $zones, $rates_mc, array( 'weight' => 0.05, 'country' => 'GB' ) );
check( '100×0.05=5 补到最低消费 50', 50.0, $quote_mc['total'] );

echo "\n----------------------------------------\n";
echo "通过 {$passed}，失败 {$failed}\n";

exit( $failed > 0 ? 1 : 0 );
