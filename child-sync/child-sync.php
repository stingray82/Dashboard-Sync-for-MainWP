<?php
/**
 * Plugin Name:       Main WP Child Sync
 * Tested up to:      6.7.2
 * Description:       MainWP Child Syner
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Version:           1.0
 * Author:            Stingray82
 * Author URI:        https://github.com/stingray82/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dashboard-sync
 * Website:           https://reallyusefulplugins.com
 * */



/* Example of filter user //

add_filter( 'custom_mainwp_sync_data', 'add_test_data_dynamically' );
function add_test_data_dynamically( $custom_data ) {
    // Add variables without prefixing the keys
    $custom_data['test_variable_1'] = get_option( 'test_variable_1', 'Default Value 1' );
    $custom_data['test_variable_2'] = get_option( 'test_variable_2', 'Default Value 2' );
    $custom_data['test_variable_3'] = get_option( 'test_variable_3', 'Default Value 3' );

    // Add an array without prefixing the key
    $custom_data['test_array'] = get_option( 'test_array', array( 'default1', 'default2' ) );

    return $custom_data;
} */

// Define the prefix for syncing custom data (fallback to 'custom_' if not defined)
if ( get_option( 'custom_mainwp_prefix' ) === false ) {
    add_option( 'custom_mainwp_prefix', 'custom' );
}

$custom_mainwp_prefix = get_option( 'custom_mainwp_prefix' );

/* Main Sync - This Adds our Custom Filter */
add_filter( 'mainwp_site_sync_others_data', 'custom_sync_data_child', 10, 2 );
function custom_sync_data_child( $information, $data = array() ) {
    global $custom_mainwp_prefix;

    try {
        $custom_data = array();

        // Allow other parts of the site to add data via this filter
        $custom_data = apply_filters( 'custom_mainwp_sync_data', $custom_data );

        // Store keys without prefix
        $keys = array_keys( $custom_data );

        // Add the keys list with the prefix applied only to the key name, not the values
        $custom_data[ $custom_mainwp_prefix . 'keys' ] = implode( ', ', $keys );

        // Add the collected custom data to the information array
        $information['customData'] = $custom_data;

    } catch ( Exception $e ) {
        error_log( 'Error syncing custom data: ' . $e->getMessage() );
    }

    return $information;
}

/* Add Settings Menu for MainWP Sync */
add_action( 'admin_menu', 'custom_mainwp_sync_settings_menu' );
function custom_mainwp_sync_settings_menu() {
    add_options_page(
        'MainWP Sync Settings',         // Page title
        'MainWP Sync',                  // Menu title
        'manage_options',               // Capability
        'mainwp-sync-settings',         // Menu slug
        'custom_mainwp_sync_settings_page' // Callback function
    );
}

/* Render Settings Page */
/* Render Settings Page */
function custom_mainwp_sync_settings_page() {
    // Check if the form is submitted and nonce is verified
    if ( isset( $_POST['save_mainwp_sync_settings'] ) && check_admin_referer( 'mainwp_sync_settings_nonce' ) ) {
        $custom_mainwp_prefix = sanitize_text_field( $_POST['custom_mainwp_prefix'] );
        
        // Update the option in the database
        update_option( 'custom_mainwp_prefix', $custom_mainwp_prefix );

        echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
    }

    // Get the current value of the prefix
    $custom_mainwp_prefix = get_option( 'custom_mainwp_prefix', 'custom' );

    echo '<div class="wrap">';
    echo '<h1>MainWP Sync Settings</h1>';
    echo '<form method="post">';
    
    // Add nonce field for security
    wp_nonce_field( 'mainwp_sync_settings_nonce' );

    echo '<h2>Custom MainWP Prefix</h2>';
    echo '<p>Set the prefix used for syncing custom data between MainWP Dashboard and child sites.</p>';
    echo '<p><input type="text" name="custom_mainwp_prefix" value="' . esc_attr( $custom_mainwp_prefix ) . '" class="regular-text"></p>';

    submit_button( 'Save Settings', 'primary', 'save_mainwp_sync_settings' );

    echo '</form>';
    echo '</div>';
}

/**
 * Handle real-time data fetching for MainWP Pro Reports.
 */
add_action('mainwp_child_fetch_data', function ($action, $params) {
    global $custom_mainwp_prefix;

    error_log("Child Plugin - Fetch Data Action Triggered: $action");
    error_log("Parameters Received: " . print_r($params, true));

    // Initialize response
    $response = ['error' => 'Invalid action requested.'];

    // Fetch dated data dynamically
    if ($action === 'fetch_dated_data') {
        $from_date = $params['from'] ?? date('Y-m-d', strtotime('-1 month'));
        $to_date = $params['to'] ?? date('Y-m-d');

        error_log("Fetching dated data from $from_date to $to_date");

        try {
            // Filter for dated data
            $dated_data = apply_filters('custom_mainwp_sync_dated_data', [], $from_date, $to_date);

            if (!empty($dated_data)) {
                $response = $dated_data;
                error_log("Dated data fetched successfully: " . print_r($dated_data, true));
            } else {
                $response = ['error' => 'No dated data found.'];
                error_log("No dated data found for range $from_date to $to_date.");
            }
        } catch (Exception $e) {
            $response = ['error' => 'Error fetching dated data: ' . $e->getMessage()];
            error_log("Error fetching dated data: " . $e->getMessage());
        }
    }

    // Allow additional custom actions to be handled dynamically
    $response = apply_filters('custom_mainwp_real_time_data', $response, $action, $params);

    return $response;
}, 10, 2);