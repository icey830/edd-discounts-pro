<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_DP_Admin {
	public function __construct() {
		if ( ! is_admin() ){
			return;
		}
		add_action( 'add_meta_boxes', array( $this, 'edd_remove_all_the_metaboxes' ), 100 );
		add_filter( 'post_updated_messages', array( $this, 'form_updated_message' ) );
		add_action( 'add_meta_boxes', array( $this, 'discount_metabox' ) );
		add_action( 'save_post', array( $this, 'save_discount' ) );
		add_action( 'wp_ajax_edd_json_search_products', array( $this, 'edd_json_search_products' ) );
		add_action( 'wp_ajax_edd_json_search_products_and_variations', array( $this, 'ajax_search_product_vars' ) );
		add_action( 'wp_ajax_edd_json_search_users_ajax', array( $this, 'edd_json_search_users' ) );
		add_filter( 'manage_edit-customer_discount_columns', array( $this, 'columns' ) );
		add_action( 'manage_customer_discount_posts_custom_column', array( $this, 'column_value' ), 10, 2 );

		add_filter( 'edd_settings_extensions', array( $this, 'settings' ), -1 );
	}

	public function settings( $settings ) {
		$new_settings = array(
			array(
				'id'    => 'edd_dp_settings',
				'name'  => '<strong>' . __( 'Discount Pro Settings', 'edd_dp' ) . '</strong>',
				'desc'  => __( 'Configure Discount Pro Settings', 'edd_dp' ),
				'type'  => 'header',
			),
			array(
				'id'   => 'edd_dp_frontend_output_toggle',
				'name' => __( 'Show discounted price?', 'edd_dp' ),
				'desc' => __( 'If enabled, the discounted price and original price will automatically appear', 'edd_dp' ),
				'type' => 'checkbox',
			),
			array(
				'id'   => 'edd_dp_old_price_text',
				'name' => __( 'Old Price Text', 'edd_dp' ),
				'type' => 'text',
				'desc' => __( 'Enter the label for the Old Price: display', 'edd_dp' ),
				'std'  => 'Old Price:',
			),
		);

		return array_merge( $settings, $new_settings );
	}


	// Kudos thomasgriffin
	public function edd_remove_all_the_metaboxes() {

		global $wp_meta_boxes;

		// This is the post type you want to target. Adjust it to match yours.
		$post_type  = 'customer_discount';

		// These are the metabox IDs you want to pass over. They don't have to match exactly. preg_match will be run on them.
		$pass_over  = array( 'submitdiv', 'edd_discounts_data' );

		// All the metabox contexts you want to check.
		$contexts   = array( 'normal', 'advanced', 'side' );

		// All the priorities you want to check.
		$priorities = array( 'high', 'core', 'default', 'low' );

		// Loop through and target each context.
		foreach ( $contexts as $context ) {
			// Now loop through each priority and start the purging process.
			foreach ( $priorities as $priority ) {
				if ( isset( $wp_meta_boxes[$post_type][$context][$priority] ) ) {
					foreach ( (array) $wp_meta_boxes[$post_type][$context][$priority] as $id => $metabox_data ) {
						// If the metabox ID to pass over matches the ID given, remove it from the array and continue.
						if ( in_array( $id, $pass_over ) ) {
							unset( $pass_over[$id] );
							continue;
						}

						// Otherwise, loop through the pass_over IDs and if we have a match, continue.
						foreach ( $pass_over as $to_pass ) {
							if ( preg_match( '#^' . $id . '#i', $to_pass ) )
								continue;
						}

						// If we reach this point, remove the metabox completely.
						unset( $wp_meta_boxes[$post_type][$context][$priority][$id] );
					}
				}
			}
		}

	}

	public function discount_metabox() {
		add_meta_box( 'edd_discounts_data', __( 'Discount Data', 'edd_dp' ), array( $this, 'discount_template' ), 'customer_discount', 'normal', 'high'	);
	}

	public function discount_template( $post ) {
		ob_start();
		wp_nonce_field( 'edd_dp_save_meta', 'edd_dp_meta_nonce' );
		?>
		<style type="text/css">#edit-slug-box { display: none;} #minor-publishing-actions, .misc-pub-visibility{ display: none;}.quantity_field {display: none;}#postbox-container-2{ margin-top: 20px;}</style>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-type"><?php _e( 'Discount Type', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'type', true );
						$args = array(
							'id'         	   => 'edd-dp-discount-type',
							'name'        	   => 'type',
							'options'    	   => $this->get_discount_types(),
							'show_option_all'  => false,
							'show_option_none' => false,
							'selected'         => $value,
						);
						echo EDD()->html->select( $args );
						?>
						<p class="description"><?php _e( 'The type of discount', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr id="edd-dp-discount-quantity-row">
					<th scope="row" valign="top">
						<label for="edd-dp-discount-quantity"><?php _e( 'Discount Quantity', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'quantity', true );
						$args = array(
							'id'          => 'edd-dp-discount-quantity',
							'name'        => 'quantity',
							'label'       => false,
							'desc'        => false,
							'placeholder' => '0',
							'value'       => $value,
						);
						echo EDD()->html->text( $args );
						?>
						<p class="description"><?php _e( 'Enter a value, i.e. 20', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-value"><?php _e( 'Discount Value', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'value', true );
						$args = array(
							'id'          => 'edd-dp-discount-value',
							'name'          => 'value',
							'type'        => 'text',
							'placeholder' => '0.00',
							'value'       => $value,
						);
						echo EDD()->html->text( $args );
						?>
						<p class="description"><?php _e( 'Enter a value, i.e. 9.99 or 20%. For free please enter 100%.', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-products"><?php _e( 'Products', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'products', true );
						$args = array(
							'name'             => 'products[]',
							'id'               => 'edd-dp-discount-products',
							'selected'         => $value,
							'multiple'         => true,
							'chosen'           => true,
							'variations'       => true,
							'show_option_all'  => false,
							'show_option_none' => false,
							'placeholder'      => sprintf( __( 'Select one or more %s', 'edd_dp' ), edd_get_label_plural() )
						);
						echo EDD()->html->product_dropdown( $args );
						?>
						<p class="description"><?php _e( 'Control which products this discount can apply to.', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-categories"><?php _e( 'Categories', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'categories', true );
						$categories = array();
						foreach ( get_terms( 'download_category', array( 'hide_empty' => false ) ) as $category ) {
							$categories[ $category->term_id ] = $category->name;
						}
						$args = array(
							'id'          => 'edd-dp-discount-categories',
							'name'        => 'categories[]',
							'multiple'    => true,
							'chosen'      => true,
							'placeholder' => __( 'Any category', 'edd_dp' ),
							'class'       => 'select long',
							'options'     => $categories,
							'selected'    => $value,
							'show_option_all'  => false,
							'show_option_none' => false,
						);
						echo EDD()->html->select( $args );
						?>
						<p class="description"><?php _e( 'Control which product categories this discount can apply to.', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-tags"><?php _e( 'Tags', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'tags', true );
						$tags = array();
						foreach ( get_terms( 'download_tag', array( 'hide_empty' => false ) ) as $tag ) {
							$tags[ $tag->term_id ] = $tag->name;
						}
						$args = array(
							'id'          => 'edd-dp-discount-tags',
							'name'        => 'tags[]',
							'multiple'    => true,
							'chosen'      => true,
							'placeholder' => __( 'Any category', 'edd_dp' ),
							'class'       => 'select long',
							'options'     => $tags,
							'selected'    => $value,
							'show_option_all'  => false,
							'show_option_none' => false,
						);
						echo EDD()->html->select( $args );
						?>
						<p class="description"><?php _e( 'Control which product tags this discount can apply to.', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-users"><?php _e( 'Users', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'users', true );
						$args = array(
							'name'        => 'users[]',
							'id'          => 'edd-dp-discount-users',
							'selected'    => $value,
							'multiple'    => true,
							'chosen'           => true,
							'placeholder'      => __( 'Select one or more users', 'edd_dp' ),
							'show_option_all'  => false,
							'show_option_none' => false,
						);
						echo EDD()->html->user_dropdown( $args );
						?>
						<p class="description"><?php _e( 'Control which users this discount can apply to.', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-groups"><?php _e( 'Roles', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'groups', true );
						$groups = $this->get_roles();
						$args = array(
							'id'          => 'edd-dp-discount-groups',
							'name'        => 'groups[]',
							'multiple'    => true,
							'chosen'      => true,
							'placeholder' => __( 'Any roles', 'edd_dp' ),
							'class'       => 'select long',
							'options'     => $groups,
							'selected'    => $selected,
							'show_option_all'  => false,
							'show_option_none' => false,
						);
						echo EDD()->html->select( $args );
						?>
						<p class="description"><?php _e( 'Control which roles this discount can apply to.', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-start"><?php _e( 'Start Date', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'start', true );
						?>
						<input id="edd-dp-discount-start" type="text" class="datepicker" data-type="text" name="start" value="<?php echo esc_attr( $value ); ?>" size="30" />
						<p class="description"><?php _e( 'Select date when this discount may start being used. Leave blank for always on.', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-end"><?php _e( 'End Date', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'end', true );
						?>
						<input id="edd-dp-discount-end" type="text" class="datepicker" data-type="text" name="end" value="<?php echo esc_attr( $value ); ?>" size="30" />
						<p class="description"><?php _e( 'Select date when this discount may no longer be used. Leave blank for always on.', 'edd_dp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-dp-discount-previous-only"><?php _e( 'Apply for previous customers only', 'edd_dp' ); ?></label>
					</th>
					<td>
						<?php
						$value = get_post_meta( $post->ID, 'cust', true );
						$args = array(
							'name'        => 'cust',
							'id'          => 'edd-dp-discount-previous-only',
							'current'     => $value,
						);
						echo EDD()->html->checkbox( $args );
						?>
						<p class="description"><?php _e( 'When checked, only customers who have previously made purchases will be eligible for this discount.', 'edd_dp' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php $date_format = $this->dateStringToDatepickerFormat( get_option( 'date_format' ) ); ?>
		<script type="text/javascript">
			jQuery(function($) {
				$("#edd-dp-discount-start").datepicker({ dateFormat: '<?php echo $date_format; ?>' });
				$("#edd-dp-discount-end").datepicker({ dateFormat: '<?php echo $date_format; ?>' });
			});
			var quantity_help = {
				'product_quantity': "<?php _e( 'Quantity of selected product in cart to apply discount, i.e. 5.', 'edd_dp' ); ?>",
				'cart_quantity':    "<?php _e( 'Number of products in cart to apply discount, i.e. 5.', 'edd_dp' ); ?>",
				'each_x_products':  "<?php _e( 'Which product has a discount, i.e. every third is 3 in this field.', 'edd_dp' ); ?>",
				'from_x_products':  "<?php _e( 'The discount will be applied to all products that increase the cart quantity beyond this field value. Example: with a field value of 2 and a cart with four products, the third and fourth products will receive the discount while the first and second products will not.', 'edd_dp' ); ?>",
				'cart_threshold':   "<?php _e( 'Minimum cart value to apply discount.', 'edd_dp' ); ?>"
			}
			jQuery(document).ready(function ($) {
				var type_val;
				$('#edd_dp_discount_type').change(function() {
					type_val = $(this).find('option:selected').val();
					if(type_val == 'cart_quantity' || type_val == 'cart_threshold' || type_val == 'product_quantity' || type_val == 'each_x_products' || type_val == 'from_x_products') {
						$('#edd-dp-discount-quantity').parent().next('.description').html(quantity_help[type_val]);
						$('#edd-dp-discount-quantity-row').show();
					} else {
						$('#edd-dp-discount-quantity-row').hide()
					}
				});
				$('#edd_dp_discount_type').change();
			});
		</script>
		<?php
		echo ob_get_clean();
	}

	public function form_updated_message( $messages ) {
		$message = array(
			0 => '',
			1 => __( 'Discount Updated!', 'edd_dp' ),
			2 => __( 'Discount updated.', 'edd_dp' ),
			3 => __( 'Discount deleted.', 'edd_dp' ),
			4 => __( 'Discount updated.', 'edd_dp' ),
			5 => isset( $_GET['revision'] ) ? sprintf( __( 'Discount restored to revision from %s', 'edd_dp' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Discount published.', 'edd_dp' ),
			7 => __( 'Discount saved!', 'edd_dp' ),
			8 => __( 'Discount submitted.', 'edd_dp' ),
			9 => '',
			10 => __( 'Discount draft updated.', 'edd_dp' ),
		);

		$messages['customer_discount'] = $message;
		return $messages;
	}

	public function save_discount( $post_id ) {

		if ( empty( $_POST ) ) {
			return $post_id;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( ! isset( $_POST['edd_dp_meta_nonce'] ) || ( isset( $_POST['edd_dp_meta_nonce'] ) && ! wp_verify_nonce( $_POST['edd_dp_meta_nonce'], 'edd_dp_save_meta' ) ) ) {
			return $post_id;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		$type       = ! empty( $_POST['type'] )       ? array_map( "sanitize_key", array_map( "strip_tags", array_map( "stripslashes", array_map( "trim", $_POST['type'] ) ) ) ) : false;
		$quantity   = ! empty( $_POST['quantity'] )   ? absint( trim( $_POST['quantity'] ) ) : false;
		$value      = ! empty( $_POST['value'] )      ? array_map( "sanitize_key", array_map( "strip_tags", array_map( "stripslashes", array_map( "trim", $_POST['value'] ) ) ) ) : false;
		$products   = ! empty( $_POST['products'] )   ? array_map( "absint", array_map( "trim", $_POST['users'] ) ) : array();
		$categories = ! empty( $_POST['categories'] ) ? array_map( 'absint', array_map( "trim", $_POST['categories'] ) ) : array();
		$tags 	    = ! empty( $_POST['tags'] ) 	  ? array_map( 'absint', array_map( "trim", $_POST['tags'] ) ) : array();		
		$users      = ! empty( $_POST['users'] ) 	  ? array_map( "absint", array_map( "trim", $_POST['users'] ) ) : array();
		$start 		= ! empty( $_POST['start'] )  	  ? sanitize_text_field( trim( $_POST['start'] ) ) : '';
		$end   		= ! empty( $_POST['end'] )  	  ? sanitize_text_field( trim( $_POST['end'] ) ) : '';
		$cust 		= ! empty( $_POST['cust'] ) 	  ? true : false;
		$groups 	= ! empty( $_POST['groups'] ) 	  ? array_map( 'sanitize_text_field', array_map( "trim", $_POST['groups'] ) ) : array();

		$meta = array(
			'type'       => $type,
			'quantity'   => $quantity,
			'value'      => $value,
			'products'   => $products,
			'categories' => $categories,
			'tags'       => $tags,
			'users'      => $users,
			'groups'     => $groups,
			'start'      => $start,
			'end'        => $end,
			'cust'       => $cust,
		);

		update_post_meta( $post_id, 'type',       $type       );
		update_post_meta( $post_id, 'quantity',   $quantity   );
		update_post_meta( $post_id, 'value',      $value      );
		update_post_meta( $post_id, 'products',   $products   );
		update_post_meta( $post_id, 'categories', $categories );
		update_post_meta( $post_id, 'tags',       $tags       );
		update_post_meta( $post_id, 'users',      $users      );
		update_post_meta( $post_id, 'groups',     $groups     );
		update_post_meta( $post_id, 'start',      $start      );
		update_post_meta( $post_id, 'end',        $end        );
		update_post_meta( $post_id, 'cust',       $cust       );
		update_post_meta( $post_id, 'frontend',   $meta       );
	}

	public function columns( $columns ) {
		$new_columns['cb']     = '<input type="checkbox" />';
		$new_columns['title']  = __( 'Name', 'edd_dp' );
		$new_columns['type']   = __( 'Type', 'edd_dp' );
		$new_columns['value']  = __( 'Value', 'edd_dp' );
		$new_columns['status'] = __( 'Status', 'edd_dp' );
		$new_columns['users']  = __( 'Users', 'edd_dp' );
		$new_columns['groups'] = __( 'Roles', 'edd_dp' );
		$new_columns['date']   = __( 'Date', 'edd_dp' );
		return $new_columns;
	}

	public function column_value( $column, $post_id ) {

		switch ( $column ) {
			case 'type':
				$type  = get_post_meta( $post_id, 'type', true );
				$value = ! empty ( $type ) ? $this->get_discount_type( $type ) : __( 'Unknown Type', 'edd_dp' );
				echo $value;
				break;

			case 'value':
				$value  = get_post_meta( $post_id, 'value', true );
				$value = ! empty ( $value ) ? $value : __( 'Value not set', 'edd_dp' );
				echo $value;
				break;

			case 'users':
				$users = get_post_meta( $post_id, 'users', true );
				if ( empty( $users ) || ! is_array( $users ) ) {
					echo __( 'All users', 'edd_dp');
				} else {
					$links = '';
					foreach ( $users as $index => $user_id ) {
						$user = get_userdata( $user_id );
						$links .= '<a href="' . admin_url( "user-edit.php?user_id=" . $user_id ) . '">' . esc_html( $user->display_name ) . '</a>, ';
					}
					echo rtrim( $links, ', ' );
				}
				break;

			case 'groups':
				$roles = get_post_meta( $post_id, 'groups', true );
			
				if ( empty( $groups ) || ! is_array( $groups ) ) {
					echo __('All user roles', 'edd_dp');
				} else {
					global $wp_roles;
					$links  = '';
					foreach ( $roles as $role ) {
						if ( !empty( $wp_roles[ $role ] ) &&  ! empty( $wp_roles[ $role ]->name ) ) {
							$name = translate_user_role( $wp_roles[ $role ]->name );
							$links .= '<a href="' . admin_url( "user-edit.php?user_id=" . $role ) . '">' . esc_html( $name ). '</a>, ';
						}
					}
					echo rtrim( $links, ', ' );

				}
				break;

			case 'status':
				$start  	  = get_post_meta( $post_id, 'start', true );
				$end     	  = get_post_meta( $post_id, 'end', true );
				$current_time = (int) current_time( "timestamp" );
				$status = '';
				if ( $start == '' && $end == '' ){ // Both not set
					$status = __( 'Active','edd_dp' );
				} else if ( $start !== '' && $end == '' ) { // Only start set
					if ( (int) strtotime( $start, $current_time ) >= $current_time ) {
						$status = __( 'Waiting to Begin','edd_dp' );
					} else {
						$status = __( 'Active','edd_dp' );
					}
				} else if ( $start == '' && $end !== '' ) { // Only end set
					if ( (int) strtotime( $end, $current_time ) <= $current_time ) {
						$status = __( 'Finished','edd_dp' );
					} else {
						$status = __( 'Active','edd_dp' );
					}
				} else { // Both set
					if ( (int) strtotime( $start, $current_time ) >= $current_time ) {
						if ( (int) strtotime( $end, $current_time ) <= $current_time ) { // started and done
							$status = __( 'Finished','edd_dp' );
						} else { //started and not done
							$status = __( 'Active','edd_dp' );
						}
					} else {
						if ( (int) strtotime( $end, $current_time ) <= $current_time ) { // not started and done
							$status = __( 'Invalid time settings','edd_dp' );
						} else { // not started and not done
							$status = __( 'Waiting to Begin','edd_dp' );
						}
					}
				}
				echo $status;
				break;
			default :
				echo $column;
				break;
		}
	}

	public function get_roles( $args = array() ) {

		global $wp_roles;

		$defaults = array(
			'include' => array()
		);
		$args     = array_merge( $defaults, $args );
		$roles    = array();

		if ( empty( $args['include'] ) ) {
			foreach ( $wp_roles->role_names as $role => $role_name ) {
				$roles[ $role ] = $role_name;
			}
		} else {
			foreach ( $wp_roles->role_names as $role => $role_name ) {
				if ( in_array( $role, $args['include'] ) ) {
					$roles[ $role ] = $role_name;
				}
			}
		}
		return $roles;
	}

	public function get_users() {
		$users_data = get_users();
		$users      = array();
		foreach ( $users_data as $user ) {
			$users[$user->ID] = $user->display_name;
		}
		return $users;
	}

	public function get_discount_types() {
		return array(
			'fixed_price'      => __( 'Fixed Price', 'edd_dp' ),
			'percentage_price' => __( 'Percentage Price', 'edd_dp' ),
			'product_quantity' => __( 'Product Quantity', 'edd_dp' ),
			'each_x_products'  => __( 'Each X products', 'edd_dp' ),
			'from_x_products'  => __( 'From X products', 'edd_dp' ),
			'cart_quantity'    => __( 'Products in cart', 'edd_dp' ),
			'cart_threshold'   => __( 'Cart threshold', 'edd_dp' ),
		);
	}

	public function get_discount_type( $key ) {
		$discount_types = $this->get_discount_types();
		return ! empty( $discount_types[ $key ] ) ? $discount_types[ $key ] : '';
	}

	public function dateStringToDatepickerFormat( $dateString ){
		$pattern = array(

			//day
			'd',		//day of the month
			'j',		//3 letter name of the day
			'l',		//full name of the day
			'z',		//day of the year

			//month
			'F',		//Month name full
			'M',		//Month name short
			'n',		//numeric month no leading zeros
			'm',		//numeric month leading zeros

			//year
			'Y',		//full numeric year
			'y',		//numeric year: 2 digit
		);
		$replace = array(
			'dd','d','DD','o',
			'MM','M','m','mm',
			'yy','y',
		);

		foreach($pattern as &$p){
			$p = '/'.$p.'/';
		}

		return preg_replace( $pattern, $replace, $dateString );
	}
}
