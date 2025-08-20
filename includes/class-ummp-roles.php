<?php
/**
 * UMMP Roles Class
 * 
 * Handles role and capability management functionality
 *
 * @package UsersMenuManagerPro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UMMP Roles class
 */
class UMMP_Roles {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_ummp_create_role', array($this, 'ajax_create_role'));
        add_action('wp_ajax_ummp_update_role', array($this, 'ajax_update_role'));
        add_action('wp_ajax_ummp_delete_role', array($this, 'ajax_delete_role'));
        add_action('wp_ajax_ummp_clone_role', array($this, 'ajax_clone_role'));
        add_action('wp_ajax_ummp_assign_role', array($this, 'ajax_assign_role'));
        add_action('wp_ajax_ummp_bulk_assign_roles', array($this, 'ajax_bulk_assign_roles'));
        add_action('wp_ajax_ummp_export_roles', array($this, 'ajax_export_roles'));
        add_action('wp_ajax_ummp_import_roles', array($this, 'ajax_import_roles'));
        add_action('wp_ajax_ummp_get_role_capabilities', array($this, 'ajax_get_role_capabilities'));
        add_action('wp_ajax_ummp_force_refresh_capabilities', array($this, 'ajax_force_refresh_capabilities'));
        add_action('wp_ajax_ummp_fix_existing_roles', array($this, 'ajax_fix_existing_roles'));
        
        // Fix WooCommerce redirect issue for normal users - with better error handling
        add_action('plugins_loaded', array($this, 'init_woocommerce_filters'));
        
        // Fix existing roles on plugin load to ensure compatibility
        add_action('init', array($this, 'maybe_fix_existing_roles'));
    }

    /**
     * Maybe fix existing roles - only runs once per session
     */
    public function maybe_fix_existing_roles() {
        // Only run this once per session to avoid performance issues
        if (!get_transient('ummp_roles_fixed')) {
            $this->fix_existing_roles();
            set_transient('ummp_roles_fixed', true, HOUR_IN_SECONDS);
        }
    }

    /**
     * Fix existing roles by adding missing dependent capabilities
     * This ensures all existing roles work properly with the new dependency system
     */
    public function fix_existing_roles() {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        $fixed_count = 0;
        
        foreach ($wp_roles->roles as $role_name => $role_data) {
            // Skip administrator role
            if ($role_name === 'administrator') {
                continue;
            }
            
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }
            
            $current_caps = array_keys(array_filter($role->capabilities));
            $enhanced_caps = $this->add_dependent_capabilities($current_caps);
            
            // Check if we need to add any missing capabilities
            $missing_caps = array_diff($enhanced_caps, $current_caps);
            
            if (!empty($missing_caps)) {
                foreach ($missing_caps as $cap) {
                    $role->add_cap($cap);
                    error_log('UMMP: Added missing dependent capability ' . $cap . ' to role ' . $role_name);
                }
                
                // Force save the role
                $this->force_save_role($role_name, $role);
                $fixed_count++;
            }
        }
        
        if ($fixed_count > 0) {
            error_log('UMMP: Fixed ' . $fixed_count . ' existing roles with missing dependent capabilities');
        }
        
        return $fixed_count;
    }

    /**
     * Initialize WooCommerce filters safely
     */
    public function init_woocommerce_filters() {
        // Only add filters if WooCommerce is active and loaded
        if (class_exists('WooCommerce') && function_exists('WC')) {
            try {
                add_filter('woocommerce_prevent_admin_access', array($this, 'prevent_woocommerce_admin_redirect'), 10, 2);
                add_filter('woocommerce_disable_admin_bar', array($this, 'prevent_woocommerce_admin_bar_disable'), 10, 2);
                add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
                error_log('UMMP: WooCommerce filters initialized successfully');
            } catch (Exception $e) {
                error_log('UMMP: Error initializing WooCommerce filters: ' . $e->getMessage());
            }
        }
    }

    /**
     * Prevent WooCommerce from redirecting users with admin capabilities to my-account page
     *
     * @param bool $prevent_access Whether to prevent admin access
     * @param int $user_id User ID
     * @return bool Whether to prevent admin access
     */
    public function prevent_woocommerce_admin_redirect($prevent_access, $user_id) {
        if (!$user_id) {
            return $prevent_access;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return $prevent_access;
        }
        
        // Check if user has any admin-like capabilities
        $admin_caps = array('edit_posts', 'edit_pages', 'upload_files', 'edit_comments', 'manage_categories');
        foreach ($admin_caps as $cap) {
            if (user_can($user_id, $cap)) {
                error_log('UMMP: Preventing WooCommerce redirect for user ' . $user_id . ' with capability: ' . $cap);
                return false; // Allow admin access
            }
        }
        
        return $prevent_access; // Keep default behavior for regular customers
    }

    /**
     * Prevent WooCommerce from disabling admin bar for users with admin capabilities
     *
     * @param bool $disable_admin_bar Whether to disable admin bar
     * @param int $user_id User ID
     * @return bool Whether to disable admin bar
     */
    public function prevent_woocommerce_admin_bar_disable($disable_admin_bar, $user_id) {
        if (!$user_id) {
            return $disable_admin_bar;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return $disable_admin_bar;
        }
        
        // Check if user has any admin-like capabilities
        $admin_caps = array('edit_posts', 'edit_pages', 'upload_files', 'edit_comments', 'manage_categories');
        foreach ($admin_caps as $cap) {
            if (user_can($user_id, $cap)) {
                error_log('UMMP: Preventing WooCommerce admin bar disable for user ' . $user_id . ' with capability: ' . $cap);
                return false; // Keep admin bar
            }
        }
        
        return $disable_admin_bar; // Keep default behavior for regular customers
    }

    /**
     * Custom login redirect to ensure users with admin capabilities go to wp-admin
     *
     * @param string $redirect_to Redirect URL
     * @param string $requested_redirect_to Requested redirect URL
     * @param WP_User $user User object
     * @return string Redirect URL
     */
    public function custom_login_redirect($redirect_to, $requested_redirect_to, $user) {
        if (!$user || is_wp_error($user)) {
            return $redirect_to;
        }
        
        // Check if user has any admin-like capabilities
        $admin_caps = array('edit_posts', 'edit_pages', 'upload_files', 'edit_comments', 'manage_categories');
        foreach ($admin_caps as $cap) {
            if (user_can($user->ID, $cap)) {
                error_log('UMMP: Redirecting user ' . $user->ID . ' with capability ' . $cap . ' to wp-admin');
                return admin_url(); // Redirect to wp-admin
            }
        }
        
        // For regular customers, let WooCommerce handle the redirect
        return $redirect_to;
    }
    
    /**
     * Get all available capabilities from WordPress core and active plugins
     *
     * @return array Capabilities grouped by source
     */
    public function get_all_capabilities() {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        $capabilities = array();
        
        // Core WordPress capabilities - REMOVE unfiltered_html from core
        $core_capabilities = array(
            'read', 'edit_posts', 'edit_pages', 'edit_others_posts', 'edit_others_pages',
            'publish_posts', 'publish_pages', 'delete_posts', 'delete_pages',
            'delete_others_posts', 'delete_others_pages', 'delete_published_posts',
            'delete_published_pages', 'edit_published_posts', 'edit_published_pages',
            'manage_categories', 'manage_links', 'moderate_comments', 'upload_files',
            'edit_comments', 'edit_others_comments', 'delete_comments', 'delete_others_comments',
            'switch_themes', 'edit_themes', 'activate_plugins', 'edit_plugins',
            'edit_users', 'list_users', 'delete_users', 'create_users', 'manage_options',
            'import', 'unfiltered_upload', 'edit_dashboard',
            'update_plugins', 'delete_plugins', 'install_plugins', 'update_themes',
            'install_themes', 'update_core', 'edit_theme_options', 'customize',
            'delete_site'
        );
        
        foreach ($core_capabilities as $cap) {
            $capabilities[$cap] = $cap;
            error_log('UMMP: Added core capability: ' . $cap);
        }
        
        // Get all capabilities from existing roles
        foreach ($wp_roles->roles as $role_name => $role_info) {
            if (isset($role_info['capabilities'])) {
                foreach ($role_info['capabilities'] as $cap => $value) {
                    $capabilities[$cap] = $cap;
                    error_log('UMMP: Added capability from role ' . $role_name . ': ' . $cap);
                }
            }
        }
        
        // Get capabilities from post types
        $post_types = get_post_types(array('_builtin' => false), 'objects');
        foreach ($post_types as $post_type) {
            if (isset($post_type->cap)) {
                $caps = (array) $post_type->cap;
                foreach ($caps as $cap) {
                    if (!empty($cap) && is_string($cap)) {
                        $capabilities[$cap] = $cap;
                        error_log('UMMP: Added capability from post type ' . $post_type->name . ': ' . $cap);
                    }
                }
            }
        }
        
        // WooCommerce capabilities if active
        if (class_exists('WooCommerce') || function_exists('WC') || defined('WC_PLUGIN_FILE')) {
            error_log('UMMP: WooCommerce detected, adding capabilities');
            $wc_caps = array(
                'manage_woocommerce', 'view_woocommerce_reports', 'edit_product', 'read_product',
                'delete_product', 'edit_products', 'edit_others_products', 'publish_products',
                'read_private_products', 'delete_products', 'delete_private_products',
                'delete_published_products', 'delete_others_products', 'edit_private_products',
                'edit_published_products', 'manage_product_terms', 'edit_product_terms',
                'delete_product_terms', 'assign_product_terms', 'edit_shop_order', 'read_shop_order',
                'delete_shop_order', 'edit_shop_orders', 'edit_others_shop_orders', 'publish_shop_orders',
                'read_private_shop_orders', 'delete_shop_orders', 'delete_private_shop_orders',
                'delete_published_shop_orders', 'delete_others_shop_orders', 'edit_private_shop_orders',
                'edit_published_shop_orders'
            );
            foreach ($wc_caps as $cap) {
                $capabilities[$cap] = $cap;
                error_log('UMMP: Added WooCommerce capability: ' . $cap);
            }
        } else {
            error_log('UMMP: WooCommerce not detected');
        }
        
        // Gravity Forms capabilities if active
        if (class_exists('GFCommon') || function_exists('gravity_form') || defined('GF_PLUGIN_FILE')) {
            error_log('UMMP: Gravity Forms detected, adding capabilities');
            $gf_caps = array(
                'gravityforms_view_entries', 'gravityforms_edit_entries', 'gravityforms_delete_entries',
                'gravityforms_view_entry_notes', 'gravityforms_edit_entry_notes', 'gravityforms_delete_entry_notes',
                'gravityforms_view_settings', 'gravityforms_edit_settings', 'gravityforms_export_entries',
                'gravityforms_view_updates', 'gravityforms_view_addons', 'gravityforms_edit_addons'
            );
            foreach ($gf_caps as $cap) {
                $capabilities[$cap] = $cap;
                error_log('UMMP: Added Gravity Forms capability: ' . $cap);
            }
        }
        
        // Elementor capabilities if active
        if (class_exists('Elementor\Plugin') || defined('ELEMENTOR_VERSION')) {
            error_log('UMMP: Elementor detected, adding capabilities');
            $elementor_caps = array(
                'edit_posts', 'edit_pages', 'edit_others_posts', 'edit_others_pages',
                'publish_posts', 'publish_pages', 'delete_posts', 'delete_pages',
                'delete_others_posts', 'delete_others_pages', 'edit_theme_options'
            );
            foreach ($elementor_caps as $cap) {
                $capabilities[$cap] = $cap;
                error_log('UMMP: Added Elementor capability: ' . $cap);
            }
        } else {
            error_log('UMMP: Elementor not detected');
        }
        
        // Group capabilities by their source/type
        $grouped_capabilities = $this->group_capabilities($capabilities);
        
        error_log('UMMP: All capabilities detected: ' . print_r($capabilities, true));
        error_log('UMMP: Grouped capabilities: ' . print_r($grouped_capabilities, true));
        
        return apply_filters('ummp_all_capabilities', $grouped_capabilities);
    }

    /**
     * Get human-readable capability name
     *
     * @param string $capability Capability slug
     * @return string Human-readable name
     */
    public function get_capability_display_name($capability) {
        $capability_names = array(
            // Core capabilities
            'read' => __('Read', 'ummp'),
            'edit_posts' => __('Edit Posts', 'ummp'),
            'edit_pages' => __('Edit Pages', 'ummp'),
            'edit_others_posts' => __('Edit Others Posts', 'ummp'),
            'edit_others_pages' => __('Edit Others Pages', 'ummp'),
            'publish_posts' => __('Publish Posts', 'ummp'),
            'publish_pages' => __('Publish Pages', 'ummp'),
            'delete_posts' => __('Delete Posts', 'ummp'),
            'delete_pages' => __('Delete Pages', 'ummp'),
            'delete_others_posts' => __('Delete Others Posts', 'ummp'),
            'delete_others_pages' => __('Delete Others Pages', 'ummp'),
            'delete_published_posts' => __('Delete Published Posts', 'ummp'),
            'delete_published_pages' => __('Delete Published Pages', 'ummp'),
            'edit_published_posts' => __('Edit Published Posts', 'ummp'),
            'edit_published_pages' => __('Edit Published Pages', 'ummp'),
            'manage_categories' => __('Manage Categories', 'ummp'),
            'manage_links' => __('Manage Links', 'ummp'),
            'moderate_comments' => __('Moderate Comments', 'ummp'),
            'upload_files' => __('Upload Files', 'ummp'),
            'edit_comments' => __('Edit Comments', 'ummp'),
            'edit_others_comments' => __('Edit Others Comments', 'ummp'),
            'delete_comments' => __('Delete Comments', 'ummp'),
            'delete_others_comments' => __('Delete Others Comments', 'ummp'),
            'switch_themes' => __('Switch Themes', 'ummp'),
            'edit_themes' => __('Edit Themes', 'ummp'),
            'activate_plugins' => __('Activate Plugins', 'ummp'),
            'edit_plugins' => __('Edit Plugins', 'ummp'),
            'edit_users' => __('Edit Users', 'ummp'),
            'list_users' => __('List Users', 'ummp'),
            'delete_users' => __('Delete Users', 'ummp'),
            'create_users' => __('Create Users', 'ummp'),
            'manage_options' => __('Manage Options', 'ummp'),
            'import' => __('Import', 'ummp'),
            'unfiltered_upload' => __('Unfiltered Upload', 'ummp'),
            'edit_dashboard' => __('Edit Dashboard', 'ummp'),
            'update_plugins' => __('Update Plugins', 'ummp'),
            'delete_plugins' => __('Delete Plugins', 'ummp'),
            'install_plugins' => __('Install Plugins', 'ummp'),
            'update_themes' => __('Update Themes', 'ummp'),
            'install_themes' => __('Install Themes', 'ummp'),
            'update_core' => __('Update Core', 'ummp'),
            'edit_theme_options' => __('Edit Theme Options', 'ummp'),
            'customize' => __('Customize', 'ummp'),
            'delete_site' => __('Delete Site', 'ummp'),

            
            // WooCommerce capabilities
            'manage_woocommerce' => __('Manage WooCommerce', 'ummp'),
            'view_woocommerce_reports' => __('View WooCommerce Reports', 'ummp'),
            'edit_product' => __('Edit Product', 'ummp'),
            'read_product' => __('Read Product', 'ummp'),
            'delete_product' => __('Delete Product', 'ummp'),
            'edit_products' => __('Edit Products', 'ummp'),
            'edit_others_products' => __('Edit Others Products', 'ummp'),
            'publish_products' => __('Publish Products', 'ummp'),
            'read_private_products' => __('Read Private Products', 'ummp'),
            'delete_products' => __('Delete Products', 'ummp'),
            'delete_private_products' => __('Delete Private Products', 'ummp'),
            'delete_published_products' => __('Delete Published Products', 'ummp'),
            'delete_others_products' => __('Delete Others Products', 'ummp'),
            'edit_private_products' => __('Edit Private Products', 'ummp'),
            'edit_published_products' => __('Edit Published Products', 'ummp'),
            'manage_product_terms' => __('Manage Product Terms', 'ummp'),
            'edit_product_terms' => __('Edit Product Terms', 'ummp'),
            'delete_product_terms' => __('Delete Product Terms', 'ummp'),
            'assign_product_terms' => __('Assign Product Terms', 'ummp'),
            'edit_shop_order' => __('Edit Shop Order', 'ummp'),
            'read_shop_order' => __('Read Shop Order', 'ummp'),
            'delete_shop_order' => __('Delete Shop Order', 'ummp'),
            'edit_shop_orders' => __('Edit Shop Orders', 'ummp'),
            'edit_others_shop_orders' => __('Edit Others Shop Orders', 'ummp'),
            'publish_shop_orders' => __('Publish Shop Orders', 'ummp'),
            'read_private_shop_orders' => __('Read Private Shop Orders', 'ummp'),
            'delete_shop_orders' => __('Delete Shop Orders', 'ummp'),
            'delete_private_shop_orders' => __('Delete Private Shop Orders', 'ummp'),
            'delete_published_shop_orders' => __('Delete Published Shop Orders', 'ummp'),
            'delete_others_shop_orders' => __('Delete Others Shop Orders', 'ummp'),
            'edit_private_shop_orders' => __('Edit Private Shop Orders', 'ummp'),
            'edit_published_shop_orders' => __('Edit Published Shop Orders', 'ummp'),

            
            // Gravity Forms capabilities
            'gravityforms_view_entries' => __('View Gravity Forms Entries', 'ummp'),
            'gravityforms_edit_entries' => __('Edit Gravity Forms Entries', 'ummp'),
            'gravityforms_delete_entries' => __('Delete Gravity Forms Entries', 'ummp'),
            'gravityforms_view_entry_notes' => __('View Entry Notes', 'ummp'),
            'gravityforms_edit_entry_notes' => __('Edit Entry Notes', 'ummp'),
            'gravityforms_delete_entry_notes' => __('Delete Entry Notes', 'ummp'),
            'gravityforms_view_settings' => __('View Gravity Forms Settings', 'ummp'),
            'gravityforms_edit_settings' => __('Edit Gravity Forms Settings', 'ummp'),
            'gravityforms_export_entries' => __('Export Gravity Forms Entries', 'ummp'),
            'gravityforms_view_updates' => __('View Gravity Forms Updates', 'ummp'),
            'gravityforms_view_addons' => __('View Gravity Forms Addons', 'ummp'),
            'gravityforms_edit_addons' => __('Edit Gravity Forms Addons', 'ummp'),
        );
        
        // Return human-readable name if available, otherwise format the slug
        if (isset($capability_names[$capability])) {
            return $capability_names[$capability];
        }
        
        // Format slug to human-readable if no specific name exists
        return ucwords(str_replace('_', ' ', $capability));
    }

    /**
     * Group capabilities by their source/type
     *
     * @param array $capabilities List of capabilities
     * @return array Grouped capabilities
     */
    private function group_capabilities($capabilities) {
        $groups = array(
            'core' => array(
                'label' => __('WordPress Core', 'ummp'),
                'capabilities' => array()
            ),
            'posts' => array(
                'label' => __('Posts & Pages', 'ummp'),
                'capabilities' => array()
            ),
            'media' => array(
                'label' => __('Media', 'ummp'),
                'capabilities' => array()
            ),
            'users' => array(
                'label' => __('Users', 'ummp'),
                'capabilities' => array()
            ),
            'comments' => array(
                'label' => __('Comments', 'ummp'),
                'capabilities' => array()
            ),
            'themes' => array(
                'label' => __('Themes', 'ummp'),
                'capabilities' => array()
            ),
            'plugins' => array(
                'label' => __('Plugins', 'ummp'),
                'capabilities' => array()
            ),
            'woocommerce' => array(
                'label' => __('WooCommerce', 'ummp'),
                'capabilities' => array()
            ),

            'gravity_forms' => array(
                'label' => __('Gravity Forms', 'ummp'),
                'capabilities' => array()
            ),
            'elementor' => array(
                'label' => __('Elementor', 'ummp'),
                'capabilities' => array()
            ),
            'custom' => array(
                'label' => __('Custom', 'ummp'),
                'capabilities' => array()
            )
        );
        
        foreach ($capabilities as $cap) {
            $group = $this->determine_capability_group($cap);
            $groups[$group]['capabilities'][$cap] = $cap;
            error_log('UMMP: Capability ' . $cap . ' grouped under ' . $group);
        }
        
        // Remove empty groups
        $groups = array_filter($groups, function($group) {
            return !empty($group['capabilities']);
        });
        
        return $groups;
    }

    /**
     * Determine which group a capability belongs to
     *
     * @param string $capability Capability name
     * @return string Group name
     */
    private function determine_capability_group($capability) {
        // Posts and pages
        if (preg_match('/^(edit|publish|delete)_(posts|pages|others_posts|others_pages)/', $capability)) {
            return 'posts';
        }
        
        // Media - EXCLUDE unfiltered_html from media section
        if (preg_match('/^upload_files/', $capability)) {
            return 'media';
        }
        
        // Users
        if (preg_match('/^(edit|list|delete|create)_users/', $capability)) {
            return 'users';
        }
        
        // Comments
        if (preg_match('/^(edit|delete|moderate)_(comments|others_comments)/', $capability)) {
            return 'comments';
        }
        
        // Themes
        if (preg_match('/^(switch|edit|install|update)_themes/', $capability) || 
            preg_match('/^edit_theme_options/', $capability) || 
            preg_match('/^customize/', $capability)) {
            return 'themes';
        }
        
        // Plugins
        if (preg_match('/^(activate|edit|delete|install|update)_plugins/', $capability)) {
            return 'plugins';
        }
        
        // WooCommerce
        if (preg_match('/^(manage_woocommerce|view_woocommerce_reports|edit_product|read_product|delete_product|edit_products|edit_others_products|publish_products|read_private_products|delete_products|delete_private_products|delete_published_products|delete_others_products|edit_private_products|edit_published_products|manage_product_terms|edit_product_terms|delete_product_terms|assign_product_terms|edit_shop_order|read_shop_order|delete_shop_order|edit_shop_orders|edit_others_shop_orders|publish_shop_orders|read_private_shop_orders|delete_shop_orders|delete_private_shop_orders|delete_published_shop_orders|delete_others_shop_orders|edit_private_shop_orders|edit_published_shop_orders)/', $capability)) {
            return 'woocommerce';
        }
        

        
        // Gravity Forms
        if (strpos($capability, 'gravityforms_') !== false) {
            return 'gravity_forms';
        }
        
        // Elementor
        if (strpos($capability, 'elementor') !== false) {
            return 'elementor';
        }
        
        // Core capabilities (including unfiltered_html)
        if (preg_match('/^(read|edit_dashboard|manage_options|import|unfiltered_upload|update_core|delete_site|manage_categories|manage_links)/', $capability) ||
            $capability === 'unfiltered_html') {
            return 'core';
        }
        
        // Default to custom
        return 'custom';
    }
    
    /**
     * Add dependent capabilities automatically
     *
     * @param array $capabilities Array of capabilities
     * @return array Enhanced capabilities with dependencies
     */
    private function add_dependent_capabilities($capabilities) {
        // Convert to array if it's not already
        if (!is_array($capabilities)) {
            $capabilities = array($capabilities);
        }
        
        // Convert associative array to indexed if needed
        $cap_list = array();
        foreach ($capabilities as $cap => $enabled) {
            if (is_string($cap)) {
                $cap_list[] = $cap;
            } else {
                $cap_list[] = $enabled;
            }
        }
        
        $enhanced_caps = $cap_list;
        
        // WordPress core dependencies
        $dependencies = array(
            // Post/Page management requires read capability
            'edit_posts' => array('read'),
            'edit_pages' => array('read'),
            'publish_posts' => array('read', 'edit_posts'),
            'publish_pages' => array('read', 'edit_pages'),
            'delete_posts' => array('read', 'edit_posts'),
            'delete_pages' => array('read', 'edit_pages'),
            
            // Media management requires read capability
            'upload_files' => array('read'),
            
            // Comment management requires read capability
            'edit_comments' => array('read'),
            'moderate_comments' => array('read', 'edit_comments'),
            
            // User management requires read capability
            'edit_users' => array('read'),
            'list_users' => array('read'),
            'create_users' => array('read'),
            'delete_users' => array('read'),
            
            // Category management requires read capability
            'manage_categories' => array('read'),
            
            // Theme/Plugin management requires read capability
            'switch_themes' => array('read'),
            'edit_themes' => array('read'),
            'activate_plugins' => array('read'),
            'edit_plugins' => array('read'),
            

            
            // WooCommerce specific dependencies
            'edit_products' => array('read'),
            'publish_products' => array('read', 'edit_products'),
            'delete_products' => array('read', 'edit_products'),
            'edit_shop_orders' => array('read'),
            'read_shop_orders' => array('read'),
            
            // Custom post type dependencies
            'edit_posts' => array('read'),
            'publish_posts' => array('read', 'edit_posts'),
            'delete_posts' => array('read', 'edit_posts'),
        );
        
        // Add dependent capabilities
        foreach ($cap_list as $cap) {
            if (isset($dependencies[$cap])) {
                foreach ($dependencies[$cap] as $dep_cap) {
                    if (!in_array($dep_cap, $enhanced_caps)) {
                        $enhanced_caps[] = $dep_cap;
                        error_log('UMMP: Added dependent capability ' . $dep_cap . ' for ' . $cap);
                    }
                }
            }
        }
        
        // Always ensure 'read' capability is present for any admin-like access
        if (!in_array('read', $enhanced_caps)) {
            $enhanced_caps[] = 'read';
            error_log('UMMP: Added read capability as base requirement');
        }
        
        error_log('UMMP: Original capabilities: ' . print_r($cap_list, true));
        error_log('UMMP: Enhanced capabilities with dependencies: ' . print_r($enhanced_caps, true));
        
        return $enhanced_caps;
    }

    /**
     * Create a new role
     *
     * @param string $role_name Role name (slug)
     * @param string $display_name Role display name
     * @param array $capabilities Capabilities to assign
     * @return bool|WP_Error Success or error
     */
    public function create_role($role_name, $display_name, $capabilities = array()) {
        // Validate input
        if (empty($role_name) || empty($display_name)) {
            return new WP_Error('invalid_input', __('Role name and display name are required.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow creating administrator role
        if ($role_name === 'administrator' || $role_name === 'admin') {
            return new WP_Error('security_error', __('Cannot create administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow creating roles with admin-like names
        if (stripos($role_name, 'admin') !== false || stripos($role_name, 'administrator') !== false) {
            return new WP_Error('security_error', __('Cannot create roles with admin-like names for security reasons.', 'ummp'));
        }
        
        // Sanitize role name
        $role_name = sanitize_key($role_name);
        
        // Check if role already exists
        if (get_role($role_name)) {
            return new WP_Error('role_exists', __('A role with this name already exists.', 'ummp'));
        }
        
        // Add dependent capabilities automatically
        $enhanced_capabilities = $this->add_dependent_capabilities($capabilities);
        
        // Prepare capabilities array - ensure all values are boolean true
        $prepared_caps = array();
        foreach ($enhanced_capabilities as $cap) {
            $prepared_caps[$cap] = true;
        }
        
        // Create the role
        $result = add_role($role_name, $display_name, $prepared_caps);
        
        if (!$result) {
            return new WP_Error('creation_failed', __('Failed to create role.', 'ummp'));
        }
        
        // Log the activity
        $this->log_activity('role_created', array(
            'role_name' => $role_name,
            'display_name' => $display_name,
            'capabilities' => array_keys($prepared_caps),
            'original_capabilities' => $capabilities,
            'enhanced_capabilities' => $enhanced_capabilities
        ));
        
        // Trigger form access synchronization
        do_action('ummp_role_created', $role_name, $display_name, array_keys($prepared_caps));
        
        return true;
    }
    
    /**
     * Update an existing role
     *
     * @param string $role_name Role name (slug)
     * @param string $display_name Display name
     * @param array $capabilities Array of capabilities
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_role($role_name, $display_name, $capabilities) {
        // SAFETY CHECK: Never allow updating administrator role
        if ($role_name === 'administrator') {
            error_log('UMMP: BLOCKED attempt to modify administrator role');
            return new WP_Error('security_error', __('Cannot modify administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow updating current user's role if they're admin
        global $current_user;
        if ($current_user && $current_user->ID && current_user_can('manage_options')) {
            $current_user_roles = $current_user->roles;
            if (in_array($role_name, $current_user_roles)) {
                error_log('UMMP: BLOCKED attempt to modify current admin user role: ' . $role_name);
                return new WP_Error('security_error', __('Cannot modify your own admin role for security reasons.', 'ummp'));
            }
        }
        
        // Get the role
        $role = get_role($role_name);
        
        if (!$role) {
            return new WP_Error('role_not_found', __('Role not found.', 'ummp'));
        }
        
        error_log('UMMP: Updating role: ' . $role_name . ' with capabilities: ' . print_r($capabilities, true));
        
        // Add dependent capabilities automatically
        $enhanced_capabilities = $this->add_dependent_capabilities($capabilities);
        error_log('UMMP: Enhanced capabilities with dependencies: ' . print_r($enhanced_capabilities, true));
        
        // Update display name
        $role->name = $display_name;
        
        // Clear all existing capabilities first
        $role->capabilities = array();
        
        // Add new capabilities - ensure they are properly set
        foreach ($enhanced_capabilities as $cap) {
            $role->add_cap($cap);
            error_log('UMMP: Added capability: ' . $cap . ' to role: ' . $role_name);
        }
        
        // Force WordPress to save the role changes
        $this->force_save_role($role_name, $role);
        
        // SAFE cache clearing - won't affect current admin
        $this->clear_user_capability_cache($role_name);
        
        // Force refresh all users with this role to ensure capabilities are applied
        $this->force_refresh_all_role_users($role_name);
        
        // Log the activity
        $this->log_activity('role_updated', array(
            'role_name' => $role_name,
            'display_name' => $display_name,
            'capabilities' => $capabilities,
            'enhanced_capabilities' => $enhanced_capabilities,
            'updated_by' => $current_user ? $current_user->ID : 0
        ));
        
        // Trigger form access synchronization
        do_action('ummp_role_updated', $role_name, $display_name, $enhanced_capabilities);
        
        error_log('UMMP: Role updated successfully: ' . $role_name . ' by user: ' . ($current_user ? $current_user->ID : 'unknown'));
        
        return true;
    }

    /**
     * Force refresh all users with a specific role
     *
     * @param string $role_name Role name
     */
    private function force_refresh_all_role_users($role_name) {
        $users = get_users(array(
            'role' => $role_name,
            'fields' => 'ID'
        ));
        
        if (empty($users)) {
            error_log('UMMP: No users found with role: ' . $role_name);
            return;
        }
        
        foreach ($users as $user_id) {
            $this->force_user_capability_refresh($user_id, $role_name);
        }
        
        error_log('UMMP: Refreshed capabilities for ' . count($users) . ' users with role: ' . $role_name);
    }

    /**
     * Force save role changes to ensure they persist
     *
     * @param string $role_name Role name
     * @param WP_Role $role Role object
     */
    private function force_save_role($role_name, $role) {
        global $wp_roles;
        
        // Ensure wp_roles is initialized
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        // Update the role in wp_roles
        $wp_roles->roles[$role_name] = array(
            'name' => $role->name,
            'capabilities' => $wp_roles->get_role($role_name)->capabilities
        );
        
        // Force update the option
        update_option($wp_roles->role_key, $wp_roles->roles);
        
        // Clear role cache
        wp_cache_delete($role_name, 'user_roles');
        wp_cache_delete($role_name, 'user_meta');
        
        error_log('UMMP: Force saved role: ' . $role_name . ' with capabilities: ' . print_r($role->capabilities, true));
    }
    
    /**
     * Clear user capability cache for all users with a specific role
     * ENHANCED VERSION - Forces immediate capability updates
     */
    private function clear_user_capability_cache($role_name) {
        global $wpdb, $current_user;
        
        // SAFETY CHECK: Never clear cache for current admin user
        if ($current_user && $current_user->ID && current_user_can('manage_options')) {
            error_log('UMMP: Skipping cache clear for current admin user (ID: ' . $current_user->ID . ')');
        }
        
        // Get all users with this role EXCEPT current admin
        $exclude_user = $current_user && $current_user->ID ? $current_user->ID : 0;
        
        $users = get_users(array(
            'role' => $role_name,
            'fields' => 'ID',
            'exclude' => array($exclude_user)
        ));
        
        if (empty($users)) {
            error_log('UMMP: No users found with role: ' . $role_name . ' (excluding current admin)');
            return;
        }
        
        foreach ($users as $user_id) {
            // Skip if this is the current user
            if ($user_id == $exclude_user) {
                continue;
            }
            
            // AGGRESSIVE cache clearing for immediate updates
            $this->force_user_capability_refresh($user_id, $role_name);
        }
        
        // Clear role-related caches
        wp_cache_delete('user_roles', 'usermeta');
        
        // Force WordPress to reload roles
        global $wp_roles;
        if (isset($wp_roles)) {
            unset($wp_roles->roles);
            unset($wp_roles->role_objects);
            unset($wp_roles->role_names);
        }
        
        error_log('UMMP: Enhanced capability cache cleared for role: ' . $role_name . ' (' . count($users) . ' users affected, excluding current admin)');
    }
    
    /**
     * Force refresh user capabilities - ENHANCED VERSION
     * This method aggressively clears all caches and forces capability regeneration
     */
    private function force_user_capability_refresh($user_id, $role_name) {
        global $wpdb, $current_user;
        
        // SAFETY CHECK: Never refresh current admin user
        if ($current_user && $current_user->ID == $user_id && current_user_can('manage_options')) {
            error_log('UMMP: Skipping capability refresh for current admin user (ID: ' . $user_id . ')');
            return false;
        }
        
        error_log('UMMP: Force refreshing capabilities for user ' . $user_id . ' with role ' . $role_name);
        
        // Get the user object
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log('UMMP: User not found for capability refresh: ' . $user_id);
            return false;
        }
        
        // Clear all WordPress user caches
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'users');
        wp_cache_delete($user_id, 'user_meta');
        wp_cache_delete($user_id, 'usermeta');
        
        // Clear user meta cache
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_user_' . $user_id . '_%'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_user_' . $user_id . '_%'));
        
        // Clear role cache
        wp_cache_delete($role_name, 'user_roles');
        wp_cache_delete($role_name, 'user_meta');
        
        // Unset internal WP_User object properties
        unset($user->caps);
        unset($user->capabilities);
        unset($user->allcaps);
        unset($user->cap_key);
        
        // Force regeneration of capabilities
        $user->get_role_caps();
        
        // Update user meta to ensure capabilities are saved
        update_user_meta($user_id, 'capabilities', $user->capabilities);
        
        // Clear additional caches
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('users');
            wp_cache_flush_group('user_meta');
        }
        
        // Force WordPress to reload user data
        wp_cache_delete($user_id, 'users');
        wp_cache_delete($user_id, 'user_meta');
        
        error_log('UMMP: Successfully refreshed capabilities for user ' . $user_id . '. New capabilities: ' . print_r($user->capabilities, true));
        
        return true;
    }
    
    /**
     * Force refresh capabilities for all users with a specific role
     * This can be called manually if needed
     */
    public function force_refresh_role_capabilities($role_name) {
        if (empty($role_name)) {
            return new WP_Error('invalid_role', __('Role name is required.', 'ummp'));
        }
        
        // Get all users with this role
        $users = get_users(array(
            'role' => $role_name,
            'fields' => 'ID'
        ));
        
        if (empty($users)) {
            return new WP_Error('no_users', __('No users found with this role.', 'ummp'));
        }
        
        foreach ($users as $user_id) {
            $this->force_user_capability_refresh($user_id, $role_name);
        }
        
        // Log the activity
        $this->log_activity('capabilities_force_refreshed', array(
            'role_name' => $role_name,
            'users_affected' => count($users)
        ));
        
        return array(
            'success' => true,
            'users_affected' => count($users),
            'message' => sprintf(__('Capabilities refreshed for %d users with role "%s".', 'ummp'), count($users), $role_name)
        );
    }
    
    /**
     * Refresh current user's capabilities immediately without destroying session
     * SAFE VERSION - Only refreshes, doesn't clear
     */
    private function refresh_current_user_capabilities() {
        global $current_user;
        
        if (!$current_user || !$current_user->ID) {
            return;
        }
        
        // SAFETY CHECK: Only refresh if user is admin
        if (!current_user_can('manage_options')) {
            error_log('UMMP: Skipping capability refresh for non-admin user');
            return;
        }
        
        $user_id = $current_user->ID;
        
        // Gentle refresh - don't clear caches, just refresh
        $fresh_user = new WP_User($user_id);
        
        // Update global current_user with fresh capabilities
        $current_user = $fresh_user;
        
        // Force WordPress to re-check capabilities
        wp_set_current_user($user_id);
        
        error_log('UMMP: Safely refreshed current admin user capabilities (ID: ' . $user_id . ')');
    }
    
    /**
     * Delete a role
     *
     * @param string $role_name Role name to delete
     * @return bool|WP_Error Success or error
     */
    public function delete_role($role_name) {
        // Don't allow deletion of default WordPress roles
        $protected_roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
        
        if (in_array($role_name, $protected_roles)) {
            return new WP_Error('protected_role', __('Cannot delete a default WordPress role.', 'ummp'));
        }
        
        $role = get_role($role_name);
        if (!$role) {
            return new WP_Error('role_not_found', __('Role not found.', 'ummp'));
        }
        
        // Get users with this role and reassign them to subscriber
        $users = get_users(array('role' => $role_name));
        foreach ($users as $user) {
            $user_obj = new WP_User($user->ID);
            $user_obj->set_role('subscriber');
        }
        
        // Remove the role
        remove_role($role_name);
        
        // Log the activity
        $this->log_activity('role_deleted', array(
            'role_name' => $role_name,
            'users_reassigned' => count($users)
        ));
        
        return true;
    }
    
    /**
     * Clone a role
     *
     * @param string $source_role Source role name
     * @param string $new_role_name New role name
     * @param string $new_display_name New role display name
     * @return bool|WP_Error Success or error
     */
    public function clone_role($source_role, $new_role_name, $new_display_name) {
        // SAFETY CHECK: Never allow cloning administrator role
        if ($source_role === 'administrator' || $new_role_name === 'administrator') {
            return new WP_Error('security_error', __('Cannot clone administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow creating roles with admin-like names
        if (stripos($new_role_name, 'admin') !== false || stripos($new_role_name, 'administrator') !== false) {
            return new WP_Error('security_error', __('Cannot create roles with admin-like names for security reasons.', 'ummp'));
        }
        
        $source = get_role($source_role);
        
        if (!$source) {
            return new WP_Error('source_not_found', __('Source role not found.', 'ummp'));
        }
        
        return $this->create_role($new_role_name, $new_display_name, $source->capabilities);
    }
    
    /**
     * Assign role to user
     *
     * @param int $user_id User ID
     * @param string $role_name Role name
     * @return bool|WP_Error Success or error
     */
    public function assign_role_to_user($user_id, $role_name) {
        // SAFETY CHECK: Never allow assigning administrator role
        if ($role_name === 'administrator') {
            error_log('UMMP: BLOCKED attempt to assign administrator role to user ' . $user_id);
            return new WP_Error('security_error', __('Cannot assign administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow assigning admin role to current user
        global $current_user;
        if ($current_user && $current_user->ID == $user_id && current_user_can('manage_options')) {
            error_log('UMMP: BLOCKED attempt to assign role to current admin user');
            return new WP_Error('security_error', __('Cannot assign roles to yourself for security reasons.', 'ummp'));
        }
        
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return new WP_Error('user_not_found', __('User not found.', 'ummp'));
        }
        
        $role = get_role($role_name);
        if (!$role) {
            return new WP_Error('role_not_found', __('Role not found.', 'ummp'));
        }
        
        $user->set_role($role_name);
        
        // Sync form access for the user
        do_action('ummp_role_assigned', $user_id, $role_name);
        
        // Log the activity
        $this->log_activity('role_assigned', array(
            'user_id' => $user_id,
            'username' => $user->user_login,
            'role_name' => $role_name,
            'assigned_by' => $current_user ? $current_user->ID : 0
        ));
        
        error_log('UMMP: Role assigned safely: ' . $role_name . ' to user ' . $user_id . ' by user: ' . ($current_user ? $current_user->ID : 'unknown'));
        
        return true;
    }
    
    /**
     * Bulk assign roles to multiple users
     *
     * @param array $user_ids Array of user IDs
     * @param string $role_name Role name
     * @return array Results
     */
    public function bulk_assign_roles($user_ids, $role_name) {
        // SAFETY CHECK: Never allow bulk assigning administrator role
        if ($role_name === 'administrator') {
            error_log('UMMP: BLOCKED bulk attempt to assign administrator role');
            return new WP_Error('security_error', __('Cannot bulk assign administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow bulk assigning to current user
        global $current_user;
        $current_user_id = $current_user ? $current_user->ID : 0;
        if (in_array($current_user_id, $user_ids)) {
            error_log('UMMP: BLOCKED bulk attempt to assign role to current user');
            return new WP_Error('security_error', __('Cannot bulk assign roles including yourself for security reasons.', 'ummp'));
        }
        
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($user_ids as $user_id) {
            $result = $this->assign_role_to_user($user_id, $role_name);
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = sprintf(
                    __('User ID %d: %s', 'ummp'),
                    $user_id,
                    $result->get_error_message()
                );
            } else {
                $results['success']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Export roles to JSON
     *
     * @param array $role_names Roles to export (empty for all)
     * @return string JSON data
     */
    public function export_roles($role_names = array()) {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        $export_data = array(
            'version' => UMMP_VERSION,
            'timestamp' => current_time('mysql'),
            'roles' => array()
        );
        
        $roles_to_export = empty($role_names) ? array_keys($wp_roles->roles) : $role_names;
        
        foreach ($roles_to_export as $role_name) {
            // SAFETY CHECK: Never export administrator role
            if ($role_name === 'administrator' || $role_name === 'admin') {
                continue;
            }
            
            // SAFETY CHECK: Never export admin-like roles
            if (stripos($role_name, 'admin') !== false || stripos($role_name, 'administrator') !== false) {
                continue;
            }
            
            if (isset($wp_roles->roles[$role_name])) {
                $export_data['roles'][$role_name] = $wp_roles->roles[$role_name];
            }
        }
        
        return wp_json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import roles from JSON
     *
     * @param string $json_data JSON data
     * @param bool $overwrite Whether to overwrite existing roles
     * @return bool|WP_Error Success or error
     */
    public function import_roles($json_data, $overwrite = false) {
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON data.', 'ummp'));
        }
        
        if (!isset($data['roles']) || !is_array($data['roles'])) {
            return new WP_Error('invalid_format', __('Invalid import format.', 'ummp'));
        }
        
        $imported = 0;
        $skipped = 0;
        
        foreach ($data['roles'] as $role_name => $role_data) {
            // SAFETY CHECK: Never allow importing administrator role
            if ($role_name === 'administrator' || $role_name === 'admin') {
                error_log('UMMP: BLOCKED import of administrator role: ' . $role_name);
                $skipped++;
                continue;
            }
            
            // SAFETY CHECK: Never allow importing roles with admin-like names
            if (stripos($role_name, 'admin') !== false || stripos($role_name, 'administrator') !== false) {
                error_log('UMMP: BLOCKED import of admin-like role: ' . $role_name);
                $skipped++;
                continue;
            }
            
            $exists = get_role($role_name);
            
            if ($exists && !$overwrite) {
                $skipped++;
                continue;
            }
            
            if ($exists && $overwrite) {
                remove_role($role_name);
            }
            
            add_role(
                $role_name,
                $role_data['name'],
                isset($role_data['capabilities']) ? $role_data['capabilities'] : array()
            );
            
            $imported++;
        }
        
        // Log the activity
        $this->log_activity('roles_imported', array(
            'imported' => $imported,
            'skipped' => $skipped,
            'overwrite' => $overwrite
        ));
        
        return array(
            'imported' => $imported,
            'skipped' => $skipped
        );
    }
    
    /**
     * Log activity
     */
    private function log_activity($action, $details = array()) {
        // Use database manager for comprehensive logging
        $database = new UMMP_Database();
        
        $object_id = isset($details['role_name']) ? $details['role_name'] : null;
        $description = $this->format_activity_description($action, $details);
        
        $database->log_activity(
            get_current_user_id(),
            $action,
            'role',
            $object_id,
            $description,
            $details
        );
        
        // Also keep the old option-based logging for backward compatibility
        $logs = get_option('ummp_activity_logs', array());
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'action' => sanitize_text_field($action),
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        );
        
        array_unshift($logs, $log_entry);
        
        // Keep only last 100 entries in options (database has more)
        $logs = array_slice($logs, 0, 100);
        
        update_option('ummp_activity_logs', $logs);
    }
    
    /**
     * Format activity description for better readability
     */
    private function format_activity_description($action, $details) {
        switch ($action) {
            case 'role_created':
                return sprintf(__('Created role "%s" with %d capabilities', 'ummp'), 
                    $details['role_name'] ?? 'Unknown', 
                    count($details['capabilities'] ?? array())
                );
            case 'role_updated':
                return sprintf(__('Updated role "%s"', 'ummp'), 
                    $details['role_name'] ?? 'Unknown'
                );
            case 'role_deleted':
                return sprintf(__('Deleted role "%s"', 'ummp'), 
                    $details['role_name'] ?? 'Unknown'
                );
            case 'role_assigned':
                return sprintf(__('Assigned role "%s" to user %s', 'ummp'), 
                    $details['role_name'] ?? 'Unknown',
                    $details['user_login'] ?? 'Unknown'
                );
            case 'roles_imported':
                return sprintf(__('Imported %d roles', 'ummp'), 
                    $details['imported_count'] ?? 0
                );
            default:
                return $action;
        }
    }
    
    /**
     * AJAX handler for creating role
     */
    public function ajax_create_role() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        $display_name = sanitize_text_field($_POST['display_name']);
        $capabilities = isset($_POST['capabilities']) ? array_map('sanitize_text_field', $_POST['capabilities']) : array();
        
        // SAFETY CHECK: Never allow creating administrator role
        if ($role_name === 'administrator' || $role_name === 'admin') {
            wp_send_json_error(__('Cannot create administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow creating roles with admin-like names
        if (stripos($role_name, 'admin') !== false || stripos($role_name, 'administrator') !== false) {
            wp_send_json_error(__('Cannot create roles with admin-like names for security reasons.', 'ummp'));
        }
        
        // Debug: Log what we received
        error_log('UMMP Debug - Role Name: ' . $role_name);
        error_log('UMMP Debug - Display Name: ' . $display_name);
        error_log('UMMP Debug - Capabilities: ' . print_r($capabilities, true));
        
        $result = $this->create_role($role_name, $display_name, $capabilities);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // Debug: Check if role was actually created with capabilities
            $created_role = get_role($role_name);
            if ($created_role) {
                error_log('UMMP Debug - Created role capabilities: ' . print_r($created_role->capabilities, true));
            }
            wp_send_json_success(__('Role created successfully.', 'ummp'));
        }
    }
    
    /**
     * AJAX handler for updating role
     */
    public function ajax_update_role() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        $display_name = sanitize_text_field($_POST['display_name']);
        $capabilities = isset($_POST['capabilities']) ? array_map('sanitize_text_field', $_POST['capabilities']) : array();
        
        // SAFETY CHECK: Never allow updating administrator role
        if ($role_name === 'administrator') {
            wp_send_json_error(__('Cannot modify administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow updating current user's role
        $current_user_roles = wp_get_current_user()->roles;
        if (in_array($role_name, $current_user_roles)) {
            wp_send_json_error(__('Cannot modify your own role for security reasons.', 'ummp'));
        }
        
        // Debug: Log what we received
        error_log('UMMP Debug Update - Role Name: ' . $role_name);
        error_log('UMMP Debug Update - Display Name: ' . $display_name);
        error_log('UMMP Debug Update - Capabilities: ' . print_r($capabilities, true));
        
        // Send capabilities as a simple array to update_role
        $result = $this->update_role($role_name, $display_name, $capabilities);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Role updated successfully.', 'ummp'));
        }
    }
    
    /**
     * AJAX handler for deleting role
     */
    public function ajax_delete_role() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!UsersMenuManagerPro::current_user_can_manage()) {
            wp_die(__('Insufficient permissions.', 'ummp'));
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        
        // SAFETY CHECK: Never allow deleting administrator role
        if ($role_name === 'administrator') {
            wp_send_json_error(__('Cannot delete administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow deleting current user's role
        $current_user_roles = wp_get_current_user()->roles;
        if (in_array($role_name, $current_user_roles)) {
            wp_send_json_error(__('Cannot delete your own role for security reasons.', 'ummp'));
        }
        
        $result = $this->delete_role($role_name);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Role deleted successfully.', 'ummp'));
        }
    }
    
    /**
     * AJAX handler for cloning role
     */
    public function ajax_clone_role() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!UsersMenuManagerPro::current_user_can_manage()) {
            wp_die(__('Insufficient permissions.', 'ummp'));
        }
        
        $source_role = sanitize_text_field($_POST['source_role']);
        $new_role_name = sanitize_text_field($_POST['new_role_name']);
        $new_display_name = sanitize_text_field($_POST['new_display_name']);
        
        // SAFETY CHECK: Never allow cloning administrator role
        if ($source_role === 'administrator' || $new_role_name === 'administrator') {
            wp_send_json_error(__('Cannot clone administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow creating roles with admin-like names
        if (stripos($new_role_name, 'admin') !== false || stripos($new_role_name, 'administrator') !== false) {
            wp_send_json_error(__('Cannot create roles with admin-like names for security reasons.', 'ummp'));
        }
        
        $result = $this->clone_role($source_role, $new_role_name, $new_display_name);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Role cloned successfully.', 'ummp'));
        }
    }
    
    /**
     * AJAX handler for assigning role
     */
    public function ajax_assign_role() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_users') && !current_user_can('ummp_assign_roles')) {
            wp_die(__('Insufficient permissions.', 'ummp'));
        }
        
        $user_id = intval($_POST['user_id']);
        $role_name = sanitize_text_field($_POST['role_name']);
        
        // SAFETY CHECK: Never allow assigning administrator role
        if ($role_name === 'administrator') {
            wp_send_json_error(__('Cannot assign administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow assigning role to current user
        $current_user_id = get_current_user_id();
        if ($user_id == $current_user_id) {
            wp_send_json_error(__('Cannot assign roles to yourself for security reasons.', 'ummp'));
        }
        
        $result = $this->assign_role_to_user($user_id, $role_name);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Role assigned successfully.', 'ummp'));
        }
    }
    
    /**
     * AJAX handler for bulk assigning roles
     */
    public function ajax_bulk_assign_roles() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_users') && !current_user_can('ummp_assign_roles')) {
            wp_die(__('Insufficient permissions.', 'ummp'));
        }
        
        $user_ids = array_map('intval', $_POST['user_ids']);
        $role_name = sanitize_text_field($_POST['role_name']);
        
        // SAFETY CHECK: Never allow bulk assigning administrator role
        if ($role_name === 'administrator') {
            wp_send_json_error(__('Cannot bulk assign administrator role for security reasons.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow bulk assigning to current user
        $current_user_id = get_current_user_id();
        if (in_array($current_user_id, $user_ids)) {
            wp_send_json_error(__('Cannot bulk assign roles including yourself for security reasons.', 'ummp'));
        }
        
        $results = $this->bulk_assign_roles($user_ids, $role_name);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for exporting roles
     */
    public function ajax_export_roles() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!UsersMenuManagerPro::current_user_can_manage()) {
            wp_die(__('Insufficient permissions.', 'ummp'));
        }
        
        $role_names = isset($_POST['role_names']) ? array_map('sanitize_text_field', $_POST['role_names']) : array();
        
        // SAFETY CHECK: Never allow exporting administrator roles
        $role_names = array_filter($role_names, function($role_name) {
            return $role_name !== 'administrator' && 
                   $role_name !== 'admin' && 
                   stripos($role_name, 'admin') === false && 
                   stripos($role_name, 'administrator') === false;
        });
        
        $export_data = $this->export_roles($role_names);
        
        wp_send_json_success(array('data' => $export_data));
    }
    
    /**
     * AJAX handler for importing roles
     */
    public function ajax_import_roles() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $json_data = stripslashes($_POST['json_data']);
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
        
        // SAFETY CHECK: Never allow importing administrator roles
        $data = json_decode($json_data, true);
        if (is_array($data) && isset($data['roles'])) {
            foreach ($data['roles'] as $role_name => $role_data) {
                if ($role_name === 'administrator' || $role_name === 'admin' || 
                    stripos($role_name, 'admin') !== false || stripos($role_name, 'administrator') !== false) {
                    wp_send_json_error(__('Cannot import administrator or admin-like roles for security reasons.', 'ummp'));
                }
            }
        }
        
        $result = $this->import_roles($json_data, $overwrite);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX handler for getting role capabilities
     */
    public function ajax_get_role_capabilities() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ummp_ajax_nonce')) {
            wp_send_json_error('Invalid nonce.');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        
        if (empty($role_name)) {
            wp_send_json_error('Role name is required.');
        }
        
        // Get the role
        $role = get_role($role_name);
        
        if (!$role) {
            wp_send_json_error('Role not found.');
        }
        
        // Get all available capabilities
        $all_capabilities = $this->get_all_capabilities();
        
        // Get current role capabilities
        $role_capabilities = $role->capabilities;
        
        // Prepare response data
        $response_data = array();
        
        foreach ($all_capabilities as $group_key => $group_data) {
            $response_data[$group_key] = array();
            
            foreach ($group_data['capabilities'] as $cap) {
                $response_data[$group_key][$cap] = isset($role_capabilities[$cap]) && $role_capabilities[$cap];
            }
        }
        
        error_log('UMMP: Role capabilities for ' . $role_name . ': ' . print_r($response_data, true));
        
        wp_send_json_success($response_data);
    }
    
    /**
     * AJAX handler for forcing capability refresh
     */
    public function ajax_force_refresh_capabilities() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        
        if (empty($role_name)) {
            wp_send_json_error(__('Role name is required.', 'ummp'));
        }
        
        // SAFETY CHECK: Never allow refreshing administrator role capabilities
        if ($role_name === 'administrator') {
            wp_send_json_error(__('Cannot refresh administrator role capabilities for security reasons.', 'ummp'));
        }
        
        $result = $this->force_refresh_role_capabilities($role_name);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX handler for fixing existing roles
     */
    public function ajax_fix_existing_roles() {
        check_ajax_referer('ummp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ummp'));
        }
        
        $fixed_count = $this->fix_existing_roles();
        
        wp_send_json_success(array(
            'success' => true,
            'fixed_count' => $fixed_count,
            'message' => sprintf(__('Fixed %d existing roles with missing dependent capabilities.', 'ummp'), $fixed_count)
        ));
    }
}
