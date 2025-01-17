<?php 
// Add FlowMattic stats to MainWP sync data
add_filter('custom_mainwp_sync_data', 'add_flowmattic_data_to_mainwp');
function add_flowmattic_data_to_mainwp($custom_data) {
    
    // Check if FlowMattic is available and load necessary data
    if (class_exists('FlowMattic_Admin') && function_exists('wp_flowmattic')) {
        FlowMattic_Admin::loader(); // Load FlowMattic Admin if needed

        // Set activated status to 1
        $custom_data['rup_mainwp_flowmattic_activated'] = 1;

        // Get all workflows
        $all_workflows = wp_flowmattic()->workflows_db->get_all();
        $custom_data['rup_mainwp_flowmattic_workflows_count'] = count($all_workflows);
        
        // Get tables count
        $tables_schema = (array) wp_flowmattic()->tables_schema_db->get_all();
        $tables_count = (!empty($tables_schema)) ? count($tables_schema) : 0;
        $custom_data['rup_mainwp_flowmattic_tables_count'] = $tables_count;

        // Get AI Assistants count
        $assistants = wp_flowmattic()->chatbots_db->get_all();
        $assistants_count = (!empty($assistants)) ? count($assistants) : 0;
        $custom_data['rup_mainwp_flowmattic_ai_assistants_count'] = $assistants_count;

        // Get connects count
        $all_connects = wp_flowmattic()->connects_db->get_all();
        $connects_count = (!empty($all_connects)) ? count($all_connects) : 0;
        $custom_data['rup_mainwp_flowmattic_connects_count'] = $connects_count;

        // Get custom variables count
        $custom_vars = wp_flowmattic()->variables->get_custom_vars();
        $variables_count = (!empty($custom_vars)) ? count($custom_vars) : 0;
        $custom_data['rup_mainwp_flowmattic_variables_count'] = $variables_count;

        // Get integrations count
        $custom_data['rup_mainwp_flowmattic_integrations_count'] = number_format(get_option('flowmattic_installed_apps', 0));

        // Get custom apps count
        $custom_apps = wp_flowmattic()->custom_apps_db->get_all();
        $apps_count = (!empty($custom_apps)) ? count($custom_apps) : 0;
        $custom_data['rup_mainwp_flowmattic_custom_apps_count'] = $apps_count;

        // Get tasks executions count
        $task_count = wp_flowmattic()->tasks_db->get_tasks_count();
        $custom_data['rup_mainwp_flowmattic_tasks_executions_count'] = $task_count;

    } else {
        // If FlowMattic is not available, set default values
        $custom_data['rup_mainwp_flowmattic_activated'] = 0;
        $custom_data['rup_mainwp_flowmattic_workflows_count'] = 0;
        $custom_data['rup_mainwp_flowmattic_tables_count'] = 0;
        $custom_data['rup_mainwp_flowmattic_ai_assistants_count'] = 0;
        $custom_data['rup_mainwp_flowmattic_connects_count'] = 0;
        $custom_data['rup_mainwp_flowmattic_variables_count'] = 0;
        $custom_data['rup_mainwp_flowmattic_integrations_count'] = 0;
        $custom_data['rup_mainwp_flowmattic_custom_apps_count'] = 0;
        $custom_data['rup_mainwp_flowmattic_tasks_executions_count'] = 0;
    }

    return $custom_data;
}

