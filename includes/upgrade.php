<?php

// Define the upgrade function
function gcsn_upgrade_plugin() {
    // Get the current version stored in the DB
    $current_version = get_option('gcsn_plugin_version');

    // Check if the current version is set, if not, set it to the plugin's initial version
    if (!$current_version) {
        update_option('gcsn_plugin_version', '1.0');  // First-time installation, set to version 1.0
    }

    // Get the current version of the plugin from the header
    $plugin_version = '1.3'; // Update this number when releasing a new version

    // Use version_compare for safe version comparison
    if (version_compare($current_version, $plugin_version, '<')) {
        // If the current version in the database is less than the current plugin version, run the upgrade path
        
        // Version-specific upgrade tasks
        if (version_compare($current_version, '1.1', '<')) {
            gcsn_upgrade_to_version_1_1();
        }

        if (version_compare($current_version, '1.2', '<')) {
            gcsn_upgrade_to_version_1_2();
        }

        if (version_compare($current_version, '1.3', '<')) {
            gcsn_upgrade_to_version_1_3();
        }
        
        // After upgrade, update the version number
        update_option('gcsn_plugin_version', $plugin_version);
    }
}

// Function for upgrading to version 1.1
function gcsn_upgrade_to_version_1_1() {
    global $wpdb;

    // Example: Adding a new column to the table for version 1.1
    $table_name = $wpdb->prefix . 'sn_calendar_events';
    
    // Check if the column already exists to avoid errors
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'new_column'");
    if (empty($column_exists)) {
        // If the column does not exist, add it
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN new_column VARCHAR(255) DEFAULT NULL;");
    }

    // Add more version 1.1 specific upgrade tasks here
    // For example, adding a new option to the WordPress options table
    add_option('gcsn_new_option', 'default_value');
}

// Function for upgrading to version 1.2
function gcsn_upgrade_to_version_1_2() {
    global $wpdb;

    // Example: Add a new column in version 1.2
    $table_name = $wpdb->prefix . 'sn_calendar_events';
    
    // Check if the column already exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'new_column_1_2'");
    if (empty($column_exists)) {
        // If the column doesn't exist, add it
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN new_column_1_2 VARCHAR(255) DEFAULT NULL;");
    }

    // Add more version 1.2 specific upgrade tasks here
    // Example: Update a setting or an option
    update_option('gcsn_feature_flag', true);
}

// Function for upgrading to version 1.3
function gcsn_upgrade_to_version_1_3() {
    global $wpdb;

    // Example: Add a new column in version 1.3
    $table_name = $wpdb->prefix . 'sn_calendar_events';

    // Check if the column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'new_column_1_3'");
    if (empty($column_exists)) {
        // If the column doesn't exist, add it
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN new_column_1_3 VARCHAR(255) DEFAULT NULL;");
    }

    // Add more version 1.3 specific upgrade tasks here
    // Example: Removing an obsolete option
    delete_option('gcsn_obsolete_option');
}

// Register the upgrade function on plugin activation

?>
