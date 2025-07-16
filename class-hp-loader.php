<?php
// includes/class-hp-loader.php

/**
 * Gestionnaire des hooks WordPress
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */
 
 
 if ( class_exists( 'HP_Loader' ) ) {
    return;
}


class HP_Loader {
    
    /**
     * Les actions enregistrées
     * @var array
     */
    protected $actions;
    
    /**
     * Les filtres enregistrés
     * @var array
     */
    protected $filters;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }
    
    /**
     * Ajouter une nouvelle action
     */
    public function addAction($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Ajouter un nouveau filtre
     */
    public function addFilter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Méthode utilitaire pour enregistrer les actions et filtres
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
        
        return $hooks;
    }
    
    /**
     * Enregistrer les filtres et actions avec WordPress
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'], 
                array($hook['component'], $hook['callback']), 
                $hook['priority'], 
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'], 
                array($hook['component'], $hook['callback']), 
                $hook['priority'], 
                $hook['accepted_args']
            );
        }
    }
}

// includes/class-hp-activator.php

/**
 * Classe d'activation du plugin
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */
class HP_Activator {
    
    /**
     * Méthode principale d'activation
     */
    public static function activate() {
        self::createTables();
        self::createDefaultOptions();
        self::createRoles();
        self::scheduleEvents();
        
        // Nettoyer les permaliens
        flush_rewrite_rules();
        
        // Marquer la version installée
        update_option('hyperplanning_version', HYPERPLANNING_VERSION);
        update_option('hyperplanning_activation_time', current_time('timestamp'));
    }
    
    /**
     * Créer les tables de base de données
     */
    private static function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table des formateurs
        $sql_trainers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_trainers (
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
        
        // Table des calendriers
        $sql_calendars = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_calendars (
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
        
        // Table des événements
        $sql_events = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_events (
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
        
        // Table de synchronisation
        $sql_sync_log = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_sync_log (
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
        
        // Table des conflits
        $sql_conflicts = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hp_conflicts (
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_trainers);
        dbDelta($sql_calendars);
        dbDelta($sql_events);
        dbDelta($sql_sync_log);
        dbDelta($sql_conflicts);
        
        // Ajouter les index pour les performances
        self::createIndexes();
    }
    
    /**
     * Créer les index additionnels
     */
    private static function createIndexes() {
        global $wpdb;
        
        // Index composé pour les recherches d'événements par date et formateur
        $wpdb->query("CREATE INDEX idx_events_date_trainer ON {$wpdb->prefix}hp_events (start_date, end_date, trainer_id)");
        
        // Index pour la recherche de conflits
        $wpdb->query("CREATE INDEX idx_events_conflicts ON {$wpdb->prefix}hp_events (trainer_id, start_date, end_date, status)");
    }
    
    /**
     * Créer les options par défaut
     */
    private static function createDefaultOptions() {
        $default_options = array(
            'hp_google_client_id' => '',
            'hp_google_client_secret' => '',
            'hp_default_calendar_view' => 'month',
            'hp_time_zone' => get_option('timezone_string', 'UTC'),
            'hp_date_format' => get_option('date_format', 'Y-m-d'),
            'hp_time_format' => get_option('time_format', 'H:i'),
            'hp_week_starts_on' => 1, // Lundi
            'hp_enable_conflicts_detection' => 1,
            'hp_auto_sync_interval' => 60, // minutes
            'hp_max_events_display' => 500,
            'hp_cache_duration' => 3600, // secondes
            'hp_enable_public_submission' => 0,
            'hp_notification_email' => get_option('admin_email'),
            'hp_color_scheme' => 'default',
            'hp_enable_ical_export' => 1,
            'hp_enable_trainer_self_management' => 1,
            'hp_default_event_duration' => 60, // minutes
            'hp_business_hours_start' => '08:00',
            'hp_business_hours_end' => '18:00',
            'hp_exclude_weekends' => 0,
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
        
        // Options cachées pour usage interne
        add_option('hp_db_version', '1.0.0');
        add_option('hp_last_cleanup', current_time('timestamp'));
    }
    
    /**
     * Créer les rôles et capacités
     */
    private static function createRoles() {
        // Rôle pour les formateurs
        add_role(
            'hp_trainer',
            __('Formateur', 'hyperplanning'),
            array(
                // Capacités WordPress de base
                'read' => true,
                'upload_files' => true,
                
                // Capacités HyperPlanning
                'hp_view_calendar' => true,
                'hp_manage_own_calendar' => true,
                'hp_create_events' => true,
                'hp_edit_own_events' => true,
                'hp_delete_own_events' => true,
                'hp_view_own_stats' => true,
                'hp_export_own_calendar' => true,
            )
        );
        
        // Rôle pour les coordinateurs
        add_role(
            'hp_coordinator',
            __('Coordinateur', 'hyperplanning'),
            array(
                // Capacités WordPress de base
                'read' => true,
                'upload_files' => true,
                
                // Capacités HyperPlanning
                'hp_view_calendar' => true,
                'hp_view_all_calendars' => true,
                'hp_manage_trainers' => true,
                'hp_create_events' => true,
                'hp_edit_all_events' => true,
                'hp_delete_all_events' => true,
                'hp_manage_conflicts' => true,
                'hp_view_reports' => true,
                'hp_export_calendars' => true,
            )
        );
        
        // Ajouter des capacités aux rôles existants
        $roles_capabilities = array(
            'administrator' => array(
                'hp_manage_calendars',
                'hp_manage_trainers',
                'hp_manage_settings',
                'hp_view_all_calendars',
                'hp_edit_all_events',
                'hp_delete_all_events',
                'hp_manage_conflicts',
                'hp_view_reports',
                'hp_export_calendars',
                'hp_manage_sync',
                'hp_view_logs',
            ),
            'editor' => array(
                'hp_view_calendar',
                'hp_view_all_calendars',
                'hp_create_events',
                'hp_view_reports',
            ),
            'author' => array(
                'hp_view_calendar',
                'hp_create_events',
                'hp_edit_own_events',
            ),
        );
        
        foreach ($roles_capabilities as $role_name => $capabilities) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Planifier les événements cron
     */
    private static function scheduleEvents() {
        // Synchronisation automatique
        if (!wp_next_scheduled('hp_sync_cron')) {
            wp_schedule_event(time(), 'hourly', 'hp_sync_cron');
        }
        
        // Nettoyage quotidien
        if (!wp_next_scheduled('hp_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'hp_daily_cleanup');
        }
        
        // Rapport hebdomadaire
        if (!wp_next_scheduled('hp_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'hp_weekly_report');
        }
    }
}

// includes/class-hp-deactivator.php

/**
 * Classe de désactivation du plugin
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */
class HP_Deactivator {
    
    /**
     * Méthode principale de désactivation
     */
    public static function deactivate() {
        // Nettoyer les tâches cron
        self::clearScheduledEvents();
        
        // Nettoyer le cache
        self::clearCache();
        
        // Nettoyer les transients
        self::clearTransients();
        
        // Réinitialiser les permaliens
        flush_rewrite_rules();
        
        // Marquer le temps de désactivation
        update_option('hyperplanning_deactivation_time', current_time('timestamp'));
        
        // Hook pour permettre des actions supplémentaires
        do_action('hp_plugin_deactivated');
    }
    
    /**
     * Supprimer les événements planifiés
     */
    private static function clearScheduledEvents() {
        wp_clear_scheduled_hook('hp_sync_cron');
        wp_clear_scheduled_hook('hp_daily_cleanup');
        wp_clear_scheduled_hook('hp_weekly_report');
        
        // Supprimer tous les crons personnalisés qui pourraient exister
        $crons = _get_cron_array();
        if (!empty($crons)) {
            foreach ($crons as $timestamp => $cron) {
                foreach ($cron as $hook => $args) {
                    if (strpos($hook, 'hp_') === 0) {
                        wp_unschedule_event($timestamp, $hook);
                    }
                }
            }
        }
    }
    
    /**
     * Vider le cache
     */
    private static function clearCache() {
        // Cache WordPress
        wp_cache_flush();
        
        // Cache des objets HyperPlanning
        wp_cache_delete_group('hyperplanning');
        
        // Cache personnalisé si utilisé
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('hyperplanning_events');
            wp_cache_flush_group('hyperplanning_trainers');
            wp_cache_flush_group('hyperplanning_calendars');
        }
    }
    
    /**
     * Supprimer les transients
     */
    private static function clearTransients() {
        global $wpdb;
        
        // Supprimer tous les transients HyperPlanning
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_hp_%' 
            OR option_name LIKE '_transient_timeout_hp_%'"
        );
        
        // Supprimer les transients de site
        $wpdb->query(
            "DELETE FROM {$wpdb->sitemeta} 
            WHERE meta_key LIKE '_site_transient_hp_%' 
            OR meta_key LIKE '_site_transient_timeout_hp_%'"
        );
    }
    
    /**
     * Désinstallation complète (appelée uniquement lors de la suppression)
     */
    public static function uninstall() {
        // Vérifier les permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Vérifier si l'option de suppression complète est activée
        if (!get_option('hp_delete_data_on_uninstall', false)) {
            return;
        }
        
        // Supprimer les tables
        self::dropTables();
        
        // Supprimer les options
        self::deleteOptions();
        
        // Supprimer les rôles
        self::removeRoles();
        
        // Supprimer les fichiers uploadés
        self::deleteUploadedFiles();
    }
    
    /**
     * Supprimer les tables de la base de données
     */
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
    
    /**
     * Supprimer toutes les options du plugin
     */
    private static function deleteOptions() {
        global $wpdb;
        
        // Liste des options à supprimer
        $options = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} 
            WHERE option_name LIKE 'hp_%' 
            OR option_name LIKE 'hyperplanning_%'"
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Pour les sites multisite
        if (is_multisite()) {
            $options = $wpdb->get_col(
                "SELECT meta_key FROM {$wpdb->sitemeta} 
                WHERE meta_key LIKE 'hp_%' 
                OR meta_key LIKE 'hyperplanning_%'"
            );
            
            foreach ($options as $option) {
                delete_site_option($option);
            }
        }
    }
    
    /**
     * Supprimer les rôles personnalisés
     */
    private static function removeRoles() {
        remove_role('hp_trainer');
        remove_role('hp_coordinator');
        
        // Supprimer les capacités des rôles existants
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
    
    /**
     * Supprimer les fichiers uploadés
     */
    private static function deleteUploadedFiles() {
        $upload_dir = wp_upload_dir();
        $hp_upload_dir = $upload_dir['basedir'] . '/hyperplanning';
        
        if (file_exists($hp_upload_dir)) {
            // Fonction récursive pour supprimer un dossier
            $delete_dir = function($dir) use (&$delete_dir) {
                if (!is_dir($dir)) return;
                
                $files = array_diff(scandir($dir), array('.', '..'));
                foreach ($files as $file) {
                    $path = $dir . '/' . $file;
                    is_dir($path) ? $delete_dir($path) : unlink($path);
                }
                rmdir($dir);
            };
            
            $delete_dir($hp_upload_dir);
        }
    }
}