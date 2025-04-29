<?php

// Function to create the events table
function gcsn_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sn_calendar_events';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id VARCHAR(255) NOT NULL UNIQUE,
        title TEXT NOT NULL,
        start_date DATE NOT NULL,
        location TEXT,
        description TEXT,
        distance_miles FLOAT NULL,
        travel_time_minutes INT NULL,
        last_synced DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Function to delete the events table
function gcsn_delete_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sn_calendar_events';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
