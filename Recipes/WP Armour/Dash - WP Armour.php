<?php
// Register the WP Armour Stats page via the custom filter
add_filter('rup_mainwp_custom_admin_pages', 'register_wp_armour_stats_page');
function register_wp_armour_stats_page($pages) {
    $pages[] = array(
        'title'    => 'WP Armour Stats',                         // Menu title
        'slug'     => 'WPArmourStats',                           // Unique slug
        'callback' => 'render_wp_armour_stats_page'              // Callback to render content
    );
    return $pages;
}

// Callback function to render the WP Armour Stats page
function render_wp_armour_stats_page() {
    $websiteId = $_GET['id'];
    $custom_mainwp_prefix = get_option('custom_mainwp_prefix', 'custom_');

    echo '<div class="wp-armour-stats-container">';

    // Title section
    echo '<h2 class="wp-armour-title">' . esc_html__('WP Armour Stats', 'wp-armour') . '</h2>';

    // Embed styles
    echo '<style>
        .wp-armour-stats-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f7f7f7;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .wp-armour-title {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            text-align: center;
            margin-bottom: 20px;
        }
        .wp-armour-stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .wp-armour-stats-table th, .wp-armour-stats-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        .wp-armour-stats-table th {
            background-color: #0073aa;
            color: #ffffff;
            font-weight: bold;
        }
        .wp-armour-stats-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .wp-armour-stats-footer {
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            margin-top: 20px;
        }
    </style>';

    // Use the wildcard filter to fetch all relevant options
    $wildcard = $custom_mainwp_prefix . 'rup_wparmour_';
    $all_options = apply_filters('mainwp_getwebsiteoptions_wildcard', $websiteId, $wildcard);

    if (empty($all_options)) {
        echo '<p>' . esc_html__('No stats available.', 'wp-armour') . '</p>';
        return;
    }

    // Process and organize stats into a horizontal format
    $stats_table = [
        'Today' => [],
        'This Week' => [],
        'This Month' => [],
        'All Time' => []
    ];

    foreach ($all_options as $key => $value) {
        $stat_key = str_replace($wildcard, '', $key);
        $parts = explode('_', $stat_key);
        $time_period = array_shift($parts); // First part (e.g., 'today', 'thisweek')
        $source = implode(' ', $parts);    // Remaining parts (e.g., 'total', 'wplogin')

        // Map time period keys to table columns
        switch ($time_period) {
            case 'today':
                $stats_table['Today'][$source] = $value;
                break;
            case 'thisweek':
                $stats_table['This Week'][$source] = $value;
                break;
            case 'thismonth':
                $stats_table['This Month'][$source] = $value;
                break;
            case 'alltime':
                $stats_table['All Time'][$source] = $value;
                break;
        }
    }

    // Render the horizontal table
    echo '<table class="wp-armour-stats-table">';
    echo '<thead><tr><th>' . esc_html__('Source', 'wp-armour') . '</th>';
    foreach (array_keys($stats_table) as $time_period) {
        echo '<th>' . esc_html__($time_period, 'wp-armour') . '</th>';
    }
    echo '</tr></thead>';
    echo '<tbody>';

    // Get all unique sources
    $all_sources = array_unique(array_merge(
        array_keys($stats_table['Today']),
        array_keys($stats_table['This Week']),
        array_keys($stats_table['This Month']),
        array_keys($stats_table['All Time'])
    ));

    // Render rows for each source
    foreach ($all_sources as $source) {
        echo '<tr>';
        echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $source))) . '</td>';
        foreach ($stats_table as $time_period => $values) {
            $count = isset($values[$source]) ? number_format($values[$source]) : '0';
            echo '<td>' . esc_html($count) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    // Footer
    echo '<div class="wp-armour-stats-footer">' . esc_html__('Stats powered by WP Armour.', 'wp-armour') . '</div>';

    echo '</div>';
}
?>
