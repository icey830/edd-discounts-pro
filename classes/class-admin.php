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

	public static function input( $field ) {
		global $post;
		$args = array(
			'id'          => null,
			'name'        => null,
			'type'        => 'text',
			'label'       => null,
			'after_label' => null,
			'class'       => 'short',
			'desc'        => false,
			'tip'         => false,
			'value'       => null,
			'min'         => null,
			'max'         => null,
			'step'        => 'any',
			'placeholder' => null,
		);
		extract( wp_parse_args( $field, $args ) );

		$value     = isset( $value ) ? esc_attr( $value ) : get_post_meta( $post->ID, $id, true );
		$disc_type = get_post_meta( $post->ID, 'type', true );

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
			'id'          => null,
			'name'        => null,
			'label'       => null,
			'after_label' => null,
			'class'       => 'select short',
			'desc'        => false,
			'tip'         => false,
			'multiple'    => false,
			'placeholder' => '',
			'options'     => array(),
			'selected'    => false,
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
			'id'          => null,
			'name'        => null,
			'label'       => null,
			'after_label' => null,
			'class'       => 'checkbox',
			'desc'        => false,
			'tip'         => false,
			'value'       => false,
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
			'label'       => __( 'Discount Type', 'edd_dp' ),
			'options'     => $this->get_discount_types(),
		);
		echo $this->select( $args );
		$args = array(
			'id'          => 'quantity',
			'label'       => __( 'Quantity', 'edd_dp' ),
			'type'        => 'number',
			'desc'        => __( 'Enter a value, i.e. 20', 'edd_dp' ),
			'placeholder' => '0',
			'min'         => 0,
		);
		echo $this->input( $args );
		$args = array(
			'id'          => 'value',
			'label'       => __( 'Discount Value', 'edd_dp' ),
			'type'        => 'text',
			'desc'        => __( '<br />Enter a value, i.e. 9.99 or 20%.', 'edd_dp' ) .' '. __( 'For free please enter 100%.', 'edd_dp' ),
			'placeholder' => '0.00',
		);
		echo $this->input( $args );
		echo '</div>';
		echo '<div class="options_group">';
		$selected = implode( ',', (array) get_post_meta( $post->ID, 'products', true ) );
		$args     = array(
			'id'          => 'products',
			'type'        => 'hidden',
			/* use hidden input type for Select2 custom data loading */
			'class'       => 'long',
			'label'       => __( 'Products', 'edd_dp' ),
			'desc'        => __( 'Control which products this coupon can apply to.', 'edd_dp' ),
			'value'       => $selected,
		);
		echo $this->input( $args );
		$categories = array();
		foreach ( get_terms( 'download_category', array( 'hide_empty' => false ) ) as $category ) {
			$categories[ $category->term_id ] = $category->name;
		}
		$args = array(
			'id'          => 'categories',
			'label'       => __( 'Categories', 'edd_dp' ),
			'desc'        => __( 'Control which product categories this discount can apply to.', 'edd_dp' ),
			'multiple'    => true,
			'placeholder' => __( 'Any category', 'edd_dp' ),
			'class'       => 'select long',
			'options'     => $categories,
		);
		echo $this->select( $args );

		$tags = array();
		foreach ( get_terms( 'download_tag', array( 'hide_empty' => false ) ) as $tag ) {
			$tags[ $tag->term_id ] = $tag->name;
		}
		$args = array(
			'id'          => 'tags',
			'label'       => __( 'Tags', 'edd_dp' ),
			'desc'        => __( 'Control which product tags this discount can apply to.', 'edd_dp' ),
			'multiple'    => true,
			'placeholder' => __( 'Any tag', 'edd_dp' ),
			'class'       => 'select long',
			'options'     => $tags,
		);
		echo $this->select( $args );

		$selected = implode( ',', (array) get_post_meta( $post->ID, 'users', true ) );
		$args     = array(
			'id'          => 'users',
			'type'        => 'hidden',
			/* use hidden input type for Select2 custom data loading */
			'class'       => 'long',
			'label'       => __( 'Users', 'edd_dp' ),
			'desc'        => __( 'Control which user this discount can apply to. Search by email address, URL, ID or username.', 'edd_dp' ),
			'value'       => $selected,
		);
		echo $this->input( $args );

		// Roles (we'll call them groups in core so we don't get confusion when EDD finally integrates w/groups plugin)
		$groups = $this->get_roles();
		$args   = array(
			'id'          => 'groups',
			'label'       => __( 'Roles', 'edd_dp' ),
			'desc'        => __( 'Control which roles this discount can apply to.', 'edd_dp' ),
			'multiple'    => true,
			'placeholder' => __( 'Any roles', 'edd_dp' ),
			'class'       => 'select long',
			'options'     => $groups,
		);
		echo $this->select( $args );
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
					$('.quantity_field > span.description').html(quantity_help[type_val]);
					$('.quantity_field').show();
				} else {
					$('.quantity_field').hide()
				}
			});
			$('#type').change();

			// allow searching of products to use on a discount
			jQuery("#products").select2({
				minimumInputLength: 3,
				multiple: true,
				closeOnSelect: true,
				placeholder: "<?php _e( 'Any product', 'edd_dp' ); ?>",
				ajax: {
					url: "<?php echo ( !is_ssl() ) ? str_replace( 'https', 'http', admin_url( 'admin-ajax.php' ) ) : admin_url( 'admin-ajax.php' ); ?>",
					dataType: 'json',
					quietMillis: 100,
					data: function(term, page) {
						return {
							term:       term,
							action:     'edd_json_search_products_and_variations',
							security:   '<?php echo wp_create_nonce( "search-products" ); ?>'
						};
					},
					results: function( data, page ) {
						return { results: data };
					}
				},
				initSelection: function( element, callback ) {
					var product_init_data = {
						action:     'edd_json_search_products_and_variations',
						security:   '<?php echo wp_create_nonce( "search-products" ); ?>',
						term:       element.val()
					};

					jQuery.ajax({
						type:     'GET',
						url:      "<?php echo ( !is_ssl() ) ? str_replace( 'https', 'http', admin_url( 'admin-ajax.php' ) ) : admin_url( 'admin-ajax.php' ); ?>",
						dataType: "json",
						data:     product_init_data,
						success: 	function( result ) {
							callback( result );
						}
					});
				}
			});
			// allow searching of users to use on a discount
			jQuery("#users").select2({
				minimumInputLength: 3,
				multiple: true,
				closeOnSelect: true,
				placeholder: "<?php _e( 'Any user', 'edd_dp' ); ?>",
				ajax: {
					type:     'GET',
					url: "<?php echo ( !is_ssl() ) ? str_replace( 'https', 'http', admin_url( 'admin-ajax.php' ) ) : admin_url( 'admin-ajax.php' ); ?>",
					dataType: 'json',
					quietMillis: 100,
					data: function(term, page) {
						return {
							user:       term,
							action:     'edd_json_search_users_ajax',
							security:   '<?php echo wp_create_nonce( "search-users" ); ?>'
						};
					},
					results: function( data, page ) {
						return { results: data };
					}
				},
				initSelection: function( element, callback ) {
					var stuff = {
						action:     'edd_json_search_users_ajax',
						security:   '<?php echo wp_create_nonce( "search-users" ); ?>',
						user:       element.val()
					};
					var data = [];
					jQuery.ajax({
						type:     'GET',
						url:      "<?php echo ( !is_ssl() ) ? str_replace( 'https', 'http', admin_url( 'admin-ajax.php' ) ) : admin_url( 'admin-ajax.php' ); ?>",
						dataType: "json",
						data:     stuff,
						success: 	function( result ) {
							callback( result );
						}
					});
				}
			});
		});
	</script>
	<?php
	$args = array(
		'id'          => 'cust',
		'label'       => __( 'Apply for previous customers only', 'edd_dp' ),
		'type'        => 'checkbox',
		'desc'        => __( 'When checked, only customers who have previously made purchases will be eligible for this discount', 'edd_dp' ),
		'placeholder' => '0',
		'min'         => 0,
	);
	echo $this->checkbox( $args );
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

		if ( isset( $_POST['products'] ) ) {
			$products = strip_tags( stripslashes( trim( $_POST['products'] ) ) );
			if ( $products == 'Array' ) {
				$products = '';
			}
			$products = $products != '' ? explode( ',', $products ) : array();
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

		if ( isset( $_POST['users'] ) ) {
			$users = strip_tags( stripslashes( trim( $_POST['users'] ) ) );
			if ( $users == 'Array' ) {
				$users = '';
			}
			$users = $users != '' ? explode( ',', $users ) : array();
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

				if ( $start !== '' && strtotime( $start ) > strtotime("now") ){
					_e('Waiting to Begin','edd_dp');
					return;
				}

				if ( $end !== '' && strtotime( $end ) < strtotime("now") ){
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

	public function edd_json_search_products( $x = '', $post_types = array( 'download' ) ) {

		check_ajax_referer( 'search-products', 'security' );

		$term = (string) urldecode( stripslashes( strip_tags( $_GET['term'] ) ) );

		if ( empty( $term ) )
			die();

		$products;
		$save_term = $term;

		if ( ! ( strpos( $term, ',' ) !== false ) && strpos( $term, '_' ) !== false ){
			$term = substr( $term, 0, strpos( $term, '_' ) );
		}

		if ( strpos( $term, ',' ) !== false ) {
			$term = (array) explode( ',', $term );

			foreach ( $term as $id => $t ) {
				if ( strpos( $t, '_' ) !== false ) {
					$term[ $id ] = substr( $t, 0, strpos( $t, '_' ) );
				}
			}

			$args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => $term,
				'fields'         => 'ids',
			);
			$products = get_posts( $args );

		} elseif ( is_numeric( $term ) ) {
			$args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => array(
					0,
					$term
				),
				'fields'         => 'ids',
			);
			$products = get_posts( $args );

		} else {
			$args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				's'              => $term,
				'fields'         => 'ids',
			);
			$products = get_posts( $args );
		}
		$found_products = array();
		if ( !empty( $products ) )
			foreach ( $products as $product_id ) {
				if ( edd_has_variable_prices( $product_id ) ) {
					$prices = edd_get_variable_prices( $product_id );

					if ( $save_term ){
						if ( strpos( $save_term, ',' ) !== false ) {
							$save_term = (array) explode( ',', $save_term );
							foreach( $save_term as $sterm ){
								$found = false;
								foreach ( $prices as $key => $value ) {
									foreach( $save_term as $sterm ){
										if ( $product_id."_".$key == $sterm ){
											$found_products[] = array(
													'id' => $product_id . '_' . $key,
													'text' => html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ) . ' (' . html_entity_decode( $value['name'], ENT_COMPAT, 'UTF-8' ) . ' )'
											);
											$found = true;
										}
									}
								}
								if ( !$found ){
									foreach ( $prices as $key => $value ) {
										$found_products[] = array(
												'id' => $product_id . '_' . $key,
												'text' => html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ) . ' (' . html_entity_decode( $value['name'], ENT_COMPAT, 'UTF-8' ) . ' )'
										);
									}
								}
							}
						}
						else{
							$found = false;
							foreach ( $prices as $key => $value ) {
								if ( $product_id."_".$key == $save_term ){
									$found_products[] = array(
											'id' => $product_id . '_' . $key,
											'text' => html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ) . ' (' . html_entity_decode( $value['name'], ENT_COMPAT, 'UTF-8' ) . ' )'
									);
									$found = true;
								}
							}
							if ( !$found ){
								foreach ( $prices as $key => $value ) {
									$found_products[] = array(
											'id' => $product_id . '_' . $key,
											'text' => html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ) . ' (' . html_entity_decode( $value['name'], ENT_COMPAT, 'UTF-8' ) . ' )'
									);
								}
							}
						}
					}
					else{
						foreach ( $prices as $key => $value ) {
							$found_products[] = array(
								'id' => $product_id . '_' . $key,
								'text' => html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ) . ' (' . html_entity_decode( $value['name'], ENT_COMPAT, 'UTF-8' ) . ' )'
							);
						}
					}
				} else {
					// If the customer turned on EDD's sku field
					$SKU = get_post_meta( $product_id, 'edd_sku', true );
					if ( isset( $SKU ) && $SKU ) {
						$SKU = ' (SKU: ' . $SKU . ')';
					} else {
						$SKU = ' (ID: ' . $product_id . ')';
					}
					$found_products[] = array(
						'id' => $product_id,
						'text' => html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ) . $SKU
					);
				}
			}
		echo json_encode( $found_products );
		die();
	}

	public function ajax_search_product_vars() {
		$this->edd_json_search_products( '', array( 'download' ) );
	}

	public function edd_json_search_users() {

		check_ajax_referer( 'search-users', 'security' );

		$term = (string) urldecode( stripslashes( strip_tags( $_GET['user'] ) ) );

		if ( empty( $term ) ){
			die();
		}

		$args = array();
		$args['include'] = array();

		if ( strpos( $term, ',' ) !== false ) {
			$term = (array) explode( ',', $term );
		}

		if( is_array( $term ) ) {
			foreach( $term as $t ) {

				if( is_numeric( $t ) ) {
					$args['include'][] = $t;
				} else {
					$args['search'] = '*' . $t . '*';
				}

			}
		} else {
			if( is_numeric( $term ) ) {
				$args['include'][] = $term;
			} else {
				$args['search'] = '*' . $term . '*';
			}
		}

		$args['search_columns'] = array(
			'ID',
			'user_login',
			'display_name',
			'user_email',
			'user_url'
		);

		if( empty( $args['include'] ) ) {
			unset( $args['include'] );
		}

		$found_users = array();
		$users = get_users( $args );

		if ( ! empty( $users ) ){
			foreach ( $users as $user ) {

				$found_users[] = array(
					'id' => $user->ID,
					'text' => html_entity_decode( $user->display_name , ENT_COMPAT, 'UTF-8' )
				);
			}
		}

		echo json_encode( $found_users );
		die();
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
