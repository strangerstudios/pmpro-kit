<?php

/**
 * Update the Kit subscriber for a user.
 *
 * @since TBD
 *
 * @param int  $user_id The WordPress user ID.
 * @param bool $update_tags Whether to update tags for the subscriber. Default true.
 */
function pmprokit_update_subscriber_for_user( $user_id, $update_tags = true ) {
	// Make sure the user exists.
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	// Build logging message.
	$log = "Updating subscriber for user ID {$user_id} (email: {$user->user_email}). ";

	// Get subscriber ID from user meta.
	$subscriber_id = get_user_meta( $user_id, 'pmprokit_subscriber_id', true );
	$log .= "Current subscriber ID: " . ( empty( $subscriber_id ) ? 'none' : intval( $subscriber_id ) ) . ". ";

	// If the user does not have a membership level and are not already a subscriber, bail.
	$user_levels = pmpro_getMembershipLevelsForUser( $user_id );
	if ( empty( $user_levels ) && empty( $subscriber_id ) ) {
		$log .= "User has no membership levels and is not a subscriber. No action taken. ";
		pmprokit_debug_log( $log );
		return;
	}

	// Set up API wrapper.
	$api_wrapper = PMPro_Kit_API_Wrapper::get_instance();

	// Prepare subscriber data.
	$subscriber_data = array(
		'email_address' => $user->user_email,
		'first_name'    => empty( $user->first_name ) ? $user->user_login : $user->first_name,
		'fields'        => array(), 
	);

	/**
	 * Filter subscriber data before sending to Kit.
	 *
	 * @since TBD
	 *
	 * @param array $subscriber_data The subscriber data to be sent to Kit.
	 * @param WP_User $user The WordPress user object.
	 */
	$subscriber_data = apply_filters( 'pmprokit_subscriber_data', $subscriber_data, $user );
	$log .= "New subscriber data: " . print_r( $subscriber_data, true ) . ". ";

	// Update existing subscriber if we have a subscriber ID.
	if ( ! empty( $subscriber_id ) ) {
		// Update existing subscriber.
		$subscriber = $api_wrapper->update_subscriber( $subscriber_id, $subscriber_data );

		// Handle errors.
		if ( is_wp_error( $subscriber ) ) {
			// If this is a 404 error, the subscriber does not exist. Clear the subscriber object and create a new subscriber below.
			if ( isset( $subscriber->errors['pmprokit_api_error'] ) && intval( $subscriber->get_error_data( 'pmprokit_api_error' )['status_code'] ) === 404 ) {
				$log .= "Subscriber ID {$subscriber_id} not found in Kit. ";
				$subscriber = null;
			} else {
				// Some other error occurred, potentially incorrect API keys or 500 error. Keep the existing subscriber ID intact and bail.
				$log .= "Error updating subscriber ID {$subscriber_id}: " . $subscriber->get_error_message() . ". ";
				pmprokit_debug_log( $log );
				return;
			}
		} else {
			// Successfully updated subscriber, no further action needed.
			$log .= "Successfully updated subscriber ID {$subscriber_id}. ";
		}
	}

	// Create new subscriber if needed.
	if ( empty( $subscriber ) ) {
		// Create new subscriber.
		$subscriber = $api_wrapper->create_subscriber( $subscriber_data );

		// If we failed to create a subscriber, bail.
		if ( is_wp_error( $subscriber ) ) {
			$log .= "Error creating new subscriber: " . $subscriber->get_error_message() . ". ";
			pmprokit_debug_log( $log );
			return;
		}

		// Save subscriber ID in user meta.
		$subscriber_id = intval( $subscriber['id'] );
		update_user_meta( $user_id, 'pmprokit_subscriber_id', $subscriber_id );
		$log .= "Successfully created subscriber with ID {$subscriber_id}. ";
	}

	// If we are not updating tags, bail.
	if ( ! $update_tags ) {
		$log .= "Not updating tags. ";
		pmprokit_debug_log( $log );
		return;
	}

	// Get all tags for the current subscriber.
	$current_tags = $api_wrapper->list_tags_for_subscriber( $subscriber_id );
	if ( is_wp_error( $current_tags ) ) {
		// Could not get current tags, bail.
		$log .= "Error retrieving current tags for subscriber ID {$subscriber_id}: " . $current_tags->get_error_message() . ". ";
		pmprokit_debug_log( $log );
		return;
	}
	$current_tag_ids = array_map( function ( $tag ) {
		return intval( $tag['id'] );
	}, $current_tags );
	$log .= "Current tag IDs for subscriber ID {$subscriber_id}: " . implode( ', ', $current_tag_ids ) . ". ";

	// Get tags that PMPro controls.
	$options = get_option( 'pmprokit_options', array() );
	$controlled_tag_ids = empty( $options['level_tags_all'] ) ? array() : $options['level_tags_all'];

	/**
	 * Filter the list of tag IDs that PMPro controls.
	 *
	 * @since TBD
	 *
	 * @param array $controlled_tag_ids The list of tag IDs that PMPro controls.
	 */
	$controlled_tag_ids = apply_filters( 'pmprokit_controlled_tag_ids', $controlled_tag_ids );
	$log .= "Controlled tag IDs: " . implode( ', ', $controlled_tag_ids ) . ". ";

	// Get tags to assign based on user's membership levels.
	$new_tag_ids = array();
	foreach ( $user_levels as $level ) {
		$key = 'level_tags_' . $level->id;
		if ( ! empty( $options[ $key ] ) && is_array( $options[ $key ] ) ) {
			$new_tag_ids = array_merge( $new_tag_ids, $options[ $key ] );
		}
	}
	$new_tag_ids = array_unique( $new_tag_ids );
	$log .= "New tag IDs: " . implode( ', ', $new_tag_ids ) . ". ";

	/**
	 * Filter the list of tag IDs to assign to the subscriber.
	 *
	 * @since TBD
	 *
	 * @param array $new_tag_ids The list of tag IDs to assign.
	 * @param WP_User $user The WordPress user object.
	 */
	$new_tag_ids = apply_filters( 'pmprokit_subscriber_tag_ids', $new_tag_ids, $user );

	// Determine tags to add and remove.
	$tags_to_add    = array_diff( $new_tag_ids, $current_tag_ids );
	$tags_to_remove = array_diff( $current_tag_ids, $new_tag_ids );

	// Only modify tags that PMPro controls.
	$tags_to_add    = array_intersect( $tags_to_add, $controlled_tag_ids );
	$tags_to_remove = array_intersect( $tags_to_remove, $controlled_tag_ids );

	$log .= "Tags to add: " . implode( ', ', $tags_to_add ) . ". ";
	$log .= "Tags to remove: " . implode( ', ', $tags_to_remove ) . ". ";

	// Add tags.
	// Note: Bulk tagging endpoint requires oAuth authentication, so we tag one at a time here.
	if ( ! empty( $tags_to_add ) ) {
	   foreach ( $tags_to_add as $tag_id ) {
			$response = $api_wrapper->tag_subscriber( $tag_id, $subscriber_id );
			if ( is_wp_error( $response ) ) {
				$log .= "Error adding tag ID {$tag_id}: " . $response->get_error_message() . ". ";
			} else {
				$log .= "Added tag ID {$tag_id}. ";
			}
		}
	}

	// Remove tags.
	$options = get_option( 'pmprokit_options', array() );
	$enable_removing_tags = isset( $options['enable_removing_tags'] ) ? $options['enable_removing_tags'] : 'yes';
	if ( 'no' !== $enable_removing_tags && ! empty( $tags_to_remove ) ) {
		foreach ( $tags_to_remove as $tag_id ) {
			$response = $api_wrapper->remove_tag_from_subscriber( $tag_id, $subscriber_id );
			if ( is_wp_error( $response ) ) {
				$log .= "Error removing tag ID {$tag_id}: " . $response->get_error_message() . ". ";
			} else {
				$log .= "Removed tag ID {$tag_id}. ";
			}
		}
	} elseif ( 'no' === $enable_removing_tags ) {
		$log .= "Tag removal is disabled. ";
	}

	// Write debug log.
	pmprokit_debug_log( $log );
}
add_action( 'pmprokit_update_subscriber_for_user', 'pmprokit_update_subscriber_for_user', 10, 2 );

/**
 * Enqueue a task to sync user data to Kit.
 *
 * @since TBD
 *
 * @param int $user_id The WordPress user ID.
 * @param bool $update_tags Whether to sync tags for the subscriber. Default true.
 */
function pmprokit_enqueue_sync_for_user( $user_id, $update_tags = true ) {
	// Check if we should process the change immediately.
	$options = get_option( 'pmprokit_options', array() );
	if ( ! empty( $options['enable_async'] ) && 'no' === $options['enable_async'] ) {
		pmprokit_update_subscriber_for_user( $user_id, $update_tags );
		return;
	}

	// Enqueue the task.
	PMPro_Action_Scheduler::instance()->maybe_add_task(
		'pmprokit_update_subscriber_for_user',
		array(
			'user_id' => $user_id,
			'update_tags' => $update_tags,
		),
		'pmprokit_update_subscriber_tasks'
	);
}

/**
 * When a user's profile is updated, sync their data to Kit.
 *
 * @since TBD
 *
 * @param int $user_id The WordPress user ID.
 */
function pmprokit_sync_user_on_profile_update( $user_id ) {
	$options = get_option( 'pmprokit_options', array() );
	$update_on_profile_save = isset( $options['update_on_profile_save'] ) ? $options['update_on_profile_save'] : 'yes';
	if ( 'no' === $options['update_on_profile_save'] ) {
		return;
	}

	pmprokit_enqueue_sync_for_user( $user_id, 'subscriber_only' !== $update_on_profile_save );
}
add_action( 'profile_update', 'pmprokit_sync_user_on_profile_update', 10, 1 );

/**
 * When user fields are saved from the PMPro Edit Member screen, sync their data to Kit.
 *
 * PMPro's user fields panel saves directly to user meta without firing profile_update,
 * so we need to detect when a user-fields panel was saved and trigger the sync.
 *
 * This code runs at priority 20 to run after PMPro's save at priority 10.
 *
 * @since TBD
 */
function pmprokit_sync_user_on_edit_member_user_fields_save() {
	// Check if we're on the pmpro-member page with a user-fields panel being saved.
	if ( empty( $_REQUEST['page'] ) || 'pmpro-member' !== $_REQUEST['page'] ) {
		return;
	}

	// Check that this is a POST request.
	if ( empty( $_POST ) ) {
		return;
	}

	// Check that a user-fields panel is being saved.
	$panel_slug = empty( $_REQUEST['pmpro_member_edit_panel'] ) ? '' : sanitize_text_field( $_REQUEST['pmpro_member_edit_panel'] );
	if ( empty( $panel_slug ) || strpos( $panel_slug, 'user-fields-' ) !== 0 ) {
		return;
	}

	// Verify the nonce.
	if ( empty( $_REQUEST['pmpro_member_edit_saved_panel_nonce'] ) || ! wp_verify_nonce( $_REQUEST['pmpro_member_edit_saved_panel_nonce'], 'pmpro_member_edit_saved_panel_' . $panel_slug ) ) {
		return;
	}

	// Get the user ID.
	$user_id = empty( $_REQUEST['user_id'] ) ? 0 : intval( $_REQUEST['user_id'] );
	if ( empty( $user_id ) ) {
		return;
	}

	// Check settings.
	$options = get_option( 'pmprokit_options', array() );
	$update_on_profile_save = isset( $options['update_on_profile_save'] ) ? $options['update_on_profile_save'] : 'yes';
	if ( 'no' === $update_on_profile_save ) {
		return;
	}

	// Sync the user.
	pmprokit_enqueue_sync_for_user( $user_id, 'subscriber_only' !== $update_on_profile_save );
}
add_action( 'admin_init', 'pmprokit_sync_user_on_edit_member_user_fields_save', 20 );

/**
 * When a user's membership level changes, sync their data to Kit.
 *
 * @since TBD
 *
 * @param array $old_users_and_levels Array of user IDs and their old levels.
 */
function pmprokit_sync_users_after_all_membership_level_changes( $old_users_and_levels ) {
	foreach ( $old_users_and_levels as $user_id => $old_level ) {
		pmprokit_enqueue_sync_for_user( $user_id );
	}
}
add_action( 'pmpro_after_all_membership_level_changes', 'pmprokit_sync_users_after_all_membership_level_changes', 10, 1 );

/**
 * Get all tags from Kit with caching.
 *
 * @since TBD
 *
 * @param bool $force_refresh Whether to force refresh the cached tags. Default false.
 * @return array|WP_Error List of tags or WP_Error on failure.
 */
function pmprokit_get_all_tags( $force_refresh = false ) {
	$cache_key = 'pmprokit_all_tags';
	if ( $force_refresh ) {
		delete_transient( $cache_key );
	} else {
		$cached_tags = get_transient( $cache_key );
		if ( $cached_tags !== false ) {
			return $cached_tags;
		}
	}

	// Fetch tags from Kit.
	$api_wrapper = PMPro_Kit_API_Wrapper::get_instance();
	$tags = $api_wrapper->get_tags();
	if ( is_wp_error( $tags ) ) {
		return $tags;
	}

	// Cache tags for 12 hours.
	set_transient( $cache_key, $tags, 12 * HOUR_IN_SECONDS );

	return $tags;
}

/**
 * Get the location of the PMPro Kit log file.
 *
 * @since TBD
 *
 * @return string The log file path.
 */
function pmprokit_get_log_file_path() {
	return apply_filters( 'pmprokit_log_file_path', pmpro_get_restricted_file_path( 'logs', 'pmpro-kit.log' ) );
}

/**
 * Maybe add an entry to the debug log.
 *
 * @since TBD
 *
 * @param string $message The log message.
 */
function pmprokit_debug_log( $message ) {
	// Check whether debug logging is enabled.
	$options = get_option( 'pmprokit_options', array() );
	$enable_debug_log = isset( $options['enable_debug_log'] ) ? $options['enable_debug_log'] : 'no';
	if ( 'yes' !== $enable_debug_log ) {
		return;
	}

	// Build the log string.
	$logstr = "Logged On: " . date_i18n("m/d/Y H:i:s") . "\n" . $message . "\n-------------\n";

	// Write to the log file.
	$logfile = pmprokit_get_log_file_path();
	$loghandle = fopen( $logfile, "a+" );
	fwrite( $loghandle, $logstr );
	fclose( $loghandle );
}