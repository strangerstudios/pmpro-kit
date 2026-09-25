<?php
/**
 * Plugin Name: Paid Memberships Pro - Kit Add On
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-kit-integration/
 * Description: Connect Paid Memberships Pro to Kit to add members as subscribers and manage tags automatically.
 * Version: 1.0
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com
 * Text Domain: pmpro-kit
 * Domain Path: /languages
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PMPRO_KIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'PMPRO_KIT_BASENAME', plugin_basename( __FILE__ ) );

include_once PMPRO_KIT_DIR . 'includes/functions.php'; // Core plugin functions.
include_once PMPRO_KIT_DIR . 'includes/admin.php'; // Admin settings and menu.
include_once PMPRO_KIT_DIR . 'includes/migration.php'; // Code to migrate from the old convertkit-paid-memberships-pro plugin.
include_once PMPRO_KIT_DIR . 'classes/class-pmpro-kit-api-wrapper.php'; // API wrapper class.

/**
 * Runs only when the plugin is activated.
 *
 * 1.0
 */
function pmpro_kit_activation() {
	// Set a transient so we can show a notice after activation.
	set_transient( 'pmpro-kit-admin-notice', true, 5 );
}
register_activation_hook( PMPRO_KIT_BASENAME, 'pmpro_kit_activation' );

/**
 * Admin Notice on Activation.
 *
 * 1.0
 */
function pmpro_kit_admin_notice() {
	// Check transient, if available display notice.
	if ( get_transient( 'pmpro-kit-admin-notice' ) ) { ?>
		<div class="updated notice is-dismissible">
			<p>
			<?php
				esc_html_e( 'Thank you for activating the Kit Add On.', 'pmpro-kit' );
				echo ' <a href="' . esc_url( add_query_arg( array( 'page' => 'pmpro-kit' ), admin_url( 'admin.php' ) ) ) . '">';
				esc_html_e( 'Click here to configure settings.', 'pmpro-kit' );
				echo '</a>';
			?>
			</p>
		</div>
		<?php
		// Delete transient, only display this notice once.
		delete_transient( 'pmpro-kit-admin-notice' );
	}
}
add_action( 'admin_notices', 'pmpro_kit_admin_notice' );

/**
 * Function to add links to the plugin action links
 *
 * @param array $links Array of links to be shown in plugin action links.
 */
function pmpro_kit_plugin_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		$new_links = array(
			'<a href="' . esc_url( add_query_arg( array( 'page' => 'pmpro-kit' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Settings', 'pmpro-kit' ) . '</a>',
		);

		$links = array_merge( $new_links, $links );
	}
	return $links;
}
add_filter( 'plugin_action_links_' . PMPRO_KIT_BASENAME, 'pmpro_kit_plugin_action_links' );

/**
 * Function to add links to the plugin row meta.
 *
 * 1.0
 */
function pmpro_kit_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-kit.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/pmpro-kit-integration/' ) . '" title="' . esc_attr__( 'View Documentation', 'pmpro-kit' ) . '">' . esc_html__( 'Docs', 'pmpro-kit' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-kit' ) . '">' . esc_html__( 'Support', 'pmpro-kit' ) . '</a>',
		);
		$links = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmpro_kit_plugin_row_meta', 10, 2 );
