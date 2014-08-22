<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Discounts {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'apply_discount' ) );
		add_action( 'init', array( $this, 'apply_discount' ),11 );
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
		return 0;
	}

	public function get_discounts( $discount, $customer_id, $cart ) {
		$args = array( 'post_type' => 'customer_discount', 'post_status' => 'publish' );
		$query = new WP_Query( $args );
		$result = array();
		foreach ( $query->posts as $id => $post ) {
			$data = get_post_meta( $post->ID, 'frontend', true );
			$result[$id]['name']       = isset( $post->title )        ? $post->title              : 'Discount'    ;
			$result[$id]['id']         = isset( $post->ID )           ? $post->ID                 : false         ;
			$result[$id]['type']       = isset( $data['type'] )       ? $data['type']             : 'fixed_price' ;
			$result[$id]['quantity']   = isset( $data['quantity'] )   ? (int) $data['quantity']   : 0             ;
			$result[$id]['value']      = isset( $data['value'] )      ? (int) $data['value']      : 0             ;
			$result[$id]['products']   = isset( $data['products'] )   ? $data['products']         : array()       ;
			$result[$id]['categories'] = isset( $data['categories'] ) ? $data['categories']       : array()       ;
			$result[$id]['tags']       = isset( $data['tags'] )       ? $data['tags']             : array()       ;
			$result[$id]['users']      = isset( $data['users'] )      ? $data['users']            : array()       ;
			$result[$id]['groups']     = isset( $data['groups'] )     ? $data['groups']           : array()       ;
			$result[$id]['start']      = isset( $data['start'] )      ? $data['start']            : false         ;
			$result[$id]['end']        = isset( $data['end'] )        ? $data['end']              : false         ;
			$result[$id]['cust']       = isset( $data['cust'] )       ? $data['cust']             : false         ;
			$result[$id]['amount']     = $this->calculate_discount( $result[$id], $cart, $customer_id );
			if ( is_string( $result[$id]['products'] ) ) {
				$result[$id]['products'] = empty( $result[$id]['products'] ) ? array() : explode( ',', $result[$id]['products'] );
			}
		}
		return $result;
	}


	public function calculate_discount( $discount, $cart, $customer_id ) {
		$amount       = 0;
		$subtotal     = edd_get_cart_subtotal();
		$quantity     = edd_get_cart_quantity();
		$cart_details = edd_get_cart_content_details();
		switch ( $discount['type'] ) {
			// discount based on number of products in cart
			case 'cart_quantity':
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
				if ( $subtotal >= $discount['quantity'] ){
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
						$amount += $this->simple_discount_amount( $discount, $customer_id, $cart, $product_id, $cart_quantity, $item_price );
					}
				}
				break;

			case 'each_x_products':
				$quantity = $download['quantity'];
				$count = 1;
				$subtotal = 0.00;
				$discountValue = 0;
				while ( $count <= $quantity ){
					if ( $quantity >= $discount['quantity'] ) {
						if ( strpos( $discount['value'], '%' ) !== false ) {
							// Percentage value
							$val = round( ( (float) $discount['value'] ) / 100, 2 );
							$price = $price * $val;
						} else {
							// Fixed value
							$discountValue = (float) $discount['value'];
						}
						if ( $count % $discount['quantity'] == 0 ) {
							$subtotal += $discountValue;
						}
					}
					$count++;
				}
				$price = $subtotal;
				break;

			case 'from_x_products':
				$cart_quantity =  0 ;
				foreach( $cart_items as $key => $item ) {
					$item_price = edd_get_cart_item_price( $item['id'], $item['options'] );
					$item_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
					$product_id = $item['id'];
					// if the cart quantity plus the next download's quantity is over the threshold
					if ( $cart_quantity + $item_quantity >= $discount['quantity'] ){
						// if cart_quantity passes discount quantity with a product's quantity, only discount the ones over the limit
						if ( $discount['quantity'] > $cart_quantity ){
							$subtract_from_quantity = $discount['quantity'] - $cart_quantity;
							$item_quantity = $item_quantity - $subtract_from_quantity;
						}
						$amount += $this->simple_discount_amount( $discount, $customer_id, $cart, $product_id, $item_quantity, $item_price );

						if ( $discount['quantity'] > $cart_quantity ){
							$item_quantity = $item_quantity + $subtract_from_quantity;
						}
					}
					$cart_quantity += $quantity;
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
					$amount += $this->simple_discount_amount( $discount, $customer_id, $cart, $product_id, $item_quantity, $item_price );
				}
				break;
		}
		$amount = $amount < 0 ? 0 : $amount;
		return $amount;
	}

	private function simple_discount_amount( $discount, $customer, $cart, $product = false, $quantity = false, $item_price = false   ){

		// take id and make WP_User
		$customer = new WP_User($customer);

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
		if ( strpos( $discount['value'], '%' ) !== false ) {
			// Percentage value
			$val = round( ( (float) $discount['value'] ) / 100, 2 );
			$amount = $quantity * $item_price * $val;
		} else {
			// Fixed value
			$amount = (float) $discount['value'] * $quantity;
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
		if ( $discount ){
			EDD()->fees->add_fee( $discount['amount'], $discount['name'], 'edd_discounts_pro');
		}
	}
}
