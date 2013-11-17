<?php
wp_nonce_field('edd_dp_save_meta', 'edd_dp_meta_nonce');
?>
<style type="text/css">
#edit-slug-box {
    display: none
}
</style>
<div id="discount_options" class="panel edd_options_panel">
    <div class="options_group">
<?php
// Discount Types
$args = array(
    'id' => 'type',
    'label' => __('Discount Type', 'edd_discounts'),
    'options' => $this->getDiscountTypes()
);
echo EDD_CF_Forms::select($args);

// Quantity
$args = array(
    'id' => 'quantity',
    'label' => __('Quantity', 'edd_discounts'),
    'type' => 'number',
    'desc' => __('Enter a value, i.e. 20', 'edd_discounts'),
    'placeholder' => '0',
	'min' => 0
);
echo EDD_CF_Forms::input($args);

// Value
$args = array(
    'id' => 'value',
    'label' => __('Discount Value', 'edd_discounts'),
    'type' => 'text',
    'desc' => __('<br />Enter a value, i.e. 9.99 or 20%.', 'edd_discounts').(' '.__('For free please enter 100%.', 'edd_discounts')),
    'placeholder' => '0.00'
);
echo EDD_CF_Forms::input($args);
?>

</div>
<div class="options_group">
<?php
// Include product ID's
$selected = implode(',', (array)get_post_meta($post->ID, 'download', true));

$args = array(
    'id'            => 'products',
    'type'          => 'hidden',        /* use hidden input type for Select2 custom data loading */
    'class'         => 'long',
    'label'         => __('Products', 'edd_discounts'),
    'desc'          => __('Control which products this coupon can apply to.','edd_discounts'),
    'value'         => $selected
);
echo EDD_CF_Forms::input($args);

// Categories
$categories = array();
foreach (get_terms('download_category', array('hide_empty' => false)) as $category)
{
    $categories[$category->term_id] = $category->name;
}

$args = array(
    'id' => 'categories',
    'label' => __('Categories', 'edd_discounts'),
    'desc' => __('Control which product categories this discount can apply to.', 'edd_discounts'),
    'multiple' => true,
    'placeholder' => __('Any category', 'edd_discounts'),
    'class' => 'select long',
    'options' => $categories
);
echo EDD_CF_Forms::select($args);

// Users
$users = $this->getUsers();
$args = array(
    'id' => 'users',
    'label' => __('Users', 'edd_discounts'),
    'desc' => __('Control which user this discount can apply to.', 'edd_discounts'),
    'multiple' => true,
    'placeholder' => __('Any user', 'edd_discounts'),
    'class' => 'select long',
    'options' => $users
);
echo EDD_CF_Forms::select($args);

// Roles
$groups = $this->getRoles();
$args = array(
    'id' => 'groups',
    'label' => __('Roles', 'edd_discounts'),
    'desc' => __('Control which roles this discount can apply to.', 'edd_discounts'),
    'multiple' => true,
    'placeholder' => __('Any roles', 'edd_discounts'),
    'class' => 'select long',
    'options' => $groups
);

echo EDD_CF_Forms::select($args);

// First purchase discount?
	$args = array(
		'id' => 'first_purchase',
		'label' => __('Only first purchase?', 'edd_discounts'),
		'desc' => __('Select to allow the discount for the first purchase that client has made in your shop.', 'edd_discounts')
	);
	//echo EDD_CF_Forms::checkbox($args);
?>
</div>
<script type="text/javascript">
		var quantity_help = {
        'product_quantity':"<?php _e('Quantity of selected product in cart to apply discount, i.e. 5.', 'edd_discounts'); ?>",
        'cart_quantity':"<?php _e('Number of products in cart to apply discount, i.e. 5.', 'edd_discounts'); ?>",
        'each_x_products':"<?php _e('Which product has a discount, i.e. every third is 3 in this field.', 'edd_discounts'); ?>",
				'from_x_products':"<?php _e('After how many products you want to give the discount, i.e. third, fourth and so on product discounted is 2 in this field.', 'edd_discounts'); ?>",
        'cart_threshold':"<?php _e('Minimum cart value to apply discount.', 'edd_discounts'); ?>"
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
            placeholder: "<?php _e('Any product', 'edd_discounts'); ?>",
            ajax: {
                url: "<?php echo (!is_ssl()) ? str_replace('https', 'http', admin_url('admin-ajax.php')) : admin_url('admin-ajax.php'); ?>",
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
                    url:        "<?php echo (!is_ssl()) ? str_replace('https', 'http', admin_url('admin-ajax.php')) : admin_url('admin-ajax.php'); ?>",
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
