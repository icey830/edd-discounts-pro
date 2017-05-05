<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Discounts {

	public function __construct() {
		if ( is_admin() && !( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'apply_discount' ) );
		add_action( 'init', array( $this, 'apply_discount' ), 11 );
		add_action( 'wp_head', array( $this, 'checkout_js' ) );
		add_action( 'wp_ajax_edd_recalculate_discounts_pro', array( $this, 'recalculate_discounts' ) );
		add_action( 'wp_ajax_nopriv_edd_recalculate_discounts_pro', array( $this, 'recalculate_discounts' ) );

		$custom = edd_get_option( 'edd_dp_frontend_output_toggle', false );

		// Purchase link
		if ( version_compare( EDD_VERSION, '2.1' ) >= 0 && $custom ) {
			// Top ( Old Price)
			add_action( 'edd_purchase_link_top', array( $this, 'edd_purchase_link_top' ), 10, 1 );
			add_action( 'edd_after_price_option', array( $this, 'edd_purchase_link_variable' ), 10, 3 );

			// Show new price on button
			add_filter( 'edd_purchase_link_args', array( $this, 'edd_purchase_link_args' ), 10, 1 );
			add_filter( 'edd_purchase_variable_prices', array( $this, 'edd_purchase_variable_prices' ), 10, 2 );
		}

		// edd_price
		if ( version_compare( EDD_VERSION, '2.3' ) >= 0 && $custom ) {
			// Top ( Old Price)
			add_filter( 'edd_download_price_after_html', array( $this, 'edd_price_top' ), 10, 3 );

			// Show new price on button
			add_filter( 'edd_download_price', array( $this, 'edd_price' ), 10, 3 );
		}
	}

	public function edd_purchase_link_top( $download_id ) {
		$variable_pricing = edd_has_variable_prices( $download_id );

		if ( $variable_pricing ) {
			return;
		}

		$old_price = edd_get_download_price( $download_id );

		$cart = array(
			0 => array(
				'id' => $download_id,
				'quantity' => 1,
			)
		);
		$discount = $this->get_discount( $cart );

		if ( !$discount ) {
			return;
		}

		$option = edd_get_option( 'edd_dp_old_price_text', __( 'Old Price:', 'edd_dp' ) );

		$line = '<span class="old-priced-container"><span class="old-price-title">' . $option . ' </span><s class="old-price">' .  edd_currency_filter( edd_format_amount( $old_price ) ) . '</s></span>';

		$line = apply_filters( 'edd_dp_purchase_link_top', $line, $download_id );

		echo $line;
	}

	public function edd_purchase_link_variable( $key, $old_price, $download_id ) {
		$variable_pricing = edd_has_variable_prices( $download_id );

		if ( !$variable_pricing ) {
			return;

		}

		$cart = array(
			0 => array(
				'id' => $download_id,
				'quantity' => 1,
				'options' => array(
					'price_id' => $key
				),
			)
		);
		$discount = $this->get_discount( $cart );

		if ( !$discount ) {
			return;
		}

		$prices = edd_get_variable_prices( $download_id );

		$option = edd_get_option( 'edd_dp_old_price_text', __( 'Old Price:', 'edd_dp' ) );

		$line = '<span class="old-priced-container"><span class="old-price-title">' . $option . ' </span><s class="old-price">' . edd_currency_filter( edd_format_amount( $prices[ $key ]['amount'] ) ) . '</s></span>';

		$line = apply_filters( 'edd_dp_purchase_link_variable', $line, $key, $prices[ $key ]['amount'], $download_id );

		echo $line;
	}

	public function edd_purchase_link_args( $args ) {
		if ( !isset( $args['download_id'] ) ) {
			return $args;
		}


		$download = new EDD_Download( $args['download_id'] );

		$variable_pricing = $download->has_variable_prices();

		if  ( $variable_pricing ) {
			return $args;
		}

		$price = $download->price;

		$cart = array(
			0 => array(
				'id' => $args['download_id'],
				'quantity' => 1,
			)
		);
		$discount = $this->get_discount( $cart );

		if ( !$discount ) {
			return $args;
		}

		$newprice = $price - $discount;

		$args[ 'text' ] = str_replace(  edd_currency_filter( edd_format_amount( $price ) ), edd_currency_filter( edd_format_amount( $newprice ) ), $args[ 'text' ] );

		return $args;
	}

	public function edd_purchase_variable_prices( $prices, $download_id ) {
		if ( !is_array( $prices ) ) {
			return $prices;
		}

		foreach ( $prices as $key => $test ) {
			$cart = array(
				0 => array(
					'id' => $download_id,
					'quantity' => 1,
					'options' => array(
						'price_id' => $key
					),
				)
			);

			$discount = $this->get_discount( $cart );
			if ( $discount ) {
				$new_price = $test[ 'amount' ] - $discount;
				$prices[ $key ][ 'amount' ] = $new_price;
			}
		}
		return $prices;
	}

	public function edd_price_top( $price, $download_id, $key ) {
		if ( !$key ) {
			$variable_pricing = edd_has_variable_prices( $download_id );

			if ( $variable_pricing ) {
				return $price;
			}

			$cart = array(
				0 => array(
					'id' => $download_id,
					'quantity' => 1,
					'options' => array(
						'price_id' => $key
					),
				)
			);

			$discount = $this->get_discount( $cart );

			if ( !$discount ) {
				return $price;
			}


			$prices = edd_get_variable_prices( $download_id );

			$option = edd_get_option( 'edd_dp_old_price_text', __( 'Old Price:', 'edd_dp' ) );

			$line = '<span class="old-priced-container"><span class="old-price-title">' . $option . ' </span><s class="old-price">' . edd_currency_filter( edd_format_amount( $prices[ $key ]['amount'] ) ) . '</s> </span>';

			$line = apply_filters( 'edd_dp_edd_price_top', $line, $key, $prices[ $key ]['amount'], $download_id );

			return $line . $price;
		}
		else {
			$cart = array(
				0 => array(
					'id' => $download_id,
					'quantity' => 1,
				)
			);

			$discount = $this->get_discount( $cart );

			if ( !$discount ) {
				return $price;
			}

			$prices = edd_get_download_price( $download_id );

			$option = edd_get_option( 'edd_dp_old_price_text',  __( 'Old Price:', 'edd_dp' ) );

			$line = '<span class="old-priced-container"><span class="old-price-title">' . $option . ' </span><s class="old-price">' . edd_currency_filter( edd_format_amount( $prices ) ) . '</s> </span>';

			$line = apply_filters( 'edd_dp_edd_price_top', $line, $key, $prices, $download_id );

			return $line . $price;
		}
	}

	public function edd_price( $price, $download_id, $key = false ) {
		if ( !$key ) {
			$variable_pricing = edd_has_variable_prices( $download_id );

			if ( $variable_pricing ) {
				return $price;
			}

			$cart = array(
				0 => array(
					'id' => $download_id,
					'quantity' => 1
				)
			);

			$discount = $this->get_discount( $cart );

			if ( !$discount ) {
				return $price;
			}
			return edd_sanitize_amount( $price - $discount );
		}
		else {
			$cart = array(
				0 => array(
					'id' => $download_id,
					'quantity' => 1,
					'options' => array(
						'price_id' => $key
					),
				)
			);

			$discount = $this->get_discount( $cart );

			if ( !$discount ) {
				return $price;
			}

			return edd_sanitize_amount( $price - $discount );
		}

	}

	public function get_discount( $cart = array(), $customer_id = false, $apply = false ) {
		if ( empty( $cart ) ) {
			$cart   = edd_get_cart_contents();
		}

		if ( ! $customer_id ) {
			$customer_id = get_current_user_id();
		}

		$args = array( 'post_type' => 'customer_discount', 'post_status' => 'publish', 'posts_per_page' => -1 );
		$query = new WP_Query( $args );
		$discounts = array();
		$amount = 0.00;
		if ( empty( $query->posts ) ) {
			return array();
		}
		foreach ( $query->posts as $id => $post ) {
			$data = get_post_meta( $post->ID, 'frontend', true );
			$discounts[$id]['name']       = isset( $post->post_title )   ? $post->post_title         : 'Discount'    ;
			$discounts[$id]['id']         = isset( $post->ID )           ? $post->ID                 : false         ;
			$discounts[$id]['type']       = isset( $data['type'] )       ? $data['type']             : 'fixed_price' ;
			$discounts[$id]['quantity']   = isset( $data['quantity'] )   ? (int) $data['quantity']   : 0             ;
			$discounts[$id]['value']      = isset( $data['value'] )      ? $data['value']            : 0             ;
			$discounts[$id]['products']   = isset( $data['products'] )   ? $data['products']         : array()       ;
			if ( is_string( $discounts[$id]['products'] ) ) {
				$discounts[$id]['products'] = empty( $discounts[$id]['products'] ) ? array() : explode( ',', $discounts[$id]['products'] );
			}
			$discounts[$id]['categories'] = isset( $data['categories'] ) ? $data['categories']       : array()       ;
			$discounts[$id]['tags']       = isset( $data['tags'] )       ? $data['tags']             : array()       ;
			$discounts[$id]['users']      = isset( $data['users'] )      ? $data['users']            : array()       ;
			$discounts[$id]['groups']     = isset( $data['groups'] )     ? $data['groups']           : array()       ;
			$discounts[$id]['start']      = isset( $data['start'] )      ? $data['start']            : false         ;
			$discounts[$id]['end']        = isset( $data['end'] )        ? $data['end']              : false         ;
			$discounts[$id]['cust']       = isset( $data['cust'] )       ? $data['cust']             : false         ;
			$discounts[$id]['amount']     = $this->calculate_discount( $discounts[$id], $cart, $customer_id );
		}

		if ( $discounts && is_array( $discounts ) ) {
			// sort discounts so the discount that saves the most is on the top of the array
			$price = array();
			foreach ( $discounts as $key => $row ) {
				$discount[$key] = $row['amount'];
			}
			array_multisort( $discount, SORT_DESC, $discounts );

			// Debugging help: if you var_dump right here, you'll get a nice array with *all* the discounts
			//        and how much each customer_discount would discount the item
			// var_dump($discounts);
			if ( isset( $discounts[0] ) ) {
				if ( $apply ) {
					$amount = $this->calculate_discount( $discounts[0], $cart, $customer_id, $apply );
				} else {
					$amount = $discounts[0]['amount'];
				}
			}
		}
		return $amount;
	}

	private function get_discount_amount( $discount, $quantity = 1, $item_price = 0.00 ) {
		$amount = 0;
		if ( strpos( $discount['value'], '%' ) !== false || $discount['type'] == 'percentage_price' ) {
			// Percentage value
			$val = round( ( (float) $discount['value'] ) / 100, 2 );
			$amount = $item_price * $val * $quantity;
		} else {
			// Fixed value
			$amount = (float) $discount['value'] * $quantity;
		}
		return $amount;
	}

	private function is_applicable( $discount, $customer, $product ) {

		// take id and make WP_User
		$customer = new WP_User( $customer );
		if ( isset( $product['options'] ) && isset( $product['options']['price_id'] ) ) {
			$product['compare_id'] = $product['id'] .'_'. $product['options']['price_id'];
		} else {
			$product['compare_id'] = $product['id'];
		}

		// Check if discount is applicable to the product
		if ( ! empty( $discount['products'] ) && !in_array( $product['compare_id'], $discount['products'] ) ) {
			return false;
		}

		// Check if it is applicable to current user
		if ( !empty( $discount['users'] ) && is_array( $discount['users'] ) && ( !$customer || !in_array( $customer->ID, $discount['users'] ) ) ) {
			return false;
		}

		// Check if current user is in an applicable group
		if ( !empty( $discount['groups'] ) && ( !$customer || array_intersect( $customer->roles, $discount['groups'] ) == array() ) ) {
			return false;
		}

		$product['categories'] = wp_get_post_terms( $product['id'], 'download_category', array(
				'fields' => 'ids'
			) );

		$product['tags'] = wp_get_post_terms( $product['id'], 'download_tag', array(
				'fields' => 'ids'
			) );

		// Check if product is in a category of discount
		if ( ! empty( $discount['categories'] ) ) {
			if ( ! empty( $product['categories'] ) ) {
				if ( array_intersect( $product['categories'], $discount['categories'] ) == array() ) {
					return false;
				}
			}
			else {
				return false;
			}
		}

		// Check if product is in a category of discount
		if ( !empty( $discount['tags'] ) ) {
			if ( !empty( $product['tags'] ) ) {
				if ( array_intersect( $product['tags'], $discount['tags'] ) == array() ) {
					return false;
				}
			}
			else {
				return false;
			}
		}

		// check start and end dates
		if ( $discount['start'] !== '' && strtotime( $discount['start'] ) > strtotime( "now" ) ) {
			return false;
		}

		if ( $discount['end'] !== '' && strtotime( $discount['end'] ) < strtotime( "now" ) ) {
			return false;
		}

		// if discount is only for previous customers and customer does not have any previous purchases
		if ( $discount['cust'] && $customer ) {
			if ( !edd_has_purchases( $customer->ID ) ) {
				return false;
			}
		}

		return true;
	}

	private function calculate_discount( $discount, $cart, $customer, $apply = false  ) {
		// get applicable items
		$applicable_items = array();
		foreach ( $cart as $key => $item ) {
			// if is_applicable
			if ( $this->is_applicable( $discount, $customer, $item ) ) {
				$applicable_items["$key"] = $item;
				if ( !isset( $applicable_items["$key"]['options'] ) ){
					$applicable_items["$key"]['options'] = array();
				}
			}
		}

		// now $applicable_items holds all the items this discount is generally valid for
		// we now need to check to see if this cart meets the discount requirements and remove any items that aren't valid after the discount
		$is_valid = false;
		switch ( $discount['type'] ) {
			// discount based on number of products in cart
		case 'cart_quantity':
			$quantity = 0;
			foreach ( $applicable_items as $key => $item ) {

				$price_id = isset( $item['options']['price_id'] ) ? $item['options']['price_id'] : null;
				if( edd_is_free_download( $item['id'], $price_id ) ) {
					unset( $applicable_items[ $key ] );
					continue;
				}

				$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
				$quantity += $cart_quantity;
			}
			if ( $quantity >= $discount['quantity'] ) {
				$is_valid = true;
			}
			break;
			// discount based on cart price
		case 'cart_threshold':
			$total = 0; // amount of applicable cart value
			foreach ( $applicable_items as $key => $item ) {
				$item_price = edd_get_cart_item_price( $item['id'], $item['options'], true );
				$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
				$total += $item_price * $cart_quantity;
			}
			if ( $total >= $discount['quantity'] ) {
				$is_valid = true;
			}
			break;
			// discount for quantities of a product
		case 'product_quantity':
			foreach ( $applicable_items as $key => $item ) {
				$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
				if ( $cart_quantity < $discount['quantity'] ) {
					unset( $applicable_items[$key] );
				}
			}
			if ( !empty( $applicable_items ) ) {
				$is_valid = true;
			}
			break;

		case 'each_x_products':
		case 'from_x_products':
			if ( count( $applicable_items ) >= $discount['quantity'] ) {
				$is_valid = true;
			}
			break;
		case 'fixed_price':
		case 'percentage_price':
		default:
			if ( !empty( $applicable_items ) ) {
				$is_valid = true;
			}
			break;
		}

		if ( !$is_valid ) {
			return 0;
		}

		// now that we have only the applicable ones, let's calculate the discount for each item in the applicable_items array
		$total_discount = 0.00;
		$total_applicable_value = 0.00;

		// we first need to know how large the applicable cart is
		foreach ( $applicable_items as $key => $item ) {
			$item_price = edd_get_cart_item_price( $item['id'], $item['options'], true );
			$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
			$total_applicable_value += ( $item_price * $cart_quantity );
		}

		if ( $total_applicable_value == 0 ){
			return 0;
		}

		// then find each item's weight ( $10 of 100 is 0.1. 0.1 is the weight. )
		foreach ( $applicable_items as $key => $item ) {
			$item_price = edd_get_cart_item_price( $item['id'], $item['options'], true );
			$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
			$applicable_items[$key]['weight'] = ( $item_price * $cart_quantity ) / $total_applicable_value;
		}

		switch ( $discount['type'] ) {
			// discount for quantities of a product
		case 'product_quantity':
			foreach ( $applicable_items as $key => $item ) {
				$item_price = edd_get_cart_item_price( $item['id'], $item['options'], true );
				$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
				if ( $cart_quantity >= $discount['quantity'] ) {
					$value = $this->get_discount_amount( $discount, $cart_quantity, $item_price );
					$total_discount += $value;
					$applicable_items[$key]['value'] = $value;
				}
			}
			break;
			// todo revisit this logic in terms of commissions
		case 'each_x_products':
			$count = 1;
			foreach ( $applicable_items as $key => $item ) {
				$item_price = edd_get_cart_item_price( $item['id'], $item['options'], true );
				$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
				if ( $count == $discount['quantity'] ) {
					$value = $this->get_discount_amount( $discount, $cart_quantity, $item_price );
					$total_discount += $value;
					$applicable_items[$key]['value'] = $value;
					$count = 1;
				}
				else {
					$count++;
				}
			}
			break;
			// todo revisit this logic in terms of commissions
		case 'from_x_products':
			$count = 1;
			foreach ( $applicable_items as $key => $item ) {
				$item_price = edd_get_cart_item_price( $item['id'], $item['options'], true );
				$cart_quantity   = edd_get_cart_item_quantity( $item['id'], $item['options'] );
				// if the cart quantity plus the next download's quantity is over the threshold
				if ( $count >= $discount['quantity'] ) {
					$value = $this->get_discount_amount( $discount, $cart_quantity, $item_price );
					$total_discount += $value;
					$applicable_items[$key]['value'] = $value;
				}
				$count++;
			}
			break;
		case 'cart_quantity':
		case 'cart_threshold':
		case 'fixed_price':
		case 'percentage_price':
		default:
			foreach ( $applicable_items as $key => $item ) {
				$checkout_exclusive = 'yes' === edd_get_option( 'checkout_include_tax' ) ? false : true;
				$item_price         = edd_get_cart_item_price( $item['id'], $item['options'], $checkout_exclusive );

				if ( ! $checkout_exclusive && ! edd_prices_include_tax() ) {
					$item_price += edd_get_cart_item_tax( $item['id'], $item['options'], $item_price );
				}

				$cart_quantity                       = edd_get_cart_item_quantity( $item['id'], $item['options'] );
				$value                               = $this->get_discount_amount( $discount, $cart_quantity, $item_price );
				$total_discount                      += $value;
				$applicable_items[ $key ][ 'value' ] = $value;
			}
			break;
		}

		// if apply is on, then loop through and add discounts
		if ( $apply && $total_discount > 0  ) {
			foreach ( $applicable_items as $key => $item ) {
				if ( isset( $item['value'] ) && $item['value'] > 0 ) {
					EDD()->fees->add_fee( array(
							'amount'      => -1* $item['value'],
							'label'       => $discount['name']. ' - ' . get_the_title( $item['id'] ),
							'id'          => 'dp_' . $key,
							'download_id' => $item['id'],
							'price_id'    => isset( $item['options']['price_id'] ) ? $item['options']['price_id'] : null,
						) );
				}
			}
		}

		return $total_discount < 0 ? 0 : $total_discount;
	}

	public function remove_dp_fees() {

		$fees = EDD()->fees->get_fees( 'fee' );
		if ( empty( $fees ) ) {
			return;
		}

		foreach ( $fees as $key => $fee ) {

			if ( false === strpos( $key, 'dp' ) ) {
				continue;
			}

			unset( $fees[ $key ] );

		}

		EDD()->session->set( 'edd_cart_fees', $fees );

	}

	public function apply_discount() {
		$cart_items  = edd_get_cart_contents();
		$customer_id = get_current_user_id();
		// for some reason removing the first cart item makes the contents empty
		if ( empty( $cart_items ) ) {
			return;
		}
		// remove old discount
		$this->remove_dp_fees();
		// get new discount
		$discount = $this->get_discount( $cart_items, $customer_id, true );
	}

	/**
	 * JS to update checkout when quantity is updated
	 *
	 * @since 1.3
	 *
	 * @access public
	 * @return void
	 */
	public function checkout_js() {
		if ( ! edd_is_checkout() ) {
			return;
		}
?>
		<script type="text/javascript">
		var edd_global_vars;
		jQuery(document).ready(function($) {
			$('body').on( 'edd_quantity_updated', function() {
				$.ajax({
					type: "POST",
					data: {
						action: 'edd_recalculate_discounts_pro'
					},
					dataType: "json",
					url: edd_global_vars.ajaxurl,
					xhrFields: {
						withCredentials: true
					},
					success: function (response) {
						$('#edd_checkout_cart_form').replaceWith(response.html);
						$('.edd_cart_amount').html(response.total);
					}
				}).fail(function (data) {
					if ( window.console && window.console.log ) {
						console.log( data );
					}
				});
			});
		});
		</script>
	<?php
	}
	/**
	 * Ajax callback to retrieve cart HTML
	 *
	 * @since 1.3
	 *
	 * @access public
	 * @return void
	 */
	public function recalculate_discounts() {
		ob_start();
		edd_checkout_cart();
		$cart = ob_get_clean();
		$response = array(
			'html'  => $cart,
			'total' => html_entity_decode( edd_cart_total( false ), ENT_COMPAT, 'UTF-8' ),
		);
		echo json_encode( $response );
		edd_die();
	}
}
