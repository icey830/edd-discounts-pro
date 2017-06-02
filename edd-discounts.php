<?php
/**
 * Plugin Name:         Easy Digital Downloads - Discounts Pro
 * Plugin URI:          https://easydigitaldownloads.com/downloads/discounts-pro/
 * Description:         Add powerful discounting options to EDD
 * Author:              Easy Digital Downloads, LLC
 * Author URI:          https://easydigitaldownloads.com
 *
 * Version:             1.4.8
 * Requires at least:   4.1
 * Tested up to:        4.5
 *
 * Text Domain:         edd_dp
 * Domain Path:         /languages/
 *
 * @category            Plugin
 * @copyright           Copyright © 2016 Easy Digital Downloads, LLC
 * @author              Easy Digital Downloads
 * @package             EDD_DP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Check if Easy Digital Downloads is active */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
class edd_dp {

	/**
	 * @var edd_dp The one true edd_dp
	 * @since 1.0
	 */
	private static $instance;

	public $id = 'edd_dp';

	public $basename;

	// Setup objects for each class
	public $discounts;

	/**
	 * Main edd_dp Instance
	 *
	 * Insures that only one instance of edd_dp exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since 1.0
	 * @static
	 * @staticvar array $instance
	 * @uses edd_dp::setup_globals() Setup the globals needed
	 * @uses edd_dp::includes() Include the required files
	 * @uses edd_dp::setup_actions() Setup the hooks and actions
	 * @see EDD()
	 * @return The one true edd_dp
	 */
	public static function instance() {
		if ( !isset( self::$instance ) && !( self::$instance instanceof edd_dp ) ) {
			self::$instance = new edd_dp;
			self::$instance->define_globals();
			self::$instance->includes();
			// Setup class instances
			self::$instance->setup     = new EDD_DP_Setup;
			if ( is_admin() ){
				self::$instance->admin = new EDD_Admin;
			}
			self::$instance->discounts = new EDD_Discounts;
		}
		return self::$instance;
	}
	public function define_globals() {
		$this->title    = __( 'Discounts Pro', 'edd_dp' );
		$this->file     = __FILE__;
		$this->basename = apply_filters( 'edd_edd_dp_plugin_basename', plugin_basename( $this->file ) );
		// Plugin Name
		if ( ! defined( 'EDD_DP_PLUGIN_NAME' ) ) {
			define( 'EDD_DP_PLUGIN_NAME', 'Discounts Pro' );
		}
		// Plugin Version
		if ( ! defined( 'EDD_DP_PLUGIN_VERSION' ) ) {
			define( 'EDD_DP_PLUGIN_VERSION', '1.4.8' );
		}
		// Plugin Root File
		if ( ! defined( 'EDD_DP_PLUGIN_FILE' ) ) {
			define( 'EDD_DP_PLUGIN_FILE', __FILE__ );
		}
		// Plugin Folder Path
		if ( ! defined( 'EDD_DP_PLUGIN_PATH' ) ) {
			define( 'EDD_DP_PLUGIN_PATH', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/' );
		}
		// Plugin Folder URL
		if ( ! defined( 'EDD_DP_PLUGIN_URL' ) ) {
			define( 'EDD_DP_PLUGIN_URL', plugin_dir_url( EDD_DP_PLUGIN_FILE ) );
		}
		// Plugin Assets URL
		if ( ! defined( 'EDD_DP_ASSETS_URL' ) ) {
			define( 'EDD_DP_ASSETS_URL', EDD_DP_PLUGIN_URL . 'assets/' );
		}

		$license = new EDD_License( __FILE__, EDD_DP_PLUGIN_NAME, EDD_DP_PLUGIN_VERSION, 'EDD Team' );

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function includes() {
		require_once EDD_DP_PLUGIN_PATH . 'classes/class-setup.php';
		require_once EDD_DP_PLUGIN_PATH . 'classes/class-admin.php';
		require_once EDD_DP_PLUGIN_PATH . 'classes/class-discounts.php';
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'edd_dp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

}

/**
 * The main function responsible for returning the one true edd_dp
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $edd_dp = EDD_DP(); ?>
 *
 * @since 2.0
 * @return object The one true edd_dp Instance
 */
function EDD_DP() {
	if ( ! class_exists( 'Easy_Digital_Downloads' ) ){
		return;
	}
	return edd_dp::instance();
}
add_action( 'plugins_loaded', 'EDD_DP' );
