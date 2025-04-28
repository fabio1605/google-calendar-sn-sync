<?php

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
?>