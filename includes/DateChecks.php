<?php

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
?>