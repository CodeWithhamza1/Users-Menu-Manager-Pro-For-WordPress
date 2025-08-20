<?php
/**
 * Plugin Name: Users Menu Manager Pro
 * Plugin URI: https://github.com/codewithhamza1/users-menu-manager-pro-for-wordpress
 * Description: Advanced WordPress role and capability management plugin with dynamic menu control, user assignment, and Ninja Forms integration.
 * Version: 1.0.1
 * Author: Hamza Yousaf
 * Author URI: https://github.com/codewithhamza1
 * Text Domain: ummp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 *
 * @package UsersMenuManagerPro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// SECURITY: Block any attempts to modify administrator role
add_action('init', function() {
    // Log any attempts to modify admin roles
    if (isset($_POST['role_name']) && in_array($_POST['role_name'], ['administrator', 'admin'])) {
        error_log('UMMP SECURITY: Blocked attempt to modify administrator role via POST');
        wp_die('Security violation: Cannot modify administrator role');
    }
    
    // Block admin role modifications via GET
    if (isset($_GET['role_name']) && in_array($_GET['role_name'], ['administrator', 'admin'])) {
        error_log('UMMP SECURITY: Blocked attempt to modify administrator role via GET');
        wp_die('Security violation: Cannot modify administrator role');
    }
});

// Define plugin constants
define('UMMP_VERSION', '1.0.1');
define('UMMP_PLUGIN_FILE', __FILE__);
define('UMMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMMP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UMMP_INCLUDES_DIR', UMMP_PLUGIN_DIR . 'includes/');
define('UMMP_ASSETS_URL', UMMP_PLUGIN_URL . 'assets/');
define('UMMP_TEXT_DOMAIN', 'ummp');

/**
 * Main plugin class
 */
class UsersMenuManagerPro {
    
    /**
     * Plugin instance
     *
     * @var UsersMenuManagerPro
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     *
     * @return UsersMenuManagerPro
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_classes();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once UMMP_INCLUDES_DIR . 'class-ummp-database.php';
        require_once UMMP_INCLUDES_DIR . 'class-ummp-roles.php';
        require_once UMMP_INCLUDES_DIR . 'class-ummp-admin.php';
        require_once UMMP_INCLUDES_DIR . 'class-ummp-ninja-viewer.php';
        require_once UMMP_INCLUDES_DIR . 'class-ummp-form-integration.php';
        require_once UMMP_INCLUDES_DIR . 'class-ummp-form-database.php';
        require_once UMMP_INCLUDES_DIR . 'class-ummp-menu-integration.php';
    }
    
    /**
     * Initialize plugin classes
     */
    private function init_classes() {
        // Initialize database first
        $database = new UMMP_Database();
        $database->init();
        
        // Initialize core classes
        new UMMP_Roles();
        new UMMP_Admin();
        new UMMP_Ninja_Viewer();
        new UMMP_Form_Integration();
        new UMMP_Form_Database();
        new UMMP_Menu_Integration();
    }
    
    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            UMMP_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Additional initialization if needed
        do_action('ummp_init');
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        // Load on our plugin pages
        $plugin_pages = array(
            'toplevel_page_ummp-dashboard',
            'menu-manager-pro_page_ummp-roles',
            'menu-manager-pro_page_ummp-menus',
            'menu-manager-pro_page_ummp-ninja-forms',
            'menu-manager-pro_page_ummp-import-export',
            'menu-manager-pro_page_ummp-logs'
        );
        
        if (!in_array($hook, $plugin_pages)) {
            return;
        }
        
        wp_enqueue_style(
            'ummp-admin-styles',
            UMMP_ASSETS_URL . 'admin.css',
            array(),
            UMMP_VERSION
        );
        
        wp_enqueue_script(
            'ummp-admin-scripts',
            UMMP_ASSETS_URL . 'admin.js',
            array('jquery', 'jquery-ui-sortable'),
            UMMP_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('ummp-admin-scripts', 'ummp_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ummp_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this role?', 'ummp'),
                'processing' => __('Processing...', 'ummp'),
                'error' => __('An error occurred. Please try again.', 'ummp'),
                'success' => __('Operation completed successfully.', 'ummp'),
            )
        ));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        try {
            // Create default capabilities if they don't exist
            $this->create_default_capabilities();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Set activation flag
            update_option('ummp_activated', true);
            
            // Log successful activation
            error_log('UMMP: Plugin activated successfully');
            
            do_action('ummp_activated');
        } catch (Exception $e) {
            error_log('UMMP: Plugin activation error: ' . $e->getMessage());
            // Don't fail activation, just log the error
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        try {
            // Clean up temporary options
            delete_option('ummp_temp_data');
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Log successful deactivation
            error_log('UMMP: Plugin deactivated successfully');
            
            do_action('ummp_deactivated');
        } catch (Exception $e) {
            error_log('UMMP: Plugin deactivation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create default capabilities
     */
    private function create_default_capabilities() {
        // Add custom capabilities if needed
        $capabilities = array(
            'ummp_manage_roles' => 'Manage User Roles',
            'ummp_assign_roles' => 'Assign User Roles',
            'ummp_view_logs' => 'View Role Activity Logs'
        );
        
        // Add capabilities to administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap => $description) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Get plugin version
     */
    public static function get_version() {
        return UMMP_VERSION;
    }
    
    /**
     * Check if user can manage plugin
     */
    public static function current_user_can_manage() {
        return current_user_can('manage_options') || current_user_can('ummp_manage_roles');
    }
    
    /**
     * Log activity
     */
    public static function log_activity($action, $details = array()) {
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
}

/**
 * Initialize the plugin
 */
function ummp_init() {
    return UsersMenuManagerPro::get_instance();
}

// Start the plugin
ummp_init();

/**
 * Uninstall hook
 */
register_uninstall_hook(__FILE__, 'ummp_uninstall');

function ummp_uninstall() {
    // Remove plugin options
    $options_to_remove = array(
        'ummp_settings',
        'ummp_roles_data',
        'ummp_activity_logs',
        'ummp_activated',
        'ummp_temp_data'
    );
    
    foreach ($options_to_remove as $option) {
        delete_option($option);
    }
    
    // Remove custom capabilities from all roles
    global $wp_roles;
    if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
    }
    
    $custom_caps = array(
        'ummp_manage_roles',
        'ummp_assign_roles',
        'ummp_view_logs'
    );
    
    foreach ($wp_roles->roles as $role_name => $role_info) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($custom_caps as $cap) {
                $role->remove_cap($cap);
            }
        }
    }
    
    // Clean up user meta if needed
    delete_metadata('user', 0, 'ummp_custom_restrictions', '', true);
}
