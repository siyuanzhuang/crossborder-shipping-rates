<?php
/**
 * WooCommerce 配送方式：跨境物流运费。
 *
 * @package CrossBorder_Shipping_Rates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CBSR_Shipping_Method
 */
class CBSR_Shipping_Method extends WC_Shipping_Method {

	/**
	 * 构造函数。
	 *
	 * @param int $instance_id 实例 ID。
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'cbsr';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( '跨境物流运费', 'crossborder-shipping' );
		$this->method_description = __( '按导入的货代报价单，根据目的国分区、重量段、体积重与附加费自动计算跨境运费。', 'crossborder-shipping' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );

		$this->init();
	}

	/**
	 * 初始化设置。
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->title        = isset( $this->settings['title'] ) ? $this->settings['title'] : $this->method_title;
		$this->enabled      = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
		$this->tax_status   = isset( $this->settings['tax_status'] ) ? $this->settings['tax_status'] : 'taxable';

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * 设置字段。
	 */
	public function init_form_fields() {
		$carriers = cbsr_get_carrier_choices();

		$carrier_options = array( '0' => __( '— 使用默认线路 —', 'crossborder-shipping' ) );
		foreach ( $carriers as $id => $name ) {
			$carrier_options[ (string) $id ] = $name;
		}

		$this->instance_form_fields = array(
			'title'             => array(
				'title'       => __( '名称', 'crossborder-shipping' ),
				'type'        => 'text',
				'description' => __( '显示在结算页的配送方式名称，例如「云途专线（7-12 天）」。', 'crossborder-shipping' ),
				'default'     => __( '跨境物流', 'crossborder-shipping' ),
				'desc_tip'    => true,
			),
			'carrier_id'        => array(
				'title'       => __( '报价线路', 'crossborder-shipping' ),
				'type'        => 'select',
				'options'     => $carrier_options,
				'default'     => '0',
				'description' => __( '选择使用哪一条线路的报价表。', 'crossborder-shipping' ),
				'desc_tip'    => true,
			),
			'tax_status'        => array(
				'title'   => __( '税', 'crossborder-shipping' ),
				'type'    => 'select',
				'options' => array(
					'taxable' => __( '应税', 'crossborder-shipping' ),
					'none'    => __( '不计税', 'crossborder-shipping' ),
				),
				'default' => 'taxable',
			),
			'packaging_weight'  => array(
				'title'       => __( '包装重量（kg）', 'crossborder-shipping' ),
				'type'        => 'number',
				'default'     => '0',
				'custom_attributes' => array( 'step' => '0.001', 'min' => '0' ),
				'description' => __( '每票额外加上的包装材料重量。', 'crossborder-shipping' ),
				'desc_tip'    => true,
			),
			'dims_mode'         => array(
				'title'       => __( '体积重取数方式', 'crossborder-shipping' ),
				'type'        => 'select',
				'options'     => array(
					'max'  => __( '按单件最大尺寸（保守，偏重）', 'crossborder-shipping' ),
					'sum'  => __( '按各向尺寸相加（粗略）', 'crossborder-shipping' ),
					'none' => __( '不计算体积重', 'crossborder-shipping' ),
				),
				'default'     => 'max',
				'description' => __( 'WooCommerce 不提供真实装箱算法，这里给出三种近似方式；需要精确请在主题里用 cbsr_package_dimensions 过滤器接管。', 'crossborder-shipping' ),
				'desc_tip'    => true,
			),
			'carton_dims'       => array(
				'title'       => __( '固定箱规（cm）', 'crossborder-shipping' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => '30x20x10',
				'description' => __( '填写后忽略商品尺寸，统一按这个箱规计算体积重。留空则不使用。', 'crossborder-shipping' ),
				'desc_tip'    => true,
			),
			'free_over'         => array(
				'title'       => __( '满额免运费', 'crossborder-shipping' ),
				'type'        => 'number',
				'default'     => '0',
				'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
				'description' => __( '订单小计达到该金额时显示免运费，0 表示不启用。', 'crossborder-shipping' ),
				'desc_tip'    => true,
			),
			'show_breakdown'    => array(
				'title'       => __( '展示费用明细', 'crossborder-shipping' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'description' => __( '在结算页的配送方式下显示运费构成（体积重、挂号费、燃油等）。', 'crossborder-shipping' ),
				'desc_tip'    => true,
			),
			'fallback_label'    => array(
				'title'       => __( '算不出价时的处理', 'crossborder-shipping' ),
				'type'        => 'select',
				'options'     => array(
					'hide'   => __( '隐藏该配送方式（推荐）', 'crossborder-shipping' ),
					'notice' => __( '显示为「暂不支持」，禁止下单', 'crossborder-shipping' ),
				),
				'default'     => 'hide',
			),
		);
	}

	/**
	 * 计算运费。
	 *
	 * @param array $package 包裹。
	 */
	public function calculate_shipping( $package = array() ) {
		$carrier_id = isset( $this->settings['carrier_id'] ) ? (int) $this->settings['carrier_id'] : 0;

		if ( $carrier_id <= 0 ) {
			$carrier_id = CBSR_DB::get_default_carrier_id();
		}

		if ( $carrier_id <= 0 ) {
			return;
		}

		$carrier = CBSR_DB::get_carrier( $carrier_id );
		if ( ! $carrier || (int) $carrier['status'] !== 1 ) {
			return;
		}

		// 满额免运费。
		$free_over = isset( $this->settings['free_over'] ) ? (float) $this->settings['free_over'] : 0.0;
		if ( $free_over > 0 ) {
			$subtotal = isset( WC()->cart ) && WC()->cart ? (float) WC()->cart->get_displayed_subtotal() : 0.0;
			if ( $subtotal >= $free_over ) {
				$this->add_rate(
					array(
						'id'    => $this->get_rate_id(),
						'label' => $this->title . ' ' . __( '（免运费）', 'crossborder-shipping' ),
						'cost'  => 0,
					)
				);
				return;
			}
		}

		$package_data = $this->build_package_data( $package );
		$zones        = CBSR_DB::get_zones( $carrier_id );
		$rates        = CBSR_DB::get_rates( $carrier_id );

		$quote = CBSR_Engine::quote( $carrier, $zones, $rates, $package_data );

		if ( ! $quote['ok'] ) {
			$this->maybe_add_fallback_rate( $quote );
			return;
		}

		$label = $this->title;

		if ( isset( $this->settings['show_breakdown'] ) && 'yes' === $this->settings['show_breakdown'] ) {
			$label .= ' — ' . $this->format_breakdown( $quote );
		}

		$this->add_rate(
			array(
				'id'        => $this->get_rate_id(),
				'label'     => $label,
				'cost'      => $quote['total'],
				'meta_data' => array(
					'_cbsr_carrier_id' => $carrier_id,
					'_cbsr_zone'       => $quote['zone_no'],
					'_cbsr_billable'   => $quote['billable_weight'],
					'_cbsr_breakdown'  => wp_json_encode( $quote['lines'] ),
				),
			)
		);
	}

	/**
	 * 从 WooCommerce 包裹生成计价输入。
	 *
	 * @param array $package 包裹。
	 * @return array
	 */
	protected function build_package_data( $package ) {
		$weight     = 0.0;
		$dimensions = array( 'length' => 0.0, 'width' => 0.0, 'height' => 0.0 );
		$sum_dims   = array( 'length' => 0.0, 'width' => 0.0, 'height' => 0.0 );

		$contents = isset( $package['contents'] ) ? $package['contents'] : array();

		foreach ( $contents as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}

			$qty    = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$product = $item['data'];

			$item_weight = (float) $product->get_weight();
			if ( $item_weight > 0 ) {
				$weight += wc_get_weight( $item_weight, 'kg' ) * $qty;
			}

			$length = (float) $product->get_length();
			$width  = (float) $product->get_width();
			$height = (float) $product->get_height();

			if ( $length > 0 && $width > 0 && $height > 0 ) {
				$dimensions['length'] = max( $dimensions['length'], wc_get_dimension( $length, 'cm' ) );
				$dimensions['width']  = max( $dimensions['width'], wc_get_dimension( $width, 'cm' ) );
				$dimensions['height'] = max( $dimensions['height'], wc_get_dimension( $height, 'cm' ) );

				$sum_dims['length'] += wc_get_dimension( $length, 'cm' ) * $qty;
				$sum_dims['width']  += wc_get_dimension( $width, 'cm' ) * $qty;
				$sum_dims['height'] += wc_get_dimension( $height, 'cm' ) * $qty;
			}
		}

		$packaging_weight = isset( $this->settings['packaging_weight'] ) ? (float) $this->settings['packaging_weight'] : 0.0;
		$weight          += $packaging_weight;

		$dims_mode = isset( $this->settings['dims_mode'] ) ? $this->settings['dims_mode'] : 'max';

		if ( 'none' === $dims_mode ) {
			$dimensions = array( 'length' => 0, 'width' => 0, 'height' => 0 );
		} elseif ( 'sum' === $dims_mode ) {
			$dimensions = $sum_dims;
		}

		// 固定箱规优先。
		$carton = isset( $this->settings['carton_dims'] ) ? trim( (string) $this->settings['carton_dims'] ) : '';
		if ( '' !== $carton && preg_match( '/(\d+(?:\.\d+)?)\s*[x×*]\s*(\d+(?:\.\d+)?)\s*[x×*]\s*(\d+(?:\.\d+)?)/i', $carton, $m ) ) {
			$dimensions = array(
				'length' => (float) $m[1],
				'width'  => (float) $m[2],
				'height' => (float) $m[3],
			);
		}

		$package_data = array(
			'weight'     => $weight,
			'dimensions' => $dimensions,
			'country'    => isset( $package['destination']['country'] ) ? $package['destination']['country'] : '',
			'postcode'   => isset( $package['destination']['postcode'] ) ? $package['destination']['postcode'] : '',
			'state'      => isset( $package['destination']['state'] ) ? $package['destination']['state'] : '',
			'city'       => isset( $package['destination']['city'] ) ? $package['destination']['city'] : '',
		);

		/**
		 * 允许主题/插件接管包裹数据（例如接入真实装箱算法）。
		 *
		 * @param array $package_data 计价输入。
		 * @param array $package      WooCommerce 包裹。
		 */
		return apply_filters( 'cbsr_package_data', $package_data, $package );
	}

	/**
	 * 算不出价时的兜底。
	 *
	 * @param array $quote 报价结果。
	 */
	protected function maybe_add_fallback_rate( $quote ) {
		$mode = isset( $this->settings['fallback_label'] ) ? $this->settings['fallback_label'] : 'hide';

		if ( 'notice' !== $mode ) {
			return;
		}

		$reasons = array();
		foreach ( $quote['errors'] as $code ) {
			$reasons[] = CBSR_Engine::error_message( $code );
		}

		$this->add_rate(
			array(
				'id'    => $this->get_rate_id(),
				'label' => $this->title . ' ' . __( '（暂不支持该目的地）', 'crossborder-shipping' ),
				'cost'  => 0,
				'meta_data' => array(
					'_cbsr_error' => implode( '；', $reasons ),
				),
			)
		);
	}

	/**
	 * 生成明细文案。
	 *
	 * @param array $quote 报价。
	 * @return string
	 */
	protected function format_breakdown( $quote ) {
		$parts = array();

		$parts[] = sprintf(
			/* translators: 1: billable weight, 2: zone */
			__( '计费重 %1$skg / 分区 %2$s', 'crossborder-shipping' ),
			CBSR_Engine::trim_decimal( $quote['billable_weight'] ),
			$quote['zone_no']
		);

		foreach ( $quote['lines'] as $line ) {
			$parts[] = $line['label'] . ' ' . CBSR_Engine::format_money( $line['amount'] );
		}

		return implode( ' · ', $parts );
	}
}
