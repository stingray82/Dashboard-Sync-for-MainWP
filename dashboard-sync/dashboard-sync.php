<?php
/**
 * Plugin Name:       Dashboard Sync for MainWP
 * Tested up to:      6.7.2
 * Description:       This extension allows syncing custom data from MainWP child sites, generting custom pro-report templates and managing settings for custom admin pages for this data
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Version:           1.1
 * Author:            Stingray82
 * Author URI:        https://github.com/stingray82/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dashboard-sync
 * Website:           https://reallyusefulplugins.com
 * */


// Define the prefix for synced data (fallback to 'custom_' if not defined)
if (get_option('custom_mainwp_prefix') === false) {
    add_option('custom_mainwp_prefix', 'custom');
}

$custom_mainwp_prefix = get_option('custom_mainwp_prefix');

// Hook: used in dashboard to handle received data from child site
add_action('mainwp_site_synced', 'custom_handle_synced_data', 10, 2);
function custom_handle_synced_data($website, $information = array()) {
    global $custom_mainwp_prefix;

    if (is_array($information) && isset($information['customData'])) {
        foreach ($information['customData'] as $key => $value) {
            $option_key = ($key === $custom_mainwp_prefix . 'keys') ? $key : $custom_mainwp_prefix . $key;

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            error_log("Received $option_key: " . print_r($value, true));

            apply_filters('mainwp_updatewebsiteoptions', false, $website, $option_key, $value);
        }
    } else {
        error_log('No custom data found for syncing.');
    }
}

// Simple token generation for MainWP Pro Reports
add_filter('mainwp_pro_reports_custom_tokens', 'mycustom_mainwp_pro_reports_custom_tokens', 10, 3);
function mycustom_mainwp_pro_reports_custom_tokens($tokensValues, $report, $site) {
    $prefix = get_option('custom_mainwp_prefix', 'custom');

    error_log('Dashboard Prefix: ' . $prefix);

    $keys = apply_filters('mainwp_getwebsiteoptions', false, $site['id'], $prefix . 'keys');

    if ($keys !== false && is_string($keys)) {
        $keys = array_map('trim', explode(',', $keys));
        error_log('Converted Keys Array: ' . print_r($keys, true));

        foreach ($keys as $key) {
            $full_key = $prefix . $key;
            $value = apply_filters('mainwp_getwebsiteoptions', false, $site['id'], $full_key);

            if ($value !== false) {
                $token_name = '[' . $full_key . ']';
                $tokensValues[$token_name] = is_array($value) ? implode(', ', $value) : $value;
                error_log("Token Added: $token_name | Value: " . print_r($tokensValues[$token_name], true));
            } else {
                error_log("No value found for key: $full_key");
            }
        }
    } else {
        error_log("No keys found or invalid keys for Site ID: " . $site['id']);
    }

    error_log('Final Tokens Array: ' . print_r($tokensValues, true));

    return $tokensValues;
}

/* Clean Up Admin Item & Function */
add_action('admin_menu', 'custom_mainwp_add_cleanup_tool');
function custom_mainwp_add_cleanup_tool() {
    add_management_page(
        'MainWP Child Data Cleanup',
        'MainWP Cleanup',
        'manage_options',
        'custom-mainwp-cleanup',
        'custom_mainwp_cleanup_tool_page'
    );
}

function custom_mainwp_cleanup_tool_page() {
    if (isset($_POST['custom_mainwp_cleanup']) && check_admin_referer('custom_mainwp_cleanup_nonce')) {
        custom_mainwp_cleanup_child_site_data();
    }

    echo '<div class="wrap">';
    echo '<h1>MainWP Child Data Cleanup</h1>';
    echo '<p>This tool will delete all synced data for child sites with the specified prefix.</p>';
    echo '<form method="post">';
    wp_nonce_field('custom_mainwp_cleanup_nonce');
    echo '<input type="submit" class="button button-primary" name="custom_mainwp_cleanup" value="Clean Up Data">';
    echo '</form>';
    echo '</div>';
}

function custom_mainwp_cleanup_child_site_data() {
    global $wpdb;
    $prefix = get_option('custom_mainwp_prefix', 'custom');

    error_log("Starting cleanup for prefix: $prefix");

    $deleted_rows = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}mainwp_wp_options WHERE name LIKE %s",
            $prefix . '%'
        )
    );

    error_log("Deleted $deleted_rows rows with prefix: $prefix");

    add_action('admin_notices', function () use ($deleted_rows) {
        echo '<div class="notice notice-success"><p><strong>' . esc_html($deleted_rows) . ' rows deleted with the specified prefix.</strong></p></div>';
    });
}

/* Universal MainWP Admin Pages */
add_filter('rup_mainwp_custom_admin_pages', 'rup_mainwp_register_custom_admin_pages');
function rup_mainwp_register_custom_admin_pages($pages) {
    return $pages;
}

add_filter('mainwp_getsubpages_sites', 'rup_mainwp_add_custom_subpages', 10, 1);
function rup_mainwp_add_custom_subpages($subArray) {
    $custom_pages = apply_filters('rup_mainwp_custom_admin_pages', []);
    $enabled_pages = get_option('enabled_mainwp_pages', []);

    foreach ($custom_pages as $page) {
        if (!empty($enabled_pages[$page['slug']])) {
            $subArray[] = array(
                'title'      => $page['title'],
                'slug'       => $page['slug'],
                'sitetab'    => true,
                'menu_hidden'=> true,
                'callback'   => function () use ($page) {
                    do_action('mainwp_pageheader_sites');
                    if (is_callable($page['callback'])) {
                        call_user_func($page['callback']);
                    }
                    do_action('mainwp_pagefooter_sites');
                }
            );
        }
    }
    return $subArray;
}

add_action('admin_enqueue_scripts', 'rup_mainwp_enqueue_custom_styles');
function rup_mainwp_enqueue_custom_styles() {
    $custom_pages = apply_filters('rup_mainwp_custom_admin_pages', []);

    foreach ($custom_pages as $page) {
        if (!empty($page['style'])) {
            wp_enqueue_style(
                $page['slug'] . '-style',
                $page['style'],
                array(),
                '1.0'
            );
        }
    }
}

// Add MainWP extension page
add_filter('mainwp_getextensions', 'rup_mainwp_register_extension');
function rup_mainwp_register_extension($extensions) {
    $extensions[] = array(
        'plugin'      => __FILE__,
        'api'         => 'dashboard-sync',
        'mainwp'      => false,
        'callback'    => 'rup_mainwp_extension_settings_page',
        'name'        => 'Dashboard Sync',                     // Custom menu item name
        'icon'        => plugins_url('assets/sync.svg', __FILE__), // Path to the custom icon
    );
    return $extensions;
}


// Render the extension settings page
function rup_mainwp_extension_settings_page() {
    do_action( 'mainwp_pageheader_sites', 'MainWPDashboardSync' );

    if (isset($_POST['save_mainwp_extension_settings']) && check_admin_referer('mainwp_extension_settings_nonce')) {
        $enabled_pages = isset($_POST['enabled_pages']) ? $_POST['enabled_pages'] : [];
        update_option('enabled_mainwp_pages', $enabled_pages);

        $custom_mainwp_prefix = sanitize_text_field($_POST['custom_mainwp_prefix']);
        update_option('custom_mainwp_prefix', $custom_mainwp_prefix);

        echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
    }

    $custom_mainwp_prefix = get_option('custom_mainwp_prefix');
    $enabled_pages = get_option('enabled_mainwp_pages', []);

    echo '<div class="wrap">';
    echo '<h1>Dashboard Sync Settings</h1>';
    echo '<form method="post">';
    wp_nonce_field('mainwp_extension_settings_nonce');

    echo '<h2>Enable/Disable Pages</h2>';
    $custom_pages = apply_filters('rup_mainwp_custom_admin_pages', []);

    foreach ($custom_pages as $page) {
        $checked = !empty($enabled_pages[$page['slug']]) ? 'checked' : '';
        echo '<p><label><input type="checkbox" name="enabled_pages[' . esc_attr($page['slug']) . ']" value="1" ' . $checked . '> ' . esc_html($page['title']) . '</label></p>';
    }

    echo '<h2>Custom MainWP Prefix</h2>';
    echo '<p><input type="text" name="custom_mainwp_prefix" value="' . esc_attr($custom_mainwp_prefix) . '" class="regular-text"></p>';

    submit_button('Save Settings', 'primary', 'save_mainwp_extension_settings');
    echo '</form>';
    echo '</div>';
    do_action( 'mainwp_pagefooter_sites', 'MainWPDashboardSync' );
    
}

// Add Wildcard Formatting for better dynamical pulling of data
// New Filter added apply_filters('mainwp_getwebsiteoptions_wildcard', $websiteId, $wildcard);
function mainwp_getwebsiteoptions_wildcard($websiteId, $wildcard) {
    global $wpdb;

    // Debugging: Log website ID and wildcard
    error_log("Wildcard Filter - Website ID: $websiteId, Wildcard: $wildcard");

    // Fetch options using the wildcard
    $like_query = $wpdb->esc_like($wildcard) . '%';
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT `name`, `value` FROM {$wpdb->prefix}mainwp_wp_options WHERE `wpid` = %d AND `name` LIKE %s",
            $websiteId,
            $like_query
        ),
        ARRAY_A
    );

    // Debugging: Log raw results
    error_log("Wildcard Results: " . print_r($results, true));

    // Format results as key-value pairs
    $options = [];
    if (!empty($results)) {
        foreach ($results as $row) {
            $options[$row['name']] = maybe_unserialize($row['value']);
        }
    }

    return $options;
}

// Register the new filter
add_filter('mainwp_getwebsiteoptions_wildcard', 'mainwp_getwebsiteoptions_wildcard', 10, 2);

/**
 * Fetch data dynamically from the child site during pro-report generation.
 */
add_filter('mainwp_pro_reports_generate_report_content', function ($report_content, $report_id, $site_id, $from_date, $to_date) {
    global $custom_mainwp_prefix;

    error_log("Generating report content for site ID: $site_id, Date Range: $from_date - $to_date");

    // Trigger the child plugin to fetch real-time data
    $response = apply_filters(
        'mainwp_child_execute',
        $site_id,
        'fetch_dated_data',
        ['from' => $from_date, 'to' => $to_date]
    );

    // Handle errors in communication
    if (is_wp_error($response)) {
        error_log('Error fetching data from child site: ' . $response->get_error_message());
        return $report_content . "\nError fetching data from child site: " . $response->get_error_message();
    }

    // Check if the response contains errors
    if (isset($response['error'])) {
        error_log('Error from child site: ' . $response['error']);
        return $report_content . "\nError from child site: " . $response['error'];
    }

    // Add fetched data to the report content
    foreach ($response as $key => $value) {
        $token_name = '[' . $key . ']';
        $formatted_value = is_array($value) ? implode(', ', $value) : $value;

        // Append to the report content
        $report_content .= strtoupper(str_replace($custom_mainwp_prefix, '', $key)) . ': ' . $formatted_value . "\n";

        // Register the token for pro-reports
        $tokensValues[$token_name] = $formatted_value;
    }

    return $report_content;
}, 10, 5);

/**
 * Add dynamically fetched tokens to MainWP Pro Reports.
 */
add_filter('mainwp_pro_reports_custom_tokens', function ($tokensValues, $report, $site) {
    global $custom_mainwp_prefix;

    error_log("Adding custom tokens for site ID: {$site['id']}");

    // Get keys for fetched data
    $keys = apply_filters('mainwp_getwebsiteoptions', false, $site['id'], $custom_mainwp_prefix . 'dated_keys');

    if ($keys !== false && is_string($keys)) {
        $keys = array_map('trim', explode(',', $keys));
        error_log('Fetched keys: ' . print_r($keys, true));

        foreach ($keys as $key) {
            $value = apply_filters('mainwp_getwebsiteoptions', false, $site['id'], $custom_mainwp_prefix . $key);
            if ($value !== false) {
                $token_name = '[' . $custom_mainwp_prefix . $key . ']';
                $tokensValues[$token_name] = is_array($value) ? implode(', ', $value) : $value;

                error_log("Token added: $token_name | Value: " . print_r($value, true));
            }
        }
    }

    return $tokensValues;
}, 10, 3);
