<?php

// Plugin initialization function
function gcsn_initialize_plugin() {
    // Add necessary actions, filters, and registrations here
    add_action('wp_ajax_nopriv_gcsn_check_date_available', 'gcsn_ajax_check_date_available');
    add_action('wp_ajax_gcsn_check_date_available', 'gcsn_ajax_check_date_available');
    // Additional initialization steps
}

// Register plugin's activation and deactivation functions
register_activation_hook(__FILE__, 'gcsn_create_table');
register_deactivation_hook(__FILE__, 'gcsn_delete_table');

// Initialize plugin
gcsn_initialize_plugin();
