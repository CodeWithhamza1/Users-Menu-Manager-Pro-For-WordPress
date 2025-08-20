<?php
/**
 * Database Manager for Users Menu Manager Pro
 * 
 * Handles dynamic database table creation and management
 * for various plugin modules
 *
 * @package UMMP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UMMP_Database {
    
    /**
     * Database version for tracking schema changes
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Table prefix
     */
    private $table_prefix;
    
    /**
     * WordPress database object
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'ummp_';
    }
    
    /**
     * Initialize database tables
     */
    public function init() {
        $this->maybe_create_tables();
        $this->maybe_upgrade_tables();
    }
    
    /**
     * Create all required tables if they don't exist
     */
    public function maybe_create_tables() {
        $current_version = get_option('ummp_db_version', '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->create_activity_logs_table();
            $this->create_role_assignments_table();
            $this->create_menu_restrictions_table();
            $this->create_user_sessions_table();
            
            update_option('ummp_db_version', self::DB_VERSION);
            error_log('UMMP Database: Tables created/updated to version ' . self::DB_VERSION);
        }
    }
    
    /**
     * Handle database upgrades
     */
    public function maybe_upgrade_tables() {
        $current_version = get_option('ummp_db_version', '0.0.0');
        
        // Future upgrade logic can be added here
        // Example:
        // if (version_compare($current_version, '1.1.0', '<')) {
        //     $this->upgrade_to_1_1_0();
        // }
    }
    
    /**
     * Create activity logs table
     */
    private function create_activity_logs_table() {
        $table_name = $this->table_prefix . 'activity_logs';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(100) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id varchar(100) DEFAULT NULL,
            description text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('UMMP Database: Activity logs table created');
    }
    
    /**
     * Create role assignments tracking table
     */
    private function create_role_assignments_table() {
        $table_name = $this->table_prefix . 'role_assignments';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            role_name varchar(100) NOT NULL,
            assigned_by bigint(20) unsigned NOT NULL,
            assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            removed_at datetime DEFAULT NULL,
            removed_by bigint(20) unsigned DEFAULT NULL,
            status enum('active', 'removed') DEFAULT 'active',
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY role_name (role_name),
            KEY assigned_by (assigned_by),
            KEY status (status),
            UNIQUE KEY unique_active_assignment (user_id, role_name, status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('UMMP Database: Role assignments table created');
    }
    
    /**
     * Create menu restrictions table
     */
    private function create_menu_restrictions_table() {
        $table_name = $this->table_prefix . 'menu_restrictions';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            role_name varchar(100) NOT NULL,
            menu_slug varchar(200) NOT NULL,
            restriction_type enum('hide', 'show') DEFAULT 'hide',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY role_name (role_name),
            KEY menu_slug (menu_slug),
            UNIQUE KEY unique_restriction (role_name, menu_slug)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('UMMP Database: Menu restrictions table created');
    }
    
    /**
     * Create user sessions table for advanced tracking
     */
    private function create_user_sessions_table() {
        $table_name = $this->table_prefix . 'user_sessions';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_token varchar(255) NOT NULL,
            login_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            logout_time datetime DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            role_at_login varchar(100) DEFAULT NULL,
            capabilities_at_login longtext DEFAULT NULL,
            status enum('active', 'expired', 'logged_out') DEFAULT 'active',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_token (session_token),
            KEY status (status),
            KEY login_time (login_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('UMMP Database: User sessions table created');
    }
    
    /**
     * Log activity to database
     */
    public function log_activity($user_id, $action, $object_type, $object_id = null, $description = null, $metadata = null) {
        $table_name = $this->table_prefix . 'activity_logs';
        
        $data = array(
            'user_id' => (int) $user_id,
            'action' => sanitize_text_field($action),
            'object_type' => sanitize_text_field($object_type),
            'object_id' => $object_id ? sanitize_text_field($object_id) : null,
            'description' => $description ? sanitize_textarea_field($description) : null,
            'metadata' => $metadata ? wp_json_encode($metadata) : null,
            'ip_address' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null,
            'created_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert($table_name, $data);
        
        if ($result === false) {
            error_log('UMMP Database: Failed to log activity - ' . $this->wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Get activity logs with filters
     */
    public function get_activity_logs($args = array()) {
        $table_name = $this->table_prefix . 'activity_logs';
        
        $defaults = array(
            'user_id' => null,
            'action' => null,
            'object_type' => null,
            'limit' => 100,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC',
            'date_from' => null,
            'date_to' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $query_params = array();
        
        if ($args['user_id']) {
            $where_conditions[] = 'user_id = %d';
            $query_params[] = $args['user_id'];
        }
        
        if ($args['action']) {
            $where_conditions[] = 'action = %s';
            $query_params[] = $args['action'];
        }
        
        if ($args['object_type']) {
            $where_conditions[] = 'object_type = %s';
            $query_params[] = $args['object_type'];
        }
        
        if ($args['date_from']) {
            $where_conditions[] = 'created_at >= %s';
            $query_params[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where_conditions[] = 'created_at <= %s';
            $query_params[] = $args['date_to'];
        }
        
        $where = implode(' AND ', $where_conditions);
        $order_by = sanitize_sql_orderby($args['order_by'] . ' ' . $args['order']);
        $limit = $args['limit'] > 0 ? 'LIMIT ' . (int) $args['offset'] . ', ' . (int) $args['limit'] : '';
        
        $query = "SELECT * FROM $table_name WHERE $where ORDER BY $order_by $limit";
        
        if (!empty($query_params)) {
            return $this->wpdb->get_results($this->wpdb->prepare($query, $query_params));
        } else {
            return $this->wpdb->get_results($query);
        }
    }
    
    /**
     * Track role assignment
     */
    public function track_role_assignment($user_id, $role_name, $assigned_by, $notes = null) {
        $table_name = $this->table_prefix . 'role_assignments';
        
        // First, mark any existing active assignment as removed
        $this->wpdb->update(
            $table_name,
            array(
                'status' => 'removed',
                'removed_at' => current_time('mysql'),
                'removed_by' => get_current_user_id()
            ),
            array(
                'user_id' => $user_id,
                'status' => 'active'
            )
        );
        
        // Then create new assignment
        $data = array(
            'user_id' => (int) $user_id,
            'role_name' => sanitize_text_field($role_name),
            'assigned_by' => (int) $assigned_by,
            'assigned_at' => current_time('mysql'),
            'status' => 'active',
            'notes' => $notes ? sanitize_textarea_field($notes) : null
        );
        
        return $this->wpdb->insert($table_name, $data);
    }
    
    /**
     * Save menu restrictions
     */
    public function save_menu_restrictions($role_name, $restricted_menus) {
        $table_name = $this->table_prefix . 'menu_restrictions';
        
        // Delete existing restrictions for this role
        $this->wpdb->delete($table_name, array('role_name' => $role_name));
        
        // Insert new restrictions
        foreach ($restricted_menus as $menu_slug) {
            $this->wpdb->insert(
                $table_name,
                array(
                    'role_name' => sanitize_text_field($role_name),
                    'menu_slug' => sanitize_text_field($menu_slug),
                    'restriction_type' => 'hide',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
        
        return true;
    }
    
    /**
     * Get menu restrictions for a role
     */
    public function get_menu_restrictions($role_name) {
        $table_name = $this->table_prefix . 'menu_restrictions';
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT menu_slug FROM $table_name WHERE role_name = %s AND restriction_type = 'hide'",
                $role_name
            )
        );
        
        return wp_list_pluck($results, 'menu_slug');
    }
    
    /**
     * Track user session
     */
    public function track_user_session($user_id, $session_token) {
        $table_name = $this->table_prefix . 'user_sessions';
        
        $user = get_user_by('id', $user_id);
        $capabilities = $user ? $user->allcaps : array();
        
        $data = array(
            'user_id' => (int) $user_id,
            'session_token' => sanitize_text_field($session_token),
            'login_time' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null,
            'role_at_login' => $user ? implode(',', $user->roles) : null,
            'capabilities_at_login' => wp_json_encode($capabilities),
            'status' => 'active'
        );
        
        return $this->wpdb->insert($table_name, $data);
    }
    
    /**
     * Clear old activity logs (older than X days)
     */
    public function cleanup_old_logs($days = 90) {
        $table_name = $this->table_prefix . 'activity_logs';
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s",
                $date_threshold
            )
        );
        
        if ($deleted !== false) {
            error_log("UMMP Database: Cleaned up $deleted old activity log entries");
        }
        
        return $deleted;
    }
    
    /**
     * Export activity logs to JSON
     */
    public function export_activity_logs($args = array()) {
        $logs = $this->get_activity_logs($args);
        
        $export_data = array(
            'export_date' => current_time('mysql'),
            'export_version' => UMMP_VERSION,
            'total_records' => count($logs),
            'logs' => $logs
        );
        
        return wp_json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Get database statistics
     */
    public function get_database_stats() {
        $stats = array();
        
        $tables = array(
            'activity_logs' => 'Activity Logs',
            'role_assignments' => 'Role Assignments',
            'menu_restrictions' => 'Menu Restrictions',
            'user_sessions' => 'User Sessions'
        );
        
        foreach ($tables as $table_suffix => $label) {
            $table_name = $this->table_prefix . $table_suffix;
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $stats[$table_suffix] = array(
                'label' => $label,
                'count' => (int) $count,
                'size' => $this->get_table_size($table_name)
            );
        }
        
        return $stats;
    }
    
    /**
     * Get table size in bytes
     */
    private function get_table_size($table_name) {
        $size = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                FROM information_schema.TABLES 
                WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            )
        );
        
        return $size ? $size . ' MB' : 'Unknown';
    }
    
    /**
     * Get user's IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Drop all plugin tables (for uninstall)
     */
    public function drop_tables() {
        $tables = array(
            'activity_logs',
            'role_assignments', 
            'menu_restrictions',
            'user_sessions'
        );
        
        foreach ($tables as $table_suffix) {
            $table_name = $this->table_prefix . $table_suffix;
            $this->wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        delete_option('ummp_db_version');
        error_log('UMMP Database: All plugin tables dropped');
    }
}
