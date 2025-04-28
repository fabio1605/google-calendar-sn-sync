<?php

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

?>