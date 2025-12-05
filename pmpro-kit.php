<?php
/*
Plugin Name: Paid Memberships Pro - Kit Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-kit-add-on/
Description: TBD
Version: 0.1
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
Text Domain: pmpro-kit
Domain Path: /languages
*/

include_once dirname(__FILE__) . '/includes/functions.php'; // Core plugin functions.
include_once dirname(__FILE__) . '/includes/admin.php'; // Admin settings and menu.
include_once dirname(__FILE__) . '/includes/migration.php'; // Code to migrate from the old convertkit-paid-memberships-pro plugin.
include_once dirname(__FILE__) . '/classes/class-pmpro-kit-api-wrapper.php'; // API wrapper class.

/**
 * Function to add links to the plugin row meta.
 *
 * @since TBD
 */
function pmpro_kit_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-kit.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/pmpro-kit-add-on/' ) . '" title="' . esc_attr__( 'View Documentation', 'pmpro-kit' ) . '">' . esc_html__( 'Docs', 'pmpro-kit' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-kit' ) . '">' . esc_html__( 'Support', 'pmpro-kit' ) . '</a>',
		);
		$links = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmpro_kit_plugin_row_meta', 10, 2 );