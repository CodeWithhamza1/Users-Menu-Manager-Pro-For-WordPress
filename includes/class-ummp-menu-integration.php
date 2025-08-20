<?php
/**
 * UMMP Menu Integration Class
 * 
 * Handles integration with form plugin menus to ensure
 * they are visible and accessible to users with form capabilities
 *
 * @package UsersMenuManagerPro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UMMP Menu Integration class
 */
class UMMP_Menu_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into admin menu to ensure form menus are accessible
        add_action('admin_menu', array($this, 'ensure_form_menu_access'), 999);
        
        // Hook into capability checks for form access
        add_filter('user_can', array($this, 'check_form_menu_access'), 10, 4);
        
        // Hook into menu visibility checks
        add_filter('custom_menu_order', array($this, 'custom_menu_order'));
        add_action('admin_menu', array($this, 'restore_form_menus'), 1000);
    }
    
    /**
     * Ensure form menu access for users with form capabilities
     */
    public function ensure_form_menu_access() {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return;
        }
        
        // Check if user has any form capabilities
        $has_ninja_forms = user_can($user->ID, 'view_nf_submissions') || user_can($user->ID, 'nf_sub');
        $has_wpforms = user_can($user->ID, 'wpforms_view_entries');
        $has_gravity_forms = user_can($user->ID, 'gravityforms_view_entries');
        
        if ($has_ninja_forms || $has_wpforms || $has_gravity_forms) {
            // Ensure form plugin menus are accessible
            $this->ensure_ninja_forms_menu($has_ninja_forms);
            $this->ensure_wpforms_menu($has_wpforms);
            $this->ensure_gravity_forms_menu($has_gravity_forms);
        }
    }
    
    /**
     * Ensure Ninja Forms menu is accessible
     */
    private function ensure_ninja_forms_menu($has_access) {
        if (!$has_access) {
            return;
        }
        
        // Check if Ninja Forms menu exists
        global $menu, $submenu;
        
        // Look for existing Ninja Forms menu
        $ninja_menu_found = false;
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && strpos($menu_item[2], 'ninja-forms') !== false) {
                $ninja_menu_found = true;
                break;
            }
        }
        
        // If no menu found, create a custom one
        if (!$ninja_menu_found) {
            add_menu_page(
                __('Ninja Forms', 'ummp'),
                __('Ninja Forms', 'ummp'),
                'view_nf_submissions',
                'ninja-forms',
                array($this, 'render_ninja_forms_page'),
                'dashicons-feedback',
                30
            );
            
            // Add submenus
            add_submenu_page(
                'ninja-forms',
                __('All Submissions', 'ummp'),
                __('All Submissions', 'ummp'),
                'view_nf_submissions',
                'ninja-forms',
                array($this, 'render_ninja_forms_page')
            );
            
            if (user_can(get_current_user_id(), 'edit_nf_submissions')) {
                add_submenu_page(
                    'ninja-forms',
                    __('Forms', 'ummp'),
                    __('Forms', 'ummp'),
                    'edit_nf_submissions',
                    'edit.php?post_type=nf_sub'
                );
            }
        }
    }
    
    /**
     * Ensure WPForms menu is accessible
     */
    private function ensure_wpforms_menu($has_access) {
        if (!$has_access) {
            return;
        }
        
        // Check if WPForms menu exists
        global $menu, $submenu;
        
        // Look for existing WPForms menu
        $wpforms_menu_found = false;
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && strpos($menu_item[2], 'wpforms') !== false) {
                $wpforms_menu_found = true;
                break;
            }
        }
        
        // If no menu found, create a custom one
        if (!$wpforms_menu_found) {
            add_menu_page(
                __('WPForms', 'ummp'),
                __('WPForms', 'ummp'),
                'wpforms_view_entries',
                'wpforms-entries',
                array($this, 'render_wpforms_page'),
                'dashicons-feedback',
                31
            );
            
            // Add submenus
            add_submenu_page(
                'wpforms-entries',
                __('Entries', 'ummp'),
                __('Entries', 'ummp'),
                'wpforms_view_entries',
                'wpforms-entries',
                array($this, 'render_wpforms_page')
            );
            
            if (user_can(get_current_user_id(), 'wpforms_edit_entries')) {
                add_submenu_page(
                    'wpforms-entries',
                    __('Forms', 'ummp'),
                    __('Forms', 'ummp'),
                    'wpforms_edit_entries',
                    'edit.php?post_type=wpforms'
                );
            }
        }
    }
    
    /**
     * Ensure Gravity Forms menu is accessible
     */
    private function ensure_gravity_forms_menu($has_access) {
        if (!$has_access) {
            return;
        }
        
        // Check if Gravity Forms menu exists
        global $menu, $submenu;
        
        // Look for existing Gravity Forms menu
        $gravity_menu_found = false;
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && strpos($menu_item[2], 'gravityforms') !== false) {
                $gravity_menu_found = true;
                break;
            }
        }
        
        // If no menu found, create a custom one
        if (!$gravity_menu_found) {
            add_menu_page(
                __('Gravity Forms', 'ummp'),
                __('Gravity Forms', 'ummp'),
                'gravityforms_view_entries',
                'gravity-forms-entries',
                array($this, 'render_gravity_forms_page'),
                'dashicons-feedback',
                32
            );
            
            // Add submenus
            add_submenu_page(
                'gravity-forms-entries',
                __('Entries', 'ummp'),
                __('Entries', 'ummp'),
                'gravityforms_view_entries',
                'gravity-forms-entries',
                array($this, 'render_gravity_forms_page')
            );
            
            if (user_can(get_current_user_id(), 'gravityforms_edit_entries')) {
                add_submenu_page(
                    'gravity-forms-entries',
                    __('Forms', 'ummp'),
                    __('Forms', 'ummp'),
                    'gravityforms_edit_entries',
                    'edit.php?post_type=gf_form'
                );
            }
        }
    }
    
    /**
     * Check form menu access for capability checks
     */
    public function check_form_menu_access($allcaps, $caps, $args, $user) {
        if (!$user || !$user->ID) {
            return $allcaps;
        }
        
        // Check Ninja Forms menu access
        if (in_array('manage_ninja_forms', $caps)) {
            $allcaps['manage_ninja_forms'] = user_can($user->ID, 'view_nf_submissions') || user_can($user->ID, 'nf_sub');
        }
        
        // Check WPForms menu access
        if (in_array('wpforms_view_entries', $caps)) {
            $allcaps['wpforms_view_entries'] = user_can($user->ID, 'wpforms_view_entries');
        }
        
        // Check Gravity Forms menu access
        if (in_array('gravityforms_view_entries', $caps)) {
            $allcaps['gravityforms_view_entries'] = user_can($user->ID, 'gravityforms_view_entries');
        }
        
        return $allcaps;
    }
    
    /**
     * Custom menu order to ensure form menus are visible
     */
    public function custom_menu_order($menu_order) {
        // Ensure form menus are properly ordered
        return $menu_order;
    }
    
    /**
     * Restore form menus that might have been removed
     */
    public function restore_form_menus() {
        // This method ensures form menus are restored if they were removed by other plugins
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return;
        }
        
        // Check if user has form capabilities but no menus
        $has_ninja_forms = user_can($user->ID, 'view_nf_submissions') || user_can($user->ID, 'nf_sub');
        $has_wpforms = user_can($user->ID, 'wpforms_view_entries');
        $has_gravity_forms = user_can($user->ID, 'gravityforms_view_entries');
        
        if ($has_ninja_forms || $has_wpforms || $has_gravity_forms) {
            // Force menu refresh
            $this->force_menu_refresh();
        }
    }
    
    /**
     * Force menu refresh for form plugins
     */
    private function force_menu_refresh() {
        // Remove and re-add form menus to ensure they're visible
        remove_menu_page('ninja-forms');
        remove_menu_page('wpforms-entries');
        remove_menu_page('gravity-forms-entries');
        
        // Re-add them
        $this->ensure_form_menu_access();
    }
    
    /**
     * Render Ninja Forms page
     */
    public function render_ninja_forms_page() {
        if (!class_exists('Ninja_Forms')) {
            echo '<div class="wrap"><h1>' . __('Ninja Forms', 'ummp') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Ninja Forms plugin is not active.', 'ummp') . '</p></div>';
            echo '</div>';
            return;
        }
        
        // Redirect to our custom submissions page
        wp_redirect(admin_url('admin.php?page=ummp-nf-submissions'));
        exit;
    }
    
    /**
     * Render WPForms page
     */
    public function render_wpforms_page() {
        if (!class_exists('WPForms')) {
            echo '<div class="wrap"><h1>' . __('WPForms', 'ummp') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('WPForms plugin is not active.', 'ummp') . '</p></div>';
            echo '</div>';
            return;
        }
        
        // Show WPForms entries if accessible
        if (class_exists('WPForms_Entries_List')) {
            echo '<div class="wrap"><h1>' . __('WPForms Entries', 'ummp') . '</h1>';
            echo '<div class="notice notice-info"><p>' . __('Redirecting to WPForms entries...', 'ummp') . '</p></div>';
            echo '<script>window.location.href = "' . admin_url('admin.php?page=wpforms-entries') . '";</script>';
            echo '</div>';
        } else {
            echo '<div class="wrap"><h1>' . __('WPForms Entries', 'ummp') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('WPForms entries list not available.', 'ummp') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Render Gravity Forms page
     */
    public function render_gravity_forms_page() {
        if (!class_exists('GFCommon')) {
            echo '<div class="wrap"><h1>' . __('Gravity Forms', 'ummp') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Gravity Forms plugin is not active.', 'ummp') . '</p></div>';
            echo '</div>';
            return;
        }
        
        // Show Gravity Forms entries if accessible
        if (class_exists('GF_Entry_List_Table')) {
            echo '<div class="wrap"><h1>' . __('Gravity Forms Entries', 'ummp') . '</h1>';
            echo '<div class="notice notice-info"><p>' . __('Redirecting to Gravity Forms entries...', 'ummp') . '</p></div>';
            echo '<script>window.location.href = "' . admin_url('admin.php?page=gf_entries') . '";</script>';
            echo '</div>';
        } else {
            echo '<div class="wrap"><h1>' . __('Gravity Forms Entries', 'ummp') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Gravity Forms entries list not available.', 'ummp') . '</p></div>';
            echo '</div>';
        }
    }
}
