<?php
/**
 * Plugin Name: HyperPlanning
 * Plugin URI: https://hyperplanning.com
 * Description: Gestion centralisée des calendriers de formateurs avec synchronisation multi-sources
 * Version: 1.0.0
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
    private $loader;
    
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
        
        // Models - Un fichier par classe
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/models/class-hp-trainer.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/models/class-hp-calendar.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/models/class-hp-event.php';
        
        // Admin
        if (is_admin()) {
            require_once HYPERPLANNING_PLUGIN_DIR . 'admin/class-hp-admin.php';
            require_once HYPERPLANNING_PLUGIN_DIR . 'admin/class-hp-settings.php';
        }
        
        // Public - Toujours charger pour les AJAX et API REST
        require_once HYPERPLANNING_PLUGIN_DIR . 'public/class-hp-public.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'public/class-hp-shortcodes.php';
        
        // Sync - Un fichier par classe
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/sync/class-hp-sync-manager.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/sync/class-hp-google-sync.php';
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/sync/class-hp-ical-sync.php';
        
        // API
        require_once HYPERPLANNING_PLUGIN_DIR . 'includes/api/class-hp-rest-controller.php';
        
        $this->loader = new HP_Loader();
    }
    
    private function defineHooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array('HP_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('HP_Deactivator', 'deactivate'));
        
        // Admin hooks
        if (is_admin()) {
            $admin = new HP_Admin();
            $this->loader->addAction('admin_menu', $admin, 'addAdminMenu');
            $this->loader->addAction('admin_enqueue_scripts', $admin, 'enqueueStyles');
            $this->loader->addAction('admin_enqueue_scripts', $admin, 'enqueueScripts');
            
            // Settings actions
            $settings = new HP_Settings();
            $this->loader->addAction('admin_init', $settings, 'handleActions');
        }
        
        // Public hooks
        $public = new HP_Public();
        $this->loader->addAction('wp_enqueue_scripts', $public, 'enqueueStyles');
        $this->loader->addAction('wp_enqueue_scripts', $public, 'enqueueScripts');
        $this->loader->addAction('init', $public, 'handleEventSubmission');
        $this->loader->addAction('init', $public, 'handleCustomRequests');
        $this->loader->addFilter('body_class', $public, 'addBodyClasses');
        
        // AJAX handlers pour le public
        $this->loader->addAction('wp_ajax_hp_get_public_events', $public, 'ajaxGetPublicEvents');
        $this->loader->addAction('wp_ajax_nopriv_hp_get_public_events', $public, 'ajaxGetPublicEvents');
        $this->loader->addAction('wp_ajax_hp_get_event_details', $public, 'ajaxGetEventDetails');
        $this->loader->addAction('wp_ajax_nopriv_hp_get_event_details', $public, 'ajaxGetEventDetails');
        
        // Shortcodes
        $shortcodes = new HP_Shortcodes();
        $this->loader->addAction('init', $shortcodes, 'registerShortcodes');
        
        // API REST
        $api = new HP_REST_Controller();
        $this->loader->addAction('rest_api_init', $api, 'registerRoutes');
        
        // Synchronisation
        $syncManager = new HP_Sync_Manager();
        $this->loader->addAction('hp_sync_cron', $syncManager, 'runSync');
        
        // Cron
        $this->loader->addAction('init', $this, 'scheduleCron');
        
        // i18n
        $this->loader->addAction('plugins_loaded', $this, 'loadTextdomain');
        
        // Admin notices
        $this->loader->addAction('admin_notices', $this, 'adminNotices');
        
        $this->loader->run();
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
    HyperPlanning::getInstance();
});

// Hook de désinstallation
register_uninstall_hook(__FILE__, array('HP_Deactivator', 'uninstall'));
