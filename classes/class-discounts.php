<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Discounts {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'apply_discount' ) );
		add_action( 'init', array( $this, 'apply_discount' ),11 );
		if ( version_compare( EDD_VERSION, '2.1' ) >= 0 ){
			add_filter( 'edd_download_price_after_html', array($this, 'edd_price' ), 10, 3 );
		}
	}

	public function edd_price( $formatted_price, $download_id, $price ){
		$custom = edd_get_option( 'edd_dp_frontend_output_toggle', false );
		if ( !$custom ){
			return $formatted_price;
		}
		else{
			$output   = edd_get_option( 'edd_dp_frontend_output_content', '<span class="edd_price" id="edd_price_{download_id}">{oldprice}</span>' );
			$discount = $this->get_simple_discount( $download_id );
			if ( !$discount ){
				return $formatted_price;
			}
			$savings = '';
			if ( strpos( $discount['value'], '%' ) !== false ) {
				// Percentage value
				$savings = $discount['value']. ' ' . __('off', 'edd_dp');
				$savings = apply_filters( 'edd_dp_edd_price_savings_percent', $savings, $discount, $download_id, $price );
			} else {
				// Fixed value
				$savings = edd_currency_filter( edd_format_amount( $discount['value'] ) ) . ' ' . __('off', 'edd_dp');
				$savings = apply_filters( 'edd_dp_edd_price_savings_percent', $savings, $discount, $download_id, $price );
			}
			$oldprice = 0;
			if ( edd_has_variable_prices( $download_id ) ) {
				$prices = edd_get_variable_prices( $download_id );
				// Return the lowest price
				$price_float = 0;
				foreach ( $prices as $key => $value ) {
					if ( ( ( (float)$prices[ $key ]['amount'] ) < $price_float ) or ( $price_float == 0 ) ) {
						$price_float = (float)$prices[ $key ]['amount'];
					}
					$oldprice = edd_sanitize_amount( $price_float );
					}
				} else {
				$oldprice = edd_get_download_price( $download_id );
			}
			global $output_vars;
			$newprice = $oldprice - $discount['amount'];
			$output   = str_replace( '{oldprice}', edd_currency_filter( edd_format_amount( $oldprice ) ) , $output );
			$output   = str_replace( '{newprice}', edd_currency_filter( edd_format_amount( $newprice ) ) , $output );
			$output   = str_replace( '{savings}', $savings , $output );
			$output   = str_replace( '{download_id}', $download_id , $output );
			$output   = str_replace( '{discount_title}', $discount['name'], $output );
			$output_vars = array(
				'newprice' => $newprice,
				'oldprice' => $oldprice,
				'savings' => $savings,
				'download_id' => $download_id,
				'download_title' => $download_title
			);
			$custom = edd_get_option( 'edd_dp_frontend_output_override', false );
			if ( $custom ){
				add_filter( 'edd_get_download_price', array( $this, 'download_price' ) );
				//add_filter( 'edd_purchase_variable_prices', array($this, 'download_price_variable') );
			}
		
			return $output;
		}
	}

	public function download_price_variable(){
		global $output_vars;
		return apply_filters( 'edd_dp_download_price_variable', $output_vars['newprice'], $output_vars );
	}

	public function download_price(){
		global $output_vars;
		return apply_filters( 'edd_dp_download_price',$output_vars['newprice'], $output_vars );
	}

	public function get_discount( $cart = array(), $customer_id = false  ) {
		if ( ! $customer_id ) {
			$customer_id = get_current_user_id();
		}

		// get discounts
		$discounts = $this->get_discounts( $customer_id, $cart );

		// what? no discounts? I'm outta here
		if ( !$discounts || !is_array( $discounts ) ){
			return null;
		}

		// sort discounts so the discount that saves the most is on the top of the array
		$price = array();
		foreach ( $discounts as $key => $row ) {
			$discount[$key] = $row['amount'];
		}
		array_multisort( $discount, SORT_DESC, $discounts );

		// Debugging help: if you var_dump right here, you'll get a nice array with *all* the discounts
		// 				   and how much each customer_discount would discount the item
		// var_dump($discounts);
		if ( isset( $discounts[0] ) ){
			return $discounts[0];
		}
		return false;
	}

	public function get_discounts( $customer_id, $cart ) {
		$args = array( 'post_type' => 'customer_discount', 'post_status' => 'publish' );
		$query = new WP_Query( $args );
		$result = array();
		if ( empty( $query->posts ) ) {
			return array();
		}
		foreach ( $query->posts as $id => $post ) {
			$data = get_post_meta( $post->ID, 'frontend', true );
			$result[$id]['name']       = isset( $post->title )        ? $post->title              : 'Discount'    ;
			$result[$id]['id']         = isset( $post->ID )           ? $post->ID                 : false         ;
			$result[$id]['type']       = isset( $data['type'] )       ? $data['type']             : 'fixed_price' ;
			$result[$id]['quantity']   = isset( $data['quantity'] )   ? (int) $data['quantity']   : 0             ;
			$result[$id]['value']      = isset( $data['value'] )      ? $data['value']            : 0             ;
			$result[$id]['products']   = isset( $data['products'] )   ? $data['products']         : array()       ;
			if ( is_string( $result[$id]['products'] ) ) {
				$result[$id]['products'] = empty( $result[$id]['products'] ) ? array() : explode( ',', $result[$id]['products'] );
			}
			$result[$id]['categories'] = isset( $data['categories'] ) ? $data['categories']       : array()       ;
			$result[$id]['tags']       = isset( $data['tags'] )       ? $data['tags']             : array()       ;
			$result[$id]['users']      = isset( $data['users'] )      ? $data['users']            : array()       ;
			$result[$id]['groups']     = isset( $data['groups'] )     ? $data['groups']           : array()       ;
			$result[$id]['start']      = isset( $data['start'] )      ? $data['start']            : false         ;
			$result[$id]['end']        = isset( $data['end'] )        ? $data['end']              : false         ;
			$result[$id]['cust']       = isset( $data['cust'] )       ? $data['cust']             : false         ;
			$result[$id]['amount']     = $this->calculate_discount( $result[$id], $cart, $customer_id );
		}
		return $result;
	}

	public function get_simple_discount( $download_id ){
		$customer_id = get_current_user_id();
		$item_price = 0;
		if ( edd_has_variable_prices( $download_id ) ) {
			$prices = edd_get_variable_prices( $download_id );
			// Return the lowest price
			$price_float = 0;
			foreach ( $prices as $key => $value ) {
				if ( ( ( (float)$prices[ $key ]['amount'] ) < $price_float ) or ( $price_float == 0 ) ) {
					$price_float = (float)$prices[ $key ]['amount'];
				}
				$item_price = edd_sanitize_amount( $price_float );
				}
			} else {
			$item_price = edd_get_download_price( $download_id );
		}
		
		// get discounts
		$args = array( 'post_type' => 'customer_discount', 'post_status' => 'publish' );
		$query = new WP_Query( $args );
		$result = array();
		foreach ( $query->posts as $id => $post ) {
			$data = get_post_meta( $post->ID, 'frontend', true );
			if ( isset( $data['type'] ) && ( $data['type'] === 'fixed_price' || $data['type'] === 'percentage_price ') ){
				$result[$id]['name']       = isset( $post->title )        ? $post->title              : 'Discount'    ;
				$result[$id]['id']         = isset( $post->ID )           ? $post->ID                 : false         ;
				$result[$id]['type']       = isset( $data['type'] )       ? $data['type']             : 'fixed_price' ;
				$result[$id]['quantity']   = isset( $data['quantity'] )   ? (int) $data['quantity']   : 0             ;
				$result[$id]['value']      = isset( $data['value'] )      ? $data['value']            : 0             ;
				$result[$id]['products']   = isset( $data['products'] )   ? $data['products']         : array()       ;
				if ( is_string( $result[$id]['products'] ) ) {
					$result[$id]['products'] = empty( $result[$id]['products'] ) ? array() : explode( ',', $result[$id]['products'] );
				}
				$result[$id]['categories'] = isset( $data['categories'] ) ? $data['categories']       : array()       ;
				$result[$id]['tags']       = isset( $data['tags'] )       ? $data['tags']             : array()       ;
				$result[$id]['users']      = isset( $data['users'] )      ? $data['users']            : array()       ;
				$result[$id]['groups']     = isset( $data['groups'] )     ? $data['groups']           : array()       ;
				$result[$id]['start']      = isset( $data['start'] )      ? $data['start']            : false         ;
				$result[$id]['end']        = isset( $data['end'] )        ? $data['end']              : false         ;
				$result[$id]['cust']       = isset( $data['cust'] )       ? $data['cust']             : false         ;
				$result[$id]['amount']     = $this->simple_discount_amount( $result[$id], $customer_id, $download_id, 1, $item_price );
			}
		}
		$discounts = $result;

		// what? no discounts? I'm outta here
		if ( !$discounts || !is_array( $discounts ) ){
			return null;
		}

		// sort discounts so the discount that saves the most is on the top of the array
		$price = array();
		foreach ( $discounts as $key => $row ) {
			$discount[$key] = $row['amount'];
		}
		array_multisort( $discount, SORT_DESC, $discounts );

		// Debugging help: if you var_dump right here, you'll get a nice array with *all* the discounts
		// 				   and how much each customer_discount would discount the item
		// var_dump($discounts);
		if ( isset( $discounts[0] ) ){
			return $discounts[0];
		}
		return false;
	}


	public function calculate_discount( $discount, $cart, $customer_id ) {
		$amount       = 0;
		$subtotal     = edd_get_cart_subtotal();
		$quantity     = edd_get_cart_quantity();
		$cart_items   = edd_get_cart_contents();
		switch ( $discount['type'] ) {
			// discount based on number of products in cart
			case 'cart_quantity':
				$discount2 = $discount;
				$discount2['type'] = 'percentage_price';
				$discount2['value'] = '100%';
				$quantity = 0;
				foreach( $cart_items as $key => $item ) {
					$item_price = edd_get_cart_item_price( $item['id'], $item['options'] );
					$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
					$product_id = $item['id'];
					$disc = $this->simple_discount_amount( $discount2, $customer_id, $product_id, $cart_quantity, $item_price );
					if ( $disc > 0 ){
						$quantity += $cart_quantity;
					}
				}
				if ( $quantity >= $discount['quantity'] ){
					if ( strpos( $discount['value'], '%' ) !== false ) {
						// Percentage value
						$val = round( ( (float) $discount['value'] ) / 100, 2 );
						$amount = $subtotal * $val;
					} else {
						// Fixed value
						$amount = (float) $discount['value'];
					}
				}
				break;
			// discount based on cart price
			case 'cart_threshold':
				$total = 0; // amount of applicable cart value
				$discount2 = $discount;
				$discount2['type'] = 'percentage_price';
				$discount2['value'] = '100%'; 
				foreach( $cart_items as $key => $item ) {
					$item_price = edd_get_cart_item_price( $item['id'], $item['options'] );
					$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
					$product_id = $item['id'];
					$total += $this->simple_discount_amount( $discount2, $customer_id, $product_id, $cart_quantity, $item_price );
				}
				if ( $total >= $discount['quantity'] ){
						if ( strpos( $discount['value'], '%' ) !== false ) {
							// Percentage value
							$val = round( ( (float) $discount['value'] ) / 100, 2 );
							$amount = $subtotal * $val;
						} else {
							// Fixed value
							$amount = (float) $discount['value'];
						}
				}
				break;
			// discount for quantities of a product
			case 'product_quantity':
				foreach( $cart_items as $key => $item ) {
					$item_price = edd_get_cart_item_price( $item['id'], $item['options'] );
					$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
					$product_id = $item['id'];
					if ( $cart_quantity >= $discount['quantity'] ){
						$amount += $this->simple_discount_amount( $discount, $customer_id, $product_id, $cart_quantity, $item_price );
					}
				}
				break;

			case 'each_x_products':
				$count = 1;
				foreach( $cart_items as $key => $item ) {
					$item_price = edd_get_cart_item_price( $item['id'], $item['options'] );
					$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
					$product_id = $item['id'];
					$disc = $this->simple_discount_amount( $discount, $customer_id, $product_id, $cart_quantity, $item_price );
					if ( $disc > 0 ){
						if ( $count == $discount['quantity'] ){
							$amount += $disc;
							$count = 1;
						}
						else{
							$count++;
						}
					}
				}
				break;

			case 'from_x_products':
				$count = 1;
				foreach( $cart_items as $key => $item ) {
					$item_price = edd_get_cart_item_price( $item['id'], $item['options'] );
					$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
					$product_id = $item['id'];
					// if the cart quantity plus the next download's quantity is over the threshold
					if ( $count >= $discount['quantity'] ){
						$disc = $this->simple_discount_amount( $discount, $customer_id, $product_id, $cart_quantity, $item_price );
						if ( $disc > 0 ){
							$amount += $disc;
						}
					}
					$count++;
				}
				break;
			// simple flat rate or percentage off product(s)
			case 'fixed_price':
			case 'percentage_price':
			default:
				foreach( $cart_items as $key => $item ) {
					$item_price = edd_get_cart_item_price( $item['id'], $item['options'] );
					$item_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
					$product_id = $item['id'];
					$amount += $this->simple_discount_amount( $discount, $customer_id, $product_id, $item_quantity, $item_price );
				}
				break;
		}
		$amount = $amount < 0 ? 0 : $amount;
		return $amount;
	}

	private function simple_discount_amount( $discount, $customer, $download_id = false, $quantity = false, $item_price = false   ){

		// take id and make WP_User
		$customer = new WP_User($customer);

		$product['id'] = $download_id;

		// Check if discount is applicable to the product
		if ( ! empty( $discount['products']) && !in_array( $product['id'], $discount['products'] ) ) {
			return 0;
		}

		// Check if it is applicable to current user
		if ( !empty( $discount['users'] ) && is_array( $discount['users'] ) && ( !$customer || !in_array( $customer->ID, $discount['users'] ) ) ) {
			return 0;
		}

		// Check if current user is in an applicable group
		if ( !empty( $discount['groups'] ) && ( !$customer || array_intersect( $customer->roles, $discount['groups'] ) == array() ) ) {
			return 0;
		}

		$product['categories'] = wp_get_post_terms( $product['id'], 'download_category', array(
			 'fields' => 'ids' 
		) );

		$product['tags'] = wp_get_post_terms( $product['id'], 'download_tag', array(
			 'fields' => 'ids' 
		) );

		// Check if product is in a category of discount
		if ( !empty( $discount['categories'] ) ) {
			if ( !empty( $product['categories'] )){
				if ( array_intersect( $product['categories'], $discount['categories'] ) == array() ) {
					return 0;
				}
			}
			else{
				return 0;
			}
		}

		// Check if product is in a category of discount
		if ( !empty( $discount['tags'] ) ) {
			if ( !empty( $product['tags'] )){
				if ( array_intersect( $product['tags'], $discount['tags'] ) == array() ) {
					return 0;
				}
			}
			else{
				return 0;
			}
		}

		// check start and end dates
		if ( $discount['start'] !== '' && strtotime( $discount['start'] ) > strtotime("now") ){
			return 0;
		}

		if ( $discount['end'] !== '' && strtotime( $discount['end'] ) < strtotime("now") ){
			return 0;
		}

		// if discount is only for previous customers and customer does not have any previous purchases
		if ( $discount['cust'] ){
			if ( !edd_has_purchases( $customer_id ) ){
				return 0;
			}
		}

		// good to go for discount
		$amount = 0;
		if ( strpos( $discount['value'], '%' ) !== false || $discount['type'] == 'percentage_price' ) {
			// Percentage value
			$val = round( ( (float) $discount['value'] ) / 100, 2 );
			$amount = $item_price * $val;
		} else {
			// Fixed value
			$amount = (float) $discount['value'];
		}
		return $amount;
	}

	public function apply_discount() {
		$cart_items  = edd_get_cart_contents();
		// for some reason removing the first cart item makes the contents empty
		if ( empty( $cart_items ) ) {
			return;
		}
		// remove old discount
		EDD()->fees->remove_fee( 'edd_discounts_pro' );
		// get new discount
		$discount = $this->get_discount( $cart_items );
		// add new discount
		if ( isset( $discount['amount'] ) && $discount['amount'] > 0 ){
			EDD()->fees->add_fee( -1 * $discount['amount'], $discount['name'], 'edd_discounts_pro');
		}
	}
}
