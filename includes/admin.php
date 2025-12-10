<?php

/**
 * Add Kit settings link to PMPro settings menu.
 *
 * @since TBD
 */
function pmprokit_admin_menu() {
    add_submenu_page(
        'pmpro-dashboard',
        __( 'Kit', 'pmpro-kit' ),
        __( 'Kit', 'pmpro-kit' ),
        'manage_options',
        'pmpro-kit',
        'pmprokit_settings_page'
    );
}
add_action( 'admin_menu', 'pmprokit_admin_menu' );

/**
 * Render the Kit settings page.
 *
 * @since TBD
 */
function pmprokit_settings_page() {
    // Get existing options.
    $options = get_option( 'pmprokit_options' );

    // Get all PMPro levels.
    $pmpro_levels = pmpro_getAllLevels( true );

    // Handle form submission.
    if ( isset( $_POST['pmprokit_settings_nonce'] ) && wp_verify_nonce( $_POST['pmprokit_settings_nonce'], 'pmprokit_save_settings' ) ) {
        // Check whether the API key has changed. If so, we may need to refresh tags.
        $old_api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $new_api_key = empty( $_POST['api_key'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
        $api_key_changed = ( $old_api_key !== $new_api_key );

        // Sanitize and save options here.
        $options['api_key'] = $new_api_key;
        $options['disable_async'] = empty( $_POST['disable_async'] ) ? 0 : 1;

        // Save level tag assignments if level tags were shown.
        if ( ! empty( $_POST['level_tags_shown'] ) ) {
            $options['level_tags_0'] = empty( $_POST['level_tags_0'] ) ? array() : array_map( 'intval', wp_unslash( (array) $_POST['level_tags_0'] ) );
            $options['level_tags_all'] = array_merge( $options['level_tags_0'], array() ); // For all levels.
            foreach ( $pmpro_levels as $level ) {
                $key = 'level_tags_' . $level->id;
                $options[ $key ] = empty( $_POST[ $key ] ) ? array() : array_map( 'intval', wp_unslash( (array) $_POST[ $key ] ) );
                $options['level_tags_all'] = array_merge( $options['level_tags_all'], $options[ $key ] );
            }
            $options['level_tags_all'] = array_unique( $options['level_tags_all'] );
        }
        update_option( 'pmprokit_options', $options );

        if ( $api_key_changed ) {
            // If the API key changed, refresh the cached tags.
            pmprokit_get_all_tags( true );
        }

        // Show a success message.
        echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'pmpro-kit' ) . '</p></div>';
    }
    ?>
    <div class="wrap pmpro_admin">
        <h1><?php esc_html_e( 'Kit Integration Settings', 'pmpro-kit' ); ?></h1>
        <form method="post" action="">
            <div class="pmpro_section" data-visibility="shown" data-activated="true">
                <div class="pmpro_section_toggle">
                    <button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
                        <span class="dashicons dashicons-arrow-up-alt2"></span>
                        <?php esc_html_e( 'General Settings', 'pmpro-kit' ); ?>
                    </button>
                </div>
                <div class="pmpro_section_inside">
                    <p><?php esc_html_e( 'Add your API key below to connect your Kit account to this membership site.', 'pmpro-kit' ); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_key"><?php esc_html_e( 'V4 API Key', 'pmpro-kit' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="api_key" id="api_key" value="<?php echo esc_attr( isset( $options['api_key'] ) ? $options['api_key'] : '' ); ?>" class="regular-text">
                                <p class="description"><a href="https://app.kit.com/account_settings/developer_settings" target="_blank" rel="noopener"><?php esc_html_e( 'Generate an V4 API Key in your Kit developer settings.', 'pmpro-kit' ); ?></a></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="disable_async"><?php esc_html_e( 'Process Updates Asynchronously?', 'pmpro-kit' ); ?></label></th>
                            <td>
                                <?php
                                $disable_async = isset( $options['disable_async'] ) ? $options['disable_async'] : 0;
                                ?>
                                <select name="disable_async" id="disable_async">
                                    <option value="0" <?php selected( $disable_async, 0 ); ?>><?php esc_html_e( 'Yes, process updates asynchronously', 'pmpro-kit' ); ?></option>
                                    <option value="1" <?php selected( $disable_async, 1 ); ?>><?php esc_html_e( 'No, process updates immediately', 'pmpro-kit' ); ?></option>
                                </select>
                                <p class="description"><small><?php esc_html_e( 'When enabled, subscriber updates and tag assignments will be processed in the background using Action Scheduler, which can help improve site performance during user profile updates and membership changes.', 'pmpro-kit' ); ?></small></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="pmpro_section" data-visibility="<?php echo empty( $options['api_key'] ) ? 'hidden' : 'shown'; ?>" data-activated="<?php echo empty( $options['api_key'] ) ? 'false' : 'true'; ?>">
                <div class="pmpro_section_toggle">
                    <button class="pmpro_section-toggle-button" type="button" aria-expanded="<?php echo empty( $options['api_key'] ) ? 'false' : 'true'; ?>">
                        <span class="dashicons <?php echo empty( $options['api_key'] ) ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2'; ?>"></span>
                        <?php esc_html_e( 'Assign Tags', 'pmpro-kit' ); ?>
                    </button>
                </div>
                <div class="pmpro_section_inside" style="<?php echo empty( $options['api_key'] ) ? 'display:none;' : ''; ?>">
                    <?php
                    // Get all Kit tags to display tag options.
                    $tags = pmprokit_get_all_tags( ! empty( $_GET['pmprokit_refresh_tags'] ) );
                    if ( is_wp_error( $tags ) ) {
                        echo '<p>' . esc_html__( 'Error fetching tags from Kit.', 'pmpro-kit' ) . ' ' . esc_html( $tags->get_error_message() ) . '</p>';
                    } elseif ( empty( $tags ) ) {
                        echo '<p>' . esc_html__( 'No tags found in your Kit account.', 'pmpro-kit' ) . '</p>';
                    } else {
                        // Loop through PMPro levels and allow assigning tags to each level via checkboxes.
                        ?>
                        <p>
                            <?php esc_html_e( 'Below is a list of the defined Membership Levels in Paid Memberships Pro. Assign a membership level to a Kit tag that will be assigned to members of that level.', 'pmpro-kit' ); ?>
                            <a href="<?php echo esc_url( add_query_arg( 'pmprokit_refresh_tags', '1' ) ); ?>">
                                <?php esc_html_e( 'Click here to refresh tags.', 'pmpro-kit' ); ?>
                            </a>
                        </p>
                        <input type="hidden" name="level_tags_shown" value="1">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Non-Members', 'pmpro-kit' ); ?></th>
                                <td>
                                    <?php
                                    $selected_tags = isset( $options['level_tags_0'] ) ? (array) $options['level_tags_0'] : array();
                                    ?>
                                    <div <?php if ( count( $tags ) > 5 ) { echo 'class="pmprokit-checkbox-list-scrollable"'; } ?>>
                                        <?php
                                        foreach ( $tags as $tag ) {
                                            $checked = in_array( $tag['id'], $selected_tags, true ) ? 'checked' : '';
                                            ?>
                                            <label>
                                                <input type="checkbox" name="level_tags_0[]" value="<?php echo esc_attr( $tag['id'] ); ?>" <?php echo esc_attr( $checked ); ?>>
                                                <?php echo esc_html( $tag['name'] ); ?>
                                            </label><br>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <p class="description"><small><?php esc_html_e( 'Tags assigned here will be applied to users without an active membership level.', 'pmpro-kit' ); ?></small></p>
                                </td>
                            </tr>
                            <?php
                            foreach ( $pmpro_levels as $level ) {
                                ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html( $level->name ); ?></th>
                                    <td>
                                        <?php
                                        $selected_tags = isset( $options[ 'level_tags_' . $level->id ] ) ? (array) $options[ 'level_tags_' . $level->id ] : array();
                                        ?>
                                        <div <?php if ( count( $tags ) > 5 ) { echo 'class="pmprokit-checkbox-list-scrollable"'; } ?>>
                                            <?php
                                            foreach ( $tags as $tag ) {
                                                $checked = in_array( $tag['id'], $selected_tags, true ) ? 'checked' : '';
                                                ?>
                                                <label>
                                                    <input type="checkbox" name="level_tags_<?php echo esc_attr( $level->id ); ?>[]" value="<?php echo esc_attr( $tag['id'] ); ?>" <?php echo esc_attr( $checked ); ?>>
                                                    <?php echo esc_html( $tag['name'] ); ?>
                                                </label><br>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </table>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php

            // Add nonce field for security.
            wp_nonce_field( 'pmprokit_save_settings', 'pmprokit_settings_nonce' );
            ?>
            <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'pmpro-kit' ); ?>">
        </form>
    </div>
    <?php
}