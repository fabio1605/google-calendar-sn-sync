
<?php



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

