<?php
wp_nonce_field( 'edd_dp_save_meta', 'edd_dp_meta_nonce' );
echo '<style type="text/css">#edit-slug-box { display: none;}</style>';
echo '<div id="discount_options" class="panel edd_options_panel"><div class="options_group">';
$args = array(
	 'id' => 'type',
	'label' => __( 'Discount Type', 'edd_dp' ),
	'options' => $this->getDiscountTypes() 
);
echo EDD_CF_Forms::select( $args );
$args = array(
	 'id' => 'quantity',
	'label' => __( 'Quantity', 'edd_dp' ),
	'type' => 'number',
	'desc' => __( 'Enter a value, i.e. 20', 'edd_dp' ),
	'placeholder' => '0',
	'min' => 0 
);
echo EDD_CF_Forms::input( $args );
$args = array(
	'id' => 'value',
	'label' => __( 'Discount Value', 'edd_dp' ),
	'type' => 'text',
	'desc' => __( '<br />Enter a value, i.e. 9.99 or 20%.', 'edd_dp' ) . ( ' ' . __( 'For free please enter 100%.', 'edd_dp' ) ),
	'placeholder' => '0.00' 
);
echo EDD_CF_Forms::input( $args );
echo '</div>';
echo '<div class="options_group">';
$selected = implode( ',', (array) get_post_meta( $post->ID, 'download', true ) );
$args     = array(
	 'id' => 'products',
	'type' => 'hidden',
	/* use hidden input type for Select2 custom data loading */
	'class' => 'long',
	'label' => __( 'Products', 'edd_dp' ),
	'desc' => __( 'Control which products this coupon can apply to.', 'edd_dp' ),
	'value' => $selected 
);
echo EDD_CF_Forms::input( $args );
$categories = array();
foreach ( get_terms( 'download_category', array(
	 'hide_empty' => false 
) ) as $category ) {
	$categories[ $category->term_id ] = $category->name;
}
$args = array(
	 'id' => 'categories',
	'label' => __( 'Categories', 'edd_dp' ),
	'desc' => __( 'Control which product categories this discount can apply to.', 'edd_dp' ),
	'multiple' => true,
	'placeholder' => __( 'Any category', 'edd_dp' ),
	'class' => 'select long',
	'options' => $categories 
);
echo EDD_CF_Forms::select( $args );
$users = $this->getUsers();
$args  = array(
	 'id' => 'users',
	'label' => __( 'Users', 'edd_dp' ),
	'desc' => __( 'Control which user this discount can apply to.', 'edd_dp' ),
	'multiple' => true,
	'placeholder' => __( 'Any user', 'edd_dp' ),
	'class' => 'select long',
	'options' => $users 
);
echo EDD_CF_Forms::select( $args );
// Roles (we'll call them groups in core so we don't get confusion when EDD finally integrates w/groups plugin)
$groups = $this->getRoles();
$args   = array(
	 'id' => 'groups',
	'label' => __( 'Roles', 'edd_dp' ),
	'desc' => __( 'Control which roles this discount can apply to.', 'edd_dp' ),
	'multiple' => true,
	'placeholder' => __( 'Any roles', 'edd_dp' ),
	'class' => 'select long',
	'options' => $groups 
);
echo EDD_CF_Forms::select( $args );
?>
</div>
<script type="text/javascript">
		var quantity_help = {
			'product_quantity':"<?php _e( 'Quantity of selected product in cart to apply discount, i.e. 5.', 'edd_dp' ); ?>",
			'cart_quantity':"<?php _e( 'Number of products in cart to apply discount, i.e. 5.', 'edd_dp' ); ?>",
			'each_x_products':"<?php _e( 'Which product has a discount, i.e. every third is 3 in this field.', 'edd_dp' ); ?>",
			'from_x_products':"<?php _e( 'After how many products you want to give the discount, i.e. third, fourth and so on product discounted is 2 in this field.', 'edd_dp' ); ?>",
			'cart_threshold':"<?php _e( 'Minimum cart value to apply discount.', 'edd_dp' ); ?>"
		}
</script>
<script type="text/javascript">
    jQuery(document).ready(function ($) {
        var type_val;
        $('#type').change(function() {
            type_val = $(this).find('option:selected').val();
            if(type_val == 'cart_quantity' || type_val == 'cart_threshold' || type_val == 'product_quantity' || type_val == 'each_x_products' || type_val == 'from_x_products')
            {
		            $('.quantity_field > span.description').html(quantity_help[type_val]);
                $('.quantity_field').show();
            }
            else
            {
                $('.quantity_field').hide()
            }
        });
        $('#type').change();
    });
</script>
<script type="text/javascript">
    jQuery(document).ready(function () {

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
                var stuff = {
                    action:     'edd_json_search_products_and_variations',
                    security:   '<?php echo wp_create_nonce( "search-products" ); ?>',
                    term:       element.val()
                };
                var data = [];
                jQuery.ajax({
                    type: 		'GET',
                    url:        "<?php echo ( !is_ssl() ) ? str_replace( 'https', 'http', admin_url( 'admin-ajax.php' ) ) : admin_url( 'admin-ajax.php' ); ?>",
                    dataType: 	"json",
                    data: 		stuff,
                    success: 	function( result ) {
                        callback( result );
                    }
                });
            }
        });
    });
</script>
