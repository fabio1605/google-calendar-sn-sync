<?php

function gcsn_delete_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sn_calendar_events';

    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// === Plugin Activation: Create Custom Table ===



function gcsn_create_date_checks_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcsn_date_checks';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        search_date DATE NOT NULL,
        venue VARCHAR(255) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'gcsn_create_date_checks_table');

function gcsn_create_token_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcsn_tokens';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(255),
        access_token TEXT,
        refresh_token TEXT,
        expires_in INT,
        token_type VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'gcsn_create_token_table');


register_activation_hook(__FILE__, 'gcsn_create_table');
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


?>