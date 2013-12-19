<?php
/**
 * Plugin Name:         Easy Digital Downloads - Discounts PRO
 * Plugin URI:          https://easydigitaldownloads.com/extension/discounts-pro/
 * Description:         Add powerful discounting options to EDD
 * Author:              Chris Christoff
 * Author URI:          http://www.chriscct7.com
 *
 * Version:             1.0
 * Requires at least:   3.6
 * Tested up to:        3.6
 *
 * Text Domain:         edd_dp
 * Domain Path:         /edd_dp/languages/
 *
 * @category            Plugin
 * @copyright           Copyright © 2013 Chris Christoff
 * @author              Chris Christoff
 * @package             EDD_DP
 */
if ( !defined( 'ABSPATH' ) ) {
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
			self::$instance->discounts = new EDD_Discounts;
		}
		return self::$instance;
	}
	public function define_globals() {
		$this->title    = __( 'Discounts Pro', 'edd-discounts-pro' );
		$this->file     = __FILE__;
		$this->basename = apply_filters( 'edd_edd_dp_plugin_basename', plugin_basename( $this->file ) );
		// Plugin Name
		if ( !defined( 'edd_dp_plugin_name' ) ) {
			define( 'edd_dp_plugin_name', 'Discounts Pro' );
		}
		// Plugin Version
		if ( !defined( 'edd_dp_plugin_version' ) ) {
			define( 'edd_dp_plugin_version', '1.0' );
		}
		// Plugin Root File
		if ( !defined( 'edd_dp_plugin_file' ) ) {
			define( 'edd_dp_plugin_file', __FILE__ );
		}
		// Plugin Folder Path
		if ( !defined( 'edd_dp_plugin_dir' ) ) {
			define( 'edd_dp_plugin_dir', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/' );
		}
		// Plugin Folder URL
		if ( !defined( 'edd_dp_plugin_url' ) ) {
			define( 'edd_dp_plugin_url', plugin_dir_url( edd_dp_plugin_file ) );
		}
		// Plugin Assets URL
		if ( !defined( 'edd_dp_assets_url' ) ) {
			define( 'edd_dp_assets_url', edd_dp_plugin_url . 'assets/' );
		}
		if ( !class_exists( 'EDD_License' ) ) {
			require_once edd_dp_plugin_dir . 'assets/lib/EDD_License_Handler.php';
		}
		$license = new EDD_License( __FILE__, edd_dp_plugin_name, edd_dp_plugin_version, 'Chris Christoff' );
	}
	public function includes() {
		require_once edd_dp_plugin_dir . 'classes/class-setup.php';
		require_once edd_dp_plugin_dir . 'classes/class-forms.php';
		require_once edd_dp_plugin_dir . 'classes/class-product.php';
		require_once edd_dp_plugin_dir . 'classes/class-discounts.php';
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
	return edd_dp::instance();
}
EDD_DP();