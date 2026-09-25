<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Kit settings link to PMPro settings menu.
 *
 * 1.0
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
 * 1.0
 */
function pmprokit_settings_page() {
	// Get existing options.
	$options = get_option( 'pmprokit_options' );

	// Get all PMPro levels.
	$pmpro_levels = pmpro_getAllLevels( true );
	$pmpro_levels = pmpro_sort_levels_by_order( $pmpro_levels );

	// Handle form submission.
	if ( isset( $_POST['pmprokit_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pmprokit_settings_nonce'] ) ), 'pmprokit_save_settings' ) ) {
		// Check whether the API key has changed. If so, we may need to refresh tags.
		$old_api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$new_api_key = empty( $_POST['api_key'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
		$api_key_changed = ( $old_api_key !== $new_api_key );

		// Sanitize and save options here.
		$options['api_key'] = $new_api_key;
		$options['update_on_profile_save'] = empty( $_POST['update_on_profile_save'] ) ? 'yes' : sanitize_text_field( wp_unslash( $_POST['update_on_profile_save'] ) );
		$options['enable_removing_tags'] = empty( $_POST['enable_removing_tags'] ) ? 'yes' : sanitize_text_field( wp_unslash( $_POST['enable_removing_tags'] ) );
		$options['enable_async'] = empty( $_POST['enable_async'] ) ? 'yes' : sanitize_text_field( wp_unslash( $_POST['enable_async'] ) );
		$options['enable_debug_log'] = empty( $_POST['enable_debug_log'] ) ? 'no' : sanitize_text_field( wp_unslash( $_POST['enable_debug_log'] ) );

		// Save level tag assignments if level tags were shown.
		if ( ! empty( $_POST['level_tags_shown'] ) ) {
			$options['level_tags_all'] = array();
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

		// If debug logging was enabled, ensure the log file exists.
		pmprokit_debug_log( 'PMPro Kit settings updated.' );

		// Show a success message.
		echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'pmpro-kit' ) . '</p></div>';
	}
	?>
	<div class="wrap pmpro_admin">
		<h1><?php esc_html_e( 'Kit Settings', 'pmpro-kit' ); ?></h1>
		<p><?php
			$pmprokit_settings_link = '<a title="' . esc_attr__( 'Paid Memberships Pro - Kit Add On Documentation', 'pmpro-kit' ) . '" target="_blank" rel="nofollow noopener" href="https://www.paidmembershipspro.com/add-ons/pmpro-kit-integration/?utm_source=plugin&utm_medium=pmpro-kit&utm_campaign=add-ons&utm_content=pmpro-kit-settings">' . esc_html__( 'Kit Add On documentation', 'pmpro-kit' ) . '</a>';
			// translators: %s: Link to Kit Add On documentation.
			printf( esc_html__('Learn more about these settings in the %s.', 'pmpro-kit' ), $pmprokit_settings_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?></p>
		<form method="post" action="">
			<div class="pmpro_section" data-visibility="shown" data-activated="true">
				<div class="pmpro_section_toggle">
					<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
						<?php esc_html_e( 'General Settings', 'pmpro-kit' ); ?>
					</button>
				</div>
				<div class="pmpro_section_inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="api_key"><?php esc_html_e( 'V4 API Key', 'pmpro-kit' ); ?></label>
							</th>
							<td>
								<input type="text" name="api_key" id="api_key" value="<?php echo esc_attr( isset( $options['api_key'] ) ? $options['api_key'] : '' ); ?>" class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Your V4 API key is used to connect your Kit account to this membership site.', 'pmpro-kit' ); ?>
									<a href="https://app.kit.com/account_settings/developer_settings" target="_blank" rel="noopener"><?php esc_html_e( 'Create a V4 API key in your Kit developer settings.', 'pmpro-kit' ); ?></a>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="update_on_profile_save"><?php esc_html_e( 'Sync on Profile Update', 'pmpro-kit' ); ?></label></th>
							<td>
								<?php
								$update_on_profile_save = isset( $options['update_on_profile_save'] ) ? $options['update_on_profile_save'] : 'yes';
								?>
								<select name="update_on_profile_save" id="update_on_profile_save">
									<option value="yes" <?php selected( $update_on_profile_save, 'yes' ); ?>><?php esc_html_e( 'Yes, sync subscriber data and tags', 'pmpro-kit' ); ?></option>
									<option value="subscriber_only" <?php selected( $update_on_profile_save, 'subscriber_only' ); ?>><?php esc_html_e( 'Yes, sync subscriber data only', 'pmpro-kit' ); ?></option>
									<option value="no" <?php selected( $update_on_profile_save, 'no' ); ?>><?php esc_html_e( 'No, do not sync anything on profile update', 'pmpro-kit' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Choose what to sync to Kit when a user profile is updated in WordPress.', 'pmpro-kit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="enable_removing_tags"><?php esc_html_e( 'Remove Tags When Membership Changes', 'pmpro-kit' ); ?></label></th>
							<td>
								<?php
								$enable_removing_tags = isset( $options['enable_removing_tags'] ) ? $options['enable_removing_tags'] : 'yes';
								?>
								<select name="enable_removing_tags" id="enable_removing_tags">
									<option value="yes" <?php selected( $enable_removing_tags, 'yes' ); ?>><?php esc_html_e( 'Yes, remove tags that no longer apply', 'pmpro-kit' ); ?></option>
									<option value="no" <?php selected( $enable_removing_tags, 'no' ); ?>><?php esc_html_e( 'No, never remove tags', 'pmpro-kit' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'When enabled, the integration will remove tags in Kit when they no longer match the current membership.', 'pmpro-kit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="disable_async"><?php esc_html_e( 'Process Updates in the Background', 'pmpro-kit' ); ?></label></th>
							<td>
								<?php
								$enable_async = isset( $options['enable_async'] ) ? $options['enable_async'] : 'yes';
								?>
								<select name="enable_async" id="enable_async">
									<option value="yes" <?php selected( $enable_async, 'yes' ); ?>><?php esc_html_e( 'Yes, run updates in the background', 'pmpro-kit' ); ?></option>
									<option value="no" <?php selected( $enable_async, 'no' ); ?>><?php esc_html_e( 'No, run updates immediately', 'pmpro-kit' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'When enabled, subscriber updates and tag changes will run in the background using Action Scheduler. This can improve performance during checkout, profile updates, and membership changes.', 'pmpro-kit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label><?php esc_html_e( 'Debug Logging', 'pmpro-kit' ); ?></label></th>
							<td>
								<?php
								$enable_debug_log = isset( $options['enable_debug_log'] ) ? $options['enable_debug_log'] : 'no';
								?>
								<select name="enable_debug_log" id="enable_debug_log">
									<option value="yes" <?php selected( $enable_debug_log, 'yes' ); ?>><?php esc_html_e( 'Yes, enable debug logging', 'pmpro-kit' ); ?></option>
									<option value="no" <?php selected( $enable_debug_log, 'no' ); ?>><?php esc_html_e( 'No, disable debug logging', 'pmpro-kit' ); ?></option>
								</select>
								<p class="description">
									<?php
									esc_html_e( 'When enabled, the integration will write debug details to the log to help troubleshoot issues.', 'pmpro-kit' );
									if ( 'yes' === $enable_debug_log ) {
										$log_file_link = add_query_arg(
											array(
												'pmpro_restricted_file_dir' => 'logs',
												'pmpro_restricted_file'     => 'pmpro-kit.log',
											),
											home_url()
										);
										echo ' <a href="' . esc_url( $log_file_link ) . '" target="_blank">' . esc_html__( 'Download log.', 'pmpro-kit' ) . '</a>';
									}
									?>
								</p>
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
						echo '<div class="pmpro_message pmpro_error"><p>' . esc_html__( 'Error fetching tags from Kit.', 'pmpro-kit' ) . ' ' . esc_html( $tags->get_error_message() ) . '</p></div>';
					} elseif ( empty( $tags ) ) {
						?>
						<p>
							<?php echo esc_html__( 'No tags found in your Kit account.', 'pmpro-kit' ); ?>
							<a href="<?php echo esc_url( add_query_arg( 'pmprokit_refresh_tags', '1' ) ); ?>">
								<?php esc_html_e( 'Click here to refresh tags', 'pmpro-kit' ); ?>
							</a>
						</p>
						<?php
					} else {
						// Loop through PMPro levels and allow assigning tags to each level via checkboxes.
						?>
						<p>
							<?php echo esc_html__( 'Select the Kit tags to assign to members when they are added to each membership level.', 'pmpro-kit' ) . ' '; ?>
							<a href="<?php echo esc_url( add_query_arg( 'pmprokit_refresh_tags', '1' ) ); ?>">
								<?php esc_html_e( 'Click here to refresh tags', 'pmpro-kit' ); ?>
							</a>
						</p>
						<input type="hidden" name="level_tags_shown" value="1">
						<table class="form-table">
							<?php
							foreach ( $pmpro_levels as $level ) {
								?>
								<tr>
									<th scope="row"><?php echo esc_html( $level->name ); ?></th>
									<td>
										<?php
										$selected_tags = isset( $options[ 'level_tags_' . $level->id ] ) ? (array) $options[ 'level_tags_' . $level->id ] : array();

										// Build the selectors for the checkbox list based on number of tags.
										$classes = array();
										$classes[] = "pmpro_checkbox_box";
										if ( count( $tags ) > 5 ) {
											$classes[] = "pmpro_scrollable";
										}
										$class = implode( ' ', array_unique( $classes ) );
										?>
										<div class="<?php echo esc_attr( $class ); ?>">
											<?php
											foreach ( $tags as $tag ) {
												$checked = in_array( $tag['id'], $selected_tags, true ) ? 'checked' : '';
												?>
												<div class="pmpro_clickable">
													<input type="checkbox" id="level_tags_<?php echo esc_attr( $level->id ); ?>_<?php echo esc_attr( $tag['id'] ); ?>" name="level_tags_<?php echo esc_attr( $level->id ); ?>[]" value="<?php echo esc_attr( $tag['id'] ); ?>" <?php echo esc_attr( $checked ); ?>>
													<label for="level_tags_<?php echo esc_attr( $level->id ); ?>_<?php echo esc_attr( $tag['id'] ); ?>">
														<?php echo esc_html( $tag['name'] ); ?>
													</label>
												</div>
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