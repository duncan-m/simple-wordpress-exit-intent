<?php
/**
 * Uninstall handler — remove plugin options when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'eip_settings' );
