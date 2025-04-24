<?php
/**
 * Plugin Name: Simple WP CRM
 * Description: A minimal CRM system with CPT, meta boxes, REST API and dashboard UI.
 * Version: 1.0
 * Author: Siraji
 * License: GPL2+
 */
function swc_register_leads_cpt() {
    $labels = [
        'name'               => 'Leads',
        'singular_name'      => 'Lead',
        'menu_name'          => 'Leads',
        'name_admin_bar'     => 'Lead',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Lead',
        'new_item'           => 'New Lead',
        'edit_item'          => 'Edit Lead',
        'view_item'          => 'View Lead',
        'all_items'          => 'All Leads',
        'search_items'       => 'Search Leads',
        'not_found'          => 'No leads found',
        'not_found_in_trash' => 'No leads in Trash',
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'show_in_menu'       => true,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-businessperson',
        'supports'           => ['title', 'editor', 'custom-fields'],
        'has_archive'        => true,
        'rewrite'            => ['slug' => 'leads'],
        'show_in_rest'       => true,
    ];

    register_post_type('lead', $args);
}
add_action('init', 'swc_register_leads_cpt');


// lead Fields
function swc_add_lead_meta_boxes() {
    add_meta_box(
        'swc_lead_details',
        'Lead Details',
        'swc_render_lead_details_box',
        'lead',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'swc_add_lead_meta_boxes');

function swc_render_lead_details_box($post) {
    $email = get_post_meta($post->ID, '_swc_email', true);
    $phone = get_post_meta($post->ID, '_swc_phone', true);
    $status = get_post_meta($post->ID, '_swc_status', true);

    ?>
    <p><label>Email:</label><br>
        <input type="email" name="swc_email" value="<?php echo esc_attr($email); ?>" class="widefat" /></p>

    <p><label>Phone:</label><br>
        <input type="text" name="swc_phone" value="<?php echo esc_attr($phone); ?>" class="widefat" /></p>

    <p><label>Status:</label><br>
        <select name="swc_status" class="widefat">
            <option value="new" <?php selected($status, 'new'); ?>>New</option>
            <option value="contacted" <?php selected($status, 'contacted'); ?>>Contacted</option>
            <option value="won" <?php selected($status, 'won'); ?>>Won</option>
            <option value="lost" <?php selected($status, 'lost'); ?>>Lost</option>
        </select></p>
    <?php
}

//Save the Meta Data
function swc_save_lead_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['swc_email'])) {
        update_post_meta($post_id, '_swc_email', sanitize_email($_POST['swc_email']));
    }

    if (isset($_POST['swc_phone'])) {
        update_post_meta($post_id, '_swc_phone', sanitize_text_field($_POST['swc_phone']));
    }

    if (isset($_POST['swc_status'])) {
        update_post_meta($post_id, '_swc_status', sanitize_text_field($_POST['swc_status']));
    }
}
add_action('save_post_lead', 'swc_save_lead_meta');

//REST API FOR GET LEADS

add_action('rest_api_init', function () {
    register_rest_route('simple-crm/v1', '/leads', [
        'methods' => 'GET',
        'callback' => 'swc_get_all_leads',
        'permission_callback' => '__return_true', // optional: public access
    ]);
});


function swc_get_all_leads(WP_REST_Request $request) {
    $status_filter = $request->get_param('status'); // Get ?status param

    $meta_query = [
        'relation' => 'AND',
    ];
    
    if ($status_filter) {
        $meta_query[] = [
            'key'     => '_swc_status',
            'value'   => $status_filter,
            'compare' => '='
        ];
    }
    

    $args = [
        'post_type'   => 'lead',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_query'  => $meta_query,
    ];

    $leads = get_posts($args);
    $data = [];

    foreach ($leads as $lead) {
        $data[] = [
            'id'     => $lead->ID,
            'title'  => $lead->post_title,
            'email'  => get_post_meta($lead->ID, '_swc_email', true),
            'phone'  => get_post_meta($lead->ID, '_swc_phone', true),
            'status' => get_post_meta($lead->ID, '_swc_status', true),
        ];
    }

    return rest_ensure_response($data);
}


// Admin Dashboard widget

add_action('wp_dashboard_setup', 'swc_add_leads_dashboard_widget');

function swc_add_leads_dashboard_widget() {
    wp_add_dashboard_widget(
        'swc_leads_widget',
        'Leads Overview',
        'swc_render_leads_dashboard_widget'
    );

    echo '<p><a href="' . admin_url('?swc_export_leads=1') . '" class="button button-primary">Export Leads to CSV</a></p>';

}

//Widget Content Callback

function swc_render_leads_dashboard_widget() {
    $statuses = ['new', 'contacted', 'won', 'lost'];
    $counts = [];

    foreach ($statuses as $status) {
        $args = [
            'post_type'  => 'lead',
            'meta_key'   => '_swc_status',
            'meta_value' => $status,
            'post_status' => 'publish',
            'fields'     => 'ids',
        ];
        $query = new WP_Query($args);
        $counts[$status] = $query->found_posts;
        wp_reset_postdata();
    }

    $total = wp_count_posts('lead')->publish;

    echo '<ul style="line-height: 1.8em;">';
    echo "<li><strong>Total Leads:</strong> {$total}</li>";
    foreach ($counts as $status => $count) {
        echo "<li><strong>" . ucfirst($status) . ":</strong> {$count}</li>";
    }
    echo '</ul>';

    // Export Button
    echo '<p><a href="' . admin_url('?swc_export_leads=1') . '" class="button button-primary">Export Leads to CSV</a></p>';
    // pai chart
    echo '<canvas id="swcrm_leads_chart" width="400" height="200"></canvas>';

}


// CSV Export

function swc_export_leads_csv() {
    if (!current_user_can('manage_options')) return;

    if (!isset($_GET['swc_export_leads']) || $_GET['swc_export_leads'] !== '1') return;

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=leads-export.csv');

    $output = fopen('php://output', 'w');

    // Column headers
    fputcsv($output, ['ID', 'Title', 'Email', 'Phone', 'Status']);

    $args = [
        'post_type'   => 'lead',
        'post_status' => 'publish',
        'numberposts' => -1,
    ];

    $leads = get_posts($args);

    foreach ($leads as $lead) {
        fputcsv($output, [
            $lead->ID,
            $lead->post_title,
            get_post_meta($lead->ID, '_swc_email', true),
            get_post_meta($lead->ID, '_swc_phone', true),
            get_post_meta($lead->ID, '_swc_status', true),
        ]);
    }

    fclose($output);
    exit;
}
add_action('admin_init', 'swc_export_leads_csv');

// pie chart part 2025

add_action('admin_enqueue_scripts', 'swc_enqueue_dashboard_chart_js');
function swc_enqueue_dashboard_chart_js($hook) {
    if ($hook !== 'index.php') return; // only load on Dashboard

    // Chart.js from CDN
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

    // Your custom chart code
    wp_enqueue_script(
        'swcrm-dashboard-chart',
        plugin_dir_url(__FILE__) . 'js/swcrm-dashboard-chart.js',
        ['chart-js'],
        '1.0',
        true
    );

    // Fetch lead counts from PHP
    $statuses = ['new', 'contacted', 'won', 'lost'];
    $counts = [];

    foreach ($statuses as $status) {
        $query = new WP_Query([
            'post_type'  => 'lead',
            'meta_key'   => '_swc_status',
            'meta_value' => $status,
            'post_status' => 'publish',
            'fields'     => 'ids',
        ]);
        $counts[] = $query->found_posts;
        wp_reset_postdata();
    }

    wp_localize_script('swcrm-dashboard-chart', 'swcrm_data', [
        'labels' => ['New', 'Contacted', 'Won', 'Lost'],
        'counts' => $counts
    ]);
}
