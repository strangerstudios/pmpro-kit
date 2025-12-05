<?php

/**
 * Disable the old ConvertKit integration plugin if it is active, and show an admin notice if needed.
 *
 * @since TBD
 */
function pmprokit_check_for_legacy_plugin() {
    $legacy_plugin_path = 'convertkit-paid-memberships-pro/convertkit-pmp.php';
    if ( file_exists( WP_PLUGIN_DIR . '/' . $legacy_plugin_path ) && is_plugin_active( $legacy_plugin_path ) ) {
        deactivate_plugins( $legacy_plugin_path );
        ?>
        <div class="notice notice-warning">
            <p>
                <?php
                echo wp_kses(
                    __( 'The Paid Memberships Pro - ConvertKit Integration Add On has been deactivated because its functionality has been replaced by the Paid Memberships Pro - Kit Add On. You can safely delete the Paid Memberships Pro - ConvertKit Integration Add On from your site.', 'pmpro-kit' ),
                    array( 'strong' => array() )
                );
                ?>
            </p>
        </div>
        <?php
    } elseif ( file_exists( WP_PLUGIN_DIR . '/' . $legacy_plugin_path ) && ! empty( $_REQUEST['page'] ) && 'pmpro-kit' === sanitize_text_field( $_REQUEST['page'] ) ) {
        ?>
        <div class="notice notice-warning">
            <p>
                <?php
                echo wp_kses(
                    __( 'The Paid Memberships Pro - ConvertKit Integration Add On is no longer needed because its functionality has been replaced by the Paid Memberships Pro - Kit Add On. If you have not already done so, you can safely delete the Paid Memberships Pro - ConvertKit Integration Add On from your site.', 'pmpro-kit' ),
                    array( 'strong' => array() )
                );
                ?>
            </p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'pmprokit_check_for_legacy_plugin', 10, 2 );

/**
 * If the pmprokit_options option does not exist, migrate settings from the old convertkit-paid-memberships-pro plugin.
 *
 * @since TBD
 *
 * @param mixed $default_value The default value for the option.
 * @return mixed The migrated options if applicable, otherwise the original default value.
 */
function pmprokit_migrate_legacy_options( $default_value ) {
    // Check if the old plugin's options exist.
    $legacy_options = get_option( 'convertkit-pmp-options' );
    if ( ! empty( $legacy_options ) && is_array( $legacy_options ) ) {
        $new_options = array();

        // We cannot migrate the API key since those are not for the v4 API,
        // so just migrate level tag assignments.
        $pmpro_levels    = pmpro_getAllLevels( true );
        $pmpro_level_ids = wp_list_pluck( $pmpro_levels, 'id' );
        $pmpro_level_ids[] = 0; // Include level 0 (no level).
        $all_tag_ids = array();
        foreach ( $pmpro_level_ids as $level_id ) {
            $old_key = 'convertkit-mapping-' . $level_id;
            $new_key = 'level_tags_' . $level_id;
            if ( ! empty( $legacy_options[ $old_key ] ) ) {
                $new_options[ $new_key ] = array( (int) $legacy_options[ $old_key ] );
                $all_tag_ids[] = (int) $legacy_options[ $old_key ];
            }
        }
        $new_options['level_tags_all'] = array_unique( $all_tag_ids );

        // Delete the old options.
        delete_option( 'convertkit-pmp-options' );

        // Save the migrated options and return them.
        update_option( 'pmprokit_options', $new_options );
        return $new_options;
    }

    // No legacy options to migrate, return the original default value.
    return $default_value;
}
add_filter( 'default_option_pmprokit_options', 'pmprokit_migrate_legacy_options' );

/**
 * If the subscriber ID is not set for a user, migrate it from the old convertkit-paid-memberships-pro plugin.
 *
 * @since TBD
 */
function pmprokit_migrate_legacy_subscriber_id_for_user( $value, $user_id, $meta_key ) {
    // If the meta key is not for the subscriber ID, bail.
    if ( 'pmprokit_subscriber_id' !== $meta_key ) {
        return $value;
    }

    // Check if there is a legacy subscriber ID.
    $legacy_subscriber_id = get_user_meta( $user_id, 'pmprock_subscriber_id', true );
    if ( ! empty( $legacy_subscriber_id ) ) {
        // Delete the old meta key.
        delete_user_meta( $user_id, 'pmprock_subscriber_id' );

        // Update the new subscriber ID meta key and return it.
        update_user_meta( $user_id, 'pmprokit_subscriber_id', intval( $legacy_subscriber_id ) );
        return $legacy_subscriber_id;
    }

    // No legacy subscriber ID to migrate, return the original value.
    return $value;
}
add_filter( 'default_user_metadata', 'pmprokit_migrate_legacy_subscriber_id_for_user', 10, 3 );
