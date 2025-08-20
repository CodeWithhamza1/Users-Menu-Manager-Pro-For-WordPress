<?php
/**
 * UMMP Form Database Class
 * 
 * Handles custom database tables for form access control
 *
 * @package UsersMenuManagerPro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UMMP Form Database class
 */
class UMMP_Form_Database {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_database'));
        add_action('ummp_role_updated', array($this, 'sync_user_access'), 10, 3);
        add_action('ummp_role_created', array($this, 'sync_user_access'), 10, 3);
    }
    
    /**
     * Initialize database tables
     */
    public function init_database() {
        $this->create_form_access_table();
        $this->create_user_form_permissions_table();
    }
    
    /**
     * Create the main form access table
     */
    private function create_form_access_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ummp_form_access';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            plugin_type varchar(50) NOT NULL,
            access_level varchar(50) NOT NULL,
            form_ids text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_plugin (user_id, plugin_type),
            KEY user_id (user_id),
            KEY plugin_type (plugin_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('UMMP: Form access table created/updated');
    }
    
    /**
     * Create user form permissions table
     */
    private function create_user_form_permissions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ummp_user_form_permissions';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            plugin_type varchar(50) NOT NULL,
            capability varchar(100) NOT NULL,
            granted tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_plugin_cap (user_id, plugin_type, capability),
            KEY user_id (user_id),
            KEY plugin_type (plugin_type),
            KEY capability (capability)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('UMMP: User form permissions table created/updated');
    }
    
    /**
     * Sync user access when roles are updated
     */
    public function sync_user_access($role_name, $display_name, $capabilities) {
        // Get all users with this role
        $users = get_users(array(
            'role' => $role_name,
            'fields' => 'ID'
        ));
        
        foreach ($users as $user_id) {
            $this->sync_single_user_access($user_id);
        }
        
        error_log('UMMP: Synced form access for role ' . $role_name . ' (' . count($users) . ' users)');
    }
    
    /**
     * Sync access for a single user
     */
    public function sync_single_user_access($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        // Sync Ninja Forms access
        $this->sync_ninja_forms_user_access($user);
        

        
        // Sync Gravity Forms access
        $this->sync_gravity_forms_user_access($user);
        
        error_log('UMMP: Synced form access for user ' . $user_id);
    }
    
    /**
     * Sync Ninja Forms user access
     */
    private function sync_ninja_forms_user_access($user) {
        if (!class_exists('Ninja_Forms')) {
            return;
        }
        
        global $wpdb;
        
        // Check capabilities
        $has_view = user_can($user->ID, 'view_nf_submissions') || user_can($user->ID, 'nf_sub');
        $has_edit = user_can($user->ID, 'edit_nf_submissions');
        $has_delete = user_can($user->ID, 'delete_nf_submissions');
        
        if ($has_view || $has_edit || $has_delete) {
            // Add to our access table
            $this->add_user_form_access($user->ID, 'ninja_forms', 'submissions');
            
            // Add specific permissions
            if ($has_view) {
                $this->add_user_form_permission($user->ID, 'ninja_forms', 'view_nf_submissions');
            }
            if ($has_edit) {
                $this->add_user_form_permission($user->ID, 'ninja_forms', 'edit_nf_submissions');
            }
            if ($has_delete) {
                $this->add_user_form_permission($user->ID, 'ninja_forms', 'delete_nf_submissions');
            }
            
            // Try to integrate with Ninja Forms own system
            $this->integrate_with_ninja_forms($user->ID);
        }
    }
    

    
    /**
     * Sync Gravity Forms user access
     */
    private function sync_gravity_forms_user_access($user) {
        if (!class_exists('GFCommon')) {
            return;
        }
        
        // Check capabilities
        $has_view = user_can($user->ID, 'gravityforms_view_entries');
        $has_edit = user_can($user->ID, 'gravityforms_edit_entries');
        $has_delete = user_can($user->ID, 'gravityforms_delete_entries');
        
        if ($has_view || $has_edit || $has_delete) {
            // Add to our access table
            $this->add_user_form_access($user->ID, 'gravity_forms', 'entries');
            
            // Add specific permissions
            if ($has_view) {
                $this->add_user_form_permission($user->ID, 'gravity_forms', 'gravityforms_view_entries');
            }
            if ($has_edit) {
                $this->add_user_form_permission($user->ID, 'gravity_forms', 'gravityforms_edit_entries');
            }
            if ($has_delete) {
                $this->add_user_form_permission($user->ID, 'gravity_forms', 'gravityforms_delete_entries');
            }
            
            // Try to integrate with Gravity Forms own system
            $this->integrate_with_gravity_forms($user->ID);
        }
    }
    
    /**
     * Add user to form access table
     */
    private function add_user_form_access($user_id, $plugin_type, $access_level) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ummp_form_access';
        
        $wpdb->replace($table_name, array(
            'user_id' => $user_id,
            'plugin_type' => $plugin_type,
            'access_level' => $access_level,
            'updated_at' => current_time('mysql')
        ), array('%d', '%s', '%s', '%s'));
        
        error_log('UMMP: Added user ' . $user_id . ' to ' . $plugin_type . ' access table');
    }
    
    /**
     * Add user form permission
     */
    private function add_user_form_permission($user_id, $plugin_type, $capability) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ummp_user_form_permissions';
        
        $wpdb->replace($table_name, array(
            'user_id' => $user_id,
            'plugin_type' => $plugin_type,
            'capability' => $capability,
            'granted' => 1,
            'updated_at' => current_time('mysql')
        ), array('%d', '%s', '%s', '%d', '%s'));
        
        error_log('UMMP: Added permission ' . $capability . ' for user ' . $user_id . ' in ' . $plugin_type);
    }
    
    /**
     * Integrate with Ninja Forms own system
     */
    private function integrate_with_ninja_forms($user_id) {
        global $wpdb;
        
        // Check if Ninja Forms has its own access table
        $table_name = $wpdb->prefix . 'nf3_user_access';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            // Add user to Ninja Forms access table
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
                $user_id
            ));
            
            if (!$exists) {
                $wpdb->insert($table_name, array(
                    'user_id' => $user_id,
                    'access_level' => 'submissions',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ));
                error_log('UMMP: Added user ' . $user_id . ' to Ninja Forms access table');
            }
        }
        
        // Check legacy table
        $legacy_table = $wpdb->prefix . 'ninja_forms_user_access';
        if ($wpdb->get_var("SHOW TABLES LIKE '$legacy_table'") == $legacy_table) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $legacy_table WHERE user_id = %d",
                $user_id
            ));
            
            if (!$exists) {
                $wpdb->insert($legacy_table, array(
                    'user_id' => $user_id,
                    'access_level' => 'submissions',
                    'created_at' => current_time('mysql')
                ));
                error_log('UMMP: Added user ' . $user_id . ' to legacy Ninja Forms access table');
            }
        }
    }
    

    
    /**
     * Integrate with Gravity Forms own system
     */
    private function integrate_with_gravity_forms($user_id) {
        // Gravity Forms typically uses WordPress capabilities, but we can add custom user meta
        update_user_meta($user_id, 'gravityforms_access_granted', true);
        update_user_meta($user_id, 'gravityforms_access_date', current_time('mysql'));
        update_user_meta($user_id, 'gravityforms_access_level', 'entries');
        
        error_log('UMMP: Added Gravity Forms access meta for user ' . $user_id);
    }
    
    /**
     * Check if user has form access
     */
    public function user_has_form_access($user_id, $plugin_type, $capability = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ummp_form_access';
        
        if ($capability) {
            // Check specific capability
            $permissions_table = $wpdb->prefix . 'ummp_user_form_permissions';
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT granted FROM $permissions_table WHERE user_id = %d AND plugin_type = %s AND capability = %s",
                $user_id, $plugin_type, $capability
            ));
            
            return $result == 1;
        } else {
            // Check general access
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND plugin_type = %s",
                $user_id, $plugin_type
            ));
            
            return $result > 0;
        }
    }
    
    /**
     * Get user's form access details
     */
    public function get_user_form_access($user_id) {
        global $wpdb;
        
        $access_table = $wpdb->prefix . 'ummp_form_access';
        $permissions_table = $wpdb->prefix . 'ummp_user_form_permissions';
        
        $access = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $access_table WHERE user_id = %d",
            $user_id
        ));
        
        $permissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $permissions_table WHERE user_id = %d",
            $user_id
        ));
        
        return array(
            'access' => $access,
            'permissions' => $permissions
        );
    }
    
    /**
     * Remove user form access
     */
    public function remove_user_form_access($user_id, $plugin_type = null) {
        global $wpdb;
        
        if ($plugin_type) {
            // Remove specific plugin access
            $access_table = $wpdb->prefix . 'ummp_form_access';
            $permissions_table = $wpdb->prefix . 'ummp_user_form_permissions';
            
            $wpdb->delete($access_table, array('user_id' => $user_id, 'plugin_type' => $plugin_type));
            $wpdb->delete($permissions_table, array('user_id' => $user_id, 'plugin_type' => $plugin_type));
            
            error_log('UMMP: Removed ' . $plugin_type . ' access for user ' . $user_id);
        } else {
            // Remove all form access
            $access_table = $wpdb->prefix . 'ummp_form_access';
            $permissions_table = $wpdb->prefix . 'ummp_user_form_permissions';
            
            $wpdb->delete($access_table, array('user_id' => $user_id));
            $wpdb->delete($permissions_table, array('user_id' => $user_id));
            
            error_log('UMMP: Removed all form access for user ' . $user_id);
        }
    }
}
