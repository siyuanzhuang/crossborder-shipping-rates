<?php
/**
 * 报价单解析测试（不需要 WordPress）。
 *
 * 用法：php tests/test-import.php
 *
 * @package CrossBorder_Shipping_Rates
 */

define( 'CBSR_TESTING', true );
define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../includes/class-cbsr-engine.php';
require_once __DIR__ . '/../includes/class-cbsr-import.php';

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

echo "== 示例报价单 ==\n";

$csv = file_get_contents( __DIR__ . '/../samples/example-rates.csv' ); // phpcs:ignore
$parsed = CBSR_Import::parse( $csv );

check( '没有致命错误', array(), $parsed['errors'] );
check( '识别出 4 个分区', 4, count( $parsed['zones'] ) );
check( '识别出 48 行报价', 48, count( $parsed['rows'] ) );
check( '1 区国家为 US', 'US', $parsed['zones']['1区']['countries'] );
check( '2 区国家为 GB,DE,FR,IT,ES,NL', 'GB,DE,FR,IT,ES,NL', $parsed['zones']['2区']['countries'] );
check( '4 区为兜底 *', '*', $parsed['zones']['4区']['countries'] );
check( '第一行单价 58', 58.0, $parsed['rows'][0]['unit_price'] );
check( '第一行重量段 0.001-0.1', array( 0.001, 0.1 ), array( $parsed['rows'][0]['weight_min'], $parsed['rows'][0]['weight_max'] ) );
check( '第一行挂号费 16', 16.0, $parsed['rows'][0]['base_fee'] );

$first_rate = CBSR_Engine::find_rate( CBSR_Engine::filter_zone( $parsed['rows'], '1区' ), 0.1 );
check( '0.1kg 命中第一档单价 58', 58.0, (float) $first_rate['unit_price'] );

echo "\n== 表头识别 ==\n";

$english = "zone,countries,weight_min,weight_max,price,base_fee\n1,US,0.001,0.1,58,16\n1,,0.101,0.2,55,16\n";
$p       = CBSR_Import::parse( $english );
check( '英文表头无错误', array(), $p['errors'] );
check( '英文表头 2 行', 2, count( $p['rows'] ) );
check( '英文表头国家沿用到第二行', 'US', $p['zones'][1]['countries'] );

$mixed = "区域,国家代码,重量段(kg),单价(元/kg),挂号费,操作费,最低消费\nA区,\"GB,DE\",≤0.1,62,16,1,0\nA区,,0.1-0.2,59,16,1,0\n";
$p     = CBSR_Import::parse( $mixed );
check( '中文表头无错误', array(), $p['errors'] );
check( '≤0.1 识别为 0.001-0.1', array( 0.001, 0.1 ), array( $p['rows'][0]['weight_min'], $p['rows'][0]['weight_max'] ) );
check( '带单位表头也能识别单价', 62.0, $p['rows'][0]['unit_price'] );

echo "\n== 容错 ==\n";

$p = CBSR_Import::parse( "分区,重量段,单价\n1,0.001-0.1,abc\n1,0.101-0.2,50\n" );
check( '非数字单价被跳过，只保留 1 行', 1, count( $p['rows'] ) );
check( '跳过时给出提示', true, count( $p['warnings'] ) > 0 );

$p = CBSR_Import::parse( "重量段,单价\n0.001-0.1,50\n" );
check( '缺少分区列报错', true, count( $p['errors'] ) > 0 );

$p = CBSR_Import::parse( '' );
check( '空文件报错', true, count( $p['errors'] ) > 0 );

$p = CBSR_Import::parse( "分区,重量段,单价\n1,0.001-0.2,50\n1,0.1-0.3,45\n" );
check( '重叠区间给出警告', true, count( $p['warnings'] ) > 0 );

echo "\n== 编码 ==\n";

$gbk = mb_convert_encoding( "分区,重量段,单价\n1,0.001-0.1,58\n", 'GB18030', 'UTF-8' );
$p   = CBSR_Import::parse( $gbk );
check( 'GBK 编码自动转 UTF-8', 1, count( $p['rows'] ) );

$bom = "\xEF\xBB\xBF分区,重量段,单价\n1,0.001-0.1,58\n";
$p   = CBSR_Import::parse( $bom );
check( 'UTF-8 BOM 被去掉', 1, count( $p['rows'] ) );

$tab = "分区\t重量段\t单价\n1\t0.001-0.1\t58\n";
$p   = CBSR_Import::parse( $tab );
check( '制表符分隔也能解析', 1, count( $p['rows'] ) );

$semi = "分区;重量段;单价\n1;0.001-0.1;58\n";
$p    = CBSR_Import::parse( $semi );
check( '分号分隔也能解析', 1, count( $p['rows'] ) );

echo "\n----------------------------------------\n";
echo "通过 {$passed}，失败 {$failed}\n";

exit( $failed > 0 ? 1 : 0 );
