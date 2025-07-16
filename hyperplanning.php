<?php
/**
 * Plugin Name: HyperPlanning-v2
 * Plugin URI: https://hyperplanning.com
 * Description: Gestion centralisée des calendriers de formateurs avec synchronisation multi-sources
 * Version: 2.0.0
 * Author: HyperPlanning Team
 * License: GPL v2 or later
 * Text Domain: hyperplanning
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Constantes du plugin
define('HYPERPLANNING_VERSION', '1.0.0');
define('HYPERPLANNING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HYPERPLANNING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HYPERPLANNING_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Vérifier si Composer est installé
if (!file_exists(HYPERPLANNING_PLUGIN_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('HyperPlanning : Les dépendances Composer ne sont pas installées. Veuillez exécuter "composer install" dans le dossier du plugin.', 'hyperplanning'); ?></p>
        </div>
        <?php
    });
    return;
}

// Autoloader Composer
require_once HYPERPLANNING_PLUGIN_DIR . 'vendor/autoload.php';

// Classe principale du plugin
class HyperPlanning {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->loadDependencies();
        $this->defineHooks();
    }
    
    private function loadDependencies() {
        // Core
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/class-hp-loader.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/class-hp-activator.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/class-hp-deactivator.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/class-hp-constants.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/helpers.php';
        
        // Models
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/models/class-hp-trainer.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/models/class-hp-calendar.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/models/class-hp-event.php';
        
        // Admin
        if (is_admin()) {
            require_once HYPERPLANNING_PLUGIN_DIR . 'admin/class-hp-admin.php';
            require_once HYPERPLANNING_PLUGIN_DIR . 'admin/class-hp-settings.php';
        }
        
        // Public
        if (!is_admin()) {
            require_once HYPERPLANNING_PLUGIN_DIR . 'public/class-hp-public.php';
            require_once HYPERPLANNING_PLUGIN_DIR . 'public/class-hp-shortcodes.php';
        }
        
        // Sync
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/sync/class-hp-sync-manager.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/sync/class-hp-google-sync.php';
        //require_once HYPERPLANNING_PLUGIN_DIR . 'includes/sync/class-hp-ical-sync.php';
        
        // API
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/api/class-hp-rest-controller.php';
    }
    
    private function defineHooks() {
        $loader = new HP_Loader();
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array('HP_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('HP_Deactivator', 'deactivate'));
        
        // Admin hooks
        if (is_admin()) {
            $admin = new HP_Admin();
            $loader->addAction('admin_menu', $admin, 'addAdminMenu');
            $loader->addAction('admin_enqueue_scripts', $admin, 'enqueueStyles');
            $loader->addAction('admin_enqueue_scripts', $admin, 'enqueueScripts');
            
            // AJAX handlers pour l'admin
            $loader->addAction('wp_ajax_hp_get_events', $admin, 'ajaxGetEvents');
            $loader->addAction('wp_ajax_hp_save_event', $admin, 'ajaxSaveEvent');
            $loader->addAction('wp_ajax_hp_delete_event', $admin, 'ajaxDeleteEvent');
        }
        
        // Public hooks
        if (!is_admin()) {
            $public = new HP_Public();
            $loader->addAction('wp_enqueue_scripts', $public, 'enqueueStyles');
            $loader->addAction('wp_enqueue_scripts', $public, 'enqueueScripts');
            
            // Shortcodes
            $shortcodes = new HP_Shortcodes();
            $loader->addAction('init', $shortcodes, 'registerShortcodes');
            
            // Formulaire de soumission
            $loader->addAction('init', $public, 'handleEventSubmission');
        }
        
        // API REST
        $api = new HP_REST_Controller();
        $loader->addAction('rest_api_init', $api, 'registerRoutes');
        
        // Synchronisation
        $syncManager = new HP_Sync_Manager();
        $loader->addAction('hp_sync_cron', $syncManager, 'runSync');
        
        // Cron
        $loader->addAction('init', $this, 'scheduleCron');
        
        // i18n
        $loader->addAction('plugins_loaded', $this, 'loadTextdomain');
        
        // Admin notices
        $loader->addAction('admin_notices', $this, 'adminNotices');
        
        $loader->run();
    }
    
    public function scheduleCron() {
        if (!wp_next_scheduled('hp_sync_cron')) {
            wp_schedule_event(time(), 'hourly', 'hp_sync_cron');
        }
        
        if (!wp_next_scheduled('hp_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'hp_daily_cleanup');
        }
    }
    
    public function loadTextdomain() {
        load_plugin_textdomain(
            'hyperplanning',
            false,
            dirname(HYPERPLANNING_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    public function adminNotices() {
        // Vérifier la version PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('HyperPlanning nécessite PHP 7.4 ou supérieur.', 'hyperplanning'); ?></p>
            </div>
            <?php
        }
        
        // Vérifier si les tables sont créées
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}hp_trainers'");
        if (!$table_exists) {
            ?>
            <div class="notice notice-warning">
                <p><?php _e('Les tables HyperPlanning ne sont pas créées. Veuillez désactiver et réactiver le plugin.', 'hyperplanning'); ?></p>
            </div>
            <?php
        }
        
        // Message après activation
        if (get_transient('hp_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('HyperPlanning a été activé avec succès !', 'hyperplanning'); ?></p>
                <p><?php printf(__('<a href="%s">Commencez par ajouter un formateur</a> ou <a href="%s">configurez les paramètres</a>.', 'hyperplanning'), 
                    admin_url('admin.php?page=hyperplanning-trainers&action=new'),
                    admin_url('admin.php?page=hyperplanning-settings')
                ); ?></p>
            </div>
            <?php
            delete_transient('hp_activation_notice');
        }
    }
}

// Initialisation du plugin
add_action('plugins_loaded', function() {
    // Vérifier les dépendances
    if (!function_exists('wp_roles')) {
        return;
    }
    
    // Initialiser le plugin
    HyperPlanning::getInstance();
});

// Hook de désinstallation
register_uninstall_hook(__FILE__, array('HP_Deactivator', 'uninstall'));