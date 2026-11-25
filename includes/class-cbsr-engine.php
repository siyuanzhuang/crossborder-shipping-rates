<?php
/**
 * 计价引擎：纯 PHP，不依赖 WordPress，可以单独跑单元测试。
 *
 * @package CrossBorder_Shipping_Rates
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'CBSR_TESTING' ) ) {
	exit;
}

/**
 * Class CBSR_Engine
 *
 * 输入：
 *  - carrier  线路设置（报价币种、体积重系数、进位规则、附加费）
 *  - zones    分区表（分区号 => 覆盖国家列表）
 *  - rates    重量段报价表
 *  - package  包裹数据（实重、尺寸、目的国、邮编）
 *
 * 输出：
 *  - 总价 + 明细，方便前台展示和后台试算。
 */
class CBSR_Engine {

	const MODE_BRACKET   = 'bracket';
	const MODE_FIRST_ADD = 'first_add';

	const EPSILON = 0.0000001;

	/**
	 * 体积重：长*宽*高 / 系数。
	 *
	 * @param float $weight_kg     实重（kg）。
	 * @param array $dimensions    尺寸 cm，键 length/width/height。
	 * @param float $dim_factor    体积重系数，0 表示不启用。
	 * @return float
	 */
	public static function volumetric_weight( $weight_kg, $dimensions, $dim_factor ) {
		$dim_factor = (float) $dim_factor;
		if ( $dim_factor <= 0 || ! is_array( $dimensions ) ) {
			return 0.0;
		}

		$length = isset( $dimensions['length'] ) ? (float) $dimensions['length'] : 0.0;
		$width  = isset( $dimensions['width'] ) ? (float) $dimensions['width'] : 0.0;
		$height = isset( $dimensions['height'] ) ? (float) $dimensions['height'] : 0.0;

		if ( $length <= 0 || $width <= 0 || $height <= 0 ) {
			return 0.0;
		}

		return ( $length * $width * $height ) / $dim_factor;
	}

	/**
	 * 计费重：取实重与体积重的较大值，再按进位规则处理。
	 *
	 * @param float  $weight_kg   实重。
	 * @param array  $dimensions  尺寸。
	 * @param float  $dim_factor  体积重系数。
	 * @param float  $step        进位粒度，例如 0.01 / 0.1 / 0.5 / 1。
	 * @param string $mode        up（进位）| round（四舍五入）| none（不进位）。
	 * @return float
	 */
	public static function billable_weight( $weight_kg, $dimensions = array(), $dim_factor = 6000, $step = 0.01, $mode = 'up' ) {
		$actual    = max( 0.0, (float) $weight_kg );
		$volumetric = self::volumetric_weight( $actual, $dimensions, $dim_factor );

		$weight = max( $actual, $volumetric );

		$step = (float) $step;
		if ( $step > 0 && $weight > 0 ) {
			if ( 'round' === $mode ) {
				$weight = round( $weight / $step ) * $step;
			} elseif ( 'none' !== $mode ) {
				// 极小值保护，避免 0.30000000000000004 这类浮点误差被进位成 0.31。
				$ratio = $weight / $step;
				$ceil  = ceil( $ratio - self::EPSILON );
				$weight = $ceil * $step;
			}
		}

		if ( $weight <= 0 ) {
			return 0.0;
		}

		return round( $weight, 4 );
	}

	/**
	 * 规范化国家代码。
	 *
	 * @param string $country 国家代码。
	 * @return string
	 */
	public static function normalize_country( $country ) {
		return strtoupper( trim( (string) $country ) );
	}

	/**
	 * 解析分区覆盖的国家列表，支持逗号、空格、分号、竖线分隔。
	 *
	 * @param array|string $zone 分区记录或国家字符串。
	 * @return array
	 */
	public static function zone_countries( $zone ) {
		$raw = is_array( $zone ) ? ( isset( $zone['countries'] ) ? $zone['countries'] : '' ) : $zone;

		if ( is_array( $raw ) ) {
			$list = $raw;
		} else {
			$list = preg_split( '/[\s,;|\/]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
		}

		$out = array();
		foreach ( (array) $list as $code ) {
			$code = self::normalize_country( $code );
			if ( '' !== $code ) {
				$out[] = $code;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * 按目的国找分区；支持 "*" 或 "ALL" 作为兜底分区。
	 *
	 * @param array  $zones   分区列表。
	 * @param string $country 国家代码。
	 * @return array|null
	 */
	public static function find_zone( $zones, $country ) {
		$country  = self::normalize_country( $country );
		$fallback = null;

		foreach ( (array) $zones as $zone ) {
			$codes = self::zone_countries( $zone );

			if ( in_array( $country, $codes, true ) ) {
				return $zone;
			}

			if ( null === $fallback && ( in_array( '*', $codes, true ) || in_array( 'ALL', $codes, true ) ) ) {
				$fallback = $zone;
			}
		}

		return $fallback;
	}

	/**
	 * 按分区号过滤重量段。
	 *
	 * @param array  $rates   全部重量段。
	 * @param string $zone_no 分区号。
	 * @return array
	 */
	public static function filter_zone( $rates, $zone_no ) {
		$out = array();
		foreach ( (array) $rates as $rate ) {
			if ( isset( $rate['zone_no'] ) && (string) $rate['zone_no'] === (string) $zone_no ) {
				$out[] = $rate;
			}
		}

		return $out;
	}

	/**
	 * 在重量段表里按计费重找档位。
	 *
	 * 约定：报价单的区间是 (上一档上限, 本档上限]，即 0.001-0.1 覆盖 ≤0.1kg，
	 * 0.101-0.2 覆盖 (0.1, 0.2]。所以取「第一条 weight_max >= 计费重」的记录，
	 * 再校验计费重不低于该档下限。
	 *
	 * @param array $rates    该分区的重量段。
	 * @param float $billable 计费重。
	 * @return array|null
	 */
	public static function find_rate( $rates, $billable ) {
		$billable = (float) $billable;

		if ( $billable <= 0 || empty( $rates ) ) {
			return null;
		}

		usort(
			$rates,
			function ( $a, $b ) {
				return (float) $a['weight_max'] <=> (float) $b['weight_max'];
			}
		);

		foreach ( $rates as $rate ) {
			$max = (float) $rate['weight_max'];
			if ( $billable <= $max + self::EPSILON ) {
				$min = (float) $rate['weight_min'];
				if ( $billable < $min - self::EPSILON ) {
					return null; // 计费重落在这档下限之外，说明报价单有缺口。
				}
				return $rate;
			}
		}

		return null; // 超重。
	}

	/**
	 * 计算一票运费。
	 *
	 * @param array $carrier 线路设置。
	 * @param array $zones   该线路的分区。
	 * @param array $rates   该线路的重量段（已按 zone_no 过滤好）。
	 * @param array $package 包裹数据：weight、dimensions、country、postcode。
	 * @return array
	 */
	public static function quote( $carrier, $zones, $rates, $package ) {
		$carrier = (array) $carrier;
		$package = (array) $package;

		$currency = isset( $carrier['currency'] ) && '' !== $carrier['currency'] ? strtoupper( $carrier['currency'] ) : 'CNY';

		$result = array(
			'ok'              => false,
			'total'           => 0.0,   // 商店币种金额。
			'subtotal'        => 0.0,   // 报价币种金额。
			'currency'        => $currency,
			'exchange_rate'   => 1.0,
			'billable_weight' => 0.0,
			'actual_weight'   => (float) ( isset( $package['weight'] ) ? $package['weight'] : 0 ),
			'volumetric'      => 0.0,
			'zone'            => null,
			'zone_no'         => '',
			'lines'           => array(),
			'errors'          => array(),
		);

		$country = self::normalize_country( isset( $package['country'] ) ? $package['country'] : '' );
		if ( '' === $country ) {
			$result['errors'][] = 'missing_country';
		}

		$dim_factor = isset( $carrier['dim_factor'] ) ? (float) $carrier['dim_factor'] : 6000;
		$step       = isset( $carrier['weight_round_step'] ) ? (float) $carrier['weight_round_step'] : 0.01;
		$round_mode = isset( $carrier['weight_round_mode'] ) && '' !== $carrier['weight_round_mode'] ? $carrier['weight_round_mode'] : 'up';

		$dimensions = isset( $package['dimensions'] ) && is_array( $package['dimensions'] ) ? $package['dimensions'] : array();

		$result['volumetric']      = self::volumetric_weight( $result['actual_weight'], $dimensions, $dim_factor );
		$result['billable_weight'] = self::billable_weight( $result['actual_weight'], $dimensions, $dim_factor, $step, $round_mode );

		if ( $result['billable_weight'] <= 0 ) {
			$result['errors'][] = 'missing_weight';
		}

		$zone = self::find_zone( $zones, $country );
		if ( ! $zone ) {
			$result['errors'][] = 'no_zone';
		} else {
			$result['zone']    = $zone;
			$result['zone_no'] = isset( $zone['zone_no'] ) ? (string) $zone['zone_no'] : '';
		}

		if ( ! empty( $result['errors'] ) ) {
			return $result;
		}

		$billable   = $result['billable_weight'];
		$base_cost  = 0.0;
		$mode       = isset( $zone['pricing_mode'] ) && '' !== $zone['pricing_mode'] ? $zone['pricing_mode'] : self::MODE_BRACKET;
		$lines      = array();

		if ( self::MODE_FIRST_ADD === $mode ) {
			$first_weight = isset( $zone['first_weight'] ) ? (float) $zone['first_weight'] : 0.0;
			$first_price  = isset( $zone['first_price'] ) ? (float) $zone['first_price'] : 0.0;
			$step_weight  = isset( $zone['step_weight'] ) ? (float) $zone['step_weight'] : 0.0;
			$step_price   = isset( $zone['step_price'] ) ? (float) $zone['step_price'] : 0.0;

			if ( $first_weight <= 0 ) {
				$result['errors'][] = 'invalid_first_weight';
				return $result;
			}

			$base_cost   = $first_price;
			$extra       = max( 0.0, $billable - $first_weight );
			$steps       = ( $step_weight > 0 ) ? (int) ceil( $extra / $step_weight - self::EPSILON ) : 0;
			$addon_money = $steps * $step_price;

			$lines[] = array(
				'code'   => 'first_weight',
				'label'  => sprintf( '首重 %1$s kg', self::trim_decimal( $first_weight ) ),
				'amount' => $first_price,
			);

			if ( $steps > 0 ) {
				$lines[] = array(
					'code'   => 'add_weight',
					'label'  => sprintf( '续重 %1$d × %2$s kg', $steps, self::trim_decimal( $step_weight ) ),
					'amount' => $addon_money,
				);
				$base_cost += $addon_money;
			}
		} else {
			$zone_rates = self::filter_zone( $rates, $result['zone_no'] );

			$rate = self::find_rate( $zone_rates, $billable );

			if ( ! $rate ) {
				$result['errors'][] = self::is_over_weight( $zone_rates, $billable ) ? 'over_weight' : 'no_rate';
				return $result;
			}

			$unit_price = (float) $rate['unit_price'];
			$freight    = $unit_price * $billable;

			$lines[] = array(
				'code'   => 'freight',
				'label'  => sprintf( '运费 %1$s kg × %2$s', self::trim_decimal( $billable ), self::format_money( $unit_price ) ),
				'amount' => $freight,
			);

			$base_cost = $freight;

			$base_fee = isset( $rate['base_fee'] ) ? (float) $rate['base_fee'] : 0.0;
			if ( $base_fee > 0 ) {
				$lines[] = array(
					'code'   => 'base_fee',
					'label'  => '挂号费/件',
					'amount' => $base_fee,
				);
				$base_cost += $base_fee;
			}

			$operation_fee = isset( $rate['operation_fee'] ) ? (float) $rate['operation_fee'] : 0.0;
			if ( $operation_fee > 0 ) {
				$lines[] = array(
					'code'   => 'operation_fee',
					'label'  => '操作费/件',
					'amount' => $operation_fee,
				);
				$base_cost += $operation_fee;
			}

			$min_charge = isset( $rate['min_charge'] ) ? (float) $rate['min_charge'] : 0.0;
			if ( $min_charge > 0 && $base_cost < $min_charge ) {
				$diff    = $min_charge - $base_cost;
				$lines[] = array(
					'code'   => 'min_charge',
					'label'  => '补足最低消费',
					'amount' => $diff,
				);
				$base_cost = $min_charge;
			}
		}

		$settings = self::carrier_settings( $carrier );

		// 燃油附加费：按「运费 + 挂号/操作」的百分比。
		$fuel_rate = isset( $settings['fuel_rate'] ) ? (float) $settings['fuel_rate'] : 0.0;
		if ( $fuel_rate > 0 ) {
			$fuel = $base_cost * $fuel_rate / 100;
			$lines[] = array(
				'code'   => 'fuel',
				'label'  => sprintf( '燃油附加费 %s%%', self::trim_decimal( $fuel_rate ) ),
				'amount' => $fuel,
			);
			$base_cost += $fuel;
		}

		// 偏远地区附加费。
		$remote_codes = self::zone_countries( isset( $zone['remote_countries'] ) ? $zone['remote_countries'] : '' );
		$remote_fee   = isset( $settings['remote_fee'] ) ? (float) $settings['remote_fee'] : 0.0;
		if ( $remote_fee > 0 && in_array( $country, $remote_codes, true ) ) {
			$lines[] = array(
				'code'   => 'remote',
				'label'  => '偏远地区附加费',
				'amount' => $remote_fee,
			);
			$base_cost += $remote_fee;
		}

		// 固定处理费。
		$handling_fee = isset( $settings['handling_fee'] ) ? (float) $settings['handling_fee'] : 0.0;
		if ( $handling_fee > 0 ) {
			$lines[] = array(
				'code'   => 'handling',
				'label'  => '包装/操作附加费',
				'amount' => $handling_fee,
			);
			$base_cost += $handling_fee;
		}

		$exchange_rate = isset( $carrier['exchange_rate'] ) ? (float) $carrier['exchange_rate'] : 1.0;
		if ( $exchange_rate <= 0 ) {
			$exchange_rate = 1.0;
		}

		$result['subtotal']      = round( $base_cost, 2 );
		$result['total']         = round( $base_cost * $exchange_rate, 2 );
		$result['exchange_rate'] = $exchange_rate;
		$result['lines']         = $lines;
		$result['ok']            = true;

		return $result;
	}

	/**
	 * 判断是否超重（计费重超过表内最大上限）。
	 *
	 * @param array $rates    重量段。
	 * @param float $billable 计费重。
	 * @return bool
	 */
	public static function is_over_weight( $rates, $billable ) {
		if ( empty( $rates ) ) {
			return false;
		}

		$max = 0.0;
		foreach ( $rates as $rate ) {
			$max = max( $max, (float) $rate['weight_max'] );
		}

		return $billable > $max + self::EPSILON;
	}

	/**
	 * 解析线路 JSON 设置，带默认值。
	 *
	 * @param array $carrier 线路。
	 * @return array
	 */
	public static function carrier_settings( $carrier ) {
		$defaults = array(
			'fuel_rate'    => 0,
			'remote_fee'   => 0,
			'handling_fee' => 0,
		);

		$raw = isset( $carrier['settings'] ) ? $carrier['settings'] : '';

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return array_merge( $defaults, $decoded );
			}
		} elseif ( is_array( $raw ) ) {
			return array_merge( $defaults, $raw );
		}

		return $defaults;
	}

	/**
	 * 去掉无意义的小数零。
	 *
	 * @param float $number 数字。
	 * @return string
	 */
	public static function trim_decimal( $number ) {
		$number = (float) $number;
		if ( abs( $number - round( $number ) ) < 0.0000001 ) {
			return (string) (int) round( $number );
		}
		return rtrim( rtrim( number_format( $number, 4, '.', '' ), '0' ), '.' );
	}

	/**
	 * 金额格式化（不带货币符号）。
	 *
	 * @param float $number 金额。
	 * @return string
	 */
	public static function format_money( $number ) {
		return number_format( (float) $number, 2, '.', '' );
	}

	/**
	 * 把错误码翻译成中文提示。
	 *
	 * @param string $code 错误码。
	 * @return string
	 */
	public static function error_message( $code ) {
		$map = array(
			'missing_country'     => '缺少收货国家',
			'missing_weight'      => '包裹重量为 0，无法计费',
			'no_zone'             => '该目的国没有匹配到分区',
			'no_rate'             => '该重量没有对应的重量段报价',
			'over_weight'         => '超出该线路的最大重量',
			'invalid_first_weight' => '首重设置无效',
		);

		return isset( $map[ $code ] ) ? $map[ $code ] : $code;
	}
}
