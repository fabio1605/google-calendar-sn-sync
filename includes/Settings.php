<?php

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
