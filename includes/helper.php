<?php




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


?>