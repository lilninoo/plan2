<?php
/**
 * Gestion des paramètres du plugin
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */
 
 if ( class_exists( 'HP_Settings' ) ) {
    return;
}


// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

class HP_Settings {
    
    private $options;
    private $tabs;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->options = $this->getOptions();
        $this->tabs = $this->getTabs();
    }
    
    /**
     * Obtenir toutes les options
     */
    private function getOptions() {
        return array(
            // API Google
            'hp_google_client_id' => get_option('hp_google_client_id', ''),
            'hp_google_client_secret' => get_option('hp_google_client_secret', ''),
            'hp_google_redirect_uri' => admin_url('admin.php?page=hyperplanning-google-auth'),
            
            // Affichage
            'hp_default_calendar_view' => get_option('hp_default_calendar_view', 'month'),
            'hp_time_zone' => get_option('hp_time_zone', get_option('timezone_string', 'UTC')),
            'hp_date_format' => get_option('hp_date_format', get_option('date_format', 'Y-m-d')),
            'hp_time_format' => get_option('hp_time_format', get_option('time_format', 'H:i')),
            'hp_week_starts_on' => get_option('hp_week_starts_on', 1),
            'hp_time_slot_duration' => get_option('hp_time_slot_duration', 30),
            'hp_business_hours_start' => get_option('hp_business_hours_start', '08:00'),
            'hp_business_hours_end' => get_option('hp_business_hours_end', '18:00'),
            'hp_exclude_weekends' => get_option('hp_exclude_weekends', 0),
            'hp_default_event_duration' => get_option('hp_default_event_duration', 60),
            'hp_color_scheme' => get_option('hp_color_scheme', 'default'),
            
            // Synchronisation
            'hp_enable_conflicts_detection' => get_option('hp_enable_conflicts_detection', 1),
            'hp_auto_sync_interval' => get_option('hp_auto_sync_interval', 60),
            'hp_max_sync_events' => get_option('hp_max_sync_events', 500),
            'hp_sync_future_days' => get_option('hp_sync_future_days', 365),
            'hp_sync_past_days' => get_option('hp_sync_past_days', 30),
            
            // Performance
            'hp_max_events_display' => get_option('hp_max_events_display', 500),
            'hp_cache_duration' => get_option('hp_cache_duration', 3600),
            'hp_enable_ajax_loading' => get_option('hp_enable_ajax_loading', 1),
            
            // Permissions
            'hp_enable_public_submission' => get_option('hp_enable_public_submission', 0),
            'hp_enable_trainer_self_management' => get_option('hp_enable_trainer_self_management', 1),
            'hp_require_event_approval' => get_option('hp_require_event_approval', 0),
            
            // Notifications
            'hp_notification_email' => get_option('hp_notification_email', get_option('admin_email')),
            'hp_enable_event_notifications' => get_option('hp_enable_event_notifications', 1),
            'hp_enable_conflict_notifications' => get_option('hp_enable_conflict_notifications', 1),
            'hp_enable_sync_notifications' => get_option('hp_enable_sync_notifications', 0),
            
            // Export/Import
            'hp_enable_ical_export' => get_option('hp_enable_ical_export', 1),
            'hp_enable_csv_export' => get_option('hp_enable_csv_export', 1),
            'hp_enable_print_view' => get_option('hp_enable_print_view', 1),
            
            // Avancé
            'hp_debug_mode' => get_option('hp_debug_mode', 0),
            'hp_delete_data_on_uninstall' => get_option('hp_delete_data_on_uninstall', 0),
            'hp_enable_rest_api' => get_option('hp_enable_rest_api', 1),
            'hp_api_rate_limit' => get_option('hp_api_rate_limit', 100),
        );
    }
    
    /**
     * Définir les onglets
     */
    private function getTabs() {
        return array(
            'general' => __('Général', 'hyperplanning'),
            'display' => __('Affichage', 'hyperplanning'),
            'sync' => __('Synchronisation', 'hyperplanning'),
            'notifications' => __('Notifications', 'hyperplanning'),
            'permissions' => __('Permissions', 'hyperplanning'),
            'advanced' => __('Avancé', 'hyperplanning'),
        );
    }
    
    /**
     * Afficher la page des paramètres
     */
    public function render() {
        // Traiter la soumission du formulaire
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hp_settings_nonce'])) {
            if (wp_verify_nonce($_POST['hp_settings_nonce'], 'save_settings')) {
                $this->saveSettings();
            }
        }
        
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        ?>
        <div class="wrap">
            <h1><?php _e('Paramètres HyperPlanning', 'hyperplanning'); ?></h1>
            
            <?php $this->renderTabs($current_tab); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_settings', 'hp_settings_nonce'); ?>
                <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
                
                <?php
                switch ($current_tab) {
                    case 'general':
                        $this->renderGeneralTab();
                        break;
                    case 'display':
                        $this->renderDisplayTab();
                        break;
                    case 'sync':
                        $this->renderSyncTab();
                        break;
                    case 'notifications':
                        $this->renderNotificationsTab();
                        break;
                    case 'permissions':
                        $this->renderPermissionsTab();
                        break;
                    case 'advanced':
                        $this->renderAdvancedTab();
                        break;
                }
                ?>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('Enregistrer les paramètres', 'hyperplanning'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Afficher les onglets
     */
    private function renderTabs($current) {
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($this->tabs as $tab => $label) {
            $class = ($tab === $current) ? ' nav-tab-active' : '';
            $url = admin_url('admin.php?page=hyperplanning-settings&tab=' . $tab);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $class . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
    }
    
    /**
     * Onglet Général
     */
    private function renderGeneralTab() {
        ?>
        <h2><?php _e('Paramètres généraux', 'hyperplanning'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('API Google Calendar', 'hyperplanning'); ?></th>
                <td>
                    <fieldset>
                        <p>
                            <label>
                                <?php _e('Client ID', 'hyperplanning'); ?><br>
                                <input type="text" name="settings[hp_google_client_id]" value="<?php echo esc_attr($this->options['hp_google_client_id']); ?>" class="regular-text">
                            </label>
                        </p>
                        <p>
                            <label>
                                <?php _e('Client Secret', 'hyperplanning'); ?><br>
                                <input type="password" name="settings[hp_google_client_secret]" value="<?php echo esc_attr($this->options['hp_google_client_secret']); ?>" class="regular-text">
                            </label>
                        </p>
                        <p>
                            <label>
                                <?php _e('URI de redirection', 'hyperplanning'); ?><br>
                                <input type="text" value="<?php echo esc_url($this->options['hp_google_redirect_uri']); ?>" class="regular-text" readonly>
                                <span class="description"><?php _e('Copiez cette URL dans la console Google', 'hyperplanning'); ?></span>
                            </label>
                        </p>
                        <p class="description">
                            <?php printf(
                                __('Obtenez vos identifiants depuis la <a href="%s" target="_blank">Console Google Developers</a>', 'hyperplanning'),
                                'https://console.developers.google.com'
                            ); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hp_notification_email"><?php _e('Email de notification', 'hyperplanning'); ?></label>
                </th>
                <td>
                    <input type="email" id="hp_notification_email" name="settings[hp_notification_email]" value="<?php echo esc_attr($this->options['hp_notification_email']); ?>" class="regular-text">
                    <p class="description"><?php _e('Adresse email pour recevoir les notifications système', 'hyperplanning'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Onglet Affichage
     */
    private function renderDisplayTab() {
        ?>
        <h2><?php _e('Paramètres d\'affichage', 'hyperplanning'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="hp_default_calendar_view"><?php _e('Vue par défaut', 'hyperplanning'); ?></label>
                </th>
                <td>
                    <select id="hp_default_calendar_view" name="settings[hp_default_calendar_view]">
                        <option value="month" <?php selected($this->options['hp_default_calendar_view'], 'month'); ?>><?php _e('Mois', 'hyperplanning'); ?></option>
                        <option value="week" <?php selected($this->options['hp_default_calendar_view'], 'week'); ?>><?php _e('Semaine', 'hyperplanning'); ?></option>
                        <option value="day" <?php selected($this->options['hp_default_calendar_view'], 'day'); ?>><?php _e('Jour', 'hyperplanning'); ?></option>
                        <option value="list" <?php selected($this->options['hp_default_calendar_view'], 'list'); ?>><?php _e('Liste', 'hyperplanning'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hp_time_zone"><?php _e('Fuseau horaire', 'hyperplanning'); ?></label>
                </th>
                <td>
                    <select id="hp_time_zone" name="settings[hp_time_zone]">
                        <?php echo wp_timezone_choice($this->options['hp_time_zone']); ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hp_week_starts_on"><?php _e('La semaine commence le', 'hyperplanning'); ?></label>
                </th>
                <td>
                    <select id="hp_week_starts_on" name="settings[hp_week_starts_on]">
                        <option value="0" <?php selected($this->options['hp_week_starts_on'], 0); ?>><?php _e('Dimanche', 'hyperplanning'); ?></option>
                        <option value="1" <?php selected($this->options['hp_week_starts_on'], 1); ?>><?php _e('Lundi', 'hyperplanning'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Heures de travail', 'hyperplanning'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <?php _e('Début', 'hyperplanning'); ?>
                            <input type="time" name="settings[hp_business_hours_start]" value="<?php echo esc_attr($this->options['hp_business_hours_start']); ?>">
                        </label>
                        <label>
                            <?php _e('Fin', 'hyperplanning'); ?>
                            <input type="time" name="settings[hp_business_hours_end]" value="<?php echo esc_attr($this->options['hp_business_hours_end']); ?>">
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Options d\'affichage', 'hyperplanning'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="settings[hp_exclude_weekends]" value="1" <?php checked($this->options['hp_exclude_weekends'], 1); ?>>
                            <?php _e('Masquer les week-ends', 'hyperplanning'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hp_time_slot_duration"><?php _e('Durée des créneaux', 'hyperplanning'); ?></label>
                </th>
                <td>
                    <select id="hp_time_slot_duration" name="settings[hp_time_slot_duration]">
                        <option value="15" <?php selected($this->options['hp_time_slot_duration'], 15); ?>><?php _e('15 minutes', 'hyperplanning'); ?></option>
                        <option value="30" <?php selected($this->options['hp_time_slot_duration'], 30); ?>><?php _e('30 minutes', 'hyperplanning'); ?></option>
                        <option value="60" <?php selected($this->options['hp_time_slot_duration'], 60); ?>><?php _e('1 heure', 'hyperplanning'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hp_default_event_duration"><?php _e('Durée par défaut des événements', 'hyperplanning'); ?></label>
                </th>
                <td>
                    <input type="number" id="hp_default_event_duration" name="settings[hp_default_event_duration]" value="<?php echo esc_attr($this->options['hp_default_event_duration']); ?>" min="15" max="480" step="15">
                    <?php _e('minutes', 'hyperplanning'); ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Onglet Synchronisation
     */
    private function renderSyncTab() {
        ?>
        <h2><?php _e('Paramètres de synchronisation', 'hyperplanning'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Détection des conflits', 'hyperplanning'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="settings[hp_enable_conflicts_detection]" value="1" <?php checked($this->options['hp_enable_conflicts_detection'], 1); ?>>
                        <?php _e('Activer la détection automatique des conflits d\'horaires', 'hyperplanning'); ?>
                    </label>
                    <p class="description"><?php _e('Empêche la création d\'événements qui se chevauchent pour un même formateur', 'hyperplanning'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hp_auto_sync_interval"><?php _e('Intervalle de synchronisation automatique', 'hyperplanning'); ?></label>
                </th>
                <td>
                    <input type="number" id="hp_auto_sync_interval" name="settings[hp_auto_sync_interval]" value="<?php echo esc_attr($this->options['hp_auto_sync_interval']); ?>" min="15" max="1440">
                    <?php _e('minutes', 'hyperplanning'); ?>
                    <p class="description"><?php _e('Minimum : 15 minutes. Recommandé : 60 minutes', 'hyperplanning'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hp_max_sync_events"><?php _e('Nombre maximum d\'événements à synchroniser', 'hyperplanning'); ?></label>
                </th>
                <td>
                    <input type="number" id="hp_max_sync_events" name="settings[hp_max_sync_events]" value="<?php echo esc_attr($this->options['hp_max_sync_events']); ?>" min="100" max="5000" step="100">
                    <p class="description"><?php _e('Limite le nombre d\'événements récupérés par synchronisation', 'hyperplanning'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Période de synchronisation', 'hyperplanning'); ?></th>
                <td>
                    <fieldset>
                        <p>
                            <label>
                                <?php _e('Jours passés', 'hyperplanning'); ?>
                                <input type="number" name="settings[hp_sync_past_days]" value="<?php echo esc_attr($this->options['hp_sync_past_days']); ?>" min="0" max="365">
                            </label>
                        </p>
                        <p>
                            <label>
                                <?php _e('Jours futurs', 'hyperplanning'); ?>
                                <input type="number" name="settings[hp_sync_future_days]" value="<?php echo esc_attr($this->options['hp_sync_future_days']); ?>" min="30" max="730">
                            </label>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Onglet Notifications
     */
    private function renderNotificationsTab() {
        ?>
        <h2><?php _e('Paramètres de notifications', 'hyperplanning'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Types de notifications', 'hyperplanning'); ?></th>
                <td>
                    <fieldset>
                        <p>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_event_notifications]" value="1" <?php checked($this->options['hp_enable_event_notifications'], 1); ?>>
                                <?php _e('Notifications d\'événements (création, modification, suppression)', 'hyperplanning'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_conflict_notifications]" value="1" <?php checked($this->options['hp_enable_conflict_notifications'], 1); ?>>
                                <?php _e('Notifications de conflits d\'horaires', 'hyperplanning'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_sync_notifications]" value="1" <?php checked($this->options['hp_enable_sync_notifications'], 0); ?>>
                                <?php _e('Notifications de synchronisation (erreurs uniquement)', 'hyperplanning'); ?>
                            </label>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Onglet Permissions
     */
    private function renderPermissionsTab() {
        ?>
        <h2><?php _e('Paramètres de permissions', 'hyperplanning'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Soumission publique', 'hyperplanning'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="settings[hp_enable_public_submission]" value="1" <?php checked($this->options['hp_enable_public_submission'], 1); ?>>
                        <?php _e('Autoriser les visiteurs à soumettre des événements', 'hyperplanning'); ?>
                    </label>
                    <p class="description"><?php _e('Les événements soumis devront être approuvés', 'hyperplanning'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Gestion des formateurs', 'hyperplanning'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="settings[hp_enable_trainer_self_management]" value="1" <?php checked($this->options['hp_enable_trainer_self_management'], 1); ?>>
                        <?php _e('Les formateurs peuvent gérer leur propre calendrier', 'hyperplanning'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Approbation des événements', 'hyperplanning'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="settings[hp_require_event_approval]" value="1" <?php checked($this->options['hp_require_event_approval'], 0); ?>>
                        <?php _e('Les nouveaux événements doivent être approuvés', 'hyperplanning'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Capacités par rôle', 'hyperplanning'); ?></h3>
        <?php $this->renderRoleCapabilities(); ?>
        <?php
    }
    
    /**
     * Afficher les capacités par rôle
     */
    private function renderRoleCapabilities() {
        $roles = wp_roles()->roles;
        $hp_capabilities = array(
            'hp_view_calendar' => __('Voir le calendrier', 'hyperplanning'),
            'hp_create_events' => __('Créer des événements', 'hyperplanning'),
            'hp_edit_own_events' => __('Modifier ses événements', 'hyperplanning'),
            'hp_edit_all_events' => __('Modifier tous les événements', 'hyperplanning'),
            'hp_delete_own_events' => __('Supprimer ses événements', 'hyperplanning'),
            'hp_delete_all_events' => __('Supprimer tous les événements', 'hyperplanning'),
            'hp_manage_trainers' => __('Gérer les formateurs', 'hyperplanning'),
            'hp_manage_calendars' => __('Gérer les calendriers', 'hyperplanning'),
            'hp_manage_settings' => __('Gérer les paramètres', 'hyperplanning'),
        );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Capacité', 'hyperplanning'); ?></th>
                    <?php foreach ($roles as $role_key => $role): ?>
                        <th><?php echo translate_user_role($role['name']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hp_capabilities as $cap => $label): ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <?php foreach ($roles as $role_key => $role_data): ?>
                            <td>
                                <?php
                                $role = get_role($role_key);
                                $checked = $role && $role->has_cap($cap);
                                ?>
                                <input type="checkbox" name="capabilities[<?php echo esc_attr($role_key); ?>][<?php echo esc_attr($cap); ?>]" value="1" <?php checked($checked); ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Onglet Avancé
     */
    private function renderAdvancedTab() {
        ?>
        <h2><?php _e('Paramètres avancés', 'hyperplanning'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Performance', 'hyperplanning'); ?></th>
                <td>
                    <fieldset>
                        <p>
                            <label>
                                <?php _e('Durée du cache', 'hyperplanning'); ?>
                                <input type="number" name="settings[hp_cache_duration]" value="<?php echo esc_attr($this->options['hp_cache_duration']); ?>" min="0" max="86400" step="60">
                                <?php _e('secondes', 'hyperplanning'); ?>
                            </label>
                            <span class="description"><?php _e('0 pour désactiver le cache', 'hyperplanning'); ?></span>
                        </p>
                        <p>
                            <label>
                                <?php _e('Nombre maximum d\'événements affichés', 'hyperplanning'); ?>
                                <input type="number" name="settings[hp_max_events_display]" value="<?php echo esc_attr($this->options['hp_max_events_display']); ?>" min="100" max="5000" step="100">
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_ajax_loading]" value="1" <?php checked($this->options['hp_enable_ajax_loading'], 1); ?>>
                                <?php _e('Activer le chargement AJAX des événements', 'hyperplanning'); ?>
                            </label>
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('API REST', 'hyperplanning'); ?></th>
                <td>
                    <fieldset>
                        <p>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_rest_api]" value="1" <?php checked($this->options['hp_enable_rest_api'], 1); ?>>
                                <?php _e('Activer l\'API REST', 'hyperplanning'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <?php _e('Limite de requêtes par heure', 'hyperplanning'); ?>
                                <input type="number" name="settings[hp_api_rate_limit]" value="<?php echo esc_attr($this->options['hp_api_rate_limit']); ?>" min="10" max="1000">
                            </label>
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Export', 'hyperplanning'); ?></th>
                <td>
                    <fieldset>
                        <p>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_ical_export]" value="1" <?php checked($this->options['hp_enable_ical_export'], 1); ?>>
                                <?php _e('Activer l\'export iCal', 'hyperplanning'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_csv_export]" value="1" <?php checked($this->options['hp_enable_csv_export'], 1); ?>>
                                <?php _e('Activer l\'export CSV', 'hyperplanning'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_print_view]" value="1" <?php checked($this->options['hp_enable_print_view'], 1); ?>>
                                <?php _e('Activer la vue impression', 'hyperplanning'); ?>
                            </label>
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Debug', 'hyperplanning'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="settings[hp_debug_mode]" value="1" <?php checked($this->options['hp_debug_mode'], 0); ?>>
                        <?php _e('Activer le mode debug', 'hyperplanning'); ?>
                    </label>
                    <p class="description"><?php _e('Active les logs détaillés (nécessite WP_DEBUG)', 'hyperplanning'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Désinstallation', 'hyperplanning'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="settings[hp_delete_data_on_uninstall]" value="1" <?php checked($this->options['hp_delete_data_on_uninstall'], 0); ?>>
                        <?php _e('Supprimer toutes les données lors de la désinstallation', 'hyperplanning'); ?>
                    </label>
                    <p class="description"><?php _e('ATTENTION : Cette option supprimera définitivement toutes les données du plugin', 'hyperplanning'); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Outils', 'hyperplanning'); ?></h3>
        <p>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hyperplanning-settings&action=clear_cache'), 'clear_cache'); ?>" class="button">
                <?php _e('Vider le cache', 'hyperplanning'); ?>
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hyperplanning-settings&action=reset_settings'), 'reset_settings'); ?>" class="button" onclick="return confirm('<?php esc_attr_e('Êtes-vous sûr de vouloir réinitialiser tous les paramètres ?', 'hyperplanning'); ?>');">
                <?php _e('Réinitialiser les paramètres', 'hyperplanning'); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Sauvegarder les paramètres
     */
    private function saveSettings() {
        $tab = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : 'general';
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        $capabilities = isset($_POST['capabilities']) ? $_POST['capabilities'] : array();
        
        // Sauvegarder les options
        foreach ($settings as $key => $value) {
            if (strpos($key, 'hp_') === 0) {
                update_option($key, $value);
            }
        }
        
        // Sauvegarder les capacités
        if (!empty($capabilities)) {
            foreach ($capabilities as $role_key => $caps) {
                $role = get_role($role_key);
                if ($role) {
                    // D'abord retirer toutes les capacités HP
                    foreach ($role->capabilities as $cap => $granted) {
                        if (strpos($cap, 'hp_') === 0) {
                            $role->remove_cap($cap);
                        }
                    }
                    // Puis ajouter les nouvelles
                    foreach ($caps as $cap => $granted) {
                        if ($granted) {
                            $role->add_cap($cap);
                        }
                    }
                }
            }
        }
        
        // Actions spécifiques selon l'onglet
        if ($tab === 'sync') {
            // Replanifier le cron si l'intervalle a changé
            if ($settings['hp_auto_sync_interval'] != $this->options['hp_auto_sync_interval']) {
                wp_clear_scheduled_hook('hp_sync_cron');
                wp_schedule_event(time(), 'hourly', 'hp_sync_cron');
            }
        }
        
        // Afficher le message de succès
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Paramètres enregistrés.', 'hyperplanning') . '</p></div>';
        
        // Recharger les options
        $this->options = $this->getOptions();
    }
    
    /**
     * Gérer les actions spéciales
     */
    public function handleActions() {
        if (!isset($_GET['action'])) {
            return;
        }
        
        $action = sanitize_key($_GET['action']);
        
        switch ($action) {
            case 'clear_cache':
                if (wp_verify_nonce($_GET['_wpnonce'], 'clear_cache')) {
                    hp_clear_cache();
                    wp_redirect(admin_url('admin.php?page=hyperplanning-settings&tab=advanced&message=cache_cleared'));
                    exit;
                }
                break;
                
            case 'reset_settings':
                if (wp_verify_nonce($_GET['_wpnonce'], 'reset_settings')) {
                    $this->resetSettings();
                    wp_redirect(admin_url('admin.php?page=hyperplanning-settings&message=settings_reset'));
                    exit;
                }
                break;
        }
    }
    
    /**
     * Réinitialiser tous les paramètres
     */
    private function resetSettings() {
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
        );
        
        foreach ($default_options as $option => $value) {
            update_option($option, $value);
        }
    }
}