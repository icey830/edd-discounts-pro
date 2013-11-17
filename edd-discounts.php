<?php
/**
 * Plugin Name:         EDD Discounts Pro
 * Description:         VooDoo magic that might just do something on nonvariable products.
 * Version:             1.0
 * Author:              Chris Christoff
 * Author URI:          http://www.chriscct7.com
 */
add_action( 'plugins_loaded', 'init_edd_discounts' );
function init_edd_discounts() {
	if ( class_exists( 'Easy_Digital_Downloads' ) ) {
		if ( !class_exists( 'EDD_Discounts' ) ) {
			class EDD_Discounts {
				private $_discountTypes;
				private $_discounts;
				public function __construct() {
					$this->_discountTypes = array(
						 'fixed_price' => __( 'Fixed Price', 'edd_discounts' ),
						'percentage_price' => __( 'Percentage Price', 'edd_discounts' ),
						'product_quantity' => __( 'Product Quantity', 'edd_discounts' ),
						'each_x_products' => __( 'Each X products', 'edd_discounts' ),
						'from_x_products' => __( 'From X products', 'edd_discounts' ),
						'cart_quantity' => __( 'Products in cart', 'edd_discounts' ),
						'cart_threshold' => __( 'Cart threshold', 'edd_discounts' ) 
					);
					if ( !is_admin() ) {
						add_filter( 'edd_download_price', array(
							 $this,
							'getPrice' 
						), 10, 2 );
					} else {
						add_action( 'admin_print_scripts', array(
							 $this,
							'adminScripts' 
						) );
						add_action( 'admin_enqueue_scripts', array(
							 $this,
							'adminStyles' 
						) );
						add_action( 'admin_menu', array(
							 $this,
							'discount_submenu' 
						), 11 );
						add_action( 'add_meta_boxes', array(
							 $this,
							'discount_metabox' 
						) );
						add_action( 'save_post', array(
							 $this,
							'save_discount' 
						) );
						add_action( 'wp_ajax_edd_json_search_products', array(
							 $this,
							'edd_json_search_products' 
						) );
						add_action( 'wp_ajax_edd_json_search_products_and_variations', array(
							 $this,
							'ajaxSearchProductsVariations' 
						) );
						add_filter( 'manage_edit-customer_discount_columns', array(
							 $this,
							'adminColumns' 
						) );
						add_action( 'manage_customer_discount_posts_custom_column', array(
							 $this,
							'adminColumn' 
						), 10, 2 );
						register_post_type( 'customer_discount', array(
							 'labels' => array(
								 'menu_name' => __( 'Discounts', 'edd_discounts' ),
								'name' => __( 'Discounts', 'edd_discounts' ),
								'singular_name' => __( 'Discount', 'edd_discounts' ),
								'add_new' => __( 'Add Discount', 'edd_discounts' ),
								'add_new_item' => __( 'Add New Discount', 'edd_discounts' ),
								'edit' => __( 'Edit', 'edd_discounts' ),
								'edit_item' => __( 'Edit Discount', 'edd_discounts' ),
								'new_item' => __( 'New Discount', 'edd_discounts' ),
								'view' => __( 'View Discounts', 'edd_discounts' ),
								'view_item' => __( 'View Discount', 'edd_discounts' ),
								'search_items' => __( 'Search Discounts', 'edd_discounts' ),
								'not_found' => __( 'No Discounts found', 'edd_discounts' ),
								'not_found_in_trash' => __( 'No Discounts found in trash', 'edd_discounts' ),
								'parent' => __( 'Parent Discount', 'edd_discounts' ) 
							),
							'description' => __( 'This is where you can add new discounts that customers can use in your store.', 'edd_discounts' ),
							'public' => true,
							'show_ui' => true,
							'capability_type' => 'post',
							'publicly_queryable' => false,
							'exclude_from_search' => true,
							'hierarchical' => false,
							'rewrite' => false,
							'query_var' => true,
							'supports' => array(
								 'title',
								'page-attributes' 
							),
							'show_in_nav_menus' => false,
							'show_in_menu' => false 
						) );
					}
				}
				public function adminScripts() {
					wp_enqueue_script( 'edd-select2', plugin_dir_url( __FILE__ ) . 'assets/js/select2.js', array(
						 'jquery' 
					), '2.1' );
				}
				public function adminStyles() {
					wp_enqueue_style( 'edd-select2', plugin_dir_url( __FILE__ ) . 'assets/css/select2.css', '', '2.1', 'screen' );
					wp_register_style( 'edd_discounts_admin', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css' );
					wp_enqueue_style( 'edd_discounts_admin' );
				}
				public function discount_submenu() {
					add_submenu_page( 'edit.php?post_type=download', __( 'Discounts PRO', 'edd_discounts' ), __( 'Discounts PRO', 'edd_discounts' ), 'manage_options', 'edit.php?post_type=customer_discount' );
				}
				public function discount_metabox() {
					add_meta_box( 'edd_discounts_data', __( 'Discount Data', 'edd_discounts' ), array(
						 $this,
						'discount_template' 
					), 'customer_discount', 'normal', 'high' );
				}
				public function discount_template( $post ) {
					require_once 'templates/admin_box.php';
				}
				public function save_discount( $postId ) {
					if ( !$_POST ) {
						return $postId;
					}
					if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
						return $postId;
					}
					if ( !isset( $_POST[ 'edd_dp_meta_nonce' ] ) || ( isset( $_POST[ 'edd_dp_meta_nonce' ] ) && !wp_verify_nonce( $_POST[ 'edd_dp_meta_nonce' ], 'edd_dp_save_meta' ) ) ) {
						return $postId;
					}
					if ( !current_user_can( 'edit_post', $postId ) ) {
						return $postId;
					}
					$type     = strip_tags( stripslashes( trim( ( $_POST[ 'type' ] ) ) ) );
					$quantity = strip_tags( stripslashes( trim( ( $_POST[ 'quantity' ] ) ) ) );
					$value    = strip_tags( stripslashes( trim( ( $_POST[ 'value' ] ) ) ) );
					if ( in_array( $type, array(
						 'fixed_price',
						'percentage_price' 
					) ) ) {
						$value = (float) rtrim( $value, '%' );
					}
					$free_shipping = isset( $_POST[ 'free_shipping' ] );
					if ( isset( $_POST[ 'products' ] ) ) {
						$products = strip_tags( stripslashes( trim( ( $_POST[ 'products' ] ) ) ) );
						if ( $products == 'Array' ) {
							$products = '';
						}
						$products = $products != '' ? explode( ',', $products ) : array();
					} else {
						$products = array();
					}
					if ( isset( $_POST[ 'categories' ] ) ) {
						$categories = $_POST[ 'categories' ];
					} else {
						$categories = array();
					}
					if ( isset( $_POST[ 'users' ] ) ) {
						$users = $_POST[ 'users' ];
					} else {
						$users = array();
					}
					if ( isset( $_POST[ 'groups' ] ) ) {
						$groups = $_POST[ 'groups' ];
					} else {
						$groups = array();
					}
					update_post_meta( $postId, 'type', $type );
					update_post_meta( $postId, 'quantity', $quantity );
					update_post_meta( $postId, 'value', $value );
					update_post_meta( $postId, 'free_shipping', $free_shipping );
					update_post_meta( $postId, 'products', $products );
					update_post_meta( $postId, 'categories', $categories );
					update_post_meta( $postId, 'users', $users );
					update_post_meta( $postId, 'groups', $groups );
				}
				public function adminColumns( $columns ) {
					$new_columns[ 'cb' ]     = '<input type="checkbox" />';
					$new_columns[ 'title' ]  = __( 'Name', 'edd_discounts' );
					$new_columns[ 'type' ]   = __( 'Type', 'edd_discounts' );
					$new_columns[ 'value' ]  = __( 'Value', 'edd_discounts' );
					$new_columns[ 'users' ]  = __( 'Users', 'edd_discounts' );
					$new_columns[ 'groups' ] = __( 'Groups', 'edd_discounts' );
					$new_columns[ 'date' ]   = __( 'Date', 'edd_discounts' );
					return $new_columns;
				}
				public function adminColumn( $column, $postId ) {
					switch ( $column ) {
						case 'type':
							$type = get_post_meta( $postId, 'type', true );
							echo count( $type ) == 1 ? $this->getDiscountType( $type ) : '-';
							break;
						case 'value':
							$value = get_post_meta( $postId, 'value', true );
							echo $value ? $value : '-';
							break;
						case 'users':
							$ids = get_post_meta( $postId, 'users', true );
							if ( empty( $ids ) ) {
								return;
							}
							$links = '';
							$users = get_users( array(
								 'include' => $ids,
								'fields' => array(
									 'ID',
									'display_name' 
								) 
							) );
							foreach ( $users as $item ) {
								$links .= '<a href="/wp-admin/user-edit.php?user_id=' . $item->ID . '">' . $item->display_name . '</a>, ';
							}
							echo rtrim( $links, ', ' );
							break;
						case 'groups':
							$groups = get_post_meta( $postId, 'groups', true );
							if ( empty( $groups ) ) {
								return;
							}
							$links  = '';
							$groups = $this->getRoles( array(
								 'include' => $groups 
							) );
							foreach ( $groups as $role => $name ) {
								$links .= '<a href="/wp-admin/user-edit.php?user_id=' . $role . '">' . $name . '</a>, ';
							}
							echo rtrim( $links, ', ' );
							break;
					}
				}
				public function edd_json_search_products( $x = '', $post_types = array( 'download' ) ) {
					check_ajax_referer( 'search-products', 'security' );
					$term = (string) urldecode( stripslashes( strip_tags( $_GET[ 'term' ] ) ) );
					if ( empty( $term ) )
						die();
					if ( strpos( $term, ',' ) !== false ) {
						$term     = (array) explode( ',', $term );
						$args     = array(
							 'post_type' => $post_types,
							'post_status' => 'publish',
							'posts_per_page' => -1,
							'post__in' => $term,
							'fields' => 'ids' 
						);
						$products = get_posts( $args );
					} elseif ( is_numeric( $term ) ) {
						$args     = array(
							 'post_type' => $post_types,
							'post_status' => 'publish',
							'posts_per_page' => -1,
							'post__in' => array(
								 0,
								$term 
							),
							'fields' => 'ids' 
						);
						$products = get_posts( $args );
					} else {
						$args     = array(
							 'post_type' => $post_types,
							'post_status' => 'publish',
							'posts_per_page' => -1,
							's' => $term,
							'fields' => 'ids' 
						);
						$products = get_posts( $args );
					}
					$found_products = array();
					if ( !empty( $products ) )
						foreach ( $products as $product_id ) {
							if ( edd_has_variable_prices( $product_id ) ) {
								$prices = edd_get_variable_prices( $product_id );
								foreach ( $prices as $key => $value ) {
									$found_products[] = array(
										 'id' => $product_id . '_' . $key,
										'text' => html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ) . ' (' . html_entity_decode( $value[ 'name' ], ENT_COMPAT, 'UTF-8' ) . ' )' 
									);
								}
							} else {
								// If the customer turned on EDD's sku field
								$SKU = get_post_meta( $product_id, 'edd_sku', true );
								if ( isset( $SKU ) && $SKU )
									$SKU = ' (SKU: ' . $SKU . ')';
								else
									$SKU = ' (ID: ' . $product_id . ')';
								$found_products[] = array(
									 'id' => $product_id,
									'text' => html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ) . $SKU 
								);
							}
						}
					echo json_encode( $found_products );
					die();
				}
				public function ajaxSearchProductsVariations() {
					$this->edd_json_search_products( '', array(
						 'download' 
					) );
				}
				public function getUsersWithGroups() {
					global $wp_roles;
					$roles = array();
					foreach ( $wp_roles->role_names as $role => $role_name ) {
						$users = get_users( array(
							 'role' => $role 
						) );
						foreach ( $users as $user ) {
							$roles[ $role ][ $user->ID ] = $user->display_name;
						}
					}
					return $roles;
				}
				public function getRoles( $args = array() ) {
					global $wp_roles;
					$defaults = array(
						 'include' => array ()
					);
					$args     = array_merge( $defaults, $args );
					$roles    = array();
					if ( empty( $args[ 'include' ] ) ) {
						foreach ( $wp_roles->role_names as $role => $role_name ) {
							$roles[ $role ] = $role_name;
						}
					} else {
						foreach ( $wp_roles->role_names as $role => $role_name ) {
							if ( in_array( $role, $args[ 'include' ] ) ) {
								$roles[ $role ] = $role_name;
							}
						}
					}
					return $roles;
				}
				public function getUsers() {
					$users_data = get_users();
					$users      = array();
					foreach ( $users_data as $user ) {
						$users[ $user->ID ] = $user->display_name;
					}
					return $users;
				}
				public function getDiscountTypes() {
					return $this->_discountTypes;
				}
				public function getDiscountType( $key ) {
					return isset( $this->_discountTypes[ $key ] ) ? $this->_discountTypes[ $key ] : null;
				}
				public function getDiscountValue( $discount, $value ) {
					switch ( $discount->type ) {
						case 'fixed_price':
							return edd_price( $value );
						case 'percentage_price':
							return "$value&#37;";
					}
					return '';
				}
				public function getCustomerDiscounts( $product, $customerId = false ) {
					if ( $this->_discounts === null ) {
						$this->_getDiscounts();
					}
					if ( !$customerId ) {
						$customerId = get_current_user_id();
					}
					$product  = new EDD_DCF_Product( $product );
					$customer = false;
					if ( $customerId != 0 ) {
						$customer = get_userdata( $customerId );
					}
					$discounts = array();
					foreach ( $this->_discounts as $item ) {
						if ( $this->_isApplicable( $item, $product, $customer ) ) {
							$discounts[] = $item;
						}
					}
					// Sort discounts by their "power ranking"
					$that = $this;
					create_function( '$a, $b', 'global $that, $product;$aPrice = $that->calculatePrice($a, $product, $product->price);$bPrice = $that->calculatePrice($b, $product, $product->price);return $aPrice == $bPrice ? 0 : ($aPrice > $bPrice ? 1 : -1);' );
					// #BlamePippin
					//usort($discounts, function ($a, $b) use ($that, $product)
					//{
					//	$aPrice = $that->calculatePrice($a, $product, $product->price);
					//	$bPrice = $that->calculatePrice($b, $product, $product->price);
					//
					//	return $aPrice == $bPrice ? 0 : ($aPrice > $bPrice ? 1 : -1);
					//});
					return $discounts;
				}
				public function calculatePrice( $discount, $productObject, $price ) {
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
							if ( $this->_hasProduct( $productObject, $discount ) ) {
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
							$quantity = $this->_getProductQuantity( $productObject, $discount );
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
							$quantity = $this->_getProductQuantity( $productObject, $discount );
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
					return $price < 0 ? 0 : $price;
				}
				public function getPrice( $price, $productId ) {
					$discounts = $this->getCustomerDiscounts( $productId );
					$product   = new EDD_DCF_Product( $productId );
					if ( !empty( $discounts ) ) {
						$discount = array_shift( $discounts );
						$price    = $this->calculatePrice( $discount, $product, $price );
						$price    = apply_filters( 'edd_multi_currencies_exchange', $price );
						return $price;
					} else {
						return $price;
					}
				}
				private function _getProductQuantity( $product, $discount ) {
					$quantity = 0;
					$cart     = edd_get_cart_contents();
					foreach ( $cart as $item ) {
						// Specified product || Simple product || Variable product
						if ( $item[ 'product_id' ] == $product->id || in_array( $item[ 'product_id' ], $discount->products ) ) {
							$quantity += $item[ 'quantity' ];
						}
					}
					return $quantity;
				}
				private function _hasProduct( $product, $discount ) {
					foreach ( edd_cart::get_cart() as $item ) {
						if ( $item[ 'product_id' ] == $product->id || in_array( $item[ 'product_id' ], $discount->products ) ) {
							return true;
						}
					}
					return false;
				}
				private function _getDiscounts() {
					$this->_discounts = array();
					$discounts        = get_posts( array(
						 'numberposts' => -1,
						'post_type' => 'customer_discount' 
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
				private function _isApplicable( $discount, $product, $customer ) {
					// Check if discount is applicable to the product
					if ( !empty( $discount->products ) && !in_array( $product, $discount->products ) ) {
						return false;
					}
					// Check if product matches quantity discounts
					switch ( $discount->type ) {
						case 'cart_quantity':
							$quantity = 0;
							$cart     = edd_get_cart_contents();
							foreach ( $cart as $item ) {
								$quantity += $item[ 'quantity' ];
							}
							if ( $quantity < $discount->quantity ) {
								return false;
							}
							break;
						case 'product_quantity':
							$quantity = 0;
							foreach ( edd_cart::get_cart() as $cartItem ) {
								// Simple products
								if ( $cartItem[ 'product_id' ] == $product->id && ( empty( $discount->products ) || in_array( $cartItem[ 'product_id' ], $discount->products ) ) ) {
									$quantity += $cartItem[ 'quantity' ];
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
					if ( !empty( $discount->groups ) && ( !$customer || array_intersect( $customer->roles, $discount->groups ) == array ()) ) {
						return false;
					}
					// Check if product is in a category of discount
					if ( !empty( $discount->categories ) && !empty( $product->categories ) ) {
						if ( array_intersect( $product->categories, $discount->categories ) == array ()) {
							return false;
						}
					}
					return true;
				}
			} 
			$EDD_Discounts = new EDD_Discounts();
		}
	}
}
class EDD_CF_Forms {
	public static function input( $field ) {
		global $post;
		$args = array(
			 'id' => null,
			'name' => null,
			'type' => 'text',
			'label' => null,
			'after_label' => null,
			'class' => 'short',
			'desc' => false,
			'tip' => false,
			'value' => null,
			'min' => null,
			'max' => null,
			'step' => 'any',
			'placeholder' => null 
		);
		extract( wp_parse_args( $field, $args ) );
		$value = isset( $value ) ? esc_attr( $value ) : get_post_meta( $post->ID, $id, true );
		$name  = isset( $name ) ? $name : $id;
		$html  = '';
		$html .= "<p class='form-field {$id}_field'>";
		$html .= "<label for='{$id}'>$label{$after_label}</label>";
		$html .= "<input type='{$type}' id='{$id}' name='{$name}' class='{$class}'";
		$html .= " value='{$value}'";
		if ( $type == 'number' ) {
			if ( !empty( $min ) )
				$html .= " min='{$min}'";
			if ( !empty( $max ) )
				$html .= " max='{$max}'";
			if ( !empty( $step ) )
				$html .= " step='{$step}'";
		}
		$html .= " placeholder='{$placeholder}' />";
		if ( $tip ) {
			$html .= '<a href="#" tip="' . $tip . '" class="tips" tabindex="99"></a>';
		}
		if ( $desc ) {
			$html .= '<span class="description">' . $desc . '</span>';
		}
		$html .= "</p>";
		return $html;
	}
	public static function select( $field ) {
		global $post;
		$args = array(
			 'id' => null,
			'name' => null,
			'label' => null,
			'after_label' => null,
			'class' => 'select short',
			'desc' => false,
			'tip' => false,
			'multiple' => false,
			'placeholder' => '',
			'options' => array(),
			'selected' => false 
		);
		extract( wp_parse_args( $field, $args ) );
		$selected = ( $selected ) ? (array) $selected : (array) get_post_meta( $post->ID, $id, true );
		$name     = isset( $name ) ? $name : $id;
		$name     = ( $multiple ) ? $name . '[]' : $name;
		$multiple = ( $multiple ) ? 'multiple="multiple"' : '';
		$desc     = ( $desc ) ? esc_html( $desc ) : false;
		$html     = '';
		$html .= "<p class='form-field {$id}_field'>";
		$html .= "<label for='{$id}'>$label{$after_label}</label>";
		$html .= "<select {$multiple} id='{$id}' name='{$name}' class='{$class}' data-placeholder='{$placeholder}'>";
		foreach ( $options as $value => $label ) {
			if ( is_array( $label ) ) {
				$html .= '<optgroup label="' . esc_attr( $value ) . '">';
				foreach ( $label as $opt_value => $opt_label ) {
					$mark = '';
					if ( in_array( $opt_value, $selected ) ) {
						$mark = 'selected="selected"';
					}
					$html .= '<option value="' . esc_attr( $opt_value ) . '"' . $mark . '>' . $opt_label . '</option>';
				}
				$html .= '</optgroup>';
			} else {
				$mark = '';
				if ( in_array( $value, $selected ) ) {
					$mark = 'selected="selected"';
				}
				$html .= '<option value="' . esc_attr( $value ) . '"' . $mark . '>' . $label . '</option>';
			}
		}
		$html .= "</select>";
		if ( $tip ) {
			$html .= '<a href="#" tip="' . $tip . '" class="tips" tabindex="99"></a>';
		}
		if ( $desc ) {
			$html .= '<span class="description">' . $desc . '</span>';
		}
		$html .= "</p>";
		$html .= '<script type="text/javascript">
					/*<![CDATA[*/
						jQuery(function() {
							jQuery("#' . $id . '").select2();
						});
					/*]]>*/
					</script>';
		return $html;
	}
	public static function checkbox( $field ) {
		global $post;
		$args = array(
			 'id' => null,
			'name' => null,
			'label' => null,
			'after_label' => null,
			'class' => 'checkbox',
			'desc' => false,
			'tip' => false,
			'value' => false 
		);
		extract( wp_parse_args( $field, $args ) );
		$name  = isset( $name ) ? $name : $id;
		$value = ( $value ) ? $value : get_post_meta( $post->ID, $id, true );
		$desc  = ( $desc ) ? esc_html( $desc ) : false;
		$mark  = checked( $value, 1, false );
		$html  = '';
		$html .= "<p class='form-field {$id}_field'>";
		$html .= "<label for='{$id}'>$label{$after_label}</label>";
		$html .= "<input type='checkbox' name='{$name}' class='{$class}' id='{$id}' {$mark} />";
		if ( $desc ) {
			$html .= "<label for='{$id}' class='description'>$desc</label>";
		}
		if ( $tip ) {
			$html .= '<a href="#" tip="' . $tip . '" class="tips" tabindex="99"></a>';
		}
		$html .= "</p>";
		return $html;
	}
}
class EDD_DCF_Product {
	public $id = 0;
	public $categories = '';
	public $price = 0;
	public function __construct( $product ) {
		$this->categories = wp_get_post_terms( $product, 'download_category', array(
			 'fields' => 'ids' 
		) );
		$this->price      = edd_price( $product, false );
	}
}