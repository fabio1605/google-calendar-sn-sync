    <?php
    /*
    Plugin Name: Google Calendar SN Sync
    Description: Syncs Google Calendar events with (SN) in the title into a custom WP table daily.
    Version: 1.0
    Author: Fabio Photography
    */

    require_once __DIR__ . '/vendor/autoload.php';

    register_deactivation_hook(__FILE__, 'gcsn_delete_table');
    add_shortcode('SN-check-availability', 'gcsn_check_availability_shortcode');

    if (interface_exists(\Psr\Log\LoggerInterface::class)) {
        $r = new \ReflectionMethod(\Psr\Log\LoggerInterface::class, 'emergency');
        error_log('LoggerInterface::emergency() signature: ' . (string) $r);
    }

    function gcsn_check_availability_shortcode() {
        ob_start();

    $enable_venue = get_option('gcsn_enable_venue_textbox', false);

        ?>
    <div id="gcsn-availability-checker">
        <?php if ($enable_venue): ?>
            <label for="gcsn-venue-input" style="display: inline-block; cursor: pointer;">
                <strong>Enter Venue </strong><br>
                <input type="text" id="gcsn-venue-input" placeholder="Enter venue" style="cursor: text;" />
            </label>
            <br><br>
        <?php endif; ?>

        <label for="gcsn-date-input" style="display: inline-block; cursor: pointer;">
            <strong>Select Date:</strong><br>
            <input type="date" id="gcsn-date-input" style="cursor: pointer;" required />
        </label>
        <br><br>

        <button id="gcsn_check_btn" class="button">Check Availability</button>
        <div id="gcsn_result" style="margin-top: 10px;"></div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkBtn = document.getElementById('gcsn_check_btn');
        const resultBox = document.getElementById('gcsn_result');
        const dateInput = document.getElementById('gcsn-date-input');
        const venueInput = document.getElementById('gcsn-venue-input'); // optional, may not exist

        if (checkBtn) {
            checkBtn.addEventListener('click', async function () {
                const date = dateInput.value;
                const venue = venueInput ? venueInput.value : '';

                if (!date) {
                    resultBox.innerHTML = '<span style="color: red;">Please select a date.</span>';
                    return;
                }

                resultBox.innerHTML = '⏳ Checking...';

                try {
                    const response = await fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=gcsn_check_date_available&date=' + encodeURIComponent(date) + '&venue=' + encodeURIComponent(venue));
                    const data = await response.json();
                    resultBox.innerHTML = data.message;
                } catch (error) {
                    resultBox.innerHTML = '<span style="color: red;">Something went wrong. Please try again later.</span>';
                }
            });
        }
    });
    </script>
        <?php

        return ob_get_clean();
    }

    add_action('wp_ajax_nopriv_gcsn_check_date_available', 'gcsn_ajax_check_date_available');
    add_action('wp_ajax_gcsn_check_date_available', 'gcsn_ajax_check_date_available');
    function gcsn_get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle proxies/load balancers
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($ip_list[0]));
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
    }

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

    // === Sync Logic ===
    function gcsn_sync_events() {
        global $wpdb;

        // Get access token from the plugin's token table
        $current_user = wp_get_current_user();
        $token_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gcsn_tokens WHERE user_email = %s",
            $current_user->user_email
        ));

        if (!$token_row || empty($token_row->access_token)) {
            error_log("❌ No access token found — user is not connected to Google.");
            return;
        }

        // Set up Google Client and Service
        $token_data = [
            'access_token' => $token_row->access_token,
            'refresh_token' => $token_row->refresh_token,
            'expires_in' => $token_row->expires_in,
            'created' => strtotime($token_row->created_at)
        ];

        $client = new Google_Client();
        $client->setAccessToken($token_data);

        // Optional: check if token is expired and refresh if needed (future improvement)
        if ($client->isAccessTokenExpired()) {
            error_log("⚠️ Access token expired. Attempting to refresh via server...");

            $refresh_token = $token_row->refresh_token;

            $response = wp_remote_post('https://plugin.fabiophotography.co.uk/refresh-token.php', [
                'body' => [
                    'refresh_token' => $refresh_token
                ]
            ]);

            if (is_wp_error($response)) {
                error_log('❌ Refresh failed: ' . $response->get_error_message());
                return;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (!empty($data['access_token'])) {
                $client->setAccessToken([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $refresh_token, // KEEP the old refresh token
                    'expires_in' => $data['expires_in'],
                    'created' => time()
                ]);

                // Update DB with new access token
                $wpdb->update($wpdb->prefix . 'gcsn_tokens', [
                    'access_token' => $data['access_token'],
                    'expires_in' => $data['expires_in'],
                    'token_type' => $data['token_type'],
                    'created_at' => current_time('mysql')
                ], ['user_email' => $current_user->user_email]);

                error_log("✅ Token refreshed via fabiophotography.co.uk!");
            } else {
                error_log("❌ Token refresh response invalid: " . wp_remote_retrieve_body($response));
                return;
            }
        }

        $service = new Google_Service_Calendar($client);

        // Define the parameters for fetching events
        $calendarId = get_option('gcsn_calendar_id');
        $params = [
            'timeMin' => '2025-01-01T00:00:00Z',
            'maxResults' => 5000,
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ];

        try {
            $events = $service->events->listEvents($calendarId, $params);
        } catch (Exception $e) {
            error_log('❌ Google Calendar API error: ' . $e->getMessage());
            return;
        }

        $sync_success = false;

        // Check if this is an auto-sync (from cron job) or manual sync
        $is_auto_sync = isset($_POST['gcsn_sync_type']) && $_POST['gcsn_sync_type'] === 'auto';

        if ($is_auto_sync) {
            // If it's an auto sync, log the timestamp before proceeding
            error_log("Auto sync started at: " . current_time('mysql'));
        }

        // Process each event
        foreach ($events->getItems() as $event) {
            $event_id = $event->getId();
            $title = $event->getSummary();
            $location = $event->getLocation();
            $description = $event->getDescription();
            $start = $event->getStart()->getDate(); // all-day event
            if (!$start) {
                $startDateTime = $event->getStart()->getDateTime();
                $start = date('Y-m-d', strtotime($startDateTime));
            }

            // Check if event already exists in DB
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sn_calendar_events WHERE event_id = %s", $event_id));

            if ($existing) {
                // If event exists, check if data needs to be updated
                $needs_update = (
                    $existing->title !== $title ||
                    $existing->start_date !== $start ||
                    $existing->location !== $location ||
                    $existing->description !== $description
                );

                if ($needs_update) {
                    $distance_reset = $existing->location !== $location ? null : $existing->distance_miles;

                    // Update the existing event
                    $wpdb->update($wpdb->prefix . 'sn_calendar_events', [
                        'title' => $title,
                        'start_date' => $start,
                        'location' => $location,
                        'description' => $description,
                        'last_synced' => current_time('mysql'),
                        'distance_miles' => $distance_reset
                    ], ['event_id' => $event_id]);
                }
            } else {
                // Insert new event if it doesn't exist in DB
                $wpdb->insert($wpdb->prefix . 'sn_calendar_events', [
                    'event_id' => $event_id,
                    'title' => $title,
                    'start_date' => $start,
                    'location' => $location,
                    'description' => $description,
                    'last_synced' => current_time('mysql')
                ]);
            }

            $sync_success = true; // Mark as successful
        }

        // Update the last sync time if the sync was successful and it's an auto sync
        if ($sync_success && $is_auto_sync) {
            update_option('gcsn_last_sync', current_time('mysql')); // Set the last sync time to now only if sync was successful
            error_log("✅ Auto Sync completed!");
        } else {
            error_log("❌ Sync failed or no changes made.");
        }
    }


    function gcsn_get_distance_info($destination, $event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sn_calendar_events';

        if (!$destination || trim($destination) === '') {
            error_log("No destination provided for event $event_id");
            return [null, null];
        }

        // Check if already calculated
        $row = $wpdb->get_row($wpdb->prepare("SELECT distance_miles, travel_time_minutes FROM $table WHERE event_id = %s", $event_id));
        if ($row && $row->distance_miles !== null && $row->travel_time_minutes !== null) {
            return [round($row->distance_miles, 1), $row->travel_time_minutes];
        }
    $api_key = 'AIzaSyDldQiRt6hZmJb1OEVc8WfNxPvVWq9VpDg';
        
    $origin_latlng = get_option('gcsn_origin_location');

        // Geocode destination
        $geo_url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(['address' => $destination, 'key' => $api_key]);
        $geo_response = wp_remote_get($geo_url);
        if (is_wp_error($geo_response)) return [null, null];
        $geo_data = json_decode(wp_remote_retrieve_body($geo_response), true);
        if (!isset($geo_data['results'][0]['geometry']['location'])) return [null, null];

        $latlng = $geo_data['results'][0]['geometry']['location'];
        $dest_latlng = "{$latlng['lat']},{$latlng['lng']}";

        // Distance Matrix call
        $distance_url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
            'origins' => $origin_latlng,
            'destinations' => $dest_latlng,
            'units' => 'imperial',
            'key' => $api_key
        ]);
        $distance_response = wp_remote_get($distance_url);
        if (is_wp_error($distance_response)) return [null, null];
        $distance_data = json_decode(wp_remote_retrieve_body($distance_response), true);

        $element = $distance_data['rows'][0]['elements'][0] ?? null;
        if (!$element || $element['status'] !== 'OK') return [null, null];

        $miles = $element['distance']['value'] / 1609.34;
        $minutes = $element['duration']['value'] / 60;

        $wpdb->update($table, [
            'distance_miles' => $miles,
            'travel_time_minutes' => round($minutes)
        ], ['event_id' => $event_id]);

        return [round($miles, 1), round($minutes)];
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


    function gcsn_render_date_checks_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'gcsn_date_checks';

        // --- Handle search filters
        $where = [];
        $params = [];

        if (!empty($_GET['venue_search'])) {
            $where[] = 'venue LIKE %s';
            $params[] = '%' . $wpdb->esc_like(sanitize_text_field($_GET['venue_search'])) . '%';
        }

        if (!empty($_GET['date_from'])) {
            $where[] = 'search_date >= %s';
            $params[] = sanitize_text_field($_GET['date_from']);
        }

        if (!empty($_GET['date_to'])) {
            $where[] = 'search_date <= %s';
            $params[] = sanitize_text_field($_GET['date_to']);
        }

        $where_sql = '';
        if (!empty($where)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where);
        }

        // --- Pagination
        $per_page = 100;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table $where_sql", $params);

        // Get actual records
        $query = $wpdb->prepare(
            "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($params, [$per_page, $offset])
        );
        $checks = $wpdb->get_results($query);

        // --- Output
        echo '<div class="wrap">';
        echo '<h1>📅 Visitor Date Check Logs</h1>';

        // --- Search Form
        echo '<form method="get" style="margin-bottom: 20px;">';
        echo '<input type="hidden" name="page" value="gcsn-date-check-logs">';
        echo 'Venue Search: <input type="text" name="venue_search" value="' . esc_attr($_GET['venue_search'] ?? '') . '" />';
        echo '&nbsp; From: <input type="date" name="date_from" value="' . esc_attr($_GET['date_from'] ?? '') . '" />';
        echo ' To: <input type="date" name="date_to" value="' . esc_attr($_GET['date_to'] ?? '') . '" />';
        echo ' <input type="submit" class="button" value="Filter">';
        echo '</form>';

        // --- Results
        if (empty($checks)) {
            echo '<p><em>No searches found.</em></p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>Search Date</th><th>Venue</th><th>IP Address</th><th>Checked At</th></tr></thead><tbody>';

            foreach ($checks as $check) {
                echo '<tr>';
                echo '<td>' . esc_html($check->search_date) . '</td>';
                echo '<td>' . esc_html($check->venue) . '</td>';
                echo '<td>' . esc_html($check->ip_address) . '</td>';
                echo '<td>' . esc_html($check->created_at) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        // --- Pagination Controls
        $total_pages = ceil($total / $per_page);

        if ($total_pages > 1) {
            echo '<div style="margin-top: 20px;">';

            $base_url = remove_query_arg('paged');

            if ($page > 1) {
                echo '<a href="' . esc_url(add_query_arg('paged', $page - 1, $base_url)) . '" class="button">&laquo; Previous</a> ';
            }

            if ($page < $total_pages) {
                echo '<a href="' . esc_url(add_query_arg('paged', $page + 1, $base_url)) . '" class="button">Next &raquo;</a>';
            }

            echo '</div>';
        }

        echo '</div>';
    }



    function gcsn_render_admin_page() {
        



        global $wpdb;


        $table = $wpdb->prefix . 'sn_calendar_events';
        

        if (isset($_POST['gcsn_manual_sync'])) {	 }
            
            if (isset($_POST['gcsn_recalc_distances'])) {
                
        $wpdb->query("UPDATE $table SET distance_miles = NULL");
        echo '<p><strong>All distances cleared. They will be re-fetched on next page load.</strong></p>';
    }


        
        if (isset($_POST['gcsn_hide_past_form_submitted'])) {
        if (isset($_POST['gcsn_hide_past'])) {
            update_user_meta(get_current_user_id(), 'gcsn_hide_past_events', '1');
        } else {
            delete_user_meta(get_current_user_id(), 'gcsn_hide_past_events');
        }
    }
        $current_user_id = get_current_user_id();
    $hide_past = get_user_meta($current_user_id, 'gcsn_hide_past_events', true);

        $search = isset($_GET['gcsn_search']) ? sanitize_text_field($_GET['gcsn_search']) : '';
    $order = in_array($_GET['gcsn_order'] ?? '', ['start_date DESC', 'start_date ASC', 'title ASC']) ? $_GET['gcsn_order'] : 'start_date ASC';

    $where_clauses = [];

    if (!empty($search)) {
        $where_clauses[] = $wpdb->prepare("(title LIKE %s OR location LIKE %s)", "%$search%", "%$search%");
    }

    if ($hide_past === '1')
    {
        $today = date('Y-m-d');
        $where_clauses[] = $wpdb->prepare("start_date >= %s", $today);
    }

    $where = '';
    if (!empty($where_clauses)) {
        $where = 'WHERE ' . implode(' AND ', $where_clauses);
    }

    $events = $wpdb->get_results("SELECT * FROM $table $where ORDER BY $order");



    //echo "select * from $table $where order by $order";

        $last_sync = get_option('gcsn_last_sync');


        // === Summary Table: Events by Year and Type ===
        $summary = [];

        foreach ($events as $e) {
            $date = DateTime::createFromFormat('Y-m-d', $e->start_date);
            if (!$date) continue;

            $year = $date->format('Y');
            $dayOfWeek = $date->format('N'); // 6 = Sat, 7 = Sun

            if (!isset($summary[$year])) {
                $summary[$year] = ['weekdays' => 0, 'weekends' => 0];
            }

            if ($dayOfWeek >= 6) {
                $summary[$year]['weekends']++;
            } else {
                $summary[$year]['weekdays']++;
            }
        }

        ksort($summary);
        echo '
    <div id="gcsn-tabs" style="margin-top: 20px;">
        <div style="margin-bottom: 10px;">
            <button class="gcsn-tab-button" data-tab="yearly">📅 Yearly Summary</button>
            <button class="gcsn-tab-button" data-tab="monthly">📆 Monthly Breakdown</button>
        </div>
    ';
    echo '<div class="gcsn-tab-content" id="tab-yearly" style="display: none;">';
        echo '<h2>Yearly Booking Summary</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th></th>';
        foreach ($summary as $year => $_) {
            echo "<th>$year</th>";
        }
        echo '</tr></thead><tbody>';

        echo '<tr><th>Weekdays</th>';
        foreach ($summary as $data) {
            echo '<td>' . $data['weekdays'] . '</td>';
        }
        echo '</tr>';

        echo '<tr><th>Weekends</th>';
        foreach ($summary as $data) {
            echo '<td>' . $data['weekends'] . '</td>';
        }
        echo '</tr>';

        echo '<tr><th><strong>Total</strong></th>';
        foreach ($summary as $data) {
            echo '<td><strong>' . ($data['weekdays'] + $data['weekends']) . '</strong></td>';
        }
        echo '</tr>';

        echo '</tbody></table>';

    echo '</div>';
    echo '<div class="gcsn-tab-content" id="tab-monthly" style="display: none;">';
    // === Monthly Breakdown Table ===
    
        $monthly_summary = [];

        foreach ($events as $e) {
            $date = DateTime::createFromFormat('Y-m-d', $e->start_date);
            if (!$date) continue;

            $year = $date->format('Y');
            $month = (int) $date->format('n'); // 1 = Jan, 12 = Dec

            if (!isset($monthly_summary[$month])) {
                $monthly_summary[$month] = [];
            }

            if (!isset($monthly_summary[$month][$year])) {
                $monthly_summary[$month][$year] = 0;
            }

            $monthly_summary[$month][$year]++;
        }

        ksort($monthly_summary);

        echo '<h2>Monthly Booking Breakdown</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Month</th>';

        $all_years = array_keys($summary); // same as previous summary
        foreach ($all_years as $year) {
            echo "<th>$year</th>";
        }

        echo '</tr></thead><tbody>';

        foreach (range(1, 12) as $month_num) {
            $month_name = date('M', mktime(0, 0, 0, $month_num, 1));
            echo "<tr><th>$month_name</th>";
            foreach ($all_years as $year) {
                $count = $monthly_summary[$month_num][$year] ?? 0;
                echo "<td>$count</td>";
            }
            echo "</tr>";
        }

        echo '</tbody></table>';
        
    echo '</div>';

    echo '
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const buttons = document.querySelectorAll(".gcsn-tab-button");
        const tabs = document.querySelectorAll(".gcsn-tab-content");

        let activeTab = "tab-yearly"; // default open

        buttons.forEach(button => {
            button.addEventListener("click", () => {
                const targetId = "tab-" + button.getAttribute("data-tab");

                // If same tab is clicked again → toggle closed
                if (activeTab === targetId) {
                    document.getElementById(activeTab).style.display = "none";
                    button.classList.remove("active");
                    activeTab = null;
                    return;
                }

                // Show selected, hide others
                tabs.forEach(tab => {
                    tab.style.display = (tab.id === targetId) ? "block" : "none";
                });

                buttons.forEach(b => b.classList.remove("active"));
                button.classList.add("active");

                activeTab = targetId;
            });
        });
    });
    </script>
    ';

        echo '<div class="wrap">';
        echo '<h1>SN Calendar Sync</h1>';
        $api_key = 'AIzaSyDldQiRt6hZmJb1OEVc8WfNxPvVWq9VpDg';
    $calendar_id = get_option('gcsn_calendar_id');

    if (empty($api_key) || empty($calendar_id)) {
        echo '<div class="notice notice-error" style="padding: 15px; margin-top: 20px; background: #fef2f2; border-left: 4px solid #dc3232;">';

        echo '<p><strong>Plugin setup incomplete.</strong></p><ul style="margin-left: 20px;">';

        if (empty($api_key)) {
            echo '<li>❌ Google Maps API key is missing</li>';
        } else {
            echo '<li>✅ Google Maps API key is set</li>';
        }

        if (empty($calendar_id)) {
            echo '<li>❌ Google Calendar ID is missing</li>';
        } else {
            echo '<li>✅ Google Calendar ID is set</li>';
        }

        echo '</ul>';
        echo '<p><a href="' . admin_url('options-general.php?page=gcsn-settings') . '" class="button button-primary">Go to Plugin Settings</a></p>';
        echo '</div>';
    }


        // Alert for upcoming events today or tomorrow
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $upcoming = array_filter($events, function($e) use ($today, $tomorrow) {
            return $e->start_date === $today || $e->start_date === $tomorrow;
        });
        if (count($upcoming)) {
            echo '<div class="notice notice-info"><p><strong>' . count($upcoming) . '</strong> event(s) happening today or tomorrow.</p></div>';
        }
        
        



    // Row 1: Filters & Search
    echo '<div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 15px;">';

    /* Hide Past Events Form */
    echo '<form method="post" style="margin: 0; display: flex; align-items: center; gap: 10px;">';
    echo '<input type="hidden" name="gcsn_hide_past_form_submitted" value="1">';
    echo '<input type="checkbox" id="gcsn_hide_past" name="gcsn_hide_past" value="1"' . checked($hide_past, '1', false) . '>';
    echo '<label for="gcsn_hide_past" style="margin: 0;">Hide past events</label>';
    echo '<input type="submit" class="button" value="Apply">';
    echo '</form>';
    /* Search + Filter Form */
    echo '<form method="get" style="margin: 0;">';
    echo '<input type="hidden" name="page" value="gcsn-sync">';
    echo '<input type="text" name="gcsn_search" placeholder="Search title or location" value="' . esc_attr($_GET['gcsn_search'] ?? '') . '">';

    $selected_order = $_GET['gcsn_order'] ?? '';

    echo '<select name="gcsn_order" style="margin: 0 5px;">';
    echo '<option value="start_date_desc"' . selected($selected_order, 'start_date_desc', false) . '>Newest First</option>';
    echo '<option value="start_date_asc"' . selected($selected_order, 'start_date_asc', false) . '>Oldest First</option>';
    echo '<option value="title_asc"' . selected($selected_order, 'title_asc', false) . '>Title A-Z</option>';
    echo '</select>';

    echo '<input type="submit" class="button" value="Filter">';
    echo '</form>';

    echo '</div>';

    // Row 2: Sync, Recalculate, Export
    echo '<div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 20px;">';

    /* Manual Sync */
    echo '<form method="post" style="margin: 0;">';
    echo '<input type="submit" name="gcsn_manual_sync" class="button button-primary" value="Manual Sync">';
    echo '</form>';

    /* Recalculate Distances */
    echo '<form method="post" style="margin: 0;">';
    echo '<input type="submit" name="gcsn_recalc_distances" class="button" value="Recalculate Distances">';
    echo '</form>';

    /* Export CSV */
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=gcsn-sync&gcsn_action=export_csv')) . '">Export to CSV</a>';

    echo '</div>';

        
    echo '<span><strong>' . count($events) . '</strong> events shown</span>';

    
            gcsn_sync_events();
            echo '<p><strong>Manual sync completed.</strong></p>';
            
        
        // Fetch the last sync time
        $last_sync_time = get_option('gcsn_last_sync', 'Never');

        // Display last sync time
        echo '<div class="wrap">';
        echo '<h1>SN Calendar Sync</h1>';
        echo '<p><strong>Last Auto Sync:</strong> ' . esc_html($last_sync_time) . '</p>';

        // Your other admin page content here...

        echo '</div>';

        echo '<table class="widefat fixed">
        <thead>
            <tr>
                <th>Title</th>
                <th>Date</th>
                <th>Location</th>
                <th>Distance (mi)</th>
                <th>Travel Time</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($events as $event) {
        $distance = null;
        $duration = null;

        if (!empty($event->location)) {
            list($distance, $duration) = gcsn_get_distance_info($event->location, $event->event_id);
        }

        $date = DateTime::createFromFormat('Y-m-d', $event->start_date);
        $dayOfWeek = $date ? (int) $date->format('N') : 0; // 1 = Monday, 7 = Sunday
        $isWeekday = $dayOfWeek >= 1 && $dayOfWeek <= 5;

        $row_style = $isWeekday ? 'style="background-color: #eef7ff;"' : '';

        echo "<tr $row_style>";
        echo '<td>' . esc_html($event->title) . '</td>';
        echo '<td>' . esc_html($event->start_date) . '</td>';
        echo '<td>' . esc_html($event->location) . '</td>';
        echo '<td>' . ($distance !== null ? esc_html($distance) . ' mi' : '-') . '</td>';
        echo '<td>' . ($duration !== null ? esc_html($duration) . ' min' : '-') . '</td>';
    echo '<td><div class="gcsn-description" onclick="this.classList.toggle(\'expanded\')">' . wp_kses_post($event->description) . '</div></td>';

        echo '</tr>';
    }
        echo '</tbody></table>';
        echo '</div>';
        echo '<style>
            .gcsn-description {
                max-height: 40px;
                overflow: hidden;
                cursor: pointer;
                transition: max-height 0.3s ease;
            }
            .gcsn-description.expanded {
                max-height: 1000px;
            }
        </style>';

    }

    function gcsn_export_csv_and_exit() {
        global $wpdb;
        $table = $wpdb->prefix . 'sn_calendar_events';

        $search = isset($_GET['gcsn_search']) ? sanitize_text_field($_GET['gcsn_search']) : '';
    //    $order = in_array($_GET['gcsn_order'] ?? '', ['start_date DESC', 'start_date ASC', 'title ASC']) ? $_GET['gcsn_order'] : 'start_date ASC';

    $allowed_orders = [
        'start_date_desc' => 'start_date DESC',
        'start_date_asc'  => 'start_date ASC',
        'title_asc'       => 'title ASC',
    ];

    $user_order = sanitize_key($_GET['gcsn_order'] ?? '');
    $order = $allowed_orders[$user_order] ?? 'start_date ASC';

        $where = $search ? $wpdb->prepare("WHERE title LIKE %s OR location LIKE %s", "%$search%", "%$search%") : '';
        $events = $wpdb->get_results("SELECT * FROM $table $where ORDER BY $order");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sn-calendar-export.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Title', 'Date', 'Location', 'Distance (mi)', 'Travel Time (min)', 'Description']);

        foreach ($events as $e) {
            fputcsv($output, [
                $e->title,
                $e->start_date,
                $e->location,
                round($e->distance_miles, 1),
                $e->travel_time_minutes,
                strip_tags($e->description)
            ]);
        }

        fclose($output);
        exit;
    }


    // Map

    function gcsn_render_map_admin_page() {
        $api_key = 'AIzaSyDldQiRt6hZmJb1OEVc8WfNxPvVWq9VpDg';

        echo '<div class="wrap">';
        echo '<h1>Event Map</h1>';
        
        ?>
        
    <div id="gcsn-map-filters" style="margin-bottom: 20px;">
        <form id="gcsn-filter-form" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
            <label>
                <strong>Show events from:</strong><br>
                <input type="date" id="gcsn-filter-date-from" />
            </label>

            <label>
                <strong>to:</strong><br>
                <input type="date" id="gcsn-filter-date-to" />
            </label>

            <label style="margin-top: 10px;">
                <input type="checkbox" id="gcsn-show-next-4-weeks" />
                Show Next 4 Weeks
            </label>

            <label style="margin-top: 10px;">
                <input type="checkbox" id="gcsn-show-past" />
                Show Past Events
            </label>
        </form>
    </div>
    <div id="gcsn-map-legend" style="margin-bottom: 20px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
        <strong>Legend:</strong><br>
            <span style="color: gold;">● Yellow</span> – Past events<br>
        <span style="color: green;">● Green</span> – Events in the next 14 days<br>
        <span style="color: orange;">● Orange</span> – Events in 2–6 weeks<br>
        <span style="color: red;">● Red</span> – Events more than 6 weeks away<br>
    </div>

    <?php
        echo '<div id="gcsn-admin-map" style="width: 100%; height: 80vh;"></div>';
        echo '</div>';

        // ✅ Load the Google Maps JS API
        echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '"></script>';

        // ✅ Defer map JS logic until full page load
        echo '<script>';
        echo 'window.addEventListener("load", function() {';
        gcsn_render_map_data_js();
        echo '});';
        echo '</script>';
    }

    function gcsn_render_map_data_js() {
        global $wpdb;

        $origin_address = get_option('gcsn_origin_location');
        $origin_coords = gcsn_geocode_location($origin_address);

        $map_lat = $origin_coords['lat'] ?? 53.7894;
        $map_lng = $origin_coords['lng'] ?? -1.8116;

        $table = $wpdb->prefix . 'sn_calendar_events';
        $events = $wpdb->get_results("SELECT title, start_date, location, distance_miles, travel_time_minutes FROM $table WHERE location IS NOT NULL AND location != ''");

        $marker_data = [];

        foreach ($events as $e) {
            $geo = gcsn_geocode_location($e->location);
            if (!$geo) continue;

            $marker_data[] = [
                'title'     => $e->title,
                'date'      => $e->start_date,
                'location'  => $e->location,
                'distance'  => $e->distance_miles,
                'time'      => $e->travel_time_minutes,
                'lat'       => $geo['lat'],
                'lng'       => $geo['lng'],
            ];
        }

        echo 'const gcsnMarkers = ' . json_encode($marker_data) . ';';
        
        echo <<<JS
        const map = new google.maps.Map(document.getElementById('gcsn-admin-map'), {
            center: { lat: $map_lat, lng: $map_lng },
            zoom: 7,
            gestureHandling: 'greedy'
        });

        const usedCoords = {};
        const markers = [];
    function renderFilteredMarkers() {
        markers.forEach(marker => marker.setMap(null));
        markers.length = 0;

        const filterFromDate = document.getElementById('gcsn-filter-date-from')?.value;
        const filterToDate = document.getElementById('gcsn-filter-date-to')?.value;
        const showPast = document.getElementById('gcsn-show-past')?.checked ?? false;
        const showNext4Weeks = document.getElementById('gcsn-show-next-4-weeks')?.checked ?? false;

        const now = new Date();
        const fourWeeksFromNow = new Date();
        fourWeeksFromNow.setDate(fourWeeksFromNow.getDate() + 28);

        gcsnMarkers.forEach(function(event) {
            const eventDate = new Date(event.date);

            // --- Smart filter ranges
            if (showNext4Weeks) {
                if (eventDate < now || eventDate > fourWeeksFromNow) {
                    return; // outside next 4 weeks
                }
            } else {
                if (filterFromDate && eventDate < new Date(filterFromDate)) {
                    return;
                }
                if (filterToDate && eventDate > new Date(filterToDate)) {
                    return;
                }
            }

            if (!showPast && eventDate < now) {
                return;
            }

            // --- Color Logic
            const diffDays = Math.ceil((eventDate - now) / (1000 * 60 * 60 * 24));

            let iconUrl;
            if (diffDays < 0) {
                iconUrl = 'http://maps.google.com/mapfiles/ms/icons/yellow-dot.png'; // Past
            } else if (diffDays <= 14) {
                iconUrl = 'http://maps.google.com/mapfiles/ms/icons/green-dot.png'; // Soon
            } else if (diffDays <= 42) {
                iconUrl = 'http://maps.google.com/mapfiles/ms/icons/orange-dot.png'; // Medium
            } else {
                iconUrl = 'http://maps.google.com/mapfiles/ms/icons/red-dot.png'; // Far
            }

            const marker = new google.maps.Marker({
                position: { lat: parseFloat(event.lat), lng: parseFloat(event.lng) },
                map: map,
                title: event.title,
                icon: { url: iconUrl }
            });

            const info = new google.maps.InfoWindow({
                content: '<strong>' + event.title + '</strong><br>' +
                        event.date + '<br>' +
                        event.location + '<br>' +
                        event.distance + ' mi – ' + event.time + ' min'
            });

            marker.addListener('click', function() {
                if (window.gcsnInfoWindow) {
                    window.gcsnInfoWindow.close();
                }
                window.gcsnInfoWindow = info;
                info.open(map, marker);
            });

            markers.push(marker);
        });
    }
    document.getElementById('gcsn-show-next-4-weeks')?.addEventListener('change', function () {
        const checked = this.checked;
        const fromInput = document.getElementById('gcsn-filter-date-from');
        const toInput = document.getElementById('gcsn-filter-date-to');

        if (checked) {
            const now = new Date();
            const fourWeeks = new Date();
            fourWeeks.setDate(fourWeeks.getDate() + 28);

            fromInput.valueAsDate = now;
            toInput.valueAsDate = fourWeeks;

            fromInput.disabled = true;
            toInput.disabled = true;
        } else {
            fromInput.disabled = false;
            toInput.disabled = false;

            // 👇 Clear the dates when unticked
            fromInput.value = '';
            toInput.value = '';
        }

        renderFilteredMarkers();
    });

        // Attach filter listeners
        document.getElementById('gcsn-filter-date')?.addEventListener('change', renderFilteredMarkers);
        document.getElementById('gcsn-show-past')?.addEventListener('change', renderFilteredMarkers);
        document.getElementById('gcsn-filter-date-from')?.addEventListener('change', renderFilteredMarkers);
    document.getElementById('gcsn-filter-date-to')?.addEventListener('change', renderFilteredMarkers);


        // Initial render
        renderFilteredMarkers();
    JS;
    }


    function gcsn_geocode_location($address) {
        
        $api_key = 'AIzaSyDldQiRt6hZmJb1OEVc8WfNxPvVWq9VpDg';
        $cache_key = 'gcsn_geo_' . md5($address);
        $cached = get_transient($cache_key);
        if ($cached) return $cached;

        $api_key = 'AIzaSyDldQiRt6hZmJb1OEVc8WfNxPvVWq9VpDg';
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'address' => $address,
            'key' => $api_key
        ]);

        $res = wp_remote_get($url);
        if (is_wp_error($res)) return null;

        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!isset($body['results'][0]['geometry']['location'])) return null;

        $location = $body['results'][0]['geometry']['location'];
        set_transient($cache_key, $location, WEEK_IN_SECONDS);

        return $location;
    }

    function gcsn_render_oauth_section() {
        


        if (!is_user_logged_in()) {
            echo '<div class="notice notice-error"><p>You must be logged in to connect your Google account.</p></div>';
            return;
        }

        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        global $wpdb;
        $table_name = $wpdb->prefix . 'gcsn_tokens';

        $token_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_email = %s ORDER BY id DESC LIMIT 1", $user_email));

        echo '<h2>Google Integration Calendar</h2>';

        if ($token_row) {
            echo "<p><strong>Connected as:</strong> {$token_row->user_email}</p>";
            echo '<p style="color: green;"><strong>Status:</strong> Connected ✅</p>';
        } else {
            // Just use the direct admin page return URL — no wp_login_url
            $redirect_back_to = admin_url('admin.php?page=gcsn-settings');
            $state = urlencode(base64_encode($redirect_back_to));
            $oauth_url = 'https://plugin.fabiophotography.co.uk/gcal-auth.php?state=' . $state;

            echo '<p style="color: red;"><strong>Status:</strong> Not Connected ❌</p>';
            echo '<a class="button button-primary" href="' . esc_url($oauth_url) . '" target="_blank">Connect to Google</a>';
        }
    }

    function gcsn_get_google_calendars($access_token) {
        $response = wp_remote_get('https://www.googleapis.com/calendar/v3/users/me/calendarList', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['items'] ?? [];
    }


    function gcsn_render_settings_page() {
        


        if (isset($_POST['gcsn_disconnect_google']) && current_user_can('manage_options')) {
        $current_user = wp_get_current_user();
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcsn_tokens';
        $wpdb->delete($table_name, ['user_email' => $current_user->user_email]);
    delete_option('gcsn_calendar_id');
        echo '<div class="notice notice-warning"><p>Google connection removed. You are now disconnected.</p></div>';
    }
        
        if (isset($_POST['gcsn_availability_save'])) {
        update_option('gcsn_available_message', sanitize_text_field($_POST['gcsn_available_message']));
        update_option('gcsn_unavailable_message', sanitize_text_field($_POST['gcsn_unavailable_message']));
        update_option('gcsn_enable_venue_textbox', isset($_POST['gcsn_enable_venue_textbox']) ? '1' : '0');
        echo '<div class="notice notice-success"><p>Availability messages saved.</p></div>';
    }
        
        if (isset($_POST['gcsn_clear_events']) && current_user_can('manage_options')) {
        global $wpdb;
        $table = $wpdb->prefix . 'sn_calendar_events';
        $wpdb->query("DELETE FROM $table");

        echo '<div class="notice notice-warning"><p>All synced events have been cleared.</p></div>';
    }
        
        
        
        if (isset($_POST['gcsn_save_calendar']) && !empty($_POST['gcsn_calendar_id'])) {
        update_option('gcsn_calendar_id', sanitize_text_field($_POST['gcsn_calendar_id']));
        echo '<div class="notice notice-success"><p>Calendar saved successfully ✅</p></div>';
    }


        if (isset($_POST['gcsn_settings_save'])) {
            update_option('gcsn_google_maps_api_key', sanitize_text_field($_POST['gcsn_google_maps_api_key']));
            update_option('gcsn_origin_location', sanitize_text_field($_POST['gcsn_origin_location']));
            update_option('gcsn_calendar_id', sanitize_text_field($_POST['gcsn_calendar_id']));

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }


    if (isset($_GET['access_token']) && isset($_GET['gcsn_oauth_email'])) {
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;

        $access_token = sanitize_text_field($_GET['access_token']);
        $refresh_token = sanitize_text_field($_GET['refresh_token']);
        $expires_in = intval($_GET['expires_in']);
        $token_type = sanitize_text_field($_GET['token_type']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'gcsn_tokens';

        $wpdb->delete($table_name, ['user_email' => $email]);

        $wpdb->insert($table_name, [
            'user_email' => $email,
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => $expires_in,
            'token_type' => $token_type,
            'created_at' => current_time('mysql')
        ]);

        echo '<div class="notice notice-success"><p>Google account connected and tokens stored locally! ✅</p></div>';
    }



        $maps_api = get_option('gcsn_google_maps_api_key', '');
        $origin = get_option('gcsn_origin_location', '');
        $calendar_id = get_option('gcsn_calendar_id', '');
        
        
        $origin_set = !empty(get_option('gcsn_origin_location'));
    $current_user = wp_get_current_user();
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcsn_tokens';
    $token_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_email = %s", $current_user->user_email));

    if ($token_row && (empty($token_row->expires_in) || empty($token_row->created_at))) {
        error_log('⚠️ Missing expires_in or created_at fields. Cannot auto-refresh token.');
    }

    $show_help_collapsed = ($token_row && $origin_set); // Collapse help if setup is complete

    echo '<div id="gcsn-help-container" style="margin-bottom: 20px;">';

    if ($show_help_collapsed) {
        echo '<button id="gcsn-help-toggle" class="button" style="margin-bottom: 10px;">Show Help</button>';
        echo '<div id="gcsn-help-content" style="display:none;">';
    } else {
        echo '<div id="gcsn-help-content">';
    }

    // your full help block HTML goes here (unchanged)
    echo '<h1>Calendar Sync Settings</h1>';

    echo '<div style="margin-bottom: 20px; padding: 15px; background: #f0f8ff; border-left: 5px solid #0073aa;">
        <h2>📘 Getting Started: SN Calendar Sync Setup Guide</h2>

        <p>This plugin connects your <strong>Google Calendar</strong> to WordPress using secure OAuth authentication. Once connected, it automatically fetches all your upcoming events, stores them in the database, calculates travel distance/time from your home postcode, and displays them in your dashboard and map view.</p>

        <h3>🔐 What You Need</h3>
        <ul>
            <li>✅ A <strong>Google Calendar</strong> connected to your Studio Ninja account</li>
            <li>✅ A <strong>Google account</strong> to sign in with</li>
            <li>✅ Your <strong>home postcode</strong> to calculate travel distance from</li>
        </ul>

        <h3>🔗 Connect to Google</h3>
        <p>Use the <strong>Connect to Google</strong> button above to securely log in and give permission for this plugin to read your calendar.</p>

        <p>Once connected, you will see a dropdown of your available calendars. Just choose the one Studio Ninja syncs to — usually it’s called something like <strong>"Fabio Jobs (SN)"</strong> — and hit <strong>Save Calendar Selection</strong>.</p>

        <h3>📍 Set Your Home Postcode</h3>
        <p>This postcode or lat/lng will be used as your starting point for distance/time calculations for each job.</p>
        <ul>
            <li>📮 For example: <code>BD14 6EJ</code></li>
            <li>📌 Or: <code>53.7894,-1.8116</code> for exact lat/lng</li>
        </ul>

        <h3>❓Need Help?</h3>
        <p>If you are unsure which calendar to use, check which one is connected to Studio Ninja under their calendar settings. Still stuck? Drop your dev a message 💬</p>
    </div>';


    echo '</div>'; // end help-content
    echo '</div>'; // end help-container

        echo '<div class="wrap">';
    

    gcsn_render_oauth_section();


    $calendar_id = get_option('gcsn_calendar_id', '');
    $access_token = ''; // default

    // Get token for current user
    $current_user = wp_get_current_user();
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcsn_tokens';

    $access_token = '';
    $current_user = wp_get_current_user();
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcsn_tokens';
    $token_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_email = %s", $current_user->user_email));

    if ($token_row) {
        $access_token = $token_row->access_token;
        echo '<form method="post" style="margin-top: 10px;">';
    echo '<input type="hidden" name="gcsn_disconnect_google" value="1" />';
    echo '<input type="submit" class="button button-secondary" value="Disconnect from Google">';
    echo '</form>';
    }

    if ($access_token) {
        $calendars = gcsn_get_google_calendars($access_token);
    echo '<h2>Select a Calendar to Sync</h2>';
    echo '<form method="post">';
    echo '<input type="hidden" name="gcsn_save_calendar" value="1" />';

    // 💡 Helpful tips
    echo '<div style="padding: 10px 15px; background: #f0f8ff; border-left: 4px solid #0073aa; margin-bottom: 15px;">';
    echo '<p><strong>💡 Tip:</strong> You’ll usually want to select the calendar named something like <strong>"[Your Name] Jobs (SN)"</strong>. That’s where Studio Ninja sends your bookings.</p>';
    echo '<p><strong>📌 Note:</strong> This only works if you’ve already connected Studio Ninja to Google Calendar. Make sure your jobs are syncing to Google before using this plugin.</p>';
    echo '</div>';

    echo '<select name="gcsn_calendar_id" style="min-width: 400px;">';

    foreach ($calendars as $calendar) {
        $selected = ($calendar_id === $calendar['id']) ? 'selected' : '';
        echo '<option value="' . esc_attr($calendar['id']) . '" ' . $selected . '>' .
            esc_html($calendar['summary']) .
            ' (' . esc_html($calendar['id']) . ')' .
            '</option>';
    }

    echo '</select>';
    echo '<p><input type="submit" class="button button-primary" value="Save Calendar Selection"></p>';
    echo '</form>';

    } else {
        echo '<p style="color: red;">Connect to Google first to load your calendars.</p>';
    }

        echo '<form method="post">';
        echo '<table class="form-table">';

    // Google Calendar ID (read-only, managed via dropdown)
    echo '<tr><th scope="row"><label for="gcsn_calendar_id">Google Calendar ID</label></th>';
    echo '<td><input type="text" id="gcsn_calendar_id" name="gcsn_calendar_id" value="' . esc_attr($calendar_id) . '" class="regular-text" readonly style="background-color: #f9f9f9;" />';
    echo '<p class="description">This is automatically set when you choose a calendar from the dropdown above.</p></td></tr>';

    // Origin Location (simplified label)
    echo '<tr><th scope="row"><label for="gcsn_origin_location">Enter your home postcode</label></th>';
    echo '<td><input type="text" id="gcsn_origin_location" name="gcsn_origin_location" value="' . esc_attr($origin) . '" class="regular-text" /></td></tr>';

    // Google Maps API Key
    //echo '<tr><th scope="row"><label for="gcsn_google_maps_api_key">Google Maps API Key</label></th>';
    //echo '<td><input type="text" id="gcsn_google_maps_api_key" name="gcsn_google_maps_api_key" value="' . esc_attr($maps_api) . '" class="regular-text" /></td></tr>';


        echo '</table>';

        echo '<p><input type="submit" name="gcsn_settings_save" class="button button-primary" value="Save Settings"></p>';
        echo '</form>';
        
        $available_message = get_option('gcsn_available_message', '✅ Great news! This date is available.');
    $unavailable_message = get_option('gcsn_unavailable_message', '❌ Sorry, this date is already booked.');


    echo '<hr style="margin: 40px 0;">';
    echo '<h2>Date Checker Shortcode Settings</h2>';
    echo '<p>This tool lets you show a public-facing date checker on your website. Visitors can select a date and instantly find out if you’re available — based on the synced events in your Google Calendar. Great for reducing enquiries for already-booked dates!</p>';

    echo '<hr style="margin: 40px 0;">';
    echo '<h2>Date Checker Shortcode Settings</h2>';
    echo '<form method="post">';
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="gcsn_available_message">Available Message</label></th>';
    echo '<td><input type="text" name="gcsn_available_message" value="' . esc_attr($available_message) . '" class="regular-text" />';
    echo '<p class="description">Shown when no event is found for the selected date.</p></td></tr>';

    echo '<tr><th scope="row"><label for="gcsn_unavailable_message">Unavailable Message</label></th>';
    echo '<td><input type="text" name="gcsn_unavailable_message" value="' . esc_attr($unavailable_message) . '" class="regular-text" />';

    $enable_venue_textbox = get_option('gcsn_enable_venue_textbox', '0');

    echo '<tr><th scope="row">Add Textbox for Venue</th>';
    echo '<td><label><input type="checkbox" name="gcsn_enable_venue_textbox" value="1" ' . checked('1', $enable_venue_textbox, false) . '> Enable venue input on availability checker</label>';
    echo '<p class="description">If checked, visitors will also be able to type in a venue when checking a date.</p></td></tr>';


    echo '<p class="description">Shown when a synced event already exists on the selected date.</p></td></tr>';
    echo '</table>';

    echo '<p><input type="submit" name="gcsn_availability_save" class="button button-primary" value="Save Messages"></p>';
    echo '</form>';

    echo '<div style="margin-top: 30px; padding: 15px; background: #e8f5e9; border-left: 5px solid #46b450;">';
    echo '<h3>📋 How to Use the Shortcode</h3>';
    echo '<p>To let visitors check if a date is available, simply add the following shortcode to any page or post on your website:</p>';
    echo '<pre style="background: #f7f7f7; padding: 10px; border: 1px solid #ddd;"><code>[SN-check-availability]</code></pre>';
    echo '<p>This will show a date input box where users can select a date and check availability in real time.</p>';
    echo '<p>You can use this shortcode in:</p>';
    echo '<ul style="list-style: disc; padding-left: 20px;">
        <li>📄 Any WordPress page or blog post</li>
        <li>🧩 A text block inside your page builder (e.g. Elementor, Divi)</li>
        <li>🎯 Or inside a widget using a shortcode block</li>
    </ul>';
    echo '<p>Responses shown to users are fully customisable above.</p>';
    echo '</div>';
        
        echo '<form method="post" style="margin-top: 30px;">';
    echo '<input type="hidden" name="gcsn_clear_events" value="1">';
    echo '<div style="padding: 15px; border: 1px solid #dc3232; background: #fff5f5;">';
    echo '<h2 style="color: #dc3232;">🗑️ Danger Zone</h2>';
    echo '<p>This will <strong>permanently delete all synced events - use this if you synced the wrong calendar</strong> from the plugin database.</p>';
    echo '<p><input type="submit" class="button button-secondary" value="Clear Synced Events"></p>';
    echo '</div>';
    echo '</form>';
        
        echo '</div>';
        
        

        
        
        echo '<script>
    document.addEventListener("DOMContentLoaded", function () {
        const toggleBtn = document.getElementById("gcsn-help-toggle");
        const helpContent = document.getElementById("gcsn-help-content");

        if (toggleBtn) {
            toggleBtn.addEventListener("click", function () {
                const visible = helpContent.style.display === "block";
                helpContent.style.display = visible ? "none" : "block";
                toggleBtn.textContent = visible ? "Show Help" : "Hide Help";
            });
        }
    });
    </script>';
    }
