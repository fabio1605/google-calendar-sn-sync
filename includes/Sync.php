<?php


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
                'refresh_token' => $refresh_token,
                'expires_in' => $data['expires_in'],
                'created' => time()
            ]);

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
        'maxResults' => 15,
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
    $is_auto_sync = isset($_POST['gcsn_sync_type']) && $_POST['gcsn_sync_type'] === 'auto';

    if ($is_auto_sync) {
        error_log("Auto sync started at: " . current_time('mysql'));
    }

    // === Process each event
    foreach ($events->getItems() as $event) {
        $event_id = $event->getId();
        $title = $event->getSummary();
        $location = $event->getLocation();
        $description = $event->getDescription();
        $start = $event->getStart()->getDate();
        if (!$start) {
            $startDateTime = $event->getStart()->getDateTime();
            $start = date('Y-m-d', strtotime($startDateTime));
        }
    
        // Fetch existing event if it exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sn_calendar_events WHERE event_id = %s",
            $event_id
        ));
    
        $latitude = null;
        $longitude = null;
    
        // If location exists...
        if (!empty($location)) {
            if ($existing && $existing->latitude !== null && $existing->longitude !== null) {
                // Use existing lat/lng from DB
                $latitude = $existing->latitude;
                $longitude = $existing->longitude;
            } else {
                // Otherwise, geocode it
                $geo_result = gcsn_geocode_location($location);
                if ($geo_result) {
                    $latitude = $geo_result['lat'];
                    $longitude = $geo_result['lng'];
                }
            }
        }

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sn_calendar_events WHERE event_id = %s", $event_id));

        if ($existing) {
            $needs_update = (
                $existing->title !== $title ||
                $existing->start_date !== $start ||
                $existing->location !== $location ||
                $existing->description !== $description
            );
        
            if ($needs_update) {
                $distance_reset = $existing->location !== $location ? null : $existing->distance_miles;
        
                $wpdb->update($wpdb->prefix . 'sn_calendar_events', [
                    'title' => $title,
                    'start_date' => $start,
                    'location' => $location,
                    'description' => $description,
                    'last_synced' => current_time('mysql'),
                    'distance_miles' => $distance_reset,
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ], ['event_id' => $event_id]);
            }
        } else {
            $wpdb->insert($wpdb->prefix . 'sn_calendar_events', [
                'event_id' => $event_id,
                'title' => $title,
                'start_date' => $start,
                'location' => $location,
                'description' => $description,
                'last_synced' => current_time('mysql'),
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
        }
        

        $sync_success = true;
    }

    if ($sync_success && $is_auto_sync) {
        update_option('gcsn_last_sync', current_time('mysql'));
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

?>