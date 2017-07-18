<?php
/**
 * Plugin Name: Story Chief
 * Plugin URI: http://storychief.io/wordpress
 * Description: This plugin integrates Storychief in WordPress.
 * Version: 0.3.1
 * Author: Gregory Claeyssens
 * Author URI: http://storychief.io
 * License: GPL2
 */

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'STORYCHIEF_VERSION', '0.3.1' );
define( 'STORYCHIEF__MINIMUM_WP_VERSION', '4.6' );
define( 'STORYCHIEF__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYCHIEF__PLUGIN_BASE_NAME', plugin_basename(__FILE__) );


register_activation_hook( __FILE__, array( 'Storychief', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'Storychief', 'plugin_deactivation' ) );

require_once( STORYCHIEF__PLUGIN_DIR . 'class.storychief.php' );

add_action( 'init', array( 'Storychief', 'init' ) );

if ( is_admin() ) {
	require_once( STORYCHIEF__PLUGIN_DIR . 'class.storychief-admin.php' );
	add_action( 'init', array( 'Storychief_Admin', 'init' ) );
	add_filter( 'plugin_action_links_'.STORYCHIEF__PLUGIN_BASE_NAME, array( 'Storychief_Admin', 'settings_link'));
}