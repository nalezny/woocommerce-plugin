<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once plugin_dir_path( __FILE__ ) . '../php-checkout-sdk/Wasa.php';

class Wasa_Kredit_Checkout_List_Widget {
	public function __construct() {
		$settings = get_option( 'wasa_kredit_settings' );

		// Connect to WASA PHP SDK
		$this->_client = new Sdk\Client(
			$settings['partner_id'],
			$settings['client_secret'],
			'yes' === $settings['test_mode'] ? true : false
		);

		// Hooks
		add_action(
			'woocommerce_before_shop_loop',
			array( $this, 'save_product_prices' ),
			10
		);

		add_action(
			'woocommerce_shortcode_before_products_loop',
			array( $this, 'save_product_prices_shortcodes' ),
			10
		);

		add_action(
			'woocommerce_after_shop_loop_item',
			array( $this, 'display_leasing_price_per_product' ),
			9
		);

		add_shortcode( 'wasa_kredit_list_widget', array(
			$this,
			'display_leasing_price_per_product',
		));
	}

	public function display_leasing_price_per_product() {
		// Adds financing info betweeen price and Add to cart button in category and [products] woocommerce shortcode
		global $product;

		$settings = get_option( 'wasa_kredit_settings' );

		if ( 'yes' !== $settings['widget_on_product_list'] ) {
			return;
		}

		$current_currency = get_woocommerce_currency();

		$payload['items'][] = array(
			'financed_price' => array(
				'amount'   => round($product->price, 0),
				'currency' => $current_currency,
			),
			'product_id'     => $product->id,
		);

		$response = $this->_client->calculate_monthly_cost($payload);

		if ( isset( $response ) && 200 === $response->statusCode ) { // @codingStandardsIgnoreLine - Our backend answers in with camelCasing, not snake_casing
			$amount = $response->data['monthly_costs'][0]['monthly_cost']['amount'];

			echo '<p>' .
				__( 'Financing', 'wasa-kredit-checkout' ) . ' <span style="white-space:nowrap;">' .
				wc_price( $amount, array( 'decimals' => 0 ) ) . __( '/month', 'wasa-kredit-checkout' ) .
				'</span></p>';
		}
	}

	public function save_product_prices() {
		// Collects all financing costs for all shown products
		// Store as global variable to be accessed in display_leasing_price_per_product()
		$settings = get_option( 'wasa_kredit_settings' );

		if ( 'yes' !== $settings['widget_on_product_list'] ) {
			return;
		}

		$payload['items'] = [];
		// Payload will contain all products with price, currency and id
		$current_currency = get_woocommerce_currency();

		global $wp_query;		
		$loop =  $wp_query;	

		// Loop through all products
		while ( $loop->have_posts() ) :
			$loop->the_post();
			global $product;

			// Add this product to payload
			$payload['items'][] = array(
				'financed_price' => array(
					'amount'   => round($product->get_price(), 2),
					'currency' => $current_currency,
				),
				'product_id'     => $product->get_id(),
			);
		endwhile;

		// Get resposne from API with all products defined in $payload
		$response      = $this->_client->calculate_monthly_cost( $payload );
		$monthly_costs = [];

		if ( isset( $response ) && 200 === $response->statusCode ) {
			foreach ( $response->data['monthly_costs'] as $current_product ) {
				$monthly_costs[
					$current_product['product_id']
				] = $current_product['monthly_cost']['amount'];
			}

			// Save prices to global variable to access it from template
			$GLOBALS['product_leasing_prices'] = $monthly_costs;
		}
	}

	public function save_product_prices_shortcodes($args) {
		// Collects all financing costs for all shown products
		// Store as global variable to be accessed in display_leasing_price_per_product()
		$settings = get_option( 'wasa_kredit_settings' );

		if ( 'yes' !== $settings['widget_on_product_list'] ) {
			return;
		}

		$payload['items'] = [];
		// Payload will contain all products with price, currency and id
		$current_currency = get_woocommerce_currency();

		$args = array(
		    'post_type' => 'product',
		    'cat' => $args['category'],
		    'page' => $args['page'],
		    'orderby' => $args['orderby'],
		    'order' => $args['order'],
		    'posts_per_page' => $args['limit'],
		    'tag' => $args['tag'],
		);

		if ( $args['page'] > 1 && $args['limit'] != -1) {
			$args['paged'] = true;
		}

		if (!empty($args['ids'])) {
			$args['post__in'] = array_map( 'trim', explode( ',', $args['ids'] ) );	
		}

		$wp_query = new WP_Query( $args );
		$loop =  $wp_query;	

		// Loop through all products
		while ( $loop->have_posts() ) :
			$loop->the_post();
			global $product;

			// Add this product to payload
			$payload['items'][] = array(
				'financed_price' => array(
					'amount'   => round($product->get_price(), 2),
					'currency' => $current_currency,
				),
				'product_id'     => $product->get_id(),
			);
		endwhile;

		// Get resposne from API with all products defined in $payload
		$response      = $this->_client->calculate_monthly_cost( $payload );
		$monthly_costs = [];

		if ( isset( $response ) && 200 === $response->statusCode ) {
			foreach ( $response->data['monthly_costs'] as $current_product ) {
				$monthly_costs[
					$current_product['product_id']
				] = $current_product['monthly_cost']['amount'];
			}

			// Save prices to global variable to access it from template
			$GLOBALS['product_leasing_prices'] = $monthly_costs;
		}
	}
}
