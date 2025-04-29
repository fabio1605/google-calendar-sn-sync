<?php

// Function to get the user's IP address
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

// Function to geocode a location using Google Maps API
function gcsn_geocode_location($address) {
    $api_key = 'your-google-maps-api-key';
    $cache_key = 'gcsn_geo_' . md5($address);
    $cached = get_transient($cache_key);
    if ($cached) return $cached;

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
