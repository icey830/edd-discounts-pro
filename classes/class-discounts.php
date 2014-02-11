<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Discounts {

	private $_discounts;

	public function __construct() {
		if ( is_admin() ){
			return;
		}
		add_action( 'template_redirect', array( $this, 'apply_discounts' ) );
	}

	public function get_customer_discounts( $product, $customerId = false ) {
		if ( ! $customerId ) {
			$customerId = get_current_user_id();
		}
		// this is nuts. Let's make this an array with
		// name of discount | amount | price | applicable | reason
		// get discounts

		// sort discounts

		// find the first applicable discount

		// return that



		if ( $this->_discounts === null ) {
			$this->get_discounts();
		}

		$product  = new EDD_DCF_Product( $product );
		$customer = false;
		
		if ( $customerId != 0 ) {
			$customer = get_userdata( $customerId );
		}
		
		$discounts = array();
		foreach ( $this->_discounts as $item ) {
			if ( $this->is_applicable( $item, $product, $customer ) ) {
				$discounts[] = $item;
			}
		}
		
		$that = $this;

		create_function( '$a, $b', 'global $that, $product;$aPrice = $that->calculate_new_product_price($a, $product, $product->price);$bPrice = $that->calculate_new_product_price($b, $product, $product->price);return $aPrice == $bPrice ? 0 : ($aPrice > $bPrice ? 1 : -1);' );
		
		return $discount;
	}

	public function calculate_new_product_price( $discount, $product, $price ) {
		$price = (float) $price;
		switch ( $discount->type ) {
			
			// Cart discounts
			case 'cart_quantity':

				if ( strpos( $discount->value, '%' ) !== false ) {
					// Percentage value
					$price = round( $price - $price * (float) rtrim( $discount->value, '%' ) / 100, 2 );
				} else {
					// Fixed value
					$price = $price - (float) $discount->value;
				}
				break;
			
			case 'cart_threshold':
				$value = 0.0;
				if ( strpos( $discount->value, '%' ) !== false ) {
					// Percentage value
					$price = round( $price - $price * (float) rtrim( $discount->value, '%' ) / 100, 2 );
				} else {
					// Fixed value
					$price = $price - (float) $discount->value * $price / $value;
				}
				break;
				// Product discounts
			case 'fixed_price':
				$price -= (float) $discount->value;
				break;
			
			case 'percentage_price':
				$price = round( $price * ( 100 - (float) $discount->value ) / 100, 2 );
				break;
			
			case 'product_quantity':
				$incart = false;
				foreach ( edd_cart::get_cart() as $item ) {
					if ( $item['product_id'] == $product->id || in_array( $item['product_id'], $discount->products ) ) {
						$incart = true;
					}
				}
				if ( $this->_hasProduct( $product, $discount ) ) {
					if ( strpos( $discount->value, '%' ) !== false ) {
						// Percentage value
						$price = round( $price - $price * (float) rtrim( $discount->value, '%' ) / 100, 2 );
					} else {
						// Fixed value
						$price -= (float) $discount->value;
					}
				}
				break;
			
			case 'each_x_products':
				$quantity = 0;
				$quantity = edd_get_cart_item_quantity( $product->id );
				if ( $quantity >= $discount->quantity ) {
					if ( strpos( $discount->value, '%' ) !== false ) {
						// Percentage value
						$discountValue = round( $price * (float) rtrim( $discount->value, '%' ) / 100, 2 );
					} else {
						// Fixed value
						$discountValue = (float) $discount->value;
					}
					if ( $quantity % $discount->quantity == 0 ) {
						$price -= $discountValue / $discount->quantity;
					} else {
						$discounted = (int) ( $quantity / $discount->quantity );
						$price -= $discountValue * $discounted / $quantity;
					}
				}
				break;
			
			case 'from_x_products':
				$quantity = 0;
				$quantity = edd_get_cart_item_quantity( $product->id );
				if ( $quantity >= $discount->quantity ) {
					if ( strpos( $discount->value, '%' ) !== false ) {
						// Percentage value
						$discountValue = round( $price * (float) rtrim( $discount->value, '%' ) / 100, 2 );
					} else {
						// Fixed value
						$discountValue = (float) $discount->value;
					}
					$overallPrice = $discount->quantity * $price + ( $quantity - $discount->quantity ) * ( $price - $discountValue );
					$price        = $overallPrice / $quantity;
				}
				break;
		}
		$price = $price < 0 ? 0 : $price;
		$to_return = array( "discount" => $discount, "price" => $price );
		
		return $to_return;
	}

	public function get_discount( $price, $product_id ) {
		var_dump($price, $product_id );
		$storeprice = $price;
		$discounts  = $this->get_customer_discounts( $product_id );
		$product    = new EDD_DCF_Product( $product_id, $price );

		if ( ! empty( $discounts ) ) {
			
			$discount = array_shift( $discounts );
			$discount = $this->calculate_new_product_price( $discount, $product, $price );
			$price    = $discount['price'];
			$title    = get_the_title( $product_id ) . ' - ' . __( 'Discount', 'edd_cfm' );
			$fee      = ( $storeprice - $price ) * -1;
			$fee_test = $fee * 500;
			$fee_test = (int) $fee;
			
			if ( $fee != 0 ) {
				EDD()->fees->add_fee( $fee, $title, 'edd_dp_'.$product_id );
			}

		}
	}
	private function get_discounts() {
		
		$this->_discounts = array();
		
		$discounts = get_posts( array(
			'numberposts' => -1,
			'post_type'   => 'customer_discount'
		) );
		
		foreach ( $discounts as $item ) {
			$discount             = new stdClass();
			$discount->id         = $item->ID;
			$discount->name       = $item->post_title;
			$discount->priority   = $item->menu_order;
			$discount->type       = get_post_meta( $discount->id, 'type', true );
			$discount->quantity   = (int) get_post_meta( $discount->id, 'quantity', true );
			$discount->value      = get_post_meta( $discount->id, 'value', true );
			$discount->products   = get_post_meta( $discount->id, 'products', true );
			$discount->categories = get_post_meta( $discount->id, 'categories', true );
			$discount->groups     = get_post_meta( $discount->id, 'groups', true );
			$discount->users      = get_post_meta( $discount->id, 'users', true );
			
			if ( is_string( $discount->products ) ) {
				$discount->products = empty( $discount->products ) ? array() : explode( ',', $discount->products );
			}
			
			$this->_discounts[] = $discount;
		}
	}
	private function is_applicable( $discount, $product, $customer ) {

		// Check if discount is applicable to the product
		if ( ! empty( $discount->products ) && ! in_array( $product->id, $discount->products ) ) {
			return false;
		}
		
		// Check if product matches quantity discounts
		switch ( $discount->type ) {
		
			case 'cart_quantity':
				
				$quantity = 0;
				$cart     = edd_get_cart_contents();
				
				foreach ( $cart as $item ) {
					$quantity += $item['quantity'];
				}
				
				if ( $quantity < $discount->quantity ) {
					return false;
				}
				
				break;
			
			case 'product_quantity':
				
				$quantity = 0;
				foreach ( edd_cart::get_cart() as $cart_item ) {
				
					// Simple products
					if ( $cart_item['product_id'] == $product->id && ( empty( $discount->products ) || in_array( $cart_item['product_id'], $discount->products ) ) ) {
						$quantity += $cart_item['quantity'];
					}
				
				}
				
				if ( $quantity < $discount->quantity ) {
					return false;
				}
				
				break;
		}

		// Check if it is applicable to current user
		if ( !empty( $discount->users ) && ( !$customer || !in_array( $customer->ID, $discount->users ) ) ) {
			return false;
		}
		
		// Check if current user is in an applicable group
		if ( !empty( $discount->groups ) && ( !$customer || array_intersect( $customer->roles, $discount->groups ) == array() ) ) {
			return false;
		}
		
		// Check if product is in a category of discount
		if ( !empty( $discount->categories ) && !empty( $product->categories ) ) {
			if ( array_intersect( $product->categories, $discount->categories ) == array() ) {
				return false;
			}
		}
		
		return true;
	}

	public function apply_discounts() {
		global $wpdb;
		if ( is_admin() ) {
			return;
		}
		if ( ! ( edd_is_checkout() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) ) {
			return;
		}
		var_dump(1);

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
		$counter = 0;
		foreach ( $cart_details as $item => $val ) {
			while ( $counter < $val['quantity'] ) {
				// add to cart
				edd_add_to_cart( $val['id'], $val['item_number']['options'] );

				// Apply the discount (if available)
				$this->get_discount( $val['price'], $val['id'] );
				var_dump($val);

				$counter++;
			}
			$counter = 0;
		}
	}
}
