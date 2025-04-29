<?php

function gcsn_ajax_check_date_available() {
    global $wpdb;
    $table = $wpdb->prefix . 'sn_calendar_events';
    
    $date = sanitize_text_field($_GET['date']);
    $venue = isset($_GET['venue']) ? sanitize_text_field($_GET['venue']) : '';

    // Save search to log table
    $table_name = $wpdb->prefix . 'gcsn_date_checks';
    $wpdb->insert($table_name, [
        'search_date' => $date,
        'venue' => $venue,
        'ip_address' => gcsn_get_user_ip()
    ]);

    // Check if date is available
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE start_date = %s", $date));

    $available_message = get_option('gcsn_available_message', '✅ This date is available.');
    $unavailable_message = get_option('gcsn_unavailable_message', '❌ Sorry, this date is already booked.');

    wp_send_json([
        'available' => !$existing,
        'message' => !$existing ? $available_message : $unavailable_message
    ]);
}

?>