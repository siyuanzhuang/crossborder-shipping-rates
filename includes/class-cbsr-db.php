<?php
/**
 * 数据层：线路 / 分区 / 重量段报价表。
 *
 * @package CrossBorder_Shipping_Rates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CBSR_DB
 */
class CBSR_DB {

	/**
	 * 线路表名。
	 *
	 * @return string
	 */
	public static function carriers_table() {
		global $wpdb;
		return $wpdb->prefix . 'cbsr_carriers';
	}

	/**
	 * 分区表名。
	 *
	 * @return string
	 */
	public static function zones_table() {
		global $wpdb;
		return $wpdb->prefix . 'cbsr_zones';
	}

	/**
	 * 重量段表名。
	 *
	 * @return string
	 */
	public static function rates_table() {
		global $wpdb;
		return $wpdb->prefix . 'cbsr_rates';
	}

	/**
	 * 建表 / 升级表结构。
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$carriers        = self::carriers_table();
		$zones           = self::zones_table();
		$rates           = self::rates_table();

		$sql = array();

		$sql[] = "CREATE TABLE {$carriers} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL DEFAULT '',
  code varchar(60) NOT NULL DEFAULT '',
  currency varchar(3) NOT NULL DEFAULT 'CNY',
  exchange_rate decimal(14,6) NOT NULL DEFAULT 1.000000,
  dim_factor decimal(10,2) NOT NULL DEFAULT 6000.00,
  weight_round_step decimal(8,3) NOT NULL DEFAULT 0.010,
  weight_round_mode varchar(10) NOT NULL DEFAULT 'up',
  status tinyint(1) NOT NULL DEFAULT 1,
  is_default tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT 0,
  settings longtext NULL,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY code (code),
  KEY status (status)
) {$charset_collate};";

		$sql[] = "CREATE TABLE {$zones} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  carrier_id bigint(20) unsigned NOT NULL DEFAULT 0,
  zone_no varchar(40) NOT NULL DEFAULT '',
  name varchar(120) NOT NULL DEFAULT '',
  countries text NULL,
  remote_countries text NULL,
  pricing_mode varchar(20) NOT NULL DEFAULT 'bracket',
  first_weight decimal(10,3) NOT NULL DEFAULT 0.000,
  first_price decimal(12,4) NOT NULL DEFAULT 0.0000,
  step_weight decimal(10,3) NOT NULL DEFAULT 0.000,
  step_price decimal(12,4) NOT NULL DEFAULT 0.0000,
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY carrier_id (carrier_id),
  KEY zone_no (zone_no)
) {$charset_collate};";

		$sql[] = "CREATE TABLE {$rates} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  carrier_id bigint(20) unsigned NOT NULL DEFAULT 0,
  zone_no varchar(40) NOT NULL DEFAULT '',
  weight_min decimal(10,3) NOT NULL DEFAULT 0.000,
  weight_max decimal(10,3) NOT NULL DEFAULT 0.000,
  unit_price decimal(12,4) NOT NULL DEFAULT 0.0000,
  base_fee decimal(12,4) NOT NULL DEFAULT 0.0000,
  operation_fee decimal(12,4) NOT NULL DEFAULT 0.0000,
  min_charge decimal(12,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY  (id),
  KEY carrier_zone (carrier_id,zone_no),
  KEY weight_max (weight_max)
) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/* ---------------------------------------------------------------------
	 * 线路
	 * ------------------------------------------------------------------ */

	/**
	 * 线路列表。
	 *
	 * @param bool $only_enabled 只取启用的。
	 * @return array
	 */
	public static function get_carriers( $only_enabled = false ) {
		global $wpdb;
		$table = self::carriers_table();

		$where = $only_enabled ? 'WHERE status = 1' : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, id ASC", ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * 单条线路。
	 *
	 * @param int $id 线路 ID。
	 * @return array|null
	 */
	public static function get_carrier( $id ) {
		global $wpdb;
		$table = self::carriers_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * 新建或更新线路。
	 *
	 * @param array $data 线路数据。
	 * @return int 线路 ID。
	 */
	public static function save_carrier( $data ) {
		global $wpdb;
		$table = self::carriers_table();
		$now   = current_time( 'mysql' );
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$settings = isset( $data['settings'] ) ? $data['settings'] : array();
		if ( is_array( $settings ) ) {
			$settings = wp_json_encode( $settings );
		}

		$row = array(
			'name'               => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'code'               => isset( $data['code'] ) ? sanitize_key( $data['code'] ) : '',
			'currency'           => isset( $data['currency'] ) ? strtoupper( sanitize_text_field( $data['currency'] ) ) : 'CNY',
			'exchange_rate'      => isset( $data['exchange_rate'] ) ? (float) $data['exchange_rate'] : 1,
			'dim_factor'         => isset( $data['dim_factor'] ) ? (float) $data['dim_factor'] : 6000,
			'weight_round_step'  => isset( $data['weight_round_step'] ) ? (float) $data['weight_round_step'] : 0.01,
			'weight_round_mode'  => isset( $data['weight_round_mode'] ) ? sanitize_key( $data['weight_round_mode'] ) : 'up',
			'status'             => ! empty( $data['status'] ) ? 1 : 0,
			'is_default'         => ! empty( $data['is_default'] ) ? 1 : 0,
			'sort_order'         => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'settings'           => (string) $settings,
			'updated_at'         => $now,
		);

		if ( $row['exchange_rate'] <= 0 ) {
			$row['exchange_rate'] = 1;
		}

		if ( $id > 0 ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) );
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( $table, $row );
			$id = (int) $wpdb->insert_id;
		}

		if ( $row['is_default'] ) {
			// 默认线路只能有一条。
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_default = 0 WHERE id <> %d", $id ) );
		}

		return $id;
	}

	/**
	 * 删除线路（连同分区与报价）。
	 *
	 * @param int $id 线路 ID。
	 */
	public static function delete_carrier( $id ) {
		global $wpdb;
		$id = (int) $id;

		$wpdb->delete( self::rates_table(), array( 'carrier_id' => $id ) );
		$wpdb->delete( self::zones_table(), array( 'carrier_id' => $id ) );
		$wpdb->delete( self::carriers_table(), array( 'id' => $id ) );
	}

	/**
	 * 取默认线路 ID。
	 *
	 * @return int
	 */
	public static function get_default_carrier_id() {
		global $wpdb;
		$table = self::carriers_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = $wpdb->get_var( "SELECT id FROM {$table} WHERE is_default = 1 AND status = 1 ORDER BY id ASC LIMIT 1" );

		if ( $id ) {
			return (int) $id;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = $wpdb->get_var( "SELECT id FROM {$table} WHERE status = 1 ORDER BY sort_order ASC, id ASC LIMIT 1" );

		return (int) $id;
	}

	/* ---------------------------------------------------------------------
	 * 分区
	 * ------------------------------------------------------------------ */

	/**
	 * 取某线路的分区。
	 *
	 * @param int $carrier_id 线路 ID。
	 * @return array
	 */
	public static function get_zones( $carrier_id ) {
		global $wpdb;
		$table = self::zones_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE carrier_id = %d ORDER BY sort_order ASC, id ASC", (int) $carrier_id ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * 保存分区（整体替换，导入时用）。
	 *
	 * @param int   $carrier_id 线路 ID。
	 * @param array $zones      分区数组。
	 */
	public static function replace_zones( $carrier_id, $zones ) {
		global $wpdb;
		$carrier_id = (int) $carrier_id;

		$wpdb->delete( self::zones_table(), array( 'carrier_id' => $carrier_id ) );

		$order = 0;
		foreach ( (array) $zones as $zone ) {
			$order += 10;
			self::insert_zone( $carrier_id, $zone, $order );
		}
	}

	/**
	 * 插入一个分区。
	 *
	 * @param int   $carrier_id 线路 ID。
	 * @param array $zone       分区数据。
	 * @param int   $order      排序。
	 * @return int
	 */
	public static function insert_zone( $carrier_id, $zone, $order = 0 ) {
		global $wpdb;

		$countries = is_array( $zone['countries'] ?? '' ) ? implode( ',', $zone['countries'] ) : (string) ( $zone['countries'] ?? '' );
		$remote    = is_array( $zone['remote_countries'] ?? '' ) ? implode( ',', $zone['remote_countries'] ) : (string) ( $zone['remote_countries'] ?? '' );

		$wpdb->insert(
			self::zones_table(),
			array(
				'carrier_id'       => (int) $carrier_id,
				'zone_no'          => (string) ( $zone['zone_no'] ?? '' ),
				'name'             => (string) ( $zone['name'] ?? '' ),
				'countries'        => strtoupper( trim( $countries ) ),
				'remote_countries' => strtoupper( trim( $remote ) ),
				'pricing_mode'     => (string) ( $zone['pricing_mode'] ?? 'bracket' ),
				'first_weight'     => (float) ( $zone['first_weight'] ?? 0 ),
				'first_price'      => (float) ( $zone['first_price'] ?? 0 ),
				'step_weight'      => (float) ( $zone['step_weight'] ?? 0 ),
				'step_price'       => (float) ( $zone['step_price'] ?? 0 ),
				'sort_order'       => (int) $order,
			)
		);

		return (int) $wpdb->insert_id;
	}

	/* ---------------------------------------------------------------------
	 * 重量段
	 * ------------------------------------------------------------------ */

	/**
	 * 取重量段。
	 *
	 * @param int    $carrier_id 线路 ID。
	 * @param string $zone_no    分区号，空则全部。
	 * @return array
	 */
	public static function get_rates( $carrier_id, $zone_no = '' ) {
		global $wpdb;
		$table = self::rates_table();

		if ( '' !== $zone_no ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE carrier_id = %d AND zone_no = %s ORDER BY weight_min ASC, weight_max ASC",
					(int) $carrier_id,
					(string) $zone_no
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE carrier_id = %d ORDER BY zone_no ASC, weight_min ASC",
					(int) $carrier_id
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * 批量写入重量段（先清空指定分区）。
	 *
	 * @param int    $carrier_id 线路 ID。
	 * @param string $zone_no    分区号。
	 * @param array  $rates      重量段列表。
	 */
	public static function replace_rates( $carrier_id, $zone_no, $rates ) {
		global $wpdb;
		$table = self::rates_table();

		$wpdb->delete(
			$table,
			array(
				'carrier_id' => (int) $carrier_id,
				'zone_no'    => (string) $zone_no,
			)
		);

		foreach ( (array) $rates as $rate ) {
			self::insert_rate( $carrier_id, $zone_no, $rate );
		}
	}

	/**
	 * 插入一条重量段。
	 *
	 * @param int    $carrier_id 线路 ID。
	 * @param string $zone_no    分区号。
	 * @param array  $rate       重量段。
	 */
	public static function insert_rate( $carrier_id, $zone_no, $rate ) {
		global $wpdb;

		$wpdb->insert(
			self::rates_table(),
			array(
				'carrier_id'    => (int) $carrier_id,
				'zone_no'       => (string) $zone_no,
				'weight_min'    => (float) ( $rate['weight_min'] ?? 0 ),
				'weight_max'    => (float) ( $rate['weight_max'] ?? 0 ),
				'unit_price'    => (float) ( $rate['unit_price'] ?? 0 ),
				'base_fee'      => (float) ( $rate['base_fee'] ?? 0 ),
				'operation_fee' => (float) ( $rate['operation_fee'] ?? 0 ),
				'min_charge'    => (float) ( $rate['min_charge'] ?? 0 ),
			)
		);
	}

	/**
	 * 删除某分区的所有重量段。
	 *
	 * @param int    $carrier_id 线路 ID。
	 * @param string $zone_no    分区号。
	 */
	public static function delete_rates_by_zone( $carrier_id, $zone_no ) {
		global $wpdb;
		$wpdb->delete(
			self::rates_table(),
			array(
				'carrier_id' => (int) $carrier_id,
				'zone_no'    => (string) $zone_no,
			)
		);
	}

	/**
	 * 统计信息，后台列表用。
	 *
	 * @param int $carrier_id 线路 ID。
	 * @return array
	 */
	public static function get_stats( $carrier_id ) {
		global $wpdb;
		$zones_table = self::zones_table();
		$rates_table = self::rates_table();
		$carrier_id  = (int) $carrier_id;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$zones = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$zones_table} WHERE carrier_id = %d", $carrier_id ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rates = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$rates_table} WHERE carrier_id = %d", $carrier_id ) );

		return array(
			'zones' => $zones,
			'rates' => $rates,
		);
	}
}
