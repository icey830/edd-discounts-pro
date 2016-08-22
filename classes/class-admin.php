<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class EDD_Admin {
	public function __construct() {
		if ( !is_admin() ){
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

		if ( version_compare( EDD_VERSION, '2.1' ) >= 0 ){
			add_filter( 'edd_settings_extensions', array( $this, 'settings' ), -1 );
		}
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
		add_meta_box(
			'edd_discounts_data',
			__( 'Discount Data', 'edd_dp' ),
			array( $this, 'discount_template' ),
			'customer_discount',
			'normal',
			'high'
		);
	}

	public function discount_template( $post ) {

		wp_nonce_field( 'edd_dp_save_meta', 'edd_dp_meta_nonce' );
		echo '<style type="text/css">#edit-slug-box { display: none;} #minor-publishing-actions, .misc-pub-visibility{ display: none;}</style>';
		echo '<div id="discount_options" class="panel edd_options_panel"><div class="options_group">';
		$args = array(
			'id'          => 'type',
			'name'        => 'type',
			'label'       => __( 'Discount Type', 'edd_dp' ),
			'options'     => $this->get_discount_types(),
			'show_option_all'  => false,
			'show_option_none' => false,
		);
		echo EDD()->html->select( $args );
		$value = get_post_meta( $post->ID, 'quantity', true );
		$args = array(
			'id'          => 'quantity',
			'name'          => 'quantity',
			'label'       => __( 'Quantity', 'edd_dp' ),
			'desc'        => __( 'Enter a value, i.e. 20', 'edd_dp' ),
			'placeholder' => '0',
			'value'       => $value,
		);
		echo EDD()->html->text( $args );
		$value = get_post_meta( $post->ID, 'value', true );
		$args = array(
			'id'          => 'value',
			'name'          => 'value',
			'label'       => __( 'Discount Value', 'edd_dp' ),
			'type'        => 'text',
			'desc'        => __( '<br />Enter a value, i.e. 9.99 or 20%.', 'edd_dp' ) .' '. __( 'For free please enter 100%.', 'edd_dp' ),
			'placeholder' => '0.00',
			'value'       => $value,
		);
		echo EDD()->html->text( $args );
		echo '</div>';
		echo '<div class="options_group">';

		$selected = get_post_meta( $post->ID, 'products', true );
		echo EDD()->html->product_dropdown( array(
			'name'        => 'products[]',
			'label'       => __( "Products", 'edd_dp' ),
			'id'          => 'products',
			'selected'    => $selected,
			'multiple'    => true,
			'chosen'      => true,
			'variations'  => true,
			'desc'        => __( 'Control which products this discount can apply to.', 'edd_dp' ),
			'placeholder' => sprintf( __( 'Select one or more %s', 'edd_dp' ), edd_get_label_plural() )
		) );


		$categories = array();
		foreach ( get_terms( 'download_category', array( 'hide_empty' => false ) ) as $category ) {
			$categories[ $category->term_id ] = $category->name;
		}
		$selected = get_post_meta( $post->ID, 'categories', true );
		$args = array(
			'id'          => 'categories',
			'name'          => 'categories[]',
			'label'       => __( 'Categories', 'edd_dp' ),
			'desc'        => __( 'Control which product categories this discount can apply to.', 'edd_dp' ),
			'multiple'    => true,
			'chosen'      => true,
			'placeholder' => __( 'Any category', 'edd_dp' ),
			'class'       => 'select long',
			'options'     => $categories,
			'selected'    => $selected,
			'show_option_all'  => false,
			'show_option_none' => false,
		);
		echo EDD()->html->select( $args );

		$tags = array();
		foreach ( get_terms( 'download_tag', array( 'hide_empty' => false ) ) as $tag ) {
			$tags[ $tag->term_id ] = $tag->name;
		}
		$selected = get_post_meta( $post->ID, 'tags', true );
		$args = array(
			'id'          => 'tags',
			'name'        => 'tags[]',
			'label'       => __( 'Tags', 'edd_dp' ),
			'desc'        => __( 'Control which product tags this discount can apply to.', 'edd_dp' ),
			'multiple'    => true,
			'chosen'      => true,
			'placeholder' => __( 'Any tag', 'edd_dp' ),
			'class'       => 'select long',
			'options'     => $tags,
			'selected'    => $selected,
			'show_option_all'  => false,
			'show_option_none' => false,
		);
		echo EDD()->html->select( $args ); echo '<br />';

		$selected = get_post_meta( $post->ID, 'users', true );
		echo EDD()->html->user_dropdown(  array(
			'name'        => 'users[]',
			'id'          => 'users',
			'key'         => 'user_login',
			'label'       => __( "Users", 'edd_dp' ),
			'selected'    => $selected,
			'multiple'    => true,
			'chosen'      => true,
			'desc'        => __( 'Control which users this discount can apply to.', 'edd_dp' ),
			'placeholder' => __( 'Select one or more users', 'edd_dp' ),
			'show_option_all'  => false,
			'show_option_none' => false,
		) ); echo '<br />';

		// Roles (we'll call them groups in core so we don't get confusion when EDD finally integrates w/groups plugin)
		$groups = $this->get_roles();
		$selected = get_post_meta( $post->ID, 'groups', true );
		$args   = array(
			'id'          => 'groups',
			'name'        => 'groups[]',
			'label'       => __( 'Roles', 'edd_dp' ),
			'desc'        => __( 'Control which roles this discount can apply to.', 'edd_dp' ),
			'multiple'    => true,
			'chosen'      => true,
			'placeholder' => __( 'Any roles', 'edd_dp' ),
			'class'       => 'select long',
			'options'     => $groups,
			'selected'    => $selected,
			'show_option_all'  => false,
			'show_option_none' => false,
		);
		echo EDD()->html->select( $args ); echo '<br />';
		$date_format = $this->dateStringToDatepickerFormat(get_option( 'date_format' ));
		$string = $date_format;
?>		<p class="form-field dp-date-start"><label for="dp-date-start">Start Date</label>
		<input id="dp-date-start" type="text" class="datepicker" data-type="text" name="dp-date-start" value="<?php echo get_post_meta( $post->ID, 'start', true ); ?>" size="30" />
		<span class="description">Select date when this discount may start being used. Leave blank for always on.</span>
		</p>
		<p class="form-field dp-date-end"><label for="dp-date-end">End Date</label>
		<input id="dp-date-end" type="text" class="datepicker" data-type="text" name="dp-date-end" value="<?php echo get_post_meta( $post->ID, 'end', true ); ?>" size="30" />
		<span class="description">Select end date when this discount may no longer used. Leave blank for always on.</span>
		</p>
		<script type="text/javascript">
			jQuery(function($) {
				$("#dp-date-start").datepicker({ dateFormat: '<?php echo $string; ?>' });
				$("#dp-date-end").datepicker({ dateFormat: '<?php echo $string; ?>' });
			});
		</script>
		</div>
		<script type="text/javascript">
		var quantity_help = {
			'product_quantity':"<?php
		_e( 'Quantity of selected product in cart to apply discount, i.e. 5.', 'edd_dp' );
		?>",
			'cart_quantity':"<?php
		_e( 'Number of products in cart to apply discount, i.e. 5.', 'edd_dp' );
		?>",
			'each_x_products':"<?php
		_e( 'Which product has a discount, i.e. every third is 3 in this field.', 'edd_dp' );
		?>",
			'from_x_products':"<?php
		_e( 'After how many products you want to give the discount, i.e. third, fourth and so on product discounted is 2 in this field.', 'edd_dp' );
		?>",
			'cart_threshold':"<?php
		_e( 'Minimum cart value to apply discount.', 'edd_dp' );
		?>"
		}
		jQuery(document).ready(function ($) {
			var type_val;
			$('#type').change(function() {
				type_val = $(this).find('option:selected').val();
				if(type_val == 'cart_quantity' || type_val == 'cart_threshold' || type_val == 'product_quantity' || type_val == 'each_x_products' || type_val == 'from_x_products') {
					$('#quantity').prev('.edd-description').html(quantity_help[type_val]);
					$('#quantity').show();
				} else {
					$('#quantity').hide()
				}
			});
			$('#type').change();
		});
	</script>
	<?php
	$current = get_post_meta( $post->ID, 'cust', true );
	$args = array(
		'name'        => 'cust',
		'label'       => __( 'Apply for previous customers only', 'edd_dp' ),
		'desc'        => __( 'When checked, only customers who have previously made purchases will be eligible for this discount', 'edd_dp' ),
		'current'     => $current,
	);
	echo EDD()->html->checkbox( $args );
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

		$type     = strip_tags( stripslashes( trim( $_POST['type'] ) ) );
		$quantity = strip_tags( stripslashes( trim( $_POST['quantity'] ) ) );
		$value    = strip_tags( stripslashes( trim( $_POST['value'] ) ) );
		if ( ! empty( $_POST['products'] ) ) {
			$products = array_map( "trim", $_POST['products'] );
			$products = array_map( "stripslashes", $products );
			$products = array_map( "strip_tags", $products );
			$products = array_map( "sanitize_key", $products );
		} else {
			$products = array();
		}

		if ( isset( $_POST['categories'] ) ) {
			$categories = array_map( 'absint', $_POST['categories'] );
		} else {
			$categories = array();
		}

		if ( isset( $_POST['tags'] ) ) {
			$tags = array_map( 'absint', $_POST['tags'] );
		} else {
			$tags = array();
		}

		if ( ! empty( $_POST['users'] ) ) {
			$users = array_map( "trim", $_POST['users'] );
			$users = array_map( "stripslashes", $users );
			$users = array_map( "strip_tags", $users );
			$users = array_map( "sanitize_key", $users );
		} else {
			$users = array();
		}

		if ( isset( $_POST['dp-date-start'] ) ) {
			$start = sanitize_text_field( $_POST['dp-date-start'] );
		} else {
			$start = '';
		}

		if ( isset( $_POST['dp-date-end'] ) ) {
			$end = sanitize_text_field( $_POST['dp-date-end'] );
		} else {
			$end = '';
		}

		if ( isset( $_POST['cust'] ) ) {
			$cust = true;
		} else {
			$cust = false;
		}

		if ( isset( $_POST['groups'] ) ) {
			$groups = array_map( 'sanitize_text_field', $_POST['groups'] );
		} else {
			$groups = array();
		}

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
				$type = get_post_meta( $post_id, 'type', true );
				echo count( $type ) == 1 ? $this->get_discount_type( $type ) : '-';
				break;

			case 'value':
				$type = get_post_meta( $post_id, 'type', true );
				$value = get_post_meta( $post_id, 'value', true );
				echo $value ? $value : '-';
				break;

			case 'users':
				$ids = get_post_meta( $post_id, 'users', true );

				if ( empty( $ids ) ) {
					echo __('All users', 'edd_dp');
					return;
				}
				$links = '';
				$users = get_users( array(
						'include' => $ids,
						'fields'  => array(
							'ID',
							'display_name'
						)
					) );
				foreach ( $users as $item ) {
					$links .= '<a href="' . admin_url( "user-edit.php?user_id=$item->ID" ) . '">' . $item->display_name . '</a>, ';
				}
				echo rtrim( $links, ', ' );
				break;

			case 'groups':
				$groups = get_post_meta( $post_id, 'groups', true );
				if ( empty( $groups ) || ! is_array( $groups ) ) {

					echo __('All user roles', 'edd_dp');

				} else {

					$links  = '';
					$groups = $this->get_roles( array(
							'include' => $groups
						) );
					foreach ( $groups as $role => $name ) {
						$links .= '<a href="' . admin_url( "user-edit.php?user_id=$role" ) . '">' . $name . '</a>, ';
					}
					echo rtrim( $links, ', ' );

				}
				break;

			case 'status':
				$start = get_post_meta( $post_id, 'start', true );
				$end   = get_post_meta( $post_id, 'end', true );
				if ( $start === '' && $end === '' ){
					_e('N/A','edd_dp');
					return;
				}

				if ( $start !== '' && (int) strtotime( $start, current_time( "timestamp" ) ) > (int) current_time( "timestamp" ) ){
					_e('Waiting to Begin','edd_dp');
					return;
				}

				if ( $end !== '' && (int) strtotime( $end, current_time( "timestamp" ) ) < (int) current_time( "timestamp" ) ){
					_e('Finished','edd_dp');
					return;
				}
				_e('In progress','edd_dp');
				return;
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

				$roles[$role] = $role_name;

			}

		} else {

			foreach ( $wp_roles->role_names as $role => $role_name ) {

				if ( in_array( $role, $args['include'] ) ) {

					$roles[$role] = $role_name;

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
		return isset( $discount_types[$key] ) ? $discount_types[$key] : null;
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
