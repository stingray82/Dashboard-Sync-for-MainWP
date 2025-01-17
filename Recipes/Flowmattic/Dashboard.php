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

    // Check if FlowMattic is activated (defer this check to page rendering)
    $is_activated = (int) apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_activated');

    if ($is_activated !== 1) {
        echo '<div class="notice notice-warning" style="padding: 20px; background-color: #fff3cd; border-left: 4px solid #ffeeba; border-radius: 5px;">
                <strong>' . esc_html__('FlowMattic is not activated or synced on this child site.', 'flowmattic') . '</strong>
              </div>';
        return;
    }

    // Embed styles directly in the page
    echo '<style>
        .flowmattic-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            padding: 30px;
            background-color: #f9fafe;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
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
        .flowmattic-title {
            font-size: 32px;
            font-weight: 800;
            color: #0056b3;
            text-align: center;
            margin-bottom: 30px;
            padding: 15px 0;
            border-bottom: 3px solid #dbe4f1;
        }
    </style>';

    // Title section
    echo '<div class="flowmattic-title">' . esc_html__('FlowMattic Stats', 'flowmattic') . '</div>';

    // Placeholder for dynamic stats from MainWP options
    $stats = [
        'Workflows'        => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_workflows_count'), 'class' => 'workflows-card'],
        'Tables'           => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_tables_count'), 'class' => 'tables-card'],
        'AI Assistants'    => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_ai_assistants_count'), 'class' => 'ai-assistants-card'],
        'Connects'         => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_connects_count'), 'class' => 'connects-card'],
        'Variables'        => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_variables_count'), 'class' => 'variables-card'],
        'Integrations'     => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_integrations_count'), 'class' => 'integrations-card'],
        'Custom Apps'      => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_custom_apps_count'), 'class' => 'custom-apps-card'],
        'Tasks Executions' => ['count' => apply_filters('mainwp_getwebsiteoptions', 0, $websiteId, $custom_mainwp_prefix . 'rup_mainwp_flowmattic_tasks_executions_count'), 'class' => 'tasks-card']
    ];

    // Stats section
    echo '<div class="flowmattic-dashboard">';
    foreach ($stats as $title => $data) {
        echo '<div class="flowmattic-card ' . esc_attr($data['class']) . '">';
        echo '<span class="stat-number">' . number_format($data['count']) . '</span>';
        echo '<h6>' . esc_html__($title, 'flowmattic') . '</h6>';
        echo '</div>';
    }
    echo '</div>';
}
