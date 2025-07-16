<?php
/**
 * Classe d'activation du plugin
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */

// Sécurité
if (!defined('ABSPATH')) {
    exit;
}

// Protection contre les inclusions multiples
if (class_exists('HP_Activator')) {
    return;
}

class HP_Activator {
    
    public static function activate() {
        // Vérifier la version PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('HyperPlanning nécessite PHP 7.4 ou supérieur.');
        }
        
        // Créer les tables
        self::createTables();
        
        // Créer les options par défaut
        self::createDefaultOptions();
        
        // Créer les rôles
        self::createRoles();
        
        // Planifier les tâches cron
        self::scheduleEvents();
        
        // Nettoyer les permaliens
        flush_rewrite_rules();
        
        // Marquer la version installée
        update_option('hyperplanning_version', HYPERPLANNING_VERSION);
        update_option('hyperplanning_activation_time', current_time('timestamp'));
        
        // Notification d'activation
        set_transient('hp_activation_notice', true, 60);
    }
    
    private static function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Utiliser dbDelta correctement
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Table des formateurs
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_trainers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50),
            specialties text,
            calendar_color varchar(7) DEFAULT '#0073aa',
            google_calendar_id varchar(255),
            ical_url text,
            sync_enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY email (email)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Table des calendriers
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_calendars (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            trainer_id bigint(20),
            name varchar(255) NOT NULL,
            description text,
            type varchar(50) DEFAULT 'training',
            visibility varchar(20) DEFAULT 'public',
            sync_source varchar(50),
            sync_id varchar(255),
            last_sync datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trainer_id (trainer_id),
            KEY type (type),
            KEY visibility (visibility)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Table des événements
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            calendar_id bigint(20) NOT NULL,
            trainer_id bigint(20),
            external_id varchar(255),
            title varchar(255) NOT NULL,
            description text,
            location varchar(255),
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            all_day tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'confirmed',
            attendees text,
            recurrence_rule text,
            parent_event_id bigint(20),
            color varchar(7),
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY calendar_id (calendar_id),
            KEY trainer_id (trainer_id),
            KEY start_date (start_date),
            KEY end_date (end_date),
            KEY status (status),
            KEY external_id (external_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Table de synchronisation
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_sync_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            calendar_id bigint(20),
            sync_type varchar(50),
            status varchar(20),
            message text,
            events_synced int DEFAULT 0,
            started_at datetime,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY calendar_id (calendar_id),
            KEY sync_type (sync_type),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Table des conflits
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_conflicts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            conflicting_event_id bigint(20) NOT NULL,
            conflict_type varchar(50),
            resolved tinyint(1) DEFAULT 0,
            resolved_by bigint(20),
            resolved_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY conflicting_event_id (conflicting_event_id),
            KEY resolved (resolved)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Vérifier que les tables ont été créées
        $tables_created = true;
        $tables = array('hp_trainers', 'hp_calendars', 'hp_events', 'hp_sync_log', 'hp_conflicts');
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $tables_created = false;
                error_log("HyperPlanning: Table $table_name n'a pas été créée");
            }
        }
        
        if (!$tables_created) {
            error_log("HyperPlanning: Erreur lors de la création des tables");
        }
    }
    
    private static function createDefaultOptions() {
        $default_options = array(
            'hp_google_client_id' => '',
            'hp_google_client_secret' => '',
            'hp_default_calendar_view' => 'month',
            'hp_time_zone' => get_option('timezone_string', 'UTC'),
            'hp_date_format' => get_option('date_format', 'Y-m-d'),
            'hp_time_format' => get_option('time_format', 'H:i'),
            'hp_week_starts_on' => 1,
            'hp_enable_conflicts_detection' => 1,
            'hp_auto_sync_interval' => 60,
            'hp_max_events_display' => 500,
            'hp_cache_duration' => 3600,
            'hp_db_version' => '1.0.0'
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    private static function createRoles() {
        // Supprimer les anciens rôles s'ils existent
        remove_role('hp_trainer');
        remove_role('hp_coordinator');
        
        // Créer le rôle formateur
        add_role(
            'hp_trainer',
            __('Formateur', 'hyperplanning'),
            array(
                'read' => true,
                'upload_files' => true,
                'hp_view_calendar' => true,
                'hp_manage_own_calendar' => true,
                'hp_create_events' => true,
                'hp_edit_own_events' => true,
                'hp_delete_own_events' => true,
            )
        );
        
        // Créer le rôle coordinateur
        add_role(
            'hp_coordinator',
            __('Coordinateur', 'hyperplanning'),
            array(
                'read' => true,
                'upload_files' => true,
                'hp_view_calendar' => true,
                'hp_view_all_calendars' => true,
                'hp_manage_trainers' => true,
                'hp_create_events' => true,
                'hp_edit_all_events' => true,
                'hp_delete_all_events' => true,
            )
        );
        
        // Ajouter les capacités à l'administrateur
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $caps = array(
                'hp_manage_calendars',
                'hp_manage_trainers',
                'hp_manage_settings',
                'hp_view_all_calendars',
                'hp_edit_all_events',
                'hp_delete_all_events',
                'hp_manage_sync',
            );
            
            foreach ($caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    private static function scheduleEvents() {
        // Nettoyer les anciens crons
        wp_clear_scheduled_hook('hp_sync_cron');
        wp_clear_scheduled_hook('hp_daily_cleanup');
        
        // Planifier les nouveaux
        if (!wp_next_scheduled('hp_sync_cron')) {
            wp_schedule_event(time() + 3600, 'hourly', 'hp_sync_cron');
        }
        
        if (!wp_next_scheduled('hp_daily_cleanup')) {
            wp_schedule_event(time() + 86400, 'daily', 'hp_daily_cleanup');
        }
    }
}