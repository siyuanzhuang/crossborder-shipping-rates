<?php
/**
 * 报价单导入：把货代给的 CSV 变成分区 + 重量段。
 *
 * 支持：
 *  - UTF-8 / UTF-8 BOM / GBK（中文 Excel 默认导出编码）
 *  - 逗号、制表符、分号分隔
 *  - 中英文表头自动识别
 *  - 「重量段」写在一列（0.1-0.2）或分成上下限两列
 *
 * @package CrossBorder_Shipping_Rates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CBSR_Import
 */
class CBSR_Import {

	/**
	 * 表头别名表。顺序有意义：越靠前，子串匹配优先级越高。
	 *
	 * @return array
	 */
	public static function header_aliases() {
		return array(
			'zone'          => array( 'zone', 'zone_no', '分区', '分区号', '区域', '区号', '价格区', '国家分区', '目的分区' ),
			'name'          => array( 'name', 'zone_name', '分区名称', '区域名称', '名称' ),
			'countries'     => array( 'countries', 'country', 'country_code', '国家', '国家代码', '国家二字码', '目的国', '目的国家', '覆盖国家', '可发国家', '可达国家' ),
			'remote'        => array( 'remote', 'remote_countries', '偏远', '偏远国家', '偏远地区', '偏远附加' ),
			'weight_min'    => array( 'weight_min', 'min_weight', '重量下限', '起始重量', '起始重', '最小重量', '下限', '重量起' ),
			'weight_max'    => array( 'weight_max', 'max_weight', '重量上限', '结束重量', '截止重量', '最大重量', '上限', '重量止', '重量至' ),
			'weight_range'  => array( 'weight_range', 'weight', '重量段', '重量区间', '重量', '计费重', '区间' ),
			'unit_price'    => array( 'unit_price', 'price', '单价', '价格', '运费单价', '每公斤', '元/kg', '每千克', '运费' ),
			'base_fee'      => array( 'base_fee', '挂号费', '挂号', '首重费', '票费', '单票费' ),
			'operation_fee' => array( 'operation_fee', '操作费', '手续费', '处理费', '操作' ),
			'min_charge'    => array( 'min_charge', '最低消费', '最低收费', '最低一票', '最低运费', '起步价', '最低' ),
		);
	}

	/**
	 * 读取上传文件内容并转成 UTF-8。
	 *
	 * @param string $path 临时文件路径。
	 * @return string
	 */
	public static function read_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return '';
		}

		return self::to_utf8( $content );
	}

	/**
	 * 编码转换：非 UTF-8 时按 GBK 处理。
	 *
	 * @param string $content 原始内容。
	 * @return string
	 */
	public static function to_utf8( $content ) {
		$content = (string) $content;

		// 去掉 UTF-8 BOM。
		if ( 0 === strpos( $content, "\xEF\xBB\xBF" ) ) {
			$content = substr( $content, 3 );
		}

		if ( '' === $content ) {
			return '';
		}

		if ( self::is_utf8( $content ) ) {
			return $content;
		}

		if ( function_exists( 'mb_convert_encoding' ) ) {
			$converted = @mb_convert_encoding( $content, 'UTF-8', 'GB18030' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_string( $converted ) && '' !== $converted ) {
				return $converted;
			}
		}

		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'GB18030', 'UTF-8//IGNORE', $content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_string( $converted ) && '' !== $converted ) {
				return $converted;
			}
		}

		return $content;
	}

	/**
	 * 判断字符串是否为合法 UTF-8。
	 *
	 * @param string $content 内容。
	 * @return bool
	 */
	public static function is_utf8( $content ) {
		if ( function_exists( 'mb_check_encoding' ) ) {
			return mb_check_encoding( $content, 'UTF-8' );
		}

		return (bool) preg_match( '//u', $content );
	}

	/**
	 * 子串查找（mbstring 不可用时退回 strpos）。
	 *
	 * 对 UTF-8 文本，strpos 在字节层面查找同样能命中，只是偏移量不可用于截取。
	 *
	 * @param string $haystack 被查找的文本。
	 * @param string $needle   要找的片段。
	 * @return int|false
	 */
	public static function find_text( $haystack, $needle ) {
		if ( function_exists( 'mb_strpos' ) ) {
			return mb_strpos( $haystack, $needle, 0, 'UTF-8' );
		}

		return strpos( $haystack, $needle );
	}

	/**
	 * 猜分隔符。
	 *
	 * @param string $first_line 第一行。
	 * @return string
	 */
	public static function detect_delimiter( $first_line ) {
		$candidates = array(
			','  => substr_count( $first_line, ',' ),
			"\t" => substr_count( $first_line, "\t" ),
			';'  => substr_count( $first_line, ';' ),
			'|'  => substr_count( $first_line, '|' ),
		);

		arsort( $candidates );
		$best = key( $candidates );

		return $candidates[ $best ] > 0 ? $best : ',';
	}

	/**
	 * 按分隔符切分一行 CSV。
	 *
	 * 不用 str_getcsv()：它在部分 PHP 版本上处理多字节字符会错位
	 * （中文表头会被错误合并）。这里按字节扫描，分隔符和引号都是 ASCII，
	 * UTF-8 的续字节不会与之冲突。
	 *
	 * 注意：不支持字段内部换行（货代报价单里基本不会出现）。
	 *
	 * @param string $line      行内容。
	 * @param string $delimiter 分隔符。
	 * @return array
	 */
	public static function split_line( $line, $delimiter ) {
		$fields    = array();
		$current   = '';
		$in_quotes = false;
		$length    = strlen( $line );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $line[ $i ];

			if ( $in_quotes ) {
				if ( '"' === $char ) {
					if ( $i + 1 < $length && '"' === $line[ $i + 1 ] ) {
						$current .= '"';
						++$i;
					} else {
						$in_quotes = false;
					}
				} else {
					$current .= $char;
				}
				continue;
			}

			if ( '"' === $char && '' === trim( $current ) ) {
				$current   = '';
				$in_quotes = true;
				continue;
			}

			if ( $char === $delimiter ) {
				$fields[] = $current;
				$current  = '';
				continue;
			}

			$current .= $char;
		}

		$fields[] = $current;

		return $fields;
	}

	/**
	 * 解析 CSV 文本。
	 *
	 * @param string $content CSV 内容。
	 * @return array {
	 *     @type array $rows     标准化后的行
	 *     @type array $zones    分区映射
	 *     @type array $errors   致命错误
	 *     @type array $warnings 提示
	 *     @type array $header   识别出的表头
	 * }
	 */
	public static function parse( $content ) {
		$content = self::to_utf8( $content );
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );

		$result = array(
			'rows'     => array(),
			'zones'    => array(),
			'errors'   => array(),
			'warnings' => array(),
			'header'   => array(),
		);

		if ( '' === trim( $content ) ) {
			$result['errors'][] = '文件是空的。';
			return $result;
		}

		$lines = explode( "\n", $content );
		$lines = array_values( array_filter( $lines, 'strlen' ) );

		if ( empty( $lines ) ) {
			$result['errors'][] = '文件是空的。';
			return $result;
		}

		$delimiter = self::detect_delimiter( $lines[0] );

		$header = self::split_line( array_shift( $lines ), $delimiter );
		$header = array_map( 'trim', $header );
		$map    = self::map_header( $header );

		$result['header'] = $map;

		if ( ! isset( $map['zone'] ) ) {
			$result['errors'][] = '没有找到「分区」列，请确认表头里有 分区 / zone 这样的列名。';
			return $result;
		}

		if ( ! isset( $map['unit_price'] ) ) {
			$result['errors'][] = '没有找到「单价」列，请确认表头里有 单价 / price 这样的列名。';
			return $result;
		}

		if ( ! isset( $map['weight_range'] ) && ( ! isset( $map['weight_min'] ) || ! isset( $map['weight_max'] ) ) ) {
			$result['errors'][] = '没有找到重量段：需要「重量段」一列（0.1-0.2），或「重量下限」「重量上限」两列。';
			return $result;
		}

		$current_zone = '';
		$line_number  = 1;

		foreach ( $lines as $line ) {
			++$line_number;

			if ( '' === trim( $line ) ) {
				continue;
			}

			$cells = self::split_line( $line, $delimiter );
			if ( '' === trim( implode( '', (array) $cells ) ) ) {
				continue;
			}

			$zone_no = self::cell( $cells, $map, 'zone' );
			$zone_no = trim( (string) $zone_no );

			// 分区列留空时，沿用上一行的分区（很多报价单是合并单元格导出的）。
			if ( '' !== $zone_no ) {
				$current_zone = $zone_no;
			} elseif ( '' !== $current_zone ) {
				$zone_no = $current_zone;
			} else {
				$result['warnings'][] = sprintf( '第 %d 行没有分区号，已跳过。', $line_number );
				continue;
			}

			$range = self::parse_weight_range( $cells, $map );

			if ( null === $range ) {
				$result['warnings'][] = sprintf( '第 %d 行重量段无法识别，已跳过。', $line_number );
				continue;
			}

			$unit_price = self::parse_number( self::cell( $cells, $map, 'unit_price' ) );
			if ( null === $unit_price ) {
				$result['warnings'][] = sprintf( '第 %d 行单价为空或不是数字，已跳过。', $line_number );
				continue;
			}

			$row = array(
				'zone_no'       => $zone_no,
				'weight_min'    => $range[0],
				'weight_max'    => $range[1],
				'unit_price'    => $unit_price,
				'base_fee'      => (float) self::parse_number( self::cell( $cells, $map, 'base_fee' ) ),
				'operation_fee' => (float) self::parse_number( self::cell( $cells, $map, 'operation_fee' ) ),
				'min_charge'    => (float) self::parse_number( self::cell( $cells, $map, 'min_charge' ) ),
				'line'          => $line_number,
			);

			if ( $row['weight_max'] < $row['weight_min'] ) {
				$result['warnings'][] = sprintf( '第 %d 行重量上下限颠倒，已自动交换。', $line_number );
				$tmp               = $row['weight_min'];
				$row['weight_min'] = $row['weight_max'];
				$row['weight_max'] = $tmp;
			}

			$result['rows'][] = $row;

			if ( ! isset( $result['zones'][ $zone_no ] ) ) {
				$result['zones'][ $zone_no ] = array(
					'zone_no'   => $zone_no,
					'name'      => '',
					'countries' => '',
					'remote'    => '',
				);
			}

			$zone_name = self::cell( $cells, $map, 'name' );
			if ( '' !== trim( (string) $zone_name ) && '' === $result['zones'][ $zone_no ]['name'] ) {
				$result['zones'][ $zone_no ]['name'] = trim( (string) $zone_name );
			}

			$countries = self::cell( $cells, $map, 'countries' );
			if ( '' !== trim( (string) $countries ) && '' === $result['zones'][ $zone_no ]['countries'] ) {
				$result['zones'][ $zone_no ]['countries'] = self::normalize_countries( $countries );
			}

			$remote = self::cell( $cells, $map, 'remote' );
			if ( '' !== trim( (string) $remote ) && '' === $result['zones'][ $zone_no ]['remote'] ) {
				$result['zones'][ $zone_no ]['remote'] = self::normalize_countries( $remote );
			}
		}

		if ( empty( $result['rows'] ) ) {
			$result['errors'][] = '没有解析出任何有效报价行。';
			return $result;
		}

		// 分区没有国家时给出明确提示，否则线上永远匹配不到。
		foreach ( $result['zones'] as $zone_no => $zone ) {
			if ( '' === $zone['countries'] ) {
				$result['warnings'][] = sprintf( '分区「%s」没有填国家代码，导入后需要手动补上，否则前台算不出价。', $zone_no );
			}
		}

		$result['warnings'] = array_merge( $result['warnings'], self::detect_overlaps( $result['rows'] ) );

		return $result;
	}

	/**
	 * 识别表头 -> 列下标。
	 *
	 * @param array $header 表头。
	 * @return array
	 */
	public static function map_header( $header ) {
		$aliases = self::header_aliases();
		$map     = array();
		$normal  = array();

		foreach ( (array) $header as $index => $raw ) {
			$normal[ $index ] = self::normalize_header( $raw );
		}

		// 第一轮：完全匹配。
		foreach ( $normal as $index => $value ) {
			foreach ( $aliases as $field => $names ) {
				if ( isset( $map[ $field ] ) ) {
					continue;
				}
				if ( in_array( $value, $names, true ) ) {
					$map[ $field ] = $index;
					break;
				}
			}
		}

		// 第二轮：包含匹配（例如「单价(元/kg)」「重量段（kg）」）。
		foreach ( $normal as $index => $value ) {
			if ( '' === $value ) {
				continue;
			}
			foreach ( $aliases as $field => $names ) {
				if ( isset( $map[ $field ] ) ) {
					continue;
				}
				foreach ( $names as $name ) {
					if ( '' !== $name && false !== self::find_text( $value, $name ) ) {
						$map[ $field ] = $index;
						break 2;
					}
				}
			}
		}

		return $map;
	}

	/**
	 * 表头规范化：小写、去空格、全角括号转半角。
	 *
	 * @param string $raw 原始表头。
	 * @return string
	 */
	public static function normalize_header( $raw ) {
		$value = trim( (string) $raw );
		$value = str_replace( array( '（', '）', '［', '］', '【', '】' ), array( '(', ')', '[', ']', '[', ']' ), $value );
		$value = strtolower( $value );

		$clean = preg_replace( '/\s+/u', '', $value );
		if ( is_string( $clean ) ) {
			$value = $clean;
		}

		return (string) $value;
	}

	/**
	 * 取单元格。
	 *
	 * @param array  $cells 行数据。
	 * @param array  $map   表头映射。
	 * @param string $field 字段名。
	 * @return string
	 */
	public static function cell( $cells, $map, $field ) {
		if ( ! isset( $map[ $field ] ) ) {
			return '';
		}

		$index = $map[ $field ];

		return isset( $cells[ $index ] ) ? (string) $cells[ $index ] : '';
	}

	/**
	 * 解析重量段，返回 [min, max]。
	 *
	 * @param array $cells 行数据。
	 * @param array $map   表头映射。
	 * @return array|null
	 */
	public static function parse_weight_range( $cells, $map ) {
		$min = self::parse_number( self::cell( $cells, $map, 'weight_min' ) );
		$max = self::parse_number( self::cell( $cells, $map, 'weight_max' ) );

		if ( null !== $min && null !== $max ) {
			return array( $min, $max );
		}

		$raw = trim( self::cell( $cells, $map, 'weight_range' ) );
		if ( '' === $raw ) {
			return null;
		}

		// 0.1-0.2 / 0.1~0.2 / 0.1 至 0.2 / ≤0.1
		$raw = str_replace( array( '～', '~', '—', '–', '至', '到' ), '-', $raw );

		if ( preg_match( '/(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)/', $raw, $m ) ) {
			return array( (float) $m[1], (float) $m[2] );
		}

		if ( preg_match( '/(\d+(?:\.\d+)?)/', $raw, $m ) ) {
			$value = (float) $m[1];
			if ( false !== self::find_text( $raw, '≤' ) || false !== self::find_text( $raw, '<' ) || false !== self::find_text( $raw, '以下' ) || false !== self::find_text( $raw, '以内' ) ) {
				return array( 0.001, $value );
			}
			if ( false !== self::find_text( $raw, '以上' ) || false !== self::find_text( $raw, '+' ) ) {
				return array( $value, 999999 );
			}
			return array( $value, $value );
		}

		return null;
	}

	/**
	 * 解析数字，容忍货币符号、千分位、单位。
	 *
	 * @param string $raw 原始值。
	 * @return float|null
	 */
	public static function parse_number( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}

		$raw = str_replace( array( '，', ',' ), '', $raw );
		$raw = preg_replace( '/[^0-9.\-]/', '', $raw );

		if ( ! is_string( $raw ) || '' === $raw || '-' === $raw || '.' === $raw ) {
			return null;
		}

		return (float) $raw;
	}

	/**
	 * 国家代码规范化：支持 "US,CA" / "US CA" 混写。
	 *
	 * @param string $raw 原始值。
	 * @return string
	 */
	public static function normalize_countries( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}

		$parts = preg_split( '/[\s,;|、\/]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			$parts = preg_split( '/[\s,;|、\/]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		}

		$out = array();

		foreach ( (array) $parts as $part ) {
			$part = strtoupper( trim( $part ) );
			if ( '' === $part ) {
				continue;
			}
			// 允许 * 作为兜底分区。
			if ( '*' === $part ) {
				$out[] = '*';
				continue;
			}
			// 只保留两位国家码；冗长的中文国家名留给后台手动改。
			if ( preg_match( '/^[A-Z]{2}$/', $part ) ) {
				$out[] = $part;
			}
		}

		return implode( ',', array_values( array_unique( $out ) ) );
	}

	/**
	 * 检查同一分区内的重量段是否有重叠。
	 *
	 * @param array $rows 行数据。
	 * @return array
	 */
	public static function detect_overlaps( $rows ) {
		$warnings = array();
		$grouped  = array();

		foreach ( $rows as $row ) {
			$grouped[ $row['zone_no'] ][] = $row;
		}

		foreach ( $grouped as $zone_no => $zone_rows ) {
			usort(
				$zone_rows,
				function ( $a, $b ) {
					return $a['weight_min'] <=> $b['weight_min'];
				}
			);

			$previous = null;
			foreach ( $zone_rows as $row ) {
				if ( null !== $previous && $row['weight_min'] < $previous['weight_max'] - 0.0000001 ) {
					$warnings[] = sprintf(
						'分区「%1$s」第 %2$d 行与第 %3$d 行的重量段有重叠（%4$s-%5$s 与 %6$s-%7$s），请确认报价单。',
						$zone_no,
						$previous['line'],
						$row['line'],
						CBSR_Engine::trim_decimal( $previous['weight_min'] ),
						CBSR_Engine::trim_decimal( $previous['weight_max'] ),
						CBSR_Engine::trim_decimal( $row['weight_min'] ),
						CBSR_Engine::trim_decimal( $row['weight_max'] )
					);
				}
				$previous = $row;
			}
		}

		return $warnings;
	}

	/**
	 * 把解析结果写入数据库。
	 *
	 * @param int   $carrier_id 线路 ID。
	 * @param array $parsed     parse() 的返回值。
	 * @return array 统计结果。
	 */
	public static function commit( $carrier_id, $parsed ) {
		global $wpdb;

		$carrier_id = (int) $carrier_id;
		$zones      = isset( $parsed['zones'] ) ? (array) $parsed['zones'] : array();
		$rows       = isset( $parsed['rows'] ) ? (array) $parsed['rows'] : array();

		$existing = array();
		foreach ( CBSR_DB::get_zones( $carrier_id ) as $zone ) {
			$existing[ (string) $zone['zone_no'] ] = $zone;
		}

		$grouped = array();
		foreach ( $rows as $row ) {
			$grouped[ $row['zone_no'] ][] = $row;
		}

		$zone_created = 0;
		$zone_updated = 0;
		$rate_count   = 0;
		$order        = 0;

		foreach ( $zones as $zone_no => $zone ) {
			$zone_no = (string) $zone_no;
			$order  += 10;

			if ( isset( $existing[ $zone_no ] ) ) {
				++$zone_updated;
				$wpdb->update(
					CBSR_DB::zones_table(),
					array(
						'name'         => '' !== $zone['name'] ? $zone['name'] : $existing[ $zone_no ]['name'],
						'countries'    => '' !== $zone['countries'] ? $zone['countries'] : $existing[ $zone_no ]['countries'],
						'pricing_mode' => CBSR_Engine::MODE_BRACKET,
						'sort_order'   => $order,
					),
					array( 'id' => (int) $existing[ $zone_no ]['id'] )
				);
			} else {
				++$zone_created;
				CBSR_DB::insert_zone(
					$carrier_id,
					array(
						'zone_no'      => $zone_no,
						'name'         => '' !== $zone['name'] ? $zone['name'] : $zone_no,
						'countries'    => $zone['countries'],
						'pricing_mode' => CBSR_Engine::MODE_BRACKET,
					),
					$order
				);
			}

			$zone_rows = isset( $grouped[ $zone_no ] ) ? $grouped[ $zone_no ] : array();
			CBSR_DB::replace_rates( $carrier_id, $zone_no, $zone_rows );
			$rate_count += count( $zone_rows );
		}

		return array(
			'zones_created' => $zone_created,
			'zones_updated' => $zone_updated,
			'rates'         => $rate_count,
		);
	}
}
