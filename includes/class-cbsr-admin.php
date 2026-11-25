<?php
/**
 * 后台界面与表单处理。
 *
 * @package CrossBorder_Shipping_Rates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CBSR_Admin
 */
class CBSR_Admin {

	const CAPABILITY = 'manage_woocommerce';

	/**
	 * 注册钩子。
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		$actions = array( 'save_carrier', 'delete_carrier', 'save_zones', 'save_rates', 'import_preview', 'import_commit' );
		foreach ( $actions as $action ) {
			add_action( 'admin_post_cbsr_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}

		add_filter(
			'plugin_action_links_' . plugin_basename( CBSR_FILE ),
			array( __CLASS__, 'plugin_action_links' )
		);
	}

	/**
	 * 插件列表页的快捷入口。
	 *
	 * @param array $links 链接。
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=cbsr-carriers' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( '物流线路', 'crossborder-shipping' ) . '</a>' );

		return $links;
	}

	/**
	 * 菜单。
	 */
	public static function register_menu() {
		add_menu_page(
			__( '跨境运费', 'crossborder-shipping' ),
			__( '跨境运费', 'crossborder-shipping' ),
			self::CAPABILITY,
			'cbsr-carriers',
			array( __CLASS__, 'render_carriers_page' ),
			'dashicons-airplane',
			56
		);

		add_submenu_page(
			'cbsr-carriers',
			__( '物流线路', 'crossborder-shipping' ),
			__( '物流线路', 'crossborder-shipping' ),
			self::CAPABILITY,
			'cbsr-carriers',
			array( __CLASS__, 'render_carriers_page' )
		);

		add_submenu_page(
			'cbsr-carriers',
			__( '运费试算', 'crossborder-shipping' ),
			__( '运费试算', 'crossborder-shipping' ),
			self::CAPABILITY,
			'cbsr-tools',
			array( __CLASS__, 'render_tools_page' )
		);

		// 编辑页不显示在菜单里。
		add_submenu_page(
			null,
			__( '编辑线路', 'crossborder-shipping' ),
			__( '编辑线路', 'crossborder-shipping' ),
			self::CAPABILITY,
			'cbsr-carrier',
			array( __CLASS__, 'render_carrier_page' )
		);
	}

	/**
	 * 资源。
	 *
	 * @param string $hook 当前页面。
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'cbsr' ) ) {
			return;
		}

		wp_enqueue_style( 'cbsr-admin', CBSR_URL . 'assets/css/admin.css', array(), CBSR_VERSION );
		wp_enqueue_script( 'cbsr-admin', CBSR_URL . 'assets/js/admin.js', array(), CBSR_VERSION, true );
	}

	/* ---------------------------------------------------------------------
	 * 页面
	 * ------------------------------------------------------------------ */

	/**
	 * 线路列表页。
	 */
	public static function render_carriers_page() {
		self::guard();

		$carriers = CBSR_DB::get_carriers();

		foreach ( $carriers as &$carrier ) {
			$carrier['stats'] = CBSR_DB::get_stats( $carrier['id'] );
		}
		unset( $carrier );

		include CBSR_PATH . 'admin/views/carriers.php';
	}

	/**
	 * 线路编辑页。
	 */
	public static function render_carrier_page() {
		self::guard();

		$carrier_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$carrier    = $carrier_id ? CBSR_DB::get_carrier( $carrier_id ) : null;

		if ( $carrier_id && ! $carrier ) {
			wp_die( esc_html__( '线路不存在。', 'crossborder-shipping' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'basic'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = in_array( $tab, array( 'basic', 'zones', 'rates', 'import' ), true ) ? $tab : 'basic';

		$zones = $carrier_id ? CBSR_DB::get_zones( $carrier_id ) : array();
		$rates = $carrier_id ? CBSR_DB::get_rates( $carrier_id ) : array();

		$zone_filter = isset( $_GET['zone'] ) ? sanitize_text_field( wp_unslash( $_GET['zone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$preview = get_transient( self::preview_key() );

		include CBSR_PATH . 'admin/views/carrier-edit.php';
	}

	/**
	 * 试算页。
	 */
	public static function render_tools_page() {
		self::guard();

		$carriers = CBSR_DB::get_carriers();
		$result   = null;
		$input    = array(
			'carrier_id' => isset( $_POST['carrier_id'] ) ? absint( $_POST['carrier_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'country'    => isset( $_POST['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : 'US', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'weight'     => isset( $_POST['weight'] ) ? (float) $_POST['weight'] : 0.5, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'length'     => isset( $_POST['length'] ) ? (float) $_POST['length'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'width'      => isset( $_POST['width'] ) ? (float) $_POST['width'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'height'     => isset( $_POST['height'] ) ? (float) $_POST['height'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( isset( $_POST['cbsr_quote'] ) && check_admin_referer( 'cbsr_tools' ) ) {
			$carrier_id = $input['carrier_id'] ? $input['carrier_id'] : CBSR_DB::get_default_carrier_id();
			$carrier    = CBSR_DB::get_carrier( $carrier_id );

			if ( $carrier ) {
				$result = CBSR_Engine::quote(
					$carrier,
					CBSR_DB::get_zones( $carrier_id ),
					CBSR_DB::get_rates( $carrier_id ),
					array(
						'weight'     => $input['weight'],
						'dimensions' => array(
							'length' => $input['length'],
							'width'  => $input['width'],
							'height' => $input['height'],
						),
						'country'    => $input['country'],
					)
				);
			}
		}

		include CBSR_PATH . 'admin/views/tools.php';
	}

	/**
	 * 权限校验。
	 */
	protected static function guard() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( '你没有权限访问这个页面。', 'crossborder-shipping' ) );
		}
	}

	/**
	 * 暂存预览的 transient key。
	 *
	 * @return string
	 */
	protected static function preview_key() {
		return 'cbsr_import_preview_' . get_current_user_id();
	}

	/**
	 * 回跳地址。
	 *
	 * @param int    $carrier_id 线路 ID。
	 * @param string $tab        标签。
	 * @param array  $args       附加参数。
	 * @return string
	 */
	protected static function redirect_url( $carrier_id, $tab, $args = array() ) {
		$args = array_merge(
			array(
				'page' => 'cbsr-carrier',
				'id'   => (int) $carrier_id,
				'tab'  => $tab,
			),
			$args
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * 跳转并退出。
	 *
	 * @param string $url 地址。
	 */
	protected static function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * 表单处理
	 * ------------------------------------------------------------------ */

	/**
	 * 保存线路基本信息。
	 */
	public static function handle_save_carrier() {
		self::guard();
		check_admin_referer( 'cbsr_save_carrier' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$settings = array(
			'fuel_rate'    => isset( $_POST['fuel_rate'] ) ? (float) $_POST['fuel_rate'] : 0,
			'remote_fee'   => isset( $_POST['remote_fee'] ) ? (float) $_POST['remote_fee'] : 0,
			'handling_fee' => isset( $_POST['handling_fee'] ) ? (float) $_POST['handling_fee'] : 0,
		);

		$data = array(
			'id'                => $id,
			'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'code'              => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
			'currency'          => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : 'CNY',
			'exchange_rate'     => isset( $_POST['exchange_rate'] ) ? (float) $_POST['exchange_rate'] : 1,
			'dim_factor'        => isset( $_POST['dim_factor'] ) ? (float) $_POST['dim_factor'] : 6000,
			'weight_round_step' => isset( $_POST['weight_round_step'] ) ? (float) $_POST['weight_round_step'] : 0.01,
			'weight_round_mode' => isset( $_POST['weight_round_mode'] ) ? sanitize_key( wp_unslash( $_POST['weight_round_mode'] ) ) : 'up',
			'status'            => isset( $_POST['status'] ) ? 1 : 0,
			'is_default'        => isset( $_POST['is_default'] ) ? 1 : 0,
			'sort_order'        => isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order'] : 0,
			'settings'          => $settings,
		);

		if ( '' === $data['name'] ) {
			$data['name'] = __( '未命名线路', 'crossborder-shipping' );
		}

		$new_id = CBSR_DB::save_carrier( $data );

		self::redirect(
			self::redirect_url( $new_id, 'basic', array( 'cbsr_notice' => $id ? 'updated' : 'created' ) )
		);
	}

	/**
	 * 删除线路。
	 */
	public static function handle_delete_carrier() {
		self::guard();
		check_admin_referer( 'cbsr_delete_carrier' );

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $id ) {
			CBSR_DB::delete_carrier( $id );
		}

		self::redirect( add_query_arg( array( 'page' => 'cbsr-carriers', 'cbsr_notice' => 'deleted' ), admin_url( 'admin.php' ) ) );
	}

	/**
	 * 保存分区。
	 */
	public static function handle_save_zones() {
		self::guard();
		check_admin_referer( 'cbsr_save_zones' );

		$carrier_id = isset( $_POST['carrier_id'] ) ? absint( $_POST['carrier_id'] ) : 0;
		if ( ! $carrier_id ) {
			self::redirect( add_query_arg( array( 'page' => 'cbsr-carriers' ), admin_url( 'admin.php' ) ) );
		}

		$raw   = isset( $_POST['zones'] ) ? (array) wp_unslash( $_POST['zones'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$zones = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$zone_no = isset( $row['zone_no'] ) ? sanitize_text_field( $row['zone_no'] ) : '';
			if ( '' === $zone_no ) {
				continue;
			}

			$zones[] = array(
				'zone_no'          => $zone_no,
				'name'             => isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '',
				'countries'        => isset( $row['countries'] ) ? strtoupper( sanitize_text_field( $row['countries'] ) ) : '',
				'remote_countries' => isset( $row['remote_countries'] ) ? strtoupper( sanitize_text_field( $row['remote_countries'] ) ) : '',
				'pricing_mode'     => ( isset( $row['pricing_mode'] ) && 'first_add' === $row['pricing_mode'] ) ? 'first_add' : 'bracket',
				'first_weight'     => isset( $row['first_weight'] ) ? (float) $row['first_weight'] : 0,
				'first_price'      => isset( $row['first_price'] ) ? (float) $row['first_price'] : 0,
				'step_weight'      => isset( $row['step_weight'] ) ? (float) $row['step_weight'] : 0,
				'step_price'       => isset( $row['step_price'] ) ? (float) $row['step_price'] : 0,
			);
		}

		CBSR_DB::replace_zones( $carrier_id, $zones );

		self::redirect( self::redirect_url( $carrier_id, 'zones', array( 'cbsr_notice' => 'saved' ) ) );
	}

	/**
	 * 保存某分区的重量段。
	 */
	public static function handle_save_rates() {
		self::guard();
		check_admin_referer( 'cbsr_save_rates' );

		$carrier_id = isset( $_POST['carrier_id'] ) ? absint( $_POST['carrier_id'] ) : 0;
		$zone_no    = isset( $_POST['zone_no'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_no'] ) ) : '';

		if ( ! $carrier_id || '' === $zone_no ) {
			self::redirect( add_query_arg( array( 'page' => 'cbsr-carriers' ), admin_url( 'admin.php' ) ) );
		}

		$raw   = isset( $_POST['rates'] ) ? (array) wp_unslash( $_POST['rates'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rates = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$max = isset( $row['weight_max'] ) ? (float) $row['weight_max'] : 0;
			if ( $max <= 0 ) {
				continue;
			}

			$rates[] = array(
				'weight_min'    => isset( $row['weight_min'] ) ? (float) $row['weight_min'] : 0,
				'weight_max'    => $max,
				'unit_price'    => isset( $row['unit_price'] ) ? (float) $row['unit_price'] : 0,
				'base_fee'      => isset( $row['base_fee'] ) ? (float) $row['base_fee'] : 0,
				'operation_fee' => isset( $row['operation_fee'] ) ? (float) $row['operation_fee'] : 0,
				'min_charge'    => isset( $row['min_charge'] ) ? (float) $row['min_charge'] : 0,
			);
		}

		CBSR_DB::replace_rates( $carrier_id, $zone_no, $rates );

		self::redirect(
			self::redirect_url(
				$carrier_id,
				'rates',
				array(
					'zone'        => rawurlencode( $zone_no ),
					'cbsr_notice' => 'saved',
				)
			)
		);
	}

	/**
	 * 导入：解析 + 预览。
	 */
	public static function handle_import_preview() {
		self::guard();
		check_admin_referer( 'cbsr_import' );

		$carrier_id = isset( $_POST['carrier_id'] ) ? absint( $_POST['carrier_id'] ) : 0;
		$content    = '';

		if ( ! empty( $_FILES['cbsr_csv']['tmp_name'] ) && is_uploaded_file( $_FILES['cbsr_csv']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$content = CBSR_Import::read_file( $_FILES['cbsr_csv']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		if ( '' === trim( $content ) && isset( $_POST['cbsr_csv_text'] ) ) {
			$content = CBSR_Import::to_utf8( wp_unslash( $_POST['cbsr_csv_text'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$parsed = CBSR_Import::parse( $content );

		set_transient( self::preview_key(), $parsed, HOUR_IN_SECONDS );

		self::redirect( self::redirect_url( $carrier_id, 'import', array( 'preview' => 1 ) ) );
	}

	/**
	 * 导入：确认写入。
	 */
	public static function handle_import_commit() {
		self::guard();
		check_admin_referer( 'cbsr_import_commit' );

		$carrier_id = isset( $_POST['carrier_id'] ) ? absint( $_POST['carrier_id'] ) : 0;
		$parsed     = get_transient( self::preview_key() );

		if ( ! is_array( $parsed ) || empty( $parsed['rows'] ) || ! $carrier_id ) {
			self::redirect( self::redirect_url( $carrier_id, 'import', array( 'cbsr_notice' => 'import_failed' ) ) );
		}

		$stats = CBSR_Import::commit( $carrier_id, $parsed );

		delete_transient( self::preview_key() );

		self::redirect(
			self::redirect_url(
				$carrier_id,
				'zones',
				array(
					'cbsr_notice' => 'imported',
					'count_zone'  => (int) $stats['zones_created'] + (int) $stats['zones_updated'],
					'count_rate'  => (int) $stats['rates'],
				)
			)
		);
	}
}

/**
 * 渲染操作结果提示。
 */
function cbsr_render_admin_notice() {
	if ( ! isset( $_GET['cbsr_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$notice = sanitize_key( wp_unslash( $_GET['cbsr_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$messages = array(
		'created'       => array( 'success', __( '线路已创建。下一步：导入报价单或手动填分区。', 'crossborder-shipping' ) ),
		'updated'       => array( 'success', __( '线路设置已保存。', 'crossborder-shipping' ) ),
		'deleted'       => array( 'success', __( '线路已删除。', 'crossborder-shipping' ) ),
		'saved'         => array( 'success', __( '已保存。', 'crossborder-shipping' ) ),
		'import_failed' => array( 'error', __( '导入失败：暂存的解析结果已过期，请重新上传报价单。', 'crossborder-shipping' ) ),
	);

	if ( 'imported' === $notice ) {
		$zone_count = isset( $_GET['count_zone'] ) ? absint( $_GET['count_zone'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rate_count = isset( $_GET['count_rate'] ) ? absint( $_GET['count_rate'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: zone count, 2: rate count */
					__( '导入完成：%1$d 个分区、%2$d 条重量段报价。请到「分区」页确认国家代码是否正确。', 'crossborder-shipping' ),
					$zone_count,
					$rate_count
				)
			)
		);
		return;
	}

	if ( ! isset( $messages[ $notice ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $messages[ $notice ][0] ),
		esc_html( $messages[ $notice ][1] )
	);
}
