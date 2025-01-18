<?php
// Register the FlowMattic Stats page via the custom filter
add_filter('rup_mainwp_custom_admin_pages', 'register_flowmattic_stats_page');
function register_flowmattic_stats_page($pages) {
    $pages[] = array(
        'title'    => 'FlowMattic',                               // Menu title
        'slug'     => 'FlowmatticStats',                          // Unique slug
        'callback' => 'render_flowmattic_stats_page'              // Callback to render content
    );
    return $pages;
}

// Callback function to render the FlowMattic Stats page
function render_flowmattic_stats_page() {
    $websiteId = $_GET['id'];
    $custom_mainwp_prefix = get_option('custom_mainwp_prefix', 'custom_');

    // Check if FlowMattic is activated
    $is_activated = (int) apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_activated');

    if ($is_activated !== 1) {
        echo '<div class="notice notice-warning" style="padding: 20px; background-color: #fff3cd; border-left: 4px solid #ffeeba; border-radius: 5px;">
                <strong>' . esc_html__('FlowMattic is not activated or synced on this child site.', 'flowmattic') . '</strong>
              </div>';
        return;
    }

    // Embed styles directly in the page
    echo '<style>
        .flowmattic-title {
            font-size: 28px;
            font-weight: 700;
            color: #0056b3;
            text-align: center;
            margin: 20px 0;
            padding: 10px 0;
            border-bottom: 2px solid #dbe4f1;
        }
        .flowmattic-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            padding: 30px;
            background-color: #f9fafe;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        .flowmattic-card {
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .flowmattic-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
        }
        .flowmattic-card h6 {
            font-size: 14px;
            color: #6c757d;
            margin-top: 10px;
            text-transform: uppercase;
        }
        .flowmattic-card .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
        .tasks-container {
            margin: 20px auto;
            padding: 15px;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .tasks-container h3 {
            font-size: 22px;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .badge {
            padding: 5px 10px;
            color: #fff;
            border-radius: 4px;
            text-align: center;
        }
        .badge.failed {
            background-color: #dc3545;
        }
        .badge.success {
            background-color: #28a745;
        }
    </style>';

    // Title section
    echo '<div class="flowmattic-title">' . esc_html__('FlowMattic Stats', 'flowmattic') . '</div>';

    // Stats section
    $stats = [
        'Workflows'        => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_workflows_count')],
        'Tables'           => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_tables_count')],
        'AI Assistants'    => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_ai_assistants_count')],
        'Connects'         => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_connects_count')],
        'Variables'        => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_variables_count')],
        'Integrations'     => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_integrations_count')],
        'Custom Apps'      => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_custom_apps_count')],
        'Tasks Executions' => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_tasks_executions_count')]
    ];

    echo '<div class="flowmattic-dashboard">';
    foreach ($stats as $title => $data) {
        echo '<div class="flowmattic-card">';
        echo '<span class="stat-number">' . number_format($data['count']) . '</span>';
        echo '<h6>' . esc_html($title) . '</h6>';
        echo '</div>';
    }
    echo '</div>';

    // Fetch tasks data
    $tasks_data = apply_filters('mainwp_getwebsiteoptions', [], $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_task_history');
    if (is_string($tasks_data)) {
        $tasks_data = json_decode($tasks_data, true);
    }
    if (!is_array($tasks_data)) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Failed to load tasks.', 'flowmattic') . '</p></div>';
        return;
    }

    // Separate tasks into failed and successful
    $failed_tasks = [];
    $successful_tasks = [];
    foreach ($tasks_data as $task) {
        $is_failed = false;
        foreach ($task['task_data'] as $step) {
            if (isset($step['captured_data']) && is_string($step['captured_data'])) {
                $captured_data = json_decode($step['captured_data'], true);
                if (isset($captured_data['status'])) {
                    $is_failed = true;
                    break;
                }
            }
        }
        if ($is_failed) {
            $failed_tasks[] = $task;
        } else {
            $successful_tasks[] = $task;
        }
    }

    // Limit to the last 10 records
    $failed_tasks = array_slice($failed_tasks, 0, 10);
    $successful_tasks = array_slice($successful_tasks, 0, 10);

    // Display Failed Tasks
    echo '<div class="tasks-container">
            <h3>' . esc_html__('Lastest 10 Failed Tasks', 'flowmattic') . '</h3>
            <table>
                <thead>
                    <tr>
                        <th>' . esc_html__('Workflow Name', 'flowmattic') . '</th>
                        <th>' . esc_html__('Recorded On', 'flowmattic') . '</th>
                        <th>' . esc_html__('Applications', 'flowmattic') . '</th>
                        <th>' . esc_html__('Status', 'flowmattic') . '</th>
                    </tr>
                </thead>
                <tbody>';
    if (!empty($failed_tasks)) {
        foreach ($failed_tasks as $task) {
            echo '<tr>
                    <td>' . esc_html(urldecode($task['workflow_name'] ?? 'Unknown Workflow')) . '</td>
                    <td>' . esc_html($task['task_time'] ?? 'Unknown') . '</td>
                    <td>' . esc_html(implode(', ', array_column($task['task_data'], 'application'))) . '</td>
                    <td><span class="badge failed">Failed</span></td>
                  </tr>';
        }
    } else {
        echo '<tr><td colspan="4">' . esc_html__('No failed tasks found.', 'flowmattic') . '</td></tr>';
    }
    echo '</tbody></table></div>';

    // Display Successful Tasks
    echo '<div class="tasks-container">
            <h3>' . esc_html__('Latest 10 Successful Tasks', 'flowmattic') . '</h3>
            <table>
                <thead>
                    <tr>
                        <th>' . esc_html__('Workflow Name', 'flowmattic') . '</th>
                        <th>' . esc_html__('Recorded On', 'flowmattic') . '</th>
                        <th>' . esc_html__('Applications', 'flowmattic') . '</th>
                        <th>' . esc_html__('Status', 'flowmattic') . '</th>
                    </tr>
                </thead>
                <tbody>';
    if (!empty($successful_tasks)) {
        foreach ($successful_tasks as $task) {
            echo '<tr>
                    <td>' . esc_html(urldecode($task['workflow_name'] ?? 'Unknown Workflow')) . '</td>
                    <td>' . esc_html($task['task_time'] ?? 'Unknown') . '</td>
                    <td>' . esc_html(implode(', ', array_column($task['task_data'], 'application'))) . '</td>
                    <td><span class="badge success">Successful</span></td>
                  </tr>';
        }
    } else {
        echo '<tr><td colspan="4">' . esc_html__('No successful tasks found.', 'flowmattic') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
