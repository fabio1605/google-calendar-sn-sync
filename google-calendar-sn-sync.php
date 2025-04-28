<?php
/*
Plugin Name: Google Calendar SN Sync
Description: Syncs Google Calendar events with (SN) in the title into a custom WP table daily.
Version: 1.0
Author: Fabio Photography
*/

require_once __DIR__ . '/vendor/autoload.php';


add_shortcode('SN-check-availability', 'gcsn_check_availability_shortcode');



require_once __DIR__ . '/includes/DateChecks.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Shortcode.php';
require_once __DIR__ . '/includes/Sync.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/dbschema.php';  // Include the database schema file
require_once __DIR__ . '/includes/datecheck.php';
require_once __DIR__ . '/includes/oauth.php';
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/includes/Map.php';


if (interface_exists(\Psr\Log\LoggerInterface::class)) {
    $r = new \ReflectionMethod(\Psr\Log\LoggerInterface::class, 'emergency');
    error_log('LoggerInterface::emergency() signature: ' . (string) $r);
}

add_action('wp_ajax_nopriv_gcsn_check_date_available', 'gcsn_ajax_check_date_available');
add_action('wp_ajax_gcsn_check_date_available', 'gcsn_ajax_check_date_available');

register_activation_hook(__FILE__, 'gcsn_create_table');
register_activation_hook(__FILE__, 'gcsn_create_token_table');
register_activation_hook(__FILE__, 'gcsn_create_date_checks_table');
register_activation_hook(__FILE__, 'gcsn_upgrade_plugin');

register_deactivation_hook(__FILE__, 'gcsn_delete_table');




// === Daily Cron Schedule ===
add_action('gcsn_daily_sync', 'gcsn_sync_events');

register_activation_hook(__FILE__, 'gcsn_schedule_cron');
function gcsn_schedule_cron() {
    if (!wp_next_scheduled('gcsn_daily_sync')) {
        wp_schedule_event(time(), 'daily', 'gcsn_daily_sync');
    }
}

add_action('admin_init', function () {
    if (!is_admin()) return;

    // Only run for your page + correct action
    if (
        isset($_GET['page']) && $_GET['page'] === 'gcsn-sync' &&
        isset($_GET['gcsn_action']) && $_GET['gcsn_action'] === 'export_csv'
    ) {
        gcsn_export_csv_and_exit();
    }
});


register_deactivation_hook(__FILE__, 'gcsn_clear_cron');
function gcsn_clear_cron() {
    wp_clear_scheduled_hook('gcsn_daily_sync');
}


add_action('admin_menu', function () {
    // Top-level menu
    add_menu_page(
        'SN Calendar Sync',
        'SN Calendar Sync',
        'manage_options',
        'gcsn-sync',
        'gcsn_render_admin_page'
    );

    // ✅ Add the main dashboard as the FIRST submenu
  

    // Then the Map page
    add_submenu_page(
        'gcsn-sync',
        'Map View',
        'Map',
        'manage_options',
        'gcsn-map',
        'gcsn_render_map_admin_page'
    );
	
		add_submenu_page(
    'gcsn-sync',              // parent slug
    'Calendar Sync Settings',// page title
    'Settings',              // menu title
    'manage_options',
    'gcsn-settings',         // unique slug
    'gcsn_render_settings_page' // callback function
);

  add_submenu_page(
        'gcsn-sync',                // parent menu slug
        'Date Check Logs',           // page title
        'Date Check Logs',           // menu label
        'manage_options',            // capability
        'gcsn-date-check-logs',      // menu slug
        'gcsn_render_date_checks_page' // function to render the page
    );
	

});




// Map


