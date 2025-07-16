<?php
/**
 * Classe de désactivation du plugin
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */
 
 if ( class_exists( 'HP_Deactivator' ) ) {
    return;
}


class HP_Deactivator {
    
    public static function deactivate() {
        self::clearScheduledEvents();
        self::clearCache();
        self::clearTransients();
        
        flush_rewrite_rules();
        
        update_option('hyperplanning_deactivation_time', current_time('timestamp'));
        
        do_action('hp_plugin_deactivated');
    }
    
    private static function clearScheduledEvents() {
        wp_clear_scheduled_hook('hp_sync_cron');
        wp_clear_scheduled_hook('hp_daily_cleanup');
        wp_clear_scheduled_hook('hp_weekly_report');
    }
    
    private static function clearCache() {
        wp_cache_flush();
        
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('hyperplanning');
            wp_cache_flush_group('hyperplanning_events');
            wp_cache_flush_group('hyperplanning_trainers');
            wp_cache_flush_group('hyperplanning_calendars');
        }
    }
    
    private static function clearTransients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_hp_%' 
            OR option_name LIKE '_transient_timeout_hp_%'"
        );
    }
    
    public static function uninstall() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        if (!get_option('hp_delete_data_on_uninstall', false)) {
            return;
        }
        
        self::dropTables();
        self::deleteOptions();
        self::removeRoles();
    }
    
    private static function dropTables() {
        global $wpdb;
        
        $tables = array(
            'hp_conflicts',
            'hp_sync_log',
            'hp_events',
            'hp_calendars',
            'hp_trainers',
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
    }
    
    private static function deleteOptions() {
        global $wpdb;
        
        $options = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} 
            WHERE option_name LIKE 'hp_%' 
            OR option_name LIKE 'hyperplanning_%'"
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
    
    private static function removeRoles() {
        remove_role('hp_trainer');
        remove_role('hp_coordinator');
        
        $all_roles = wp_roles()->roles;
        
        foreach ($all_roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                $capabilities = array_keys($role->capabilities);
                foreach ($capabilities as $cap) {
                    if (strpos($cap, 'hp_') === 0) {
                        $role->remove_cap($cap);
                    }
                }
            }
        }
    }
}