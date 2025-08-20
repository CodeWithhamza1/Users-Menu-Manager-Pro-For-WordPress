<?php
/**
 * UMMP Form Integration Class
 * 
 * Handles database integration for form plugins to ensure
 * users with form capabilities actually get access
 *
 * @package UsersMenuManagerPro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UMMP Form Integration class
 */
class UMMP_Form_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into user role updates to sync database access
        add_action('ummp_role_updated', array($this, 'sync_form_access'), 10, 3);
        add_action('ummp_role_created', array($this, 'sync_form_access'), 10, 3);
        add_action('ummp_role_assigned', array($this, 'sync_user_form_access'), 10, 2);
        
        // Hook into user login to ensure access is current
        add_action('wp_login', array($this, 'sync_user_form_access'), 10, 2);
        
        // Hook into capability checks for form access
        add_filter('user_can', array($this, 'check_form_access'), 10, 4);
        
        // Initialize form plugin integrations
        add_action('init', array($this, 'init_form_integrations'));
    }
    
    /**
     * Initialize form plugin integrations
     */
    public function init_form_integrations() {
        // Ninja Forms integration
        if (class_exists('Ninja_Forms') || function_exists('ninja_forms_setup_license') || defined('NF_PLUGIN_DIR')) {
            $this->init_ninja_forms_integration();
        }
        

        
        // Gravity Forms integration
        if (class_exists('GFCommon') || function_exists('gravity_form') || defined('GF_PLUGIN_FILE')) {
            $this->init_gravity_forms_integration();
        }
    }
    
    /**
     * Initialize Ninja Forms integration
     */
    private function init_ninja_forms_integration() {
        // Hook into Ninja Forms capability checks
        add_filter('ninja_forms_user_can_view_submissions', array($this, 'ninja_forms_can_view_submissions'), 10, 2);
        add_filter('ninja_forms_user_can_edit_submissions', array($this, 'ninja_forms_can_edit_submissions'), 10, 2);
        add_filter('ninja_forms_user_can_delete_submissions', array($this, 'ninja_forms_can_delete_submissions'), 10, 2);
        
        // Hook into Ninja Forms menu access
        add_action('admin_menu', array($this, 'ninja_forms_menu_access'), 999);
        
        error_log('UMMP: Ninja Forms integration initialized');
    }
    

    
    /**
     * Initialize Gravity Forms integration
     */
    private function init_gravity_forms_integration() {
        // Hook into Gravity Forms capability checks
        add_filter('gform_user_can_view_entries', array($this, 'gravity_forms_can_view_entries'), 10, 3);
        add_filter('gform_user_can_edit_entries', array($this, 'gravity_forms_can_edit_entries'), 10, 3);
        
        // Hook into Gravity Forms menu access
        add_action('admin_menu', array($this, 'gravity_forms_menu_access'), 999);
        
        error_log('UMMP: Gravity Forms integration initialized');
    }
    
    /**
     * Sync form access when a role is updated
     */
    public function sync_form_access($role_name, $display_name, $capabilities) {
        // Get all users with this role
        $users = get_users(array(
            'role' => $role_name,
            'fields' => 'ID'
        ));
        
        foreach ($users as $user_id) {
            $this->sync_user_form_access($user_id);
        }
        
        error_log('UMMP: Synced form access for role ' . $role_name . ' (' . count($users) . ' users)');
    }
    
    /**
     * Sync form access for a specific user
     */
    public function sync_user_form_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        // Preserve Ninja Forms access if user had it before
        $had_ninja_access = get_user_meta($user_id, 'ummp_ninja_forms_access', true);
        if ($had_ninja_access) {
            // Ensure the user retains the view_nf_submissions capability
            $user->add_cap('view_nf_submissions');
            error_log('UMMP: Preserved Ninja Forms access for user ' . $user_id);
        }
        
        // Sync Ninja Forms access
        $this->sync_ninja_forms_access($user);
        

        
        // Sync Gravity Forms access
        $this->sync_gravity_forms_access($user);
        
        error_log('UMMP: Synced form access for user ' . $user_id);
    }
    
    /**
     * Sync Ninja Forms access for a user
     */
    private function sync_ninja_forms_access($user) {
        if (!class_exists('Ninja_Forms')) {
            return;
        }
        
        global $wpdb;
        
        // Check if user has Ninja Forms capabilities
        $has_view = user_can($user->ID, 'view_nf_submissions') || user_can($user->ID, 'nf_sub');
        $has_edit = user_can($user->ID, 'edit_nf_submissions');
        $has_delete = user_can($user->ID, 'delete_nf_submissions');
        
        if ($has_view || $has_edit || $has_delete) {
            // Ensure user has access to Ninja Forms submissions
            $this->ensure_ninja_forms_user_access($user->ID);
            
            // Add user to Ninja Forms access table if it exists
            $this->add_ninja_forms_user_access($user->ID);
        }
    }
    

    
    /**
     * Sync Gravity Forms access for a user
     */
    private function sync_gravity_forms_access($user) {
        if (!class_exists('GFCommon')) {
            return;
        }
        
        // Check if user has Gravity Forms capabilities
        $has_view = user_can($user->ID, 'gravityforms_view_entries');
        $has_edit = user_can($user->ID, 'gravityforms_edit_entries');
        $has_delete = user_can($user->ID, 'gravityforms_delete_entries');
        
        if ($has_view || $has_edit || $has_delete) {
            // Ensure user has access to Gravity Forms entries
            $this->ensure_gravity_forms_user_access($user->ID);
        }
    }
    
    /**
     * Ensure Ninja Forms user access in database
     */
    private function ensure_ninja_forms_user_access($user_id) {
        global $wpdb;
        
        // Check if Ninja Forms has a custom access table
        $table_name = $wpdb->prefix . 'nf3_user_access';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            // Add user to access table if not exists
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
        
        // Also check for legacy access table
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
        
        // Add user meta to track Ninja Forms access
        update_user_meta($user_id, 'ummp_ninja_forms_access', true);
        update_user_meta($user_id, 'ummp_ninja_forms_access_date', current_time('mysql'));
        error_log('UMMP: Updated Ninja Forms access meta for user ' . $user_id);
    }
    
    /**
     * Add user to Ninja Forms access system
     */
    private function add_ninja_forms_user_access($user_id) {
        // Try to use Ninja Forms API if available
        if (class_exists('Ninja_Forms') && method_exists('Ninja_Forms', 'instance')) {
            try {
                $nf = Ninja_Forms::instance();
                if (method_exists($nf, 'add_user_access')) {
                    $nf->add_user_access($user_id, 'submissions');
                    error_log('UMMP: Added user ' . $user_id . ' to Ninja Forms via API');
                }
            } catch (Exception $e) {
                error_log('UMMP: Error adding user to Ninja Forms via API: ' . $e->getMessage());
            }
        }
    }
    

    
    /**
     * Ensure Gravity Forms user access
     */
    private function ensure_gravity_forms_user_access($user_id) {
        // Gravity Forms typically uses WordPress capabilities, but we can add custom access if needed
        update_user_meta($user_id, 'gravityforms_access_granted', true);
        update_user_meta($user_id, 'gravityforms_access_date', current_time('mysql'));
        
        error_log('UMMP: Ensured Gravity Forms access for user ' . $user_id);
    }
    
    /**
     * Check form access for capability checks
     */
    public function check_form_access($allcaps, $caps, $args, $user) {
        if (!$user || !$user->ID) {
            return $allcaps;
        }
        
        // Check Ninja Forms capabilities
        if (in_array('view_nf_submissions', $caps) || in_array('nf_sub', $caps)) {
            $allcaps['view_nf_submissions'] = $this->user_has_ninja_forms_access($user->ID);
        }
        
        if (in_array('edit_nf_submissions', $caps)) {
            $allcaps['edit_nf_submissions'] = $this->user_has_ninja_forms_access($user->ID) && user_can($user->ID, 'edit_nf_submissions');
        }
        
        if (in_array('delete_nf_submissions', $caps)) {
            $allcaps['delete_nf_submissions'] = $this->user_has_ninja_forms_access($user->ID) && user_can($user->ID, 'delete_nf_submissions');
        }
        

        
        // Check Gravity Forms capabilities
        if (in_array('gravityforms_view_entries', $caps)) {
            $allcaps['gravityforms_view_entries'] = $this->user_has_gravity_forms_access($user->ID);
        }
        
        if (in_array('gravityforms_edit_entries', $caps)) {
            $allcaps['gravityforms_edit_entries'] = $this->user_has_gravity_forms_access($user->ID) && user_can($user->ID, 'gravityforms_edit_entries');
        }
        
        if (in_array('gravityforms_delete_entries', $caps)) {
            $allcaps['gravityforms_delete_entries'] = $this->user_has_gravity_forms_access($user->ID) && user_can($user->ID, 'gravityforms_delete_entries');
        }
        
        return $allcaps;
    }
    
    /**
     * Check if user has Ninja Forms access
     */
    private function user_has_ninja_forms_access($user_id) {
        global $wpdb;
        
        // Check WordPress capabilities first
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }
        
        $has_cap = user_can($user_id, 'view_nf_submissions') || user_can($user_id, 'nf_sub');
        if (!$has_cap) {
            return false;
        }
        
        // Check database access
        $table_name = $wpdb->prefix . 'nf3_user_access';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $access = $wpdb->get_var($wpdb->prepare(
                "SELECT access_level FROM $table_name WHERE user_id = %d",
                $user_id
            ));
            return !empty($access);
        }
        
        // Check legacy table
        $legacy_table = $wpdb->prefix . 'ninja_forms_user_access';
        if ($wpdb->get_var("SHOW TABLES LIKE '$legacy_table'") == $legacy_table) {
            $access = $wpdb->get_var($wpdb->prepare(
                "SELECT access_level FROM $legacy_table WHERE user_id = %d",
                $user_id
            ));
            return !empty($access);
        }
        
        // If no custom table, rely on WordPress capabilities
        return $has_cap;
    }
    

    
    /**
     * Check if user has Gravity Forms access
     */
    private function user_has_gravity_forms_access($user_id) {
        // Check WordPress capabilities
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }
        
        $has_cap = user_can($user_id, 'gravityforms_view_entries');
        if (!$has_cap) {
            return false;
        }
        
        // Check user meta
        $access_granted = get_user_meta($user_id, 'gravityforms_access_granted', true);
        return !empty($access_granted);
    }
    
    /**
     * Ninja Forms capability filters
     */
    public function ninja_forms_can_view_submissions($can_view, $user_id) {
        return $this->user_has_ninja_forms_access($user_id);
    }
    
    public function ninja_forms_can_edit_submissions($can_edit, $user_id) {
        return $this->user_has_ninja_forms_access($user_id) && user_can($user_id, 'edit_nf_submissions');
    }
    
    public function ninja_forms_can_delete_submissions($can_delete, $user_id) {
        return $this->user_has_ninja_forms_access($user_id) && user_can($user_id, 'delete_nf_submissions');
    }
    

    
    /**
     * Gravity Forms capability filters
     */
    public function gravity_forms_can_view_entries($can_view, $form_id, $user_id) {
        return $this->user_has_gravity_forms_access($user_id);
    }
    
    public function gravity_forms_can_edit_entries($can_edit, $form_id, $user_id) {
        return $this->user_has_gravity_forms_access($user_id) && user_can($user_id, 'gravityforms_edit_entries');
    }
    
    /**
     * Menu access control for form plugins
     */
    public function ninja_forms_menu_access() {
        // This will be handled by the existing Ninja Viewer class
    }
    

    
    public function gravity_forms_menu_access() {
        // This will be handled by the existing admin class
    }
}
