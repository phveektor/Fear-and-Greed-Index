<?php
/**
 * Uninstall handler for Fear & Greed Gauge
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'fgg_installed' );
delete_option( 'fgg_settings' );

// Delete transients
global $wpdb;
$transient_key = 'fg_gauge_data';
$like = "%_transient_{$transient_key}%";
$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", $like ) );

// If site is multisite, consider network-wide cleanup (left minimal to avoid accidental data loss)
