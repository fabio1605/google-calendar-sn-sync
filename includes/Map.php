
<?php




// === Geocode helper function ===
function gcsn_geocode_location($address) {
    $api_key = get_option('gcsn_google_maps_api_key');
    $address = urlencode($address);
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$api_key}";

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['results'][0]['geometry']['location'])) {
        return [
            'lat' => $data['results'][0]['geometry']['location']['lat'],
            'lng' => $data['results'][0]['geometry']['location']['lng'],
        ];
    }

    return null;
}

function gcsn_geocode_location_new($address) {
    $server_url = 'https://plugin.fabiophotography.co.uk/geocode.php';

    // Base64-encoded API key (decoded just before use)
    $encoded_key = 'RDdmOEE5MnNLeDFQcU00Tnc1QnZaM0x0WXFIczlKZFhjVmU2VG1ScExnUWhBejJVc1d5Qm5LclZjWHBRc01hWnQ=';
    $api_key = base64_decode($encoded_key);

    // Build request URL
    $url = $server_url . '?address=' . urlencode($address) . '&api_key=' . urlencode($api_key);

    // Make the request
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['lat']) && !empty($data['lng'])) {
        return [
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'postcode' => $data['postcode'] ?? null,
        ];
    }

    return null;
}


function gcsn_render_map_admin_page() {
    $api_key = get_option('gcsn_google_maps_api_key');
    echo '<div class="wrap">';
    echo '<h1>Event Map</h1>';
    
    if (empty($api_key)) {
        echo '<div style="padding: 20px; background: #fff3cd; border-left: 5px solid #ffeeba; margin-top: 20px;">';
        echo '<p><strong>⚠️ Google Maps API Key Not Set</strong></p>';
        echo '<p>You must <a href="' . esc_url(admin_url('admin.php?page=gcsn-settings')) . '">enter your Google Maps API key in the plugin settings</a> to enable the map view.</p>';
        echo '</div>';
        echo '</div>'; // end wrap
        return;
    }
	
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
    $events = $wpdb->get_results("SELECT title, start_date, location, distance_miles, travel_time_minutes, latitude, longitude FROM $table WHERE location IS NOT NULL AND location != '' AND latitude IS NOT NULL AND longitude IS NOT NULL");


    $marker_data = [];



     

        foreach ($events as $e) {
            $marker_data[] = [
                'title'     => $e->title,
                'date'      => $e->start_date,
                'location'  => $e->location,
                'distance'  => $e->distance_miles,
                'time'      => $e->travel_time_minutes,
                'lat'       => $e->latitude,
                'lng'       => $e->longitude,
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

