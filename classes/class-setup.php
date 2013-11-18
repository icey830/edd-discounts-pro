<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}
class EDD_DP_Setup {
	public function __construct() {
		add_action( 'admin_init', array(
			 $this,
			'is_wp_36_and_edd_activated' 
		), 1 );
		add_action( 'init', array(
			 $this,
			'register_post_type' 
		) );
		add_action( 'plugins_loaded', array(
			 $this,
			'load_textdomain' 
		) );
		add_action( 'admin_menu', array(
			 $this,
			'discount_submenu' 
		), 9 );
		add_action( 'admin_enqueue_scripts', array(
			 $this,
			'admin_enqueue_scripts' 
		) );
		add_action( 'admin_enqueue_scripts', array(
			 $this,
			'admin_enqueue_styles' 
		) );
		add_action( 'wp_head', array(
			 $this,
			'dp_version' 
		) );
	}
	public function is_wp_36_and_edd_activated() {
		global $wp_version;
		if ( version_compare( $wp_version, '3.6', '< ' ) ) {
			if ( is_plugin_active( EDD_DP()->basename ) ) {
				deactivate_plugins( EDD_DP()->basename );
				unset( $_GET[ 'activate' ] );
				add_action( 'admin_notices', array(
					 $this,
					'wp_notice' 
				) );
			}
		} else if ( !class_exists( 'Easy_Digital_Downloads' ) || ( version_compare( EDD_VERSION, '1.8' ) < 0 ) ) {
			if ( is_plugin_active( EDD_DP()->basename ) ) {
				deactivate_plugins( EDD_DP()->basename );
				unset( $_GET[ 'activate' ] );
				add_action( 'admin_notices', array(
					 $this,
					'edd_notice' 
				) );
			}
		}
	}
	public function edd_notice() {
?>
	<div class="updated">
		<p><?php
		printf( __( '<strong>Notice:</strong> Easy Digital Downloads Discounts Pro requires Easy Digital Downloads 1.8 or higher in order to function properly.', 'edd_fes' ) );
?>
		</p>
	</div>
	<?php
	}
	public function wp_notice() {
?>
	<div class="updated">
		<p><?php
		printf( __( '<strong>Notice:</strong> Easy Digital Downloads Discounts Pro requires WordPress 3.6 or higher in order to function properly.', 'edd_fes' ) );
?>
		</p>
	</div>
	<?php
	}
	public function load_textdomain() {
		load_plugin_textdomain( 'edd-discounts-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	public function admin_enqueue_scripts() {
		if ( !is_admin() ) {
			return;
		}
		$current_screen = get_current_screen();
		if ( $current_screen->post_type === 'customer_discount' ) {
			wp_enqueue_script( 'edd-select2', edd_dp_assets_url . 'js/select2.js', array(
				 'jquery' 
			), '2.1' );
		}
	}
	public function admin_enqueue_styles() {
		if ( !is_admin() ) {
			return;
		}
		$current_screen = get_current_screen();
		if ( $current_screen->post_type === 'customer_discounts' ) {
			wp_enqueue_style( 'edd-select2', edd_dp_assets_url . 'css/select2.css', '', '2.1', 'screen' );
			wp_register_style( 'edd_discounts_admin', edd_dp_assets_url . 'css/admin.css' );
			wp_enqueue_style( 'edd_discounts_admin' );
		}
	}
	public function register_post_type() {
		register_post_type( 'customer_discount', array(
			 'labels' => array(
				 'menu_name' => __( 'Discounts', 'edd_discounts_pro' ),
				'name' => __( 'Discounts', 'edd_discounts_pro' ),
				'singular_name' => __( 'Discount', 'edd_discounts_pro' ),
				'add_new' => __( 'Add Discount', 'edd_discounts_pro' ),
				'add_new_item' => __( 'Add New Discount', 'edd_discounts_pro' ),
				'edit' => __( 'Edit', 'edd_discounts_pro' ),
				'edit_item' => __( 'Edit Discount', 'edd_discounts_pro' ),
				'new_item' => __( 'New Discount', 'edd_discounts_pro' ),
				'view' => __( 'View Discounts', 'edd_discounts_pro' ),
				'view_item' => __( 'View Discount', 'edd_discounts_pro' ),
				'search_items' => __( 'Search Discounts', 'edd_discounts_pro' ),
				'not_found' => __( 'No Discounts found', 'edd_discounts_pro' ),
				'not_found_in_trash' => __( 'No Discounts found in trash', 'edd_discounts_pro' ),
				'parent' => __( 'Parent Discount', 'edd_discounts_pro' ) 
			),
			'description' => __( 'This is where you can add new discounts that customers can use in your store.', 'edd_discounts_pro' ),
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
	public function discount_submenu() {
		add_submenu_page( 'edit.php?post_type=download', __( 'Discounts PRO', 'edd_discounts_pro' ), __( 'Discounts PRO', 'edd_discounts_pro' ), 'manage_options', 'edit.php?post_type=customer_discount' );
	}
}