<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'custom_mainwp_sync_data', 'sync_wp_armour_stats_with_mainwp' );

function sync_wp_armour_stats_with_mainwp( $custom_data ) {
    // Check if WP Armour or WP Armour Extended is installed
    if ( ! function_exists('get_option') || ( ! function_exists('wpae_reset_stats') && ! function_exists('wpa_check_date') ) ) {
        $custom_data['rup_wparmour_stats'] = 'WP Armour not installed';
        return $custom_data;
    }

    // Get the current stats from the options table
    $currentStats = json_decode(get_option('wpa_stats'), true);

    if (!empty($currentStats)) {
        foreach ($currentStats as $source => $statData) {
            // Generate dynamic stat keys for each type with the required prefix
            $custom_data["rup_wparmour_today_" . $source] = @rup_mainwp_wpa_check_date($statData['today']['date'], 'today') ? $statData['today']['count'] : '0';
            $custom_data["rup_wparmour_thisweek_" . $source] = @rup_mainwp_wpa_check_date($statData['week']['date'], 'week') ? $statData['week']['count'] : '0';
            $custom_data["rup_wparmour_thismonth_" . $source] = @rup_mainwp_wpa_check_date($statData['month']['date'], 'month') ? $statData['month']['count'] : '0';
            $custom_data["rup_wparmour_alltime_" . $source] = $statData['all_time'];
        }
    } else {
        // Add default no-record stats to avoid empty values
        $custom_data['rup_wparmour_stats'] = 'No Record Found';
    }

    return $custom_data;
}

// Utility function to check date validity (replicating wpa_check_date for context if necessary)
function rup_mainwp_wpa_check_date($stat_date, $type) {
    $current_time = current_time('timestamp');

    switch ($type) {
        case 'today':
            return date('Y-m-d', $current_time) === date('Y-m-d', strtotime($stat_date));

        case 'week':
            return date('W', $current_time) === date('W', strtotime($stat_date));

        case 'month':
            return date('Y-m', $current_time) === date('Y-m', strtotime($stat_date));

        default:
            return false;
    }
}
?>