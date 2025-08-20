<?php
/**
 * UMMP Ninja Viewer Class
 * 
 * Integrates Ninja Forms access control functionality
 * Based on SimpleNinjaFormsAccess class
 *
 * @package UsersMenuManagerPro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UMMP Ninja Viewer class
 */
class UMMP_Ninja_Viewer {
    
    /**
     * Role name for forms viewer
     *
     * @var string
     */
    private $role_name = 'nf_viewer';
    
    /**
     * Role display name
     *
     * @var string
     */
    private $role_display_name = 'Forms Viewer';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'create_role'));
        add_action('admin_menu', array($this, 'remove_menus'), 999);
        add_action('admin_menu', array($this, 'add_custom_menu'), 1000);
        add_action('admin_init', array($this, 'redirect_non_submissions'));
        add_action('admin_head', array($this, 'hide_elements'));
        add_action('wp_before_admin_bar_render', array($this, 'clean_admin_bar'));
        add_filter('login_redirect', array($this, 'login_redirect'), 10, 3);
        
        // AJAX handlers for managing viewers
        add_action('wp_ajax_ummp_create_ninja_viewer', array($this, 'ajax_create_viewer'));
        add_action('wp_ajax_ummp_assign_ninja_viewer', array($this, 'ajax_assign_viewer'));
        add_action('wp_ajax_ummp_remove_ninja_viewer', array($this, 'ajax_remove_viewer'));
        add_action('wp_ajax_ummp_get_submission_details', array($this, 'ajax_get_submission_details'));
        add_action('wp_ajax_ummp_get_ninja_viewers', array($this, 'ajax_get_viewers'));
        
        // Load scripts for Ninja Forms pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_ninja_scripts'));
    }
    
    /**
     * Create restricted role
     */
    public function create_role() {
        // Don't recreate if it already exists with proper capabilities
        $existing_role = get_role($this->role_name);
        if ($existing_role && isset($existing_role->capabilities['view_nf_submissions'])) {
            return;
        }
        
        // Remove existing role to recreate it
        remove_role($this->role_name);
        
        // Get subscriber capabilities as base
        $subscriber = get_role('subscriber');
        if (!$subscriber) {
            return;
        }
        
        $capabilities = $subscriber->capabilities;
        $capabilities['view_nf_submissions'] = true;
        $capabilities['read'] = true;
        
        // Add the role
        add_role($this->role_name, $this->role_display_name, $capabilities);
        
        // Log the activity
        $this->log_activity('ninja_forms_role_created', array(
            'role_name' => $this->role_name,
            'capabilities' => array_keys($capabilities)
        ));
    }
    
    /**
     * Check if current user is a forms viewer
     *
     * @return bool
     */
    private function is_viewer() {
        $user = wp_get_current_user();
        
        // Check if user has the viewer role
        if (in_array($this->role_name, (array) $user->roles)) {
            return true;
        }
        
        // Check if user has Ninja Forms access meta
        if (get_user_meta($user->ID, 'ummp_ninja_forms_access', true)) {
            return true;
        }
        
        // Check if user has the capability (but is not an admin)
        if (user_can($user->ID, 'view_nf_submissions') && !user_can($user->ID, 'manage_options')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Remove ALL admin menus for viewers
     */
    public function remove_menus() {
        if (!$this->is_viewer()) {
            return;
        }
        
        // Remove all default WordPress admin menus
        remove_menu_page('index.php');                  // Dashboard
        remove_menu_page('edit.php');                   // Posts
        remove_menu_page('upload.php');                 // Media
        remove_menu_page('edit.php?post_type=page');    // Pages
        remove_menu_page('edit-comments.php');          // Comments
        remove_menu_page('themes.php');                 // Appearance
        remove_menu_page('plugins.php');                // Plugins
        remove_menu_page('users.php');                  // Users
        remove_menu_page('tools.php');                  // Tools
        remove_menu_page('options-general.php');        // Settings
        
        // Remove Ninja Forms admin menu (they'll access via custom menu)
        remove_menu_page('ninja-forms');
        
        // Remove other common plugin menus
        remove_menu_page('woocommerce');
        remove_menu_page('edit.php?post_type=product');
        remove_menu_page('elementor');
        
        // Remove UMMP menus for viewers
        remove_menu_page('ummp-dashboard');
    }
    
    /**
     * Add custom menu for forms viewer
     */
    public function add_custom_menu() {
        if (!$this->is_viewer()) {
            return;
        }
        
        // Add the main submissions page
        add_menu_page(
            __('Form Submissions', 'ummp'),
            __('Form Submissions', 'ummp'),
            'view_nf_submissions',
            'ummp-nf-submissions',
            array($this, 'display_submissions_page'),
            'dashicons-feedback',
            20
        );
        
        // Add submenu for better organization
        add_submenu_page(
            'ummp-nf-submissions',
            __('All Submissions', 'ummp'),
            __('All Submissions', 'ummp'),
            'view_nf_submissions',
            'ummp-nf-submissions',
            array($this, 'display_submissions_page')
        );
        
        // Add a dashboard redirect page for better UX
        add_submenu_page(
            'ummp-nf-submissions',
            __('Dashboard', 'ummp'),
            __('Dashboard', 'ummp'),
            'read',
            'ummp-dashboard',
            array($this, 'display_dashboard_page')
        );
        
        error_log('UMMP Ninja: Added custom menu for viewer role');
    }

    /**
     * Display dashboard page for forms viewer
     */
    public function display_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Forms Viewer Dashboard', 'ummp'); ?></h1>
            <div class="ummp-dashboard-welcome">
                <p><?php _e('Welcome to your Forms Viewer dashboard. You can view and manage form submissions here.', 'ummp'); ?></p>
                <div class="ummp-dashboard-stats">
                    <h3><?php _e('Quick Stats', 'ummp'); ?></h3>
                    <?php
                    global $wpdb;
                    $total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nf3_submissions");
                    $recent_submissions = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}nf3_submissions WHERE created_at >= %s",
                        date('Y-m-d H:i:s', strtotime('-7 days'))
                    ));
                    ?>
                    <p><strong><?php _e('Total Submissions:', 'ummp'); ?></strong> <?php echo number_format($total_submissions); ?></p>
                    <p><strong><?php _e('Recent Submissions (7 days):', 'ummp'); ?></strong> <?php echo number_format($recent_submissions); ?></p>
                </div>
                <div class="ummp-dashboard-actions">
                    <a href="<?php echo admin_url('admin.php?page=ummp-nf-submissions'); ?>" class="button button-primary">
                        <?php _e('View All Submissions', 'ummp'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display custom submissions page
     */
    public function display_submissions_page() {
        // Check if Ninja Forms is active
        if (!class_exists('Ninja_Forms')) {
            echo '<div class="wrap"><h1>' . __('Form Submissions', 'ummp') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Ninja Forms plugin is not active. Please contact your administrator.', 'ummp') . '</p></div>';
            echo '</div>';
            return;
        }
        
        global $wpdb;
        
        // Get all forms using Ninja Forms API
        $forms = Ninja_Forms()->form()->get_forms();
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        
        ?>
        <div class="wrap ummp-ninja-submissions">
            <h1><?php _e('Form Submissions', 'ummp'); ?></h1>
            
            <?php if (current_user_can('manage_options')): ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('Debug Info:', 'ummp'); ?></strong> <?php printf(__('Forms found: %d', 'ummp'), count($forms)); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="ummp-submissions-filters">
                <form method="get" class="ummp-filter-form">
                    <input type="hidden" name="page" value="ummp-nf-submissions">
                    
                    <div class="ummp-filter-row">
                        <div class="ummp-filter-group">
                            <label for="form_id"><?php _e('Select Form:', 'ummp'); ?></label>
                            <select name="form_id" id="form_id">
                                <option value=""><?php _e('All Forms', 'ummp'); ?></option>
                                <?php foreach ($forms as $form): ?>
                                    <option value="<?php echo esc_attr($form->get_id()); ?>" <?php selected($form_id, $form->get_id()); ?>>
                                        <?php echo esc_html($form->get_setting('title')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="ummp-filter-group">
                            <label for="search"><?php _e('Search:', 'ummp'); ?></label>
                            <input type="text" name="search" id="search" value="<?php echo esc_attr($search); ?>" 
                                   placeholder="<?php _e('Search submissions...', 'ummp'); ?>">
                        </div>
                        
                        <div class="ummp-filter-group">
                            <label for="date_from"><?php _e('From:', 'ummp'); ?></label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                        </div>
                        
                        <div class="ummp-filter-group">
                            <label for="date_to"><?php _e('To:', 'ummp'); ?></label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                        </div>
                        
                        <div class="ummp-filter-group">
                            <button type="submit" class="button button-primary">
                                <?php _e('Filter', 'ummp'); ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=ummp-nf-submissions'); ?>" class="button">
                                <?php _e('Clear', 'ummp'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if ($form_id || !empty($forms)): ?>
                <?php
                // Build query conditions
                $where_conditions = array("p.post_type = 'nf_sub'", "p.post_status = 'publish'");
                $join_conditions = array();
                $query_params = array();
                
                if ($form_id) {
                    $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_form ON p.ID = pm_form.post_id";
                    $where_conditions[] = "pm_form.meta_key = '_form_id' AND pm_form.meta_value = %d";
                    $query_params[] = $form_id;
                    
                    // Get form fields safely
                    try {
                        $fields = Ninja_Forms()->form($form_id)->get_fields();
                        if (empty($fields)) {
                            $fields = $wpdb->get_results($wpdb->prepare(
                                "SELECT id, label, `key` FROM {$wpdb->prefix}nf3_fields WHERE form_id = %d ORDER BY `order`",
                                $form_id
                            ));
                        }
                    } catch (Exception $e) {
                        error_log('UMMP Ninja Forms - Error getting fields for form ' . $form_id . ': ' . $e->getMessage());
                        $fields = array();
                    }
                } else {
                    // For "All Forms" view, don't try to get specific fields
                    $fields = array();
                }
                
                // Date filters
                if ($date_from) {
                    $where_conditions[] = "p.post_date >= %s";
                    $query_params[] = $date_from . ' 00:00:00';
                }
                
                if ($date_to) {
                    $where_conditions[] = "p.post_date <= %s";
                    $query_params[] = $date_to . ' 23:59:59';
                }
                
                // Search filter
                if ($search) {
                    $join_conditions[] = "LEFT JOIN {$wpdb->postmeta} pm_search ON p.ID = pm_search.post_id";
                    $where_conditions[] = "pm_search.meta_value LIKE %s";
                    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
                }
                
                // Build and execute query safely
                try {
                    $joins = implode(' ', $join_conditions);
                    $where = implode(' AND ', $where_conditions);
                    
                    $query = "SELECT DISTINCT p.ID, p.post_date FROM {$wpdb->posts} p {$joins} WHERE {$where} ORDER BY p.post_date DESC LIMIT 100";
                    
                    if (!empty($query_params)) {
                        $submissions = $wpdb->get_results($wpdb->prepare($query, $query_params));
                    } else {
                        $submissions = $wpdb->get_results($query);
                    }
                    
                    // Check for database errors
                    if ($wpdb->last_error) {
                        error_log('UMMP Ninja Forms - Database error: ' . $wpdb->last_error);
                        echo '<div class="notice notice-error"><p>' . __('Database error occurred while fetching submissions.', 'ummp') . '</p></div>';
                        $submissions = array();
                    }
                } catch (Exception $e) {
                    error_log('UMMP Ninja Forms - Query error: ' . $e->getMessage());
                    echo '<div class="notice notice-error"><p>' . __('Error occurred while fetching submissions.', 'ummp') . '</p></div>';
                    $submissions = array();
                }
                
                if (!empty($submissions)): ?>
                    <div class="ummp-submissions-stats">
                        <p><?php printf(__('Showing %d submissions', 'ummp'), count($submissions)); ?></p>
                    </div>
                    
                    <div class="ummp-submissions-table-container">
                        <table class="wp-list-table widefat fixed striped ummp-submissions-table">
                            <thead>
                                <tr>
                                    <th class="ummp-col-date"><?php _e('Date', 'ummp'); ?></th>
                                    <?php if (!$form_id): ?>
                                        <th class="ummp-col-form"><?php _e('Form', 'ummp'); ?></th>
                                    <?php endif; ?>
                                    <?php if ($form_id && !empty($fields)): ?>
                                        <?php foreach ($fields as $field): ?>
                                            <th class="ummp-col-field">
                                                <?php echo esc_html(is_object($field) ? $field->get_setting('label') : $field->label); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <th class="ummp-col-content"><?php _e('Content Preview', 'ummp'); ?></th>
                                    <?php endif; ?>
                                    <th class="ummp-col-actions"><?php _e('Actions', 'ummp'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td class="ummp-col-date">
                                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->post_date)); ?>
                                        </td>
                                        
                                        <?php if (!$form_id): ?>
                                            <td class="ummp-col-form">
                                                <?php
                                                try {
                                                    $sub_form_id = get_post_meta($submission->ID, '_form_id', true);
                                                    if ($sub_form_id) {
                                                        $form = Ninja_Forms()->form($sub_form_id);
                                                        if ($form && method_exists($form, 'get_setting')) {
                                                            $form_title = $form->get_setting('title');
                                                            echo esc_html($form_title ?: __('Untitled Form', 'ummp'));
                                                        } else {
                                                            echo esc_html(__('Form #' . $sub_form_id, 'ummp'));
                                                        }
                                                    } else {
                                                        echo esc_html(__('Unknown Form', 'ummp'));
                                                    }
                                                } catch (Exception $e) {
                                                    echo esc_html(__('Form Error', 'ummp'));
                                                    error_log('UMMP Ninja Forms - Form title error: ' . $e->getMessage());
                                                }
                                                ?>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <?php if ($form_id && !empty($fields)): ?>
                                            <?php foreach ($fields as $field): ?>
                                                <td class="ummp-col-field">
                                                    <?php
                                                    try {
                                                        $field_id = is_object($field) ? $field->get_id() : $field->id;
                                                        $meta_key = '_field_' . $field_id;
                                                        $value = get_post_meta($submission->ID, $meta_key, true);
                                                        
                                                        if (is_array($value)) {
                                                            echo '<ul class="ummp-array-value">';
                                                            foreach ($value as $item) {
                                                                echo '<li>' . esc_html($item) . '</li>';
                                                            }
                                                            echo '</ul>';
                                                        } elseif (is_serialized($value)) {
                                                            $unserialized = maybe_unserialize($value);
                                                            if (is_array($unserialized)) {
                                                                echo '<ul class="ummp-array-value">';
                                                                foreach ($unserialized as $item) {
                                                                    echo '<li>' . esc_html($item) . '</li>';
                                                                }
                                                                echo '</ul>';
                                                            } else {
                                                                echo esc_html($unserialized ?: '-');
                                                            }
                                                        } else {
                                                            echo esc_html($value ?: '-');
                                                        }
                                                    } catch (Exception $e) {
                                                        echo '-';
                                                        error_log('UMMP Ninja Forms field error: ' . $e->getMessage());
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <td class="ummp-col-content">
                                                <?php
                                                try {
                                                    // Show a preview of submission data
                                                    $meta_data = get_post_meta($submission->ID);
                                                    $preview_items = array();
                                                    if (is_array($meta_data)) {
                                                        foreach ($meta_data as $key => $values) {
                                                            if (strpos($key, '_field_') === 0 && !empty($values[0])) {
                                                                $value = is_string($values[0]) ? $values[0] : '';
                                                                if ($value) {
                                                                    $preview_items[] = esc_html(wp_trim_words($value, 5));
                                                                    if (count($preview_items) >= 3) break;
                                                                }
                                                            }
                                                        }
                                                    }
                                                    echo implode(', ', $preview_items) ?: '-';
                                                } catch (Exception $e) {
                                                    echo '-';
                                                    error_log('UMMP Ninja Forms - Content preview error: ' . $e->getMessage());
                                                }
                                                ?>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <td class="ummp-col-actions">
                                            <button type="button" class="button button-small ummp-view-submission" 
                                                    data-submission-id="<?php echo esc_attr($submission->ID); ?>">
                                                <?php _e('View Details', 'ummp'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="ummp-no-submissions">
                        <p><?php _e('No submissions found matching your criteria.', 'ummp'); ?></p>
                        <?php if ($form_id || $search || $date_from || $date_to): ?>
                            <p>
                                <a href="<?php echo admin_url('admin.php?page=ummp-nf-submissions'); ?>" class="button">
                                    <?php _e('View All Submissions', 'ummp'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="ummp-no-forms">
                    <p><?php _e('No forms found. Please contact your administrator.', 'ummp'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Submission Details Modal -->
        <div id="ummp-submission-modal" class="ummp-modal" style="display: none;">
            <div class="ummp-modal-content">
                <div class="ummp-modal-header">
                    <h2><?php _e('Submission Details', 'ummp'); ?></h2>
                    <button class="ummp-modal-close">&times;</button>
                </div>
                <div class="ummp-modal-body">
                    <div id="submission-details-content">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                </div>
                <div class="ummp-modal-footer">
                    <button type="button" class="button" id="ummp-close-modal"><?php _e('Close', 'ummp'); ?></button>
                </div>
            </div>
        </div>
        
        <style>
        .ummp-ninja-submissions {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .ummp-submissions-filters {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .ummp-filter-form .ummp-filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: end;
        }
        
        .ummp-filter-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }
        
        .ummp-filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .ummp-filter-group input,
        .ummp-filter-group select {
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .ummp-submissions-stats {
            background: #e7f3ff;
            border-left: 4px solid #0073aa;
            padding: 12px;
            margin: 15px 0;
        }
        
        .ummp-submissions-table-container {
            overflow-x: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .ummp-submissions-table {
            margin: 0;
            border-collapse: collapse;
        }
        
        .ummp-submissions-table th,
        .ummp-submissions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            word-wrap: break-word;
        }
        
        .ummp-submissions-table th {
            background: #f9f9f9;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        
        .ummp-submissions-table tr:hover {
            background: #f5f5f5;
        }
        
        .ummp-col-date { min-width: 140px; }
        .ummp-col-form { min-width: 120px; }
        .ummp-col-field { min-width: 120px; max-width: 300px; }
        .ummp-col-content { min-width: 200px; max-width: 400px; }
        .ummp-col-actions { min-width: 100px; }
        
        .ummp-array-value {
            margin: 0;
            padding-left: 20px;
        }
        
        .ummp-array-value li {
            font-size: 13px;
            margin: 2px 0;
        }
        
        .ummp-no-submissions,
        .ummp-no-forms {
            text-align: center;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        /* Modal Styles */
        .ummp-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ummp-modal-content {
            background: white;
            border-radius: 8px;
            max-width: 90vw;
            max-height: 90vh;
            width: 800px;
            display: flex;
            flex-direction: column;
        }
        
        .ummp-modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ummp-modal-header h2 {
            margin: 0;
        }
        
        .ummp-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ummp-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        
        .ummp-modal-footer {
            padding: 20px;
            border-top: 1px solid #eee;
            text-align: right;
        }
        
        /* Responsive */
        @media screen and (max-width: 768px) {
            .ummp-filter-form .ummp-filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .ummp-filter-group {
                min-width: auto;
            }
            
            .ummp-submissions-table th,
            .ummp-submissions-table td {
                font-size: 13px;
                padding: 8px;
            }
            
            .ummp-modal-content {
                width: 95vw;
                margin: 10px;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Logout page
     */
    public function logout_page() {
        wp_logout();
        wp_redirect(home_url());
        exit;
    }
    
    /**
     * Redirect users away from non-submissions pages
     */
    public function redirect_non_submissions() {
        if (!$this->is_viewer() || wp_doing_ajax()) {
            return;
        }
        
        global $pagenow;
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        
        // Allowed pages for viewers
        $allowed_pages = array('ummp-nf-submissions', 'ummp-logout');
        $allowed_files = array('profile.php', 'user-edit.php');
        
        // Redirect from dashboard or admin.php without allowed page
        if ($pagenow === 'index.php' || ($pagenow === 'admin.php' && empty($page))) {
            wp_redirect(admin_url('admin.php?page=ummp-nf-submissions'));
            exit;
        }
        
        // Check if trying to access non-allowed page
        if (!empty($page) && !in_array($page, $allowed_pages)) {
            wp_redirect(admin_url('admin.php?page=ummp-nf-submissions'));
            exit;
        }
        
        // Block access to specific admin files
        $blocked_files = array(
            'edit.php', 'post-new.php', 'post.php',
            'upload.php', 'media-new.php',
            'themes.php', 'theme-editor.php',
            'plugins.php', 'plugin-editor.php',
            'users.php', 'user-new.php',
            'tools.php', 'options-general.php',
            'edit-comments.php'
        );
        
        if (in_array($pagenow, $blocked_files)) {
            wp_redirect(admin_url('admin.php?page=ummp-nf-submissions'));
            exit;
        }
    }
    
    /**
     * Login redirect to submissions page
     */
    public function login_redirect($redirect_to, $request, $user) {
        if (!is_wp_error($user) && in_array($this->role_name, (array) $user->roles)) {
            return admin_url('admin.php?page=ummp-nf-submissions');
        }
        return $redirect_to;
    }
    
    /**
     * Clean admin bar for viewers
     */
    public function clean_admin_bar() {
        if (!$this->is_viewer()) {
            return;
        }
        
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('wp-logo');
        $wp_admin_bar->remove_menu('new-content');
        $wp_admin_bar->remove_menu('comments');
        $wp_admin_bar->remove_menu('customize');
        $wp_admin_bar->remove_menu('updates');
    }
    
    /**
     * Hide admin elements with CSS
     */
    public function hide_elements() {
        if (!$this->is_viewer()) {
            return;
        }
        ?>
        <style>
        /* Hide unnecessary admin elements for forms viewers */
        .welcome-panel, 
        .page-title-action, 
        #screen-meta-links,
        .notice:not(.ummp-notice), 
        .updated:not(.ummp-updated), 
        .error:not(.ummp-error), 
        .update-nag,
        #wp-admin-bar-wp-logo, 
        #wp-admin-bar-new-content,
        #wp-admin-bar-comments, 
        #wp-admin-bar-customize,
        #wp-admin-bar-updates,
        #footer-thankyou, 
        #footer-upgrade {
            display: none !important;
        }
        
        /* Hide bulk actions and row actions */
        .bulk-actions, 
        .row-actions {
            display: none !important;
        }
        
        /* Clean up admin styling */
        #adminmenu .wp-submenu {
            min-width: 190px;
        }
        
        /* Ensure our notices show */
        .ummp-notice,
        .ummp-updated,
        .ummp-error {
            display: block !important;
        }
        </style>
        <?php
    }
    
    /**
     * Create viewer user
     *
     * @param string $username Username
     * @param string $email Email
     * @param string $password Password (optional)
     * @return array|WP_Error User data or error
     */
    public function create_viewer_user($username, $email, $password = '') {
        if (empty($password)) {
            $password = wp_generate_password(12, true);
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $user = new WP_User($user_id);
        $user->set_role($this->role_name);
        
        // Add user meta to track Ninja Forms access
        update_user_meta($user_id, 'ummp_ninja_forms_access', true);
        update_user_meta($user_id, 'ummp_ninja_forms_access_date', current_time('mysql'));
        
        // Ensure user has the view_nf_submissions capability
        $user->add_cap('view_nf_submissions');
        
        // Log the activity
        $this->log_activity('ninja_viewer_created', array(
            'user_id' => $user_id,
            'username' => $username,
            'email' => $email
        ));
        
        return array(
            'user_id' => $user_id,
            'username' => $username,
            'password' => $password,
            'email' => $email
        );
    }
    
    /**
     * Assign existing user to viewer role
     *
     * @param int $user_id User ID
     * @return bool|WP_Error Success or error
     */
    public function assign_viewer_role($user_id) {
        $user = new WP_User($user_id);
        
        if (!$user->exists()) {
            return new WP_Error('user_not_found', __('User not found.', 'ummp'));
        }
        
        $user->set_role($this->role_name);
        
        // Add user meta to track Ninja Forms access
        update_user_meta($user_id, 'ummp_ninja_forms_access', true);
        update_user_meta($user_id, 'ummp_ninja_forms_access_date', current_time('mysql'));
        
        // Ensure user has the view_nf_submissions capability
        $user->add_cap('view_nf_submissions');
        
        // Log the activity
        $this->log_activity('ninja_viewer_assigned', array(
            'user_id' => $user_id,
            'username' => $user->user_login
        ));
        
        return true;
    }
    
    /**
     * Remove viewer role from user
     *
     * @param int $user_id User ID
     * @return bool|WP_Error Success or error
     */
    public function remove_viewer_role($user_id) {
        $user = new WP_User($user_id);
        
        if (!$user->exists()) {
            return new WP_Error('user_not_found', __('User not found.', 'ummp'));
        }
        
        // Set to subscriber role instead of removing all roles
        $user->set_role('subscriber');
        
        // Remove Ninja Forms access tracking
        delete_user_meta($user_id, 'ummp_ninja_forms_access');
        delete_user_meta($user_id, 'ummp_ninja_forms_access_date');
        
        // Remove the view_nf_submissions capability
        $user->remove_cap('view_nf_submissions');
        
        // Log the activity
        $this->log_activity('ninja_viewer_removed', array(
            'user_id' => $user_id,
            'username' => $user->user_login
        ));
        
        return true;
    }
    
    /**
     * Get all ninja forms viewers
     *
     * @return array List of users with viewer role or Ninja Forms access
     */
    public function get_viewers() {
        // Get users with the viewer role
        $role_users = get_users(array(
            'role' => $this->role_name,
            'orderby' => 'registered',
            'order' => 'DESC'
        ));
        
        // Get users with Ninja Forms access meta (this captures users whose role was changed)
        $meta_users = get_users(array(
            'meta_key' => 'ummp_ninja_forms_access',
            'meta_value' => true,
            'orderby' => 'registered',
            'order' => 'DESC'
        ));
        
        // Get users with view_nf_submissions capability (backup check)
        $cap_users = get_users(array(
            'orderby' => 'registered',
            'order' => 'DESC'
        ));
        
        // Filter users with the capability
        $cap_users = array_filter($cap_users, function($user) {
            return user_can($user->ID, 'view_nf_submissions') && !user_can($user->ID, 'manage_options');
        });
        
        // Merge all users and remove duplicates
        $all_users = array_merge($role_users, $meta_users, $cap_users);
        $unique_users = array();
        $user_ids = array();
        
        foreach ($all_users as $user) {
            if (!in_array($user->ID, $user_ids)) {
                $unique_users[] = $user;
                $user_ids[] = $user->ID;
            }
        }
        
        return $unique_users;
    }
    
    /**
     * Render admin interface for managing viewers
     */
    public function render_admin_interface() {
        $viewers = $this->get_viewers();
        ?>
        <div class="ummp-ninja-admin">
            <!-- Create New Viewer -->
            <div class="ummp-card">
                <h3><?php _e('Create New Forms Viewer', 'ummp'); ?></h3>
                <form id="ummp-create-ninja-viewer-form" class="ummp-form">
                    <div class="ummp-form-row">
                        <label for="ninja_username"><?php _e('Username', 'ummp'); ?></label>
                        <input type="text" id="ninja_username" name="username" required 
                               placeholder="<?php _e('Enter username', 'ummp'); ?>">
                    </div>
                    <div class="ummp-form-row">
                        <label for="ninja_email"><?php _e('Email', 'ummp'); ?></label>
                        <input type="email" id="ninja_email" name="email" required 
                               placeholder="<?php _e('Enter email address', 'ummp'); ?>">
                    </div>
                    <div class="ummp-form-row">
                        <label for="ninja_password"><?php _e('Password', 'ummp'); ?></label>
                        <input type="password" id="ninja_password" name="password" 
                               placeholder="<?php _e('Leave empty for auto-generated password', 'ummp'); ?>">
                    </div>
                    <button type="submit" class="button button-primary">
                        <?php _e('Create Forms Viewer', 'ummp'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Assign Existing User -->
            <div class="ummp-card">
                <h3><?php _e('Convert Existing User', 'ummp'); ?></h3>
                <form id="ummp-assign-ninja-viewer-form" class="ummp-form">
                    <div class="ummp-form-row">
                        <label for="existing_user"><?php _e('Select User', 'ummp'); ?></label>
                        <select id="existing_user" name="user_id" required>
                            <option value=""><?php _e('Choose user...', 'ummp'); ?></option>
                            <?php
                            $users = get_users(array('role__not_in' => array($this->role_name)));
                            foreach ($users as $user) {
                                echo '<option value="' . esc_attr($user->ID) . '">';
                                echo esc_html($user->display_name . ' (' . $user->user_login . ')');
                                echo '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="button button-secondary">
                        <?php _e('Convert to Forms Viewer', 'ummp'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Current Viewers -->
            <div class="ummp-card ummp-card-full">
                <h3><?php _e('Current Forms Viewers', 'ummp'); ?></h3>
                <?php if (!empty($viewers)): ?>
                    <div class="ummp-viewers-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Avatar', 'ummp'); ?></th>
                                    <th><?php _e('Username', 'ummp'); ?></th>
                                    <th><?php _e('Display Name', 'ummp'); ?></th>
                                    <th><?php _e('Email', 'ummp'); ?></th>
                                    <th><?php _e('Registered', 'ummp'); ?></th>
                                    <th><?php _e('Actions', 'ummp'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($viewers as $viewer): ?>
                                    <tr>
                                        <td><?php echo get_avatar($viewer->ID, 32); ?></td>
                                        <td><strong><?php echo esc_html($viewer->user_login); ?></strong></td>
                                        <td><?php echo esc_html($viewer->display_name); ?></td>
                                        <td><?php echo esc_html($viewer->user_email); ?></td>
                                        <td><?php echo date_i18n(get_option('date_format'), strtotime($viewer->user_registered)); ?></td>
                                        <td>
                                            <a href="<?php echo get_edit_user_link($viewer->ID); ?>" class="button button-small">
                                                <?php _e('Edit', 'ummp'); ?>
                                            </a>
                                            <button type="button" class="button button-small button-link-delete ummp-remove-viewer" 
                                                    data-user-id="<?php echo esc_attr($viewer->ID); ?>">
                                                <?php _e('Remove Access', 'ummp'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p><?php _e('No forms viewers found.', 'ummp'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Instructions -->
            <div class="ummp-card">
                <h3><?php _e('How It Works', 'ummp'); ?></h3>
                <ul>
                    <li><?php _e('Forms viewers can only access the Form Submissions page and their profile.', 'ummp'); ?></li>
                    <li><?php _e('All other admin menus and pages are hidden or blocked.', 'ummp'); ?></li>
                    <li><?php _e('Viewers are automatically redirected to submissions page after login.', 'ummp'); ?></li>
                    <li><?php _e('They can view and search through form submissions but cannot edit or delete them.', 'ummp'); ?></li>
                    <li><?php _e('The interface is clean and minimal, showing only relevant information.', 'ummp'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Log activity
     */
    private function log_activity($action, $details = array()) {
        $logs = get_option('ummp_activity_logs', array());
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'action' => sanitize_text_field($action),
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        );
        
        array_unshift($logs, $log_entry);
        
        // Keep only last 1000 entries
        $logs = array_slice($logs, 0, 1000);
        
        update_option('ummp_activity_logs', $logs);
    }
    
    /**
     * AJAX: Create new ninja forms viewer
     */
    public function ajax_create_viewer() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);
        
        $result = $this->create_viewer_user($username, $email, $password);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Forms viewer created successfully.', 'ummp'),
                'user_data' => $result
            ));
        }
    }
    
    /**
     * AJAX: Assign ninja forms viewer role
     */
    public function ajax_assign_viewer() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $user_id = intval($_POST['user_id']);
        $result = $this->assign_viewer_role($user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('User converted to forms viewer successfully.', 'ummp'));
        }
    }
    
    /**
     * AJAX: Remove ninja forms viewer role
     */
    public function ajax_remove_viewer() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $user_id = intval($_POST['user_id']);
        $result = $this->remove_viewer_role($user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Forms viewer access removed successfully.', 'ummp'));
        }
    }
    
    /**
     * AJAX: Get ninja forms viewers
     */
    public function ajax_get_viewers() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $viewers = $this->get_viewers();
        wp_send_json_success($viewers);
    }
    
    /**
     * AJAX handler for getting submission details
     */
    public function ajax_get_submission_details() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        // Check if user has permission to view submissions
        if (!current_user_can('view_nf_submissions') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions to view submission details.', 'ummp'));
        }
        
        $submission_id = intval($_POST['submission_id']);
        
        if (!$submission_id) {
            wp_send_json_error(__('Invalid submission ID.', 'ummp'));
        }
        
        global $wpdb;
        
        try {
            // Get submission details
            $submission = get_post($submission_id);
            
            if (!$submission || $submission->post_type !== 'nf_sub') {
                wp_send_json_error(__('Submission not found.', 'ummp'));
            }
            
            // Get form ID
            $form_id = get_post_meta($submission_id, '_form_id', true);
            
            if (!$form_id) {
                wp_send_json_error(__('Form ID not found for this submission.', 'ummp'));
            }
            
            // Get form title
            $form_title = 'Unknown Form';
            try {
                $form = Ninja_Forms()->form($form_id);
                if ($form && method_exists($form, 'get_setting')) {
                    $form_title = $form->get_setting('title') ?: ('Form #' . $form_id);
                }
            } catch (Exception $e) {
                $form_title = 'Form #' . $form_id;
                error_log('UMMP: Error getting form title: ' . $e->getMessage());
            }
            
            // Get form fields
            $fields = array();
            try {
                $fields = Ninja_Forms()->form($form_id)->get_fields();
                if (empty($fields)) {
                    $fields = $wpdb->get_results($wpdb->prepare(
                        "SELECT id, label, `key`, type FROM {$wpdb->prefix}nf3_fields WHERE form_id = %d ORDER BY `order`",
                        $form_id
                    ));
                }
            } catch (Exception $e) {
                error_log('UMMP: Error getting form fields: ' . $e->getMessage());
            }
            
            // Build the details HTML
            ob_start();
            ?>
            <div class="ummp-submission-details">
                <div class="ummp-submission-header">
                    <h3><?php echo esc_html($form_title); ?></h3>
                    <p class="ummp-submission-meta">
                        <strong><?php _e('Submitted:', 'ummp'); ?></strong> 
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->post_date)); ?>
                    </p>
                    <p class="ummp-submission-meta">
                        <strong><?php _e('Submission ID:', 'ummp'); ?></strong> 
                        <?php echo esc_html($submission_id); ?>
                    </p>
                </div>
                
                <div class="ummp-submission-fields">
                    <?php if (!empty($fields)): ?>
                        <table class="ummp-details-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Field', 'ummp'); ?></th>
                                    <th><?php _e('Value', 'ummp'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fields as $field): ?>
                                    <?php
                                    try {
                                        $field_id = is_object($field) ? $field->get_id() : $field->id;
                                        $field_label = is_object($field) ? $field->get_setting('label') : $field->label;
                                        $field_key = is_object($field) ? $field->get_setting('key') : $field->key;
                                        
                                        if (empty($field_label)) {
                                            $field_label = $field_key ?: __('Untitled Field', 'ummp');
                                        }
                                        
                                        $meta_key = '_field_' . $field_id;
                                        $value = get_post_meta($submission_id, $meta_key, true);
                                        
                                        if (is_array($value)) {
                                            $value = implode(', ', $value);
                                        } elseif (is_serialized($value)) {
                                            $unserialized = maybe_unserialize($value);
                                            if (is_array($unserialized)) {
                                                $value = implode(', ', $unserialized);
                                            } else {
                                                $value = $unserialized;
                                            }
                                        }
                                        
                                        $value = $value ?: '-';
                                        ?>
                                        <tr>
                                            <td class="ummp-field-label">
                                                <strong><?php echo esc_html($field_label); ?></strong>
                                            </td>
                                            <td class="ummp-field-value">
                                                <?php echo esc_html($value); ?>
                                            </td>
                                        </tr>
                                    <?php } catch (Exception $e) { ?>
                                        <tr>
                                            <td colspan="2">
                                                <em><?php _e('Error loading field data', 'ummp'); ?></em>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No field data available for this submission.', 'ummp'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="ummp-submission-raw" style="margin-top: 20px;">
                    <details>
                        <summary><?php _e('Raw Submission Data', 'ummp'); ?></summary>
                        <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-top: 10px; overflow-x: auto;"><?php
                            $all_meta = get_post_meta($submission_id);
                            foreach ($all_meta as $key => $values) {
                                if (strpos($key, '_field_') === 0) {
                                    echo esc_html($key) . ': ' . esc_html(print_r($values, true)) . "\n";
                                }
                            }
                        ?></pre>
                    </details>
                </div>
            </div>
            <?php
            $html = ob_get_clean();
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            error_log('UMMP: Error in ajax_get_submission_details: ' . $e->getMessage());
            wp_send_json_error(__('An error occurred while loading submission details.', 'ummp'));
        }
    }
    
    /**
     * Enqueue scripts for Ninja Forms pages
     */
    public function enqueue_ninja_scripts($hook) {
        // Only load on our custom submissions page
        if ($hook !== 'toplevel_page_ummp-nf-submissions') {
            return;
        }
        
        // Enqueue main admin styles and scripts
        wp_enqueue_style(
            'ummp-admin-styles',
            plugin_dir_url(dirname(__FILE__)) . 'assets/admin.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'ummp-admin-scripts',
            plugin_dir_url(dirname(__FILE__)) . 'assets/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('ummp-admin-scripts', 'ummp_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ummp_ajax_nonce'),
            'strings' => array(
                'error' => __('An error occurred.', 'ummp'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'ummp')
            )
        ));
    }
}
