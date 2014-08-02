<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Discounts {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'apply_discounts' ) );
		add_action( 'init', array( $this, 'apply_discounts' ),11 );
	}

	public function get_customer_discount( $download = false, $customer_id = false ) {
		if ( ! $download ){
			return null;
		}

		if ( ! $customer_id ) {
			$customer_id = get_current_user_id();
		}

		// get discounts
		$discounts = $this->get_discounts( $download, $customer_id );

		// what? no discounts? I'm outta here
		if ( !$discounts || !is_array( $discounts ) ){
			return null;
		}

		// sort discounts so the discount that saves the most is on the top of the array
		$price = array();
		foreach ( $discounts as $key => $row ) {
			$price[$key] = $row['amount'];
		}
		array_multisort( $price, SORT_DESC, $discounts );

		// Debugging help: if you var_dump right here, you'll get a nice array with *all* the discounts
		// 				   and how much each customer_discount would discount the item
		// var_dump($discounts);

		// find the first applicable discount
		foreach ( $discounts as $discount ) {
			$is_applicable = $this->is_applicable( $discount, $download, $customer_id );
			if ( $is_applicable ) {
				return $discount;
			}
		}
		return null;
	}

	public function get_discounts( $download, $customerId ) {
		$args = array( 'post_type' => 'customer_discount', 'post_status' => 'publish' );
		$query = new WP_Query( $args );
		$result = array();
		foreach ( $query->posts as $id => $post ) {
			$data = get_post_meta( $post->ID, 'frontend', true );
			$result[$id]['name'] = $post->post_title;
			$result[$id]['id'] = $post->ID;
			$result[$id]['type'] = $data['type'];
			$result[$id]['quantity'] =  (int) $data['quantity'];
			$result[$id]['value'] = $data['value'];
			$result[$id]['products'] = $data['products'];
			$result[$id]['categories'] = $data['categories'];
			$result[$id]['users'] = $data['users'];
			$result[$id]['groups'] = get_post_meta( $post->ID, 'groups', true ) ;
			$result[$id]['amount'] = $this->calculate_new_product_price( $result[$id], $download );
			if ( is_string( $result[$id]['products'] ) ) {
				$result[$id]['products'] = empty( $result[$id]['products'] ) ? array() : explode( ',', $result[$id]['products'] );
			}
		}
		return $result;
	}


	public function calculate_new_product_price( $discount, $download ) {
		$price = (float) $download['price'];
		$is_var = false;
		$var_id = 0;
		if ( isset( $download['item_number']['options']['price_id'] ) && $download['item_number']['options']['price_id'] !== null ) {
			// product is variable
			$is_var = true;
			$var_id = $download['item_number']['options']['price_id'];
		}

		switch ( $discount['type'] ) {

			// Cart discounts
		case 'cart_quantity':
			if ( $download['quantity'] >= $discount['quantity'] ){
				if ( strpos( $discount['value'], '%' ) !== false ) {
					// Percentage value
					$price = round( $price - $price * (float) rtrim( $discount['value'], '%' ) / 100, 2 ) * ( $download['quantity'] - $discount['quantity'] + 1 );
				} else {
					// Fixed value
					$price = (float) $discount['value'] * ( $download['quantity'] - $discount['quantity'] + 1 );
				}
			}
			break;

		case 'cart_threshold':
			$value = 0.0;
			if ( strpos( $discount['value'], '%' ) !== false ) {
				// Percentage value
				$price = round( $price - $price * (float) rtrim( $discount['value'], '%' ) / 100, 2 ) * $download['quantity'];
			} else {
				// Fixed value
				$price = ($price - (float) $discount['value'] * $price / $value) * $download['quantity'];
			}
			break;
			// Product discounts
		case 'fixed_price':
			$price = (float) $discount['value'] * $download['quantity'];
			break;

		case 'percentage_price':
			$price = round( $price * ( (float) $discount['value'] ) / 100, 2 ) * $download['quantity'];
			break;

			// TODO: these cases below all need to be fixed for variable products
		case 'product_quantity':
			if ( $this->has_product( $download, $discount, $download['quantity'] ) ) {
				if ( strpos( $discount['value'], '%' ) !== false ) {
					// Percentage value
					$price = ( round( $price - $price * (float) rtrim( $discount['value'], '%' ) / 100, 2 ) ) * $download['quantity'];
				} else {
					// Fixed value
					$price = (float) $discount['value'] * $download['quantity'];
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
						$discountValue = round( $download['item_price'] * (float) rtrim( $discount['value'], '%' ) / 100, 2 );
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
			$quantity = $download['quantity'];
			$count = 1;
			$subtotal = 0.00;
			$discountValue = 0;
			while ( $count <= $quantity ){
				if ( $quantity >= $discount['quantity'] ) {
					if ( strpos( $discount['value'], '%' ) !== false ) {
						// Percentage value
						$discountValue = round( $download['item_price'] * (float) rtrim( $discount['value'], '%' ) / 100, 2 );
					} else {
						// Fixed value
						$discountValue = (float) $discount['value'];
					}
					if ( $count >= $discount['quantity'] ) {
						$subtotal += $discountValue;
					}
				}
				$count++;
			}
			$price = $subtotal;
		}
		$price = $price < 0 ? 0 : $price;
		return $price;
	}
	private function has_product( $product, $discount, $quantity ) {
		if (!$quantity){
			return false;
		}
		foreach ( edd_get_cart_contents() as $item ) {
			if ( $item['id'] == $product['id'] || in_array( $item['id'], $discount['products'] ) ) {
				return true;
			}
		}
		return false;
	}

	public function get_discount( $download ) {
		if ( !isset($download['price'])){
			return;
		}
		$discount = $this->get_customer_discount( $download );
		if ( ! empty( $discount ) ) {
			return $discount;
		}
	}

	private function is_applicable( $discount, $product, $customer ) {

		// take id and make WP_User
		$customer = new WP_User($customer);

		// Check if discount is applicable to the product
		if ( ! empty( $discount['products']) && !in_array( $product['id'], $discount['products'] ) ) {
			return false;
		}

		$cart     = edd_get_cart_contents();
		// Check if product matches quantity discounts
		switch ( $discount['type'] ) {
		case 'cart_quantity':

			$quantity = 0;

			foreach ( $cart as $item ) {
				$quantity += $item['quantity'];
			}

			if ( $quantity < $discount['quantity'] ) {
				return false;
			}

			break;

		case 'product_quantity':

			$quantity = 0;

			foreach ( $cart as $cart_item ) {
				// Simple products
				if ( $cart_item['id'] == $product['id'] && ( empty( $discount['products'] ) || in_array( $cart_item['id'], $discount['products']) ) ) {
					$quantity += $cart_item['quantity'];
				}

			}

			if ( $quantity < $discount['quantity'] ) {
				return false;
			}

			break;
		}

		// Check if it is applicable to current user
		if ( !empty( $discount['users'] ) && ( !$customer || !in_array( $customer->ID, $discount['users'] ) ) ) {
			return false;
		}

		// Check if current user is in an applicable group
		if ( !empty( $discount['groups'] ) && ( !$customer || array_intersect( $customer->roles, $discount['groups'] ) == array() ) ) {
			return false;
		}

		// Check if product is in a category of discount
		if ( !empty( $discount['categories'] ) && !empty( $product->categories ) ) {
			if ( array_intersect( $product->categories, $discount['categories'] ) == array() ) {
				return false;
			}
		}

		return true;
	}

	public function apply_discounts() {
		$cart_items  = edd_get_cart_contents();
		$cart_details = edd_get_cart_content_details();
		$fees = edd_get_cart_fees();

		// for some reason removing the first cart item makes the contents empty
		if ( empty( $cart_items ) ) {
			return;
		}

		foreach ( $fees as $fee => $val ) {
			if ( substr( $fee, 0, 7 ) === 'edd_dp_' ) {
				EDD()->fees->remove_fee( $fee );
			}
		}

		// start praying
		EDD()->session->set( 'edd_cart', NULL );
		foreach ( $cart_details as $item => $val ) {
			$val['item_number']['options']['quantity'] = $val['quantity'];
			edd_add_to_cart( $val['id'], $val['item_number']['options'] );
			
			// Apply the discount (if available)
			$discount = $this->get_discount( $val );
			$amount = -1 * (double) $discount['amount'];
			if ( $amount < 0 ) {
				EDD()->fees->add_fee( $amount, $discount['name'], 'edd_dp_'.$val['id']);
			}
		}
	}
}
