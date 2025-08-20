<?php
/**
 * UMMP Admin Class
 * 
 * Handles admin UI and menu rendering functionality
 *
 * @package UsersMenuManagerPro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UMMP Admin class
 */
class UMMP_Admin {
    
    /**
     * Roles instance
     *
     * @var UMMP_Roles
     */
    private $roles;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->roles = new UMMP_Roles();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_ummp_get_menu_structure', array($this, 'ajax_get_menu_structure'));
        add_action('wp_ajax_ummp_save_menu_restrictions', array($this, 'ajax_save_menu_restrictions'));
        add_action('wp_ajax_ummp_reset_menu_restrictions', array($this, 'ajax_reset_menu_restrictions'));
        add_action('wp_ajax_ummp_search_users', array($this, 'ajax_search_users'));
        add_action('wp_ajax_ummp_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_ummp_export_logs', array($this, 'ajax_export_logs'));
        add_filter('custom_menu_order', array($this, 'custom_menu_order'));
        add_action('admin_menu', array($this, 'restrict_menus'), 999);
        add_action('admin_menu', array($this, 'ensure_form_menu_access'), 1000);
    }
    
    /**
     * Ensure form plugin menus are accessible to users with form capabilities
     */
    public function ensure_form_menu_access() {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return;
        }
        
        // Check Ninja Forms access
        if (user_can($user->ID, 'view_nf_submissions') || user_can($user->ID, 'nf_sub')) {
            $this->ensure_ninja_forms_menu_access();
        }
        

        
        // Check Gravity Forms access
        if (user_can($user->ID, 'gravityforms_view_entries')) {
            $this->ensure_gravity_forms_menu_access();
        }
    }
    
    /**
     * Ensure Ninja Forms menu access
     */
    private function ensure_ninja_forms_menu_access() {
        // Check if Ninja Forms menu exists and is accessible
        global $menu, $submenu;
        
        // Look for Ninja Forms menu
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && strpos($menu_item[2], 'ninja-forms') !== false) {
                // Ensure user can access this menu
                if (!current_user_can('manage_options')) {
                    // Add custom capability check
                    add_filter('user_can', function($allcaps, $caps, $args, $user) {
                        if (in_array('manage_ninja_forms', $caps)) {
                            $allcaps['manage_ninja_forms'] = user_can($user->ID, 'view_nf_submissions');
                        }
                        return $allcaps;
                    }, 10, 4);
                }
                break;
            }
        }
    }
    

    
    /**
     * Ensure Gravity Forms menu access
     */
    private function ensure_gravity_forms_menu_access() {
        // Check if Gravity Forms menu exists and is accessible
        global $menu, $submenu;
        
        // Look for Gravity Forms menu
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && strpos($menu_item[2], 'gravityforms') !== false) {
                // Ensure user can access this menu
                if (!current_user_can('manage_options')) {
                    // Add custom capability check
                    add_filter('user_can', function($allcaps, $caps, $args, $user) {
                        if (in_array('gravityforms_view_entries', $caps)) {
                            $allcaps['gravityforms_view_entries'] = true;
                        }
                        return $allcaps;
                    }, 10, 4);
                }
                break;
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Users Menu Manager Pro', 'ummp'),
            __('Menu Manager Pro', 'ummp'),
            'manage_options',
            'ummp-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-admin-users',
            25
        );
        
        // Roles Manager submenu
        add_submenu_page(
            'ummp-dashboard',
            __('Roles Manager', 'ummp'),
            __('Roles Manager', 'ummp'),
            'manage_options',
            'ummp-roles',
            array($this, 'render_roles_page')
        );
        

        
        // Menu Manager submenu
        add_submenu_page(
            'ummp-dashboard',
            __('Menu Manager', 'ummp'),
            __('Menu Manager', 'ummp'),
            'manage_options',
            'ummp-menus',
            array($this, 'render_menus_page')
        );
        
        // Ninja Forms Access submenu
        add_submenu_page(
            'ummp-dashboard',
            __('Ninja Forms Access', 'ummp'),
            __('Ninja Forms Access', 'ummp'),
            'manage_options',
            'ummp-ninja-forms',
            array($this, 'render_ninja_forms_page')
        );
        

        
        // Activity Logs submenu
        add_submenu_page(
            'ummp-dashboard',
            __('Activity Logs', 'ummp'),
            __('Activity Logs', 'ummp'),
            'ummp_view_logs',
            'ummp-logs',
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('ummp_settings', 'ummp_menu_restrictions');
        register_setting('ummp_settings', 'ummp_post_type_restrictions');
        register_setting('ummp_settings', 'ummp_general_settings');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on UMMP admin pages
        if (strpos($hook, 'ummp') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'ummp-admin-css',
            plugin_dir_url(dirname(__FILE__)) . 'assets/admin.css',
            array(),
            UMMP_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'ummp-admin-js',
            plugin_dir_url(dirname(__FILE__)) . 'assets/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            UMMP_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('ummp-admin-js', 'ummp_ajax', array(
            'nonce' => wp_create_nonce('ummp_ajax_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        ?>
        <div class="wrap ummp-admin">
            <h1><?php _e('Users Menu Manager Pro', 'ummp'); ?></h1>
            
            <div class="ummp-dashboard-grid">
                <!-- Quick Stats -->
                <div class="ummp-card ummp-card-stats">
                    <h2><span class="dashicons dashicons-chart-bar"></span> <?php _e('Quick Stats', 'ummp'); ?></h2>
                    <div class="ummp-stats">
                        <?php
                        global $wp_roles;
                        $user_count = count_users();
                        $role_count = count($wp_roles->roles);
                        $activity_logs = get_option('ummp_activity_logs', array());
                        
                        // Get Ninja Forms viewers count
                        $ninja_viewers = 0;
                        if (class_exists('UMMP_Ninja_Viewer')) {
                            $ninja_viewer = new UMMP_Ninja_Viewer();
                            $ninja_viewers = count($ninja_viewer->get_viewers());
                        }
                        ?>
                        <div class="ummp-stat">
                            <div class="ummp-stat-number"><?php echo $role_count; ?></div>
                            <div class="ummp-stat-label"><?php _e('Total Roles', 'ummp'); ?></div>
                        </div>
                        <div class="ummp-stat">
                            <div class="ummp-stat-number"><?php echo $user_count['total_users']; ?></div>
                            <div class="ummp-stat-label"><?php _e('Total Users', 'ummp'); ?></div>
                        </div>
                        <div class="ummp-stat">
                            <div class="ummp-stat-number"><?php echo $ninja_viewers; ?></div>
                            <div class="ummp-stat-label"><?php _e('Forms Viewers', 'ummp'); ?></div>
                        </div>
                        <div class="ummp-stat">
                            <div class="ummp-stat-number"><?php echo count($activity_logs); ?></div>
                            <div class="ummp-stat-label"><?php _e('Activity Logs', 'ummp'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="ummp-card ummp-card-actions">
                    <h2><span class="dashicons dashicons-admin-tools"></span> <?php _e('Quick Actions', 'ummp'); ?></h2>
                    <div class="ummp-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=ummp-roles'); ?>" class="ummp-quick-action">
                            <span class="dashicons dashicons-admin-users"></span>
                            <?php _e('Manage Roles', 'ummp'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=ummp-menus'); ?>" class="ummp-quick-action">
                            <span class="dashicons dashicons-menu"></span>
                            <?php _e('Menu Manager', 'ummp'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=ummp-ninja-forms'); ?>" class="ummp-quick-action">
                            <span class="dashicons dashicons-forms"></span>
                            <?php _e('Forms Access', 'ummp'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=ummp-logs'); ?>" class="ummp-quick-action">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php _e('Activity Logs', 'ummp'); ?>
                        </a>
                    </div>
                </div>
                
                <!-- User Management -->
                <div class="ummp-card ummp-card-users">
                    <h2><span class="dashicons dashicons-groups"></span> <?php _e('User Management', 'ummp'); ?></h2>
                    <div class="ummp-user-management">
                        <?php
                        // Get recent user registrations
                        $recent_users = get_users(array(
                            'number' => 5,
                            'orderby' => 'registered',
                            'order' => 'DESC'
                        ));
                        
                        // Get users by role
                        $admin_users = get_users(array('role' => 'administrator', 'number' => -1));
                        $editor_users = get_users(array('role' => 'editor', 'number' => -1));
                        ?>
                        <div class="ummp-user-stats">
                            <div class="ummp-user-stat">
                                <span class="ummp-user-count"><?php echo count($admin_users); ?></span>
                                <span class="ummp-user-role"><?php _e('Administrators', 'ummp'); ?></span>
                            </div>
                            <div class="ummp-user-stat">
                                <span class="ummp-user-count"><?php echo count($editor_users); ?></span>
                                <span class="ummp-user-role"><?php _e('Editors', 'ummp'); ?></span>
                            </div>
                        </div>
                        <div class="ummp-recent-users">
                            <h4><?php _e('Recent Users', 'ummp'); ?></h4>
                            <?php if (!empty($recent_users)): ?>
                                <div class="ummp-user-list">
                                    <?php foreach ($recent_users as $user): ?>
                                        <div class="ummp-user-item">
                                            <span class="ummp-user-avatar"><?php echo get_avatar($user->ID, 24); ?></span>
                                            <span class="ummp-user-name"><?php echo esc_html($user->display_name); ?></span>
                                            <span class="ummp-user-date"><?php echo human_time_diff(strtotime($user->user_registered), current_time('timestamp')); ?> <?php _e('ago', 'ummp'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="ummp-no-users"><?php _e('No recent users.', 'ummp'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity - Full Width at Bottom -->
            <div class="ummp-card ummp-card-activity ummp-card-full">
                <h2><span class="dashicons dashicons-update"></span> <?php _e('Recent Activity', 'ummp'); ?></h2>
                <div class="ummp-activity-list">
                    <?php
                    $recent_logs = array_slice($activity_logs, 0, 8);
                    if (!empty($recent_logs)) {
                        foreach ($recent_logs as $log) {
                            $user = get_user_by('ID', $log['user_id']);
                            $username = $user ? $user->display_name : __('Unknown User', 'ummp');
                            ?>
                            <div class="ummp-activity-item">
                                <div class="ummp-activity-user"><?php echo esc_html($username); ?></div>
                                <div class="ummp-activity-action"><?php echo esc_html($log['action']); ?></div>
                                <div class="ummp-activity-time"><?php echo human_time_diff(strtotime($log['timestamp']), current_time('timestamp')); ?> <?php _e('ago', 'ummp'); ?></div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<p class="ummp-no-activity">' . __('No recent activity.', 'ummp') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render roles management page
     */
    public function render_roles_page() {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        $capabilities = $this->roles->get_all_capabilities();
        ?>
        <div class="wrap ummp-admin">
            <h1><?php _e('Roles Manager', 'ummp'); ?></h1>
            
            <div class="ummp-roles-container">
                <!-- Create New Role -->
                <div class="ummp-card">
                    <h2><?php _e('Create New Role', 'ummp'); ?></h2>
                    <form id="ummp-create-role-form" class="ummp-form">
                        <div class="ummp-form-row">
                            <label for="role_name"><?php _e('Role Name (slug)', 'ummp'); ?></label>
                            <input type="text" id="role_name" name="role_name" required 
                                   placeholder="<?php _e('e.g., custom_editor', 'ummp'); ?>">
                        </div>
                        <div class="ummp-form-row">
                            <label for="display_name"><?php _e('Display Name', 'ummp'); ?></label>
                            <input type="text" id="display_name" name="display_name" required 
                                   placeholder="<?php _e('e.g., Custom Editor', 'ummp'); ?>">
                        </div>
                        <div class="ummp-form-row">
                            <label><?php _e('Clone from existing role (optional)', 'ummp'); ?></label>
                            <select id="clone_from" name="clone_from">
                                <option value=""><?php _e('Start from scratch', 'ummp'); ?></option>
                                <?php foreach ($wp_roles->roles as $role_key => $role_data): ?>
                                    <option value="<?php echo esc_attr($role_key); ?>">
                                        <?php echo esc_html($role_data['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="button button-primary">
                            <?php _e('Create Role', 'ummp'); ?>
                        </button>
                    </form>
                </div>
                
                <!-- Capabilities Selection -->
                <div class="ummp-card ummp-capabilities-card">
                    <h2><?php _e('Capabilities', 'ummp'); ?></h2>
                    <div class="ummp-capabilities-search">
                        <input type="text" id="capabilities-search" placeholder="<?php _e('Search capabilities...', 'ummp'); ?>">
                    </div>
                    <div class="ummp-capabilities-groups">
                        <?php foreach ($capabilities as $group_key => $group_data): ?>
                            <div class="ummp-capability-group" data-group="<?php echo esc_attr($group_key); ?>">
                                <h3 class="ummp-group-title">
                                    <label>
                                        <input type="checkbox" class="ummp-group-toggle" data-group="<?php echo esc_attr($group_key); ?>">
                                        <?php echo esc_html($group_data['label']); ?>
                                    </label>
                                </h3>
                                <div class="ummp-capabilities-list">
                                    <?php foreach ($group_data['capabilities'] as $cap): ?>
                                        <label class="ummp-capability-item">
                                            <input type="checkbox" name="capabilities[]" value="<?php echo esc_attr($cap); ?>" 
                                                   data-group="<?php echo esc_attr($group_key); ?>">
                                            <span class="ummp-capability-name"><?php echo esc_html($this->roles->get_capability_display_name($cap)); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Existing Roles -->
                <div class="ummp-card ummp-card-full">
                    <h2><?php _e('Existing Roles', 'ummp'); ?></h2>
                    
                    <!-- Fix Existing Roles Button -->
                    <div class="ummp-actions-bar" style="margin-bottom: 15px;">
                        <button type="button" id="fix-existing-roles" class="button button-secondary">
                            <?php _e('Fix Existing Roles', 'ummp'); ?>
                        </button>
                        <span class="description" style="margin-left: 10px;">
                            <?php _e('Automatically add missing dependent capabilities to existing roles', 'ummp'); ?>
                        </span>
                    </div>
                    
                    <div class="ummp-roles-table-container">
                        <table class="wp-list-table widefat fixed striped ummp-roles-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Role Name', 'ummp'); ?></th>
                                    <th><?php _e('Display Name', 'ummp'); ?></th>
                                    <th><?php _e('Capabilities', 'ummp'); ?></th>
                                    <th><?php _e('Users', 'ummp'); ?></th>
                                    <th><?php _e('Actions', 'ummp'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wp_roles->roles as $role_key => $role_data): ?>
                                    <?php
                                    $users_in_role = count(get_users(array('role' => $role_key)));
                                    $capability_count = count(array_filter($role_data['capabilities']));
                                    ?>
                                    <tr data-role="<?php echo esc_attr($role_key); ?>">
                                        <td><code><?php echo esc_html($role_key); ?></code></td>
                                        <td><strong><?php echo esc_html($role_data['name']); ?></strong></td>
                                        <td><?php echo $capability_count; ?> <?php _e('capabilities', 'ummp'); ?></td>
                                        <td><?php echo $users_in_role; ?> <?php _e('users', 'ummp'); ?></td>
                                        <td class="ummp-actions">
                                            <button class="button button-small ummp-edit-role" 
                                                    data-role="<?php echo esc_attr($role_key); ?>">
                                                <?php _e('Edit', 'ummp'); ?>
                                            </button>
                                            <?php if (!in_array($role_key, array('administrator', 'editor', 'author', 'contributor', 'subscriber'))): ?>
                                                <button class="button button-small button-link-delete ummp-delete-role" 
                                                        data-role="<?php echo esc_attr($role_key); ?>">
                                                    <?php _e('Delete', 'ummp'); ?>
                                                </button>
                                                <button class="button button-small button-secondary ummp-refresh-capabilities" 
                                                        data-role="<?php echo esc_attr($role_key); ?>"
                                                        title="<?php _e('Force refresh capabilities for all users with this role', 'ummp'); ?>">
                                                    <?php _e('Refresh', 'ummp'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Role Modal -->
        <div id="ummp-edit-role-modal" class="ummp-modal" style="display: none;">
            <div class="ummp-modal-content">
                <div class="ummp-modal-header">
                    <h2><?php _e('Edit Role', 'ummp'); ?></h2>
                    <button class="ummp-modal-close">&times;</button>
                </div>
                <div class="ummp-modal-body">
                    <form id="ummp-edit-role-form">
                        <input type="hidden" id="edit_role_name" name="role_name">
                        <div class="ummp-form-row">
                            <label for="edit_display_name"><?php _e('Display Name', 'ummp'); ?></label>
                            <input type="text" id="edit_display_name" name="display_name" required>
                        </div>
                        <div class="ummp-form-row">
                            <label><?php _e('Capabilities', 'ummp'); ?></label>
                            <div id="edit-capabilities-container">
                                <!-- Capabilities will be loaded here -->
                            </div>
                        </div>
                    </form>
                </div>
                <div class="ummp-modal-footer">
                    <button type="button" class="button" id="ummp-cancel-edit"><?php _e('Cancel', 'ummp'); ?></button>
                    <button type="button" class="button button-primary" id="ummp-save-role"><?php _e('Save Changes', 'ummp'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    

    
    /**
     * Render menu manager page
     */
    public function render_menus_page() {
        global $wp_roles, $menu, $submenu;
        ?>
        <div class="wrap ummp-admin">
            <h1><?php _e('Menu Manager', 'ummp'); ?></h1>
            
            <div class="ummp-menus-container">
                <div class="ummp-card">
                    <h2><?php _e('Role-based Menu Restrictions', 'ummp'); ?></h2>
                    <p><?php _e('Drag and drop menu items to configure which menus are visible for each role.', 'ummp'); ?></p>
                    
                    <div class="ummp-menu-manager">
                        <div class="ummp-role-selector">
                            <label for="selected_role"><?php _e('Configure menus for role:', 'ummp'); ?></label>
                            <select id="selected_role" name="selected_role">
                                <option value=""><?php _e('Select a role...', 'ummp'); ?></option>
                                <?php foreach ($wp_roles->roles as $role_key => $role_data): ?>
                                    <option value="<?php echo esc_attr($role_key); ?>">
                                        <?php echo esc_html($role_data['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="menu_configuration" class="ummp-menu-configuration" style="display: none;">
                            <div class="ummp-menu-columns">
                                <div class="ummp-menu-column">
                                    <h3><?php _e('Available Menus', 'ummp'); ?></h3>
                                    <div id="available_menus" class="ummp-menu-list ummp-sortable">
                                        <!-- Menu items will be loaded here -->
                                    </div>
                                </div>
                                <div class="ummp-menu-column">
                                    <h3><?php _e('Hidden Menus', 'ummp'); ?></h3>
                                    <div id="hidden_menus" class="ummp-menu-list ummp-sortable">
                                        <!-- Hidden menu items will be loaded here -->
                                    </div>
                                </div>
                            </div>
                            <div class="ummp-menu-actions">
                                <button type="button" id="save_menu_restrictions" class="button button-primary">
                                    <?php _e('Save Menu Configuration', 'ummp'); ?>
                                </button>
                                <button type="button" id="reset_menu_restrictions" class="button">
                                    <?php _e('Reset to Default', 'ummp'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Menu Preview - Fixed positioning -->
                <div class="ummp-card ummp-menu-preview-card">
                    <h2><?php _e('Menu Preview', 'ummp'); ?></h2>
                    <div id="menu_preview" class="ummp-menu-preview">
                        <p><?php _e('Select a role to preview the menu structure.', 'ummp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render ninja forms page
     */
    public function render_ninja_forms_page() {
        ?>
        <div class="wrap ummp-admin">
            <h1><?php _e('Ninja Forms Access', 'ummp'); ?></h1>
            
            <div class="ummp-ninja-container">
                <div class="ummp-card">
                    <h2><?php _e('Forms Viewer Management', 'ummp'); ?></h2>
                    <p><?php _e('Manage users who can view Ninja Forms submissions with restricted access.', 'ummp'); ?></p>
                    
                    <!-- This content will be handled by the UMMP_Ninja_Viewer class -->
                    <div id="ninja-forms-content">
                        <?php
                        // Check if Ninja Forms is active
                        if (class_exists('Ninja_Forms')) {
                            echo '<div class="notice notice-success"><p>' . __('Ninja Forms is active and ready for integration.', 'ummp') . '</p></div>';
                            
                            // Display the ninja forms viewer interface
                            if (class_exists('UMMP_Ninja_Viewer')) {
                                $ninja_viewer = new UMMP_Ninja_Viewer();
                                $ninja_viewer->render_admin_interface();
                            }
                        } else {
                            echo '<div class="notice notice-warning"><p>' . __('Ninja Forms plugin is not active. Please install and activate Ninja Forms to use this feature.', 'ummp') . '</p></div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render import/export page
     */
    public function render_import_export_page() {
        global $wp_roles;
        ?>
        <div class="wrap ummp-admin">
            <h1><?php _e('Export/Import Roles', 'ummp'); ?></h1>
            
            <div class="ummp-import-export-container">
                <!-- Export Roles -->
                <div class="ummp-card">
                    <h2><?php _e('Export Roles', 'ummp'); ?></h2>
                    <p><?php _e('Export roles and their capabilities to a JSON file for backup or migration.', 'ummp'); ?></p>
                    
                    <form id="ummp-export-form" class="ummp-form">
                        <div class="ummp-form-row">
                            <label><?php _e('Select Roles to Export', 'ummp'); ?></label>
                            <div class="ummp-checkbox-group">
                                <label>
                                    <input type="checkbox" id="export_all_roles" checked>
                                    <strong><?php _e('Export All Roles', 'ummp'); ?></strong>
                                </label>
                                <?php foreach ($wp_roles->roles as $role_key => $role_data): ?>
                                    <label>
                                        <input type="checkbox" name="export_roles[]" value="<?php echo esc_attr($role_key); ?>" 
                                               class="export-role-checkbox" checked>
                                        <?php echo esc_html($role_data['name']); ?> (<?php echo esc_html($role_key); ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" class="button button-primary">
                            <?php _e('Export Roles', 'ummp'); ?>
                        </button>
                    </form>
                </div>
                
                <!-- Import Roles -->
                <div class="ummp-card">
                    <h2><?php _e('Import Roles', 'ummp'); ?></h2>
                    <p><?php _e('Import roles from a JSON file. You can choose to overwrite existing roles or skip them.', 'ummp'); ?></p>
                    
                    <form id="ummp-import-form" class="ummp-form">
                        <div class="ummp-form-row">
                            <label for="import_file"><?php _e('Select JSON File', 'ummp'); ?></label>
                            <input type="file" id="import_file" name="import_file" accept=".json" required>
                        </div>
                        <div class="ummp-form-row">
                            <label>
                                <input type="checkbox" id="overwrite_existing" name="overwrite_existing">
                                <?php _e('Overwrite existing roles', 'ummp'); ?>
                            </label>
                            <p class="description">
                                <?php _e('If checked, existing roles with the same name will be replaced. Otherwise, they will be skipped.', 'ummp'); ?>
                            </p>
                        </div>
                        <div class="ummp-form-row">
                            <label for="import_preview"><?php _e('Preview Import Data', 'ummp'); ?></label>
                            <textarea id="import_preview" name="import_preview" rows="10" readonly 
                                      placeholder="<?php _e('Select a file to preview its contents...', 'ummp'); ?>"></textarea>
                        </div>
                        <button type="submit" class="button button-primary" disabled>
                            <?php _e('Import Roles', 'ummp'); ?>
                        </button>
                    </form>
                </div>
                
                <!-- Import/Export History -->
                <div class="ummp-card ummp-card-full">
                    <h2><?php _e('Recent Import/Export Activity', 'ummp'); ?></h2>
                    <div class="ummp-activity-table">
                        <?php
                        $activity_logs = get_option('ummp_activity_logs', array());
                        $import_export_logs = array_filter($activity_logs, function($log) {
                            return in_array($log['action'], array('roles_exported', 'roles_imported'));
                        });
                        $import_export_logs = array_slice($import_export_logs, 0, 20);
                        
                        if (!empty($import_export_logs)): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Date', 'ummp'); ?></th>
                                        <th><?php _e('Action', 'ummp'); ?></th>
                                        <th><?php _e('User', 'ummp'); ?></th>
                                        <th><?php _e('Details', 'ummp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($import_export_logs as $log): ?>
                                        <?php
                                        $user = get_user_by('ID', $log['user_id']);
                                        $username = $user ? $user->display_name : __('Unknown User', 'ummp');
                                        ?>
                                        <tr>
                                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['timestamp'])); ?></td>
                                            <td>
                                                <?php if ($log['action'] === 'roles_exported'): ?>
                                                    <span class="ummp-badge ummp-badge-success"><?php _e('Export', 'ummp'); ?></span>
                                                <?php else: ?>
                                                    <span class="ummp-badge ummp-badge-info"><?php _e('Import', 'ummp'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($username); ?></td>
                                            <td>
                                                <?php
                                                if (isset($log['details'])) {
                                                    if ($log['action'] === 'roles_imported') {
                                                        printf(__('%d imported, %d skipped', 'ummp'), 
                                                            $log['details']['imported'] ?? 0, 
                                                            $log['details']['skipped'] ?? 0
                                                        );
                                                    } else {
                                                        echo __('Roles exported successfully', 'ummp');
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php _e('No import/export activity found.', 'ummp'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render activity logs page
     */
    public function render_logs_page() {
        if (!current_user_can('ummp_view_logs') && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get database logs with fallback to options
        $database = new UMMP_Database();
        $per_page = 50;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        
        // Get activity logs from database
        $logs_for_page = $database->get_activity_logs(array(
            'limit' => $per_page,
            'offset' => $offset,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ));
        
        // Get statistics
        $stats = $database->get_database_stats();
        $total_logs = $stats['activity_logs']['count'] ?? 0;
        
        ?>
        <div class="wrap ummp-admin">
            <h1><?php _e('Activity Logs', 'ummp'); ?></h1>
            
            <!-- Database Statistics -->
            <div class="ummp-stats-container" style="margin-bottom: 20px;">
                <div class="ummp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <?php foreach ($stats as $table => $data): ?>
                        <div class="ummp-stat-card" style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                            <h3 style="margin: 0 0 10px 0; color: #0073aa;"><?php echo esc_html($data['label']); ?></h3>
                            <p style="margin: 0; font-size: 18px; font-weight: bold;"><?php echo number_format($data['count']); ?> records</p>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">Size: <?php echo esc_html($data['size']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="ummp-logs-container">
                <div class="ummp-card ummp-card-full">
                    <div class="ummp-logs-header">
                        <h2><?php _e('Recent Activity', 'ummp'); ?> (<?php echo number_format($total_logs); ?> total)</h2>
                        <div class="ummp-logs-actions">
                            <button id="clear_logs" class="button button-secondary">
                                <?php _e('Clear All Logs', 'ummp'); ?>
                            </button>
                            <button id="export_logs" class="button">
                                <?php _e('Export Logs', 'ummp'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($logs_for_page)): ?>
                        <div class="ummp-logs-table">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Date/Time', 'ummp'); ?></th>
                                        <th><?php _e('User', 'ummp'); ?></th>
                                        <th><?php _e('Action', 'ummp'); ?></th>
                                        <th><?php _e('Details', 'ummp'); ?></th>
                                        <th><?php _e('IP Address', 'ummp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs_for_page as $log): ?>
                                        <?php
                                        $user = get_user_by('ID', $log->user_id);
                                        $username = $user ? $user->display_name : __('Unknown User', 'ummp');
                                        
                                        // Handle both old and new log formats
                                        $timestamp = isset($log->created_at) ? $log->created_at : (isset($log['timestamp']) ? $log['timestamp'] : '');
                                        $action = isset($log->action) ? $log->action : (isset($log['action']) ? $log['action'] : '');
                                        $description = isset($log->description) ? $log->description : '';
                                        $ip_address = isset($log->ip_address) ? $log->ip_address : (isset($log['ip_address']) ? $log['ip_address'] : '');
                                        ?>
                                        <tr>
                                            <td><?php echo $timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($timestamp)) : '-'; ?></td>
                                            <td><?php echo esc_html($username); ?></td>
                                            <td>
                                                <span class="ummp-action-badge ummp-action-<?php echo esc_attr($action); ?>">
                                                    <?php echo esc_html(str_replace('_', ' ', ucfirst($action))); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                if (!empty($description)) {
                                                    echo '<span class="ummp-log-description">' . esc_html($description) . '</span>';
                                                } elseif (!empty($log->metadata)) {
                                                    $metadata = json_decode($log->metadata, true);
                                                    if ($metadata) {
                                                        echo '<code>' . esc_html(wp_trim_words(wp_json_encode($metadata), 10)) . '</code>';
                                                    } else {
                                                        echo '<code>' . esc_html(wp_trim_words($log->metadata, 10)) . '</code>';
                                                    }
                                                } elseif (!empty($log['details']) && is_array($log)) {
                                                    $details = is_array($log['details']) ? wp_json_encode($log['details']) : $log['details'];
                                                    echo '<code>' . esc_html(wp_trim_words($details, 10)) . '</code>';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo esc_html($ip_address ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php
                        // Pagination
                        $total_pages = ceil($total_logs / $per_page);
                        if ($total_pages > 1) {
                            echo '<div class="tablenav bottom">';
                            echo '<div class="tablenav-pages">';
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'current' => $page,
                                'total' => $total_pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;'
                            ));
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    <?php else: ?>
                        <p><?php _e('No activity logs found.', 'ummp'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get menu structure for AJAX
     */
    public function ajax_get_menu_structure() {
        if (!wp_verify_nonce($_POST['nonce'], 'ummp_ajax_nonce')) {
            wp_send_json_error(__('Security check failed.', 'ummp'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $role = sanitize_text_field($_POST['role']);
        
        // Use database manager to get restrictions (with fallback to options)
        $database = new UMMP_Database();
        $role_restrictions = $database->get_menu_restrictions($role);
        
        // Fallback to options if database returns empty
        if (empty($role_restrictions)) {
            $restrictions = get_option('ummp_menu_restrictions', array());
            $role_restrictions = isset($restrictions[$role]) ? $restrictions[$role] : array();
        }
        
        // If still empty, get actual capability-based restrictions for the role
        if (empty($role_restrictions)) {
            $role_restrictions = $this->get_capability_based_menu_restrictions($role);
        }
        
        // Enhanced debugging
        error_log('UMMP Debug - Role: ' . $role);
        error_log('UMMP Debug - Database restrictions: ' . print_r($role_restrictions, true));
        error_log('UMMP Debug - All options restrictions: ' . print_r(get_option('ummp_menu_restrictions', array()), true));
        error_log('UMMP Debug - Role exists in WP: ' . (get_role($role) ? 'Yes' : 'No'));
        error_log('UMMP Debug - Capability-based restrictions for role: ' . print_r($this->get_capability_based_menu_restrictions($role), true));
        
        // Define core WordPress admin menu items
        $all_menus = array(
            array('title' => 'Dashboard', 'slug' => 'index.php', 'icon' => 'dashicons-dashboard'),
            array('title' => 'Posts', 'slug' => 'edit.php', 'icon' => 'dashicons-admin-post'),
            array('title' => 'Media', 'slug' => 'upload.php', 'icon' => 'dashicons-admin-media'),
            array('title' => 'Pages', 'slug' => 'edit.php?post_type=page', 'icon' => 'dashicons-admin-page'),
            array('title' => 'Comments', 'slug' => 'edit-comments.php', 'icon' => 'dashicons-admin-comments'),
            array('title' => 'Appearance', 'slug' => 'themes.php', 'icon' => 'dashicons-admin-appearance'),
            array('title' => 'Plugins', 'slug' => 'plugins.php', 'icon' => 'dashicons-admin-plugins'),
            array('title' => 'Users', 'slug' => 'users.php', 'icon' => 'dashicons-admin-users'),
            array('title' => 'Tools', 'slug' => 'tools.php', 'icon' => 'dashicons-admin-tools'),
            array('title' => 'Settings', 'slug' => 'options-general.php', 'icon' => 'dashicons-admin-settings')
        );
        
        // Add custom post types dynamically
        $custom_post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
        foreach ($custom_post_types as $post_type) {
            if ($post_type->show_in_menu && $post_type->show_ui) {
                $icon = isset($post_type->menu_icon) ? $post_type->menu_icon : 'dashicons-admin-post';
                $all_menus[] = array(
                    'title' => $post_type->labels->menu_name,
                    'slug' => "edit.php?post_type={$post_type->name}",
                    'icon' => $icon
                );
            }
        }
        
        // Check for WooCommerce
        if (class_exists('WooCommerce')) {
            $all_menus[] = array('title' => 'WooCommerce', 'slug' => 'woocommerce', 'icon' => 'dashicons-cart');
            // Note: WooCommerce post types are handled above in custom post types
        }
        
        // Check for Ninja Forms - only add the working submissions page
        if (class_exists('Ninja_Forms')) {
            $all_menus[] = array('title' => 'Ninja Forms Submissions', 'slug' => 'ummp-nf-submissions', 'icon' => 'dashicons-forms');
        }
        
        $menu_structure = array();
        foreach ($all_menus as $menu_item) {
            $menu_structure[] = array(
                'title' => $menu_item['title'],
                'slug' => $menu_item['slug'],
                'icon' => $menu_item['icon'],
                'hidden' => in_array($menu_item['slug'], $role_restrictions)
            );
        }
        
        // Debug log
        error_log('UMMP Debug - Menu structure for role ' . $role . ': ' . print_r($menu_structure, true));
        
        wp_send_json_success($menu_structure);
    }
    
    /**
     * Save menu restrictions
     */
    public function ajax_save_menu_restrictions() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $role = sanitize_text_field($_POST['role']);
        $hidden_menus = isset($_POST['hidden_menus']) ? array_map('sanitize_text_field', $_POST['hidden_menus']) : array();
        
        // Use database manager for menu restrictions
        $database = new UMMP_Database();
        $database->save_menu_restrictions($role, $hidden_menus);
        
        // Also keep the old option-based system for backward compatibility
        $restrictions = get_option('ummp_menu_restrictions', array());
        $restrictions[$role] = $hidden_menus;
        update_option('ummp_menu_restrictions', $restrictions);
        
        // Debug logging for save operation
        error_log('UMMP Debug - Saving restrictions for role: ' . $role);
        error_log('UMMP Debug - Hidden menus being saved: ' . print_r($hidden_menus, true));
        
        // Log the activity using database manager
        $database->log_activity(
            get_current_user_id(),
            'menu_restrictions_updated',
            'menu',
            $role,
            sprintf(__('Updated menu restrictions for role "%s"', 'ummp'), $role),
            array('role' => $role, 'hidden_menus' => $hidden_menus, 'restriction_count' => count($hidden_menus))
        );
        
        wp_send_json_success(__('Menu restrictions saved successfully.', 'ummp'));
    }
    
    /**
     * Reset menu restrictions to defaults
     */
    public function ajax_reset_menu_restrictions() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $role = sanitize_text_field($_POST['role']);
        $default_restrictions = $this->get_capability_based_menu_restrictions($role);
        
        // Use database manager to save default restrictions
        $database = new UMMP_Database();
        $database->save_menu_restrictions($role, $default_restrictions);
        
        // Also clear the old option-based system
        $restrictions = get_option('ummp_menu_restrictions', array());
        $restrictions[$role] = $default_restrictions;
        update_option('ummp_menu_restrictions', $restrictions);
        
        // Log the activity
        $database->log_activity(
            get_current_user_id(),
            'menu_restrictions_reset',
            'menu',
            $role,
            sprintf(__('Reset menu restrictions to defaults for role "%s"', 'ummp'), $role),
            array('role' => $role, 'default_restrictions' => $default_restrictions)
        );
        
        wp_send_json_success(__('Menu restrictions reset to defaults successfully.', 'ummp'));
    }
    
    /**
     * Get menu restrictions based on actual role capabilities
     */
    private function get_capability_based_menu_restrictions($role) {
        // Get the role object
        $role_obj = get_role($role);
        
        if (!$role_obj) {
            error_log('UMMP Debug - Role not found: ' . $role);
            return array(); // If role doesn't exist, don't restrict anything
        }
        
        $capabilities = $role_obj->capabilities;
        $restricted_menus = array();
        
        // Define menu items and their required capabilities
        $menu_capabilities = array(
            'index.php' => 'read', // Dashboard - everyone with read access
            'edit.php' => 'edit_posts', // Posts
            'upload.php' => 'upload_files', // Media
            'edit.php?post_type=page' => 'edit_pages', // Pages
            'edit-comments.php' => 'moderate_comments', // Comments
            'themes.php' => 'switch_themes', // Appearance
            'plugins.php' => 'activate_plugins', // Plugins
            'users.php' => 'list_users', // Users
            'tools.php' => 'import', // Tools
            'options-general.php' => 'manage_options', // Settings
        );
        
        // Get all public custom post types and their capabilities
        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
        foreach ($post_types as $post_type) {
            if (isset($post_type->cap->edit_posts)) {
                $menu_capabilities["edit.php?post_type={$post_type->name}"] = $post_type->cap->edit_posts;
            }
        }
        
        // Check WooCommerce menus if active
        if (class_exists('WooCommerce')) {
            $menu_capabilities['woocommerce'] = 'manage_woocommerce';
            $menu_capabilities['edit.php?post_type=product'] = 'edit_products';
            $menu_capabilities['edit.php?post_type=shop_order'] = 'edit_shop_orders';
            $menu_capabilities['edit.php?post_type=shop_coupon'] = 'edit_shop_coupons';
        }
        
        // Check Ninja Forms menus if active - only the working submissions page
        if (class_exists('Ninja_Forms')) {
            $menu_capabilities['ummp-nf-submissions'] = 'view_nf_submissions';
        }
        
        // Check each menu against role capabilities
        foreach ($menu_capabilities as $menu_slug => $required_capability) {
            // If role doesn't have the required capability, restrict the menu
            if (!isset($capabilities[$required_capability]) || !$capabilities[$required_capability]) {
                $restricted_menus[] = $menu_slug;
            }
        }
        
        error_log('UMMP Debug - Role capabilities: ' . print_r($capabilities, true));
        error_log('UMMP Debug - Capability-based restrictions: ' . print_r($restricted_menus, true));
        
        return $restricted_menus;
    }
    
    /**
     * Search users via AJAX
     */
    public function ajax_search_users() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_users') && !current_user_can('ummp_assign_roles')) {
            wp_die(__('Insufficient permissions.', 'ummp'));
        }
        
        $search_term = sanitize_text_field($_POST['search_term']);
        
        $users = get_users(array(
            'search' => '*' . $search_term . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => 10
        ));
        
        $results = array();
        foreach ($users as $user) {
            $results[] = array(
                'id' => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Restrict menus based on role
     */
    public function restrict_menus() {
        $current_user = wp_get_current_user();
        if (empty($current_user->roles)) {
            return;
        }
        
        $restrictions = get_option('ummp_menu_restrictions', array());
        
        foreach ($current_user->roles as $role) {
            if (isset($restrictions[$role])) {
                foreach ($restrictions[$role] as $menu_slug) {
                    remove_menu_page($menu_slug);
                    remove_submenu_page('', $menu_slug);
                }
            }
        }
    }
    
    /**
     * Custom menu order
     */
    public function custom_menu_order($menu_order) {
        return true;
    }
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        // Clear both database and option logs
        $database = new UMMP_Database();
        global $wpdb;
        $table_name = $wpdb->prefix . 'ummp_activity_logs';
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        // Also clear option-based logs for backward compatibility
        update_option('ummp_activity_logs', array());
        
        // Log the clear action
        $database->log_activity(
            get_current_user_id(),
            'logs_cleared',
            'system',
            null,
            __('All activity logs cleared', 'ummp'),
            array('cleared_by' => get_current_user_id())
        );
        
        wp_send_json_success(__('Activity logs cleared successfully.', 'ummp'));
    }
    
    /**
     * AJAX handler for exporting logs
     */
    public function ajax_export_logs() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        // Export from database with all logs
        $database = new UMMP_Database();
        $export_data = $database->export_activity_logs(array('limit' => 0)); // Get all logs
        
        // Log the export action
        $database->log_activity(
            get_current_user_id(),
            'logs_exported',
            'system',
            null,
            __('Activity logs exported', 'ummp'),
            array('exported_by' => get_current_user_id())
        );
        
        wp_send_json_success(array(
            'data' => $export_data,
            'filename' => 'ummp-activity-logs-' . date('Y-m-d-H-i-s') . '.json'
        ));
    }
    

}
