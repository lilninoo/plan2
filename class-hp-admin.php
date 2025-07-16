<?php
// admin/class-hp-admin.php


if ( class_exists( 'HP_Admin' ) ) {
    return;
}


class HP_Admin {
    
    public function addAdminMenu() {
        // Menu principal
        add_menu_page(
            __('HyperPlanning', 'hyperplanning'),
            __('HyperPlanning', 'hyperplanning'),
            'hp_view_calendar',
            'hyperplanning',
            array($this, 'renderDashboard'),
            'dashicons-calendar-alt',
            30
        );
        
        // Sous-menu Dashboard
        add_submenu_page(
            'hyperplanning',
            __('Tableau de bord', 'hyperplanning'),
            __('Tableau de bord', 'hyperplanning'),
            'hp_view_calendar',
            'hyperplanning',
            array($this, 'renderDashboard')
        );
        
        // Sous-menu Calendrier
        add_submenu_page(
            'hyperplanning',
            __('Calendrier Central', 'hyperplanning'),
            __('Calendrier Central', 'hyperplanning'),
            'hp_view_calendar',
            'hyperplanning-calendar',
            array($this, 'renderCalendar')
        );
        
        // Sous-menu Formateurs
        add_submenu_page(
            'hyperplanning',
            __('Formateurs', 'hyperplanning'),
            __('Formateurs', 'hyperplanning'),
            'hp_manage_trainers',
            'hyperplanning-trainers',
            array($this, 'renderTrainers')
        );
        
        // Sous-menu Synchronisation
        add_submenu_page(
            'hyperplanning',
            __('Synchronisation', 'hyperplanning'),
            __('Synchronisation', 'hyperplanning'),
            'hp_manage_calendars',
            'hyperplanning-sync',
            array($this, 'renderSync')
        );
        
        // Sous-menu Paramètres
        add_submenu_page(
            'hyperplanning',
            __('Paramètres', 'hyperplanning'),
            __('Paramètres', 'hyperplanning'),
            'hp_manage_settings',
            'hyperplanning-settings',
            array($this, 'renderSettings')
        );
        
        // Page cachée pour l'authentification Google
        add_submenu_page(
            null,
            __('Authentification Google', 'hyperplanning'),
            '',
            'hp_manage_calendars',
            'hyperplanning-google-auth',
            array($this, 'handleGoogleAuth')
        );
    }
    
    public function enqueueStyles($hook) {
        if (strpos($hook, 'hyperplanning') === false) {
            return;
        }
        
        wp_enqueue_style(
            'hyperplanning-admin',
            HYPERPLANNING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            HYPERPLANNING_VERSION
        );
        
        // FullCalendar CSS
        wp_enqueue_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css',
            array(),
            '6.1.8'
        );
    }
    
    public function enqueueScripts($hook) {
        if (strpos($hook, 'hyperplanning') === false) {
            return;
        }
        
        // FullCalendar JS
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
            array(),
            '6.1.8',
            true
        );
        
        // Admin JS
        wp_enqueue_script(
            'hyperplanning-admin',
            HYPERPLANNING_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'fullcalendar'),
            HYPERPLANNING_VERSION,
            true
        );
        
        // Localisation
        wp_localize_script('hyperplanning-admin', 'hp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('hyperplanning/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale' => get_locale(),
            'date_format' => get_option('hp_date_format', 'Y-m-d'),
            'time_format' => get_option('hp_time_format', 'H:i'),
            'week_starts_on' => get_option('hp_week_starts_on', 1),
            'i18n' => array(
                'loading' => __('Chargement...', 'hyperplanning'),
                'error' => __('Une erreur est survenue', 'hyperplanning'),
                'confirm_delete' => __('Êtes-vous sûr de vouloir supprimer cet élément ?', 'hyperplanning'),
                'save' => __('Enregistrer', 'hyperplanning'),
                'cancel' => __('Annuler', 'hyperplanning'),
                'close' => __('Fermer', 'hyperplanning'),
            ),
        ));
    }
    
    public function renderDashboard() {
        ?>
        <div class="wrap">
            <h1><?php _e('Tableau de bord HyperPlanning', 'hyperplanning'); ?></h1>
            
            <div class="hp-dashboard">
                <!-- Statistiques -->
                <div class="hp-stats">
                    <div class="hp-stat-box">
                        <h3><?php _e('Formateurs', 'hyperplanning'); ?></h3>
                        <p class="hp-stat-number"><?php echo count(HP_Trainer::all()); ?></p>
                    </div>
                    
                    <div class="hp-stat-box">
                        <h3><?php _e('Calendriers', 'hyperplanning'); ?></h3>
                        <p class="hp-stat-number"><?php echo count(HP_Calendar::all()); ?></p>
                    </div>
                    
                    <div class="hp-stat-box">
                        <h3><?php _e('Événements ce mois', 'hyperplanning'); ?></h3>
                        <p class="hp-stat-number"><?php echo $this->getMonthEventCount(); ?></p>
                    </div>
                    
                    <div class="hp-stat-box">
                        <h3><?php _e('Prochaine synchronisation', 'hyperplanning'); ?></h3>
                        <p class="hp-stat-time"><?php echo $this->getNextSyncTime(); ?></p>
                    </div>
                </div>
                
                <!-- Actions rapides -->
                <div class="hp-quick-actions">
                    <h2><?php _e('Actions rapides', 'hyperplanning'); ?></h2>
                    <a href="<?php echo admin_url('admin.php?page=hyperplanning-calendar'); ?>" class="button button-primary">
                        <?php _e('Voir le calendrier central', 'hyperplanning'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=hyperplanning-trainers&action=new'); ?>" class="button">
                        <?php _e('Ajouter un formateur', 'hyperplanning'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=hyperplanning-sync'); ?>" class="button">
                        <?php _e('Synchroniser maintenant', 'hyperplanning'); ?>
                    </a>
                </div>
                
                <!-- Événements récents -->
                <div class="hp-recent-events">
                    <h2><?php _e('Événements à venir', 'hyperplanning'); ?></h2>
                    <?php $this->renderUpcomingEvents(); ?>
                </div>
                
                <!-- Logs de synchronisation -->
                <div class="hp-sync-logs">
                    <h2><?php _e('Dernières synchronisations', 'hyperplanning'); ?></h2>
                    <?php $this->renderSyncLogs(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function renderCalendar() {
        ?>
        <div class="wrap">
            <h1><?php _e('Calendrier Central', 'hyperplanning'); ?></h1>
            
            <!-- Filtres -->
            <div class="hp-calendar-filters">
                <label>
                    <?php _e('Formateur :', 'hyperplanning'); ?>
                    <select id="hp-trainer-filter">
                        <option value=""><?php _e('Tous les formateurs', 'hyperplanning'); ?></option>
                        <?php foreach (HP_Trainer::all() as $trainer): ?>
                            <option value="<?php echo esc_attr($trainer->getId()); ?>">
                                <?php echo esc_html($trainer->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                
                <label>
                    <?php _e('Type :', 'hyperplanning'); ?>
                    <select id="hp-type-filter">
                        <option value=""><?php _e('Tous les types', 'hyperplanning'); ?></option>
                        <option value="training"><?php _e('Formation', 'hyperplanning'); ?></option>
                        <option value="availability"><?php _e('Disponibilité', 'hyperplanning'); ?></option>
                        <option value="personal"><?php _e('Personnel', 'hyperplanning'); ?></option>
                    </select>
                </label>
                
                <button class="button" id="hp-calendar-today"><?php _e('Aujourd\'hui', 'hyperplanning'); ?></button>
                
                <div class="hp-calendar-views">
                    <button class="button" data-view="dayGridMonth"><?php _e('Mois', 'hyperplanning'); ?></button>
                    <button class="button" data-view="timeGridWeek"><?php _e('Semaine', 'hyperplanning'); ?></button>
                    <button class="button" data-view="timeGridDay"><?php _e('Jour', 'hyperplanning'); ?></button>
                    <button class="button" data-view="listWeek"><?php _e('Liste', 'hyperplanning'); ?></button>
                </div>
            </div>
            
            <!-- Calendrier -->
            <div id="hp-calendar-container"></div>
            
            <!-- Modal pour les événements -->
            <div id="hp-event-modal" class="hp-modal" style="display: none;">
                <div class="hp-modal-content">
                    <span class="hp-modal-close">&times;</span>
                    <h2 id="hp-event-title"></h2>
                    <div id="hp-event-details"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function renderTrainers() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->renderTrainerForm();
                break;
            default:
                $this->renderTrainersList();
        }
    }
    
    private function renderTrainersList() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Formateurs', 'hyperplanning'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=hyperplanning-trainers&action=new'); ?>" class="page-title-action">
                <?php _e('Ajouter', 'hyperplanning'); ?>
            </a>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Nom', 'hyperplanning'); ?></th>
                        <th><?php _e('Email', 'hyperplanning'); ?></th>
                        <th><?php _e('Téléphone', 'hyperplanning'); ?></th>
                        <th><?php _e('Spécialités', 'hyperplanning'); ?></th>
                        <th><?php _e('Synchronisation', 'hyperplanning'); ?></th>
                        <th><?php _e('Actions', 'hyperplanning'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (HP_Trainer::all() as $trainer): ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=hyperplanning-trainers&action=edit&id=' . $trainer->getId()); ?>">
                                        <?php echo esc_html($trainer->getName()); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=hyperplanning-trainers&action=edit&id=' . $trainer->getId()); ?>">
                                            <?php _e('Modifier', 'hyperplanning'); ?>
                                        </a> |
                                    </span>
                                    <span class="trash">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hyperplanning-trainers&action=delete&id=' . $trainer->getId()), 'delete_trainer_' . $trainer->getId()); ?>" onclick="return confirm('<?php esc_attr_e('Êtes-vous sûr ?', 'hyperplanning'); ?>');">
                                            <?php _e('Supprimer', 'hyperplanning'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo esc_html($trainer->getEmail()); ?></td>
                            <td><?php echo esc_html($trainer->getPhone()); ?></td>
                            <td><?php echo esc_html(implode(', ', $trainer->getSpecialties())); ?></td>
                            <td>
                                <?php if ($trainer->getSyncEnabled()): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php _e('Activée', 'hyperplanning'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                                    <?php _e('Désactivée', 'hyperplanning'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=hyperplanning-calendar&trainer=' . $trainer->getId()); ?>" class="button button-small">
                                    <?php _e('Voir calendrier', 'hyperplanning'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function renderTrainerForm() {
        $trainer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $trainer = $trainer_id ? HP_Trainer::find($trainer_id) : new HP_Trainer();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hp_trainer_nonce'])) {
            if (wp_verify_nonce($_POST['hp_trainer_nonce'], 'save_trainer')) {
                $this->saveTrainer($trainer);
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo $trainer_id ? __('Modifier le formateur', 'hyperplanning') : __('Ajouter un formateur', 'hyperplanning'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_trainer', 'hp_trainer_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hp_trainer_name"><?php _e('Nom', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="hp_trainer_name" name="trainer[name]" value="<?php echo esc_attr($trainer->getName()); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_trainer_email"><?php _e('Email', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="hp_trainer_email" name="trainer[email]" value="<?php echo esc_attr($trainer->getEmail()); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_trainer_phone"><?php _e('Téléphone', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="tel" id="hp_trainer_phone" name="trainer[phone]" value="<?php echo esc_attr($trainer->getPhone()); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_trainer_specialties"><?php _e('Spécialités', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="hp_trainer_specialties" name="trainer[specialties]" value="<?php echo esc_attr(implode(', ', $trainer->getSpecialties())); ?>" class="regular-text">
                            <p class="description"><?php _e('Séparez les spécialités par des virgules', 'hyperplanning'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_trainer_color"><?php _e('Couleur du calendrier', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="hp_trainer_color" name="trainer[calendar_color]" value="<?php echo esc_attr($trainer->getCalendarColor()); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_trainer_user"><?php _e('Utilisateur WordPress', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_users(array(
                                'name' => 'trainer[user_id]',
                                'id' => 'hp_trainer_user',
                                'selected' => $trainer->getUserId(),
                                'show_option_none' => __('-- Aucun --', 'hyperplanning'),
                                'option_none_value' => '0',
                            ));
                            ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Synchronisation', 'hyperplanning'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="trainer[sync_enabled]" value="1" <?php checked($trainer->getSyncEnabled()); ?>>
                                    <?php _e('Activer la synchronisation automatique', 'hyperplanning'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_trainer_google_calendar"><?php _e('ID Google Calendar', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="hp_trainer_google_calendar" name="trainer[google_calendar_id]" value="<?php echo esc_attr($trainer->getGoogleCalendarId()); ?>" class="regular-text">
                            <p class="description"><?php _e('Ex: votreemail@gmail.com', 'hyperplanning'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_trainer_ical_url"><?php _e('URL iCal', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="hp_trainer_ical_url" name="trainer[ical_url]" value="<?php echo esc_attr($trainer->getIcalUrl()); ?>" class="regular-text">
                            <p class="description"><?php _e('URL du calendrier iCal à synchroniser', 'hyperplanning'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php echo $trainer_id ? __('Mettre à jour', 'hyperplanning') : __('Ajouter', 'hyperplanning'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=hyperplanning-trainers'); ?>" class="button"><?php _e('Annuler', 'hyperplanning'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function saveTrainer($trainer) {
        $data = $_POST['trainer'];
        
        $trainer->setName($data['name']);
        $trainer->setEmail($data['email']);
        $trainer->setPhone($data['phone']);
        $trainer->setCalendarColor($data['calendar_color']);
        $trainer->setUserId(intval($data['user_id']));
        $trainer->setSyncEnabled(isset($data['sync_enabled']));
        $trainer->setGoogleCalendarId($data['google_calendar_id']);
        $trainer->setIcalUrl($data['ical_url']);
        
        // Spécialités
        $specialties = array_map('trim', explode(',', $data['specialties']));
        $trainer->setSpecialties(array_filter($specialties));
        
        if ($trainer->save()) {
            wp_redirect(admin_url('admin.php?page=hyperplanning-trainers&message=saved'));
            exit;
        }
    }
    
    public function renderSync() {
        ?>
        <div class="wrap">
            <h1><?php _e('Synchronisation', 'hyperplanning'); ?></h1>
            
            <div class="hp-sync-section">
                <h2><?php _e('Configuration Google Calendar', 'hyperplanning'); ?></h2>
                
                <?php if (get_option('hp_google_client_id') && get_option('hp_google_client_secret')): ?>
                    <?php if (get_option('hp_google_token')): ?>
                        <p class="hp-sync-status hp-sync-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Connecté à Google Calendar', 'hyperplanning'); ?>
                        </p>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hyperplanning-sync&action=google_disconnect'), 'google_disconnect'); ?>" class="button">
                            <?php _e('Déconnecter', 'hyperplanning'); ?>
                        </a>
                    <?php else: ?>
                        <p class="hp-sync-status hp-sync-disconnected">
                            <span class="dashicons dashicons-no-alt"></span>
                            <?php _e('Non connecté à Google Calendar', 'hyperplanning'); ?>
                        </p>
                        <?php
                        $google_sync = new HP_Google_Sync();
                        $auth_url = $google_sync->getAuthUrl();
                        ?>
                        <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                            <?php _e('Se connecter à Google Calendar', 'hyperplanning'); ?>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <p><?php _e('Veuillez configurer les clés API Google dans les paramètres.', 'hyperplanning'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="hp-sync-section">
                <h2><?php _e('Synchronisation manuelle', 'hyperplanning'); ?></h2>
                <p><?php _e('Forcer la synchronisation de tous les calendriers maintenant.', 'hyperplanning'); ?></p>
                <button id="hp-sync-now" class="button button-primary">
                    <?php _e('Synchroniser maintenant', 'hyperplanning'); ?>
                </button>
                <div id="hp-sync-progress" style="display: none;">
                    <div class="spinner is-active"></div>
                    <span><?php _e('Synchronisation en cours...', 'hyperplanning'); ?></span>
                </div>
            </div>
            
            <div class="hp-sync-section">
                <h2><?php _e('Historique des synchronisations', 'hyperplanning'); ?></h2>
                <?php $this->renderSyncLogs(20); ?>
            </div>
        </div>
        <?php
    }
    
    public function renderSettings() {
        $settings = new HP_Settings();
        $settings->render();
    }
    
    public function handleGoogleAuth() {
        if (!isset($_GET['code'])) {
            wp_die(__('Code d\'autorisation manquant', 'hyperplanning'));
        }
        
        $google_sync = new HP_Google_Sync();
        $result = $google_sync->authenticate($_GET['code']);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        wp_redirect(admin_url('admin.php?page=hyperplanning-sync&message=google_connected'));
        exit;
    }
    
    private function getMonthEventCount() {
        $events = HP_Event::all(array(
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t'),
            'status' => 'confirmed',
        ));
        
        return count($events);
    }
    
    private function getNextSyncTime() {
        $timestamp = wp_next_scheduled('hp_sync_cron');
        
        if (!$timestamp) {
            return __('Non planifiée', 'hyperplanning');
        }
        
        return sprintf(
            __('Dans %s', 'hyperplanning'),
            human_time_diff(current_time('timestamp'), $timestamp)
        );
    }
    
    private function renderUpcomingEvents($limit = 5) {
        $events = HP_Event::all(array(
            'start_date' => current_time('mysql'),
            'status' => 'confirmed',
            'orderby' => 'start_date',
            'order' => 'ASC',
            'limit' => $limit,
        ));
        
        if (empty($events)) {
            echo '<p>' . __('Aucun événement à venir', 'hyperplanning') . '</p>';
            return;
        }
        
        echo '<ul class="hp-event-list">';
        foreach ($events as $event) {
            $trainer = $event->getTrainer();
            echo '<li>';
            echo '<strong>' . esc_html($event->getTitle()) . '</strong><br>';
            echo '<span class="hp-event-meta">';
            echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->getStartDate()));
            if ($trainer) {
                echo ' - ' . esc_html($trainer->getName());
            }
            echo '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    private function renderSyncLogs($limit = 10) {
        $sync_manager = new HP_Sync_Manager();
        $logs = $sync_manager->getSyncLogs(null, $limit);
        
        if (empty($logs)) {
            echo '<p>' . __('Aucune synchronisation effectuée', 'hyperplanning') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'hyperplanning'); ?></th>
                    <th><?php _e('Calendrier', 'hyperplanning'); ?></th>
                    <th><?php _e('Type', 'hyperplanning'); ?></th>
                    <th><?php _e('Statut', 'hyperplanning'); ?></th>
                    <th><?php _e('Message', 'hyperplanning'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->started_at)); ?></td>
                        <td>
                            <?php
                            if ($log->calendar_id) {
                                $calendar = HP_Calendar::find($log->calendar_id);
                                echo $calendar ? esc_html($calendar->getName()) : '-';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($log->sync_type); ?></td>
                        <td>
                            <?php if ($log->status === 'success'): ?>
                                <span class="hp-status-success"><?php _e('Succès', 'hyperplanning'); ?></span>
                            <?php elseif ($log->status === 'error'): ?>
                                <span class="hp-status-error"><?php _e('Erreur', 'hyperplanning'); ?></span>
                            <?php else: ?>
                                <span class="hp-status-progress"><?php _e('En cours', 'hyperplanning'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

// admin/class-hp-settings.php

class HP_Settings {
    
    private $options;
    
    public function __construct() {
        $this->options = $this->getOptions();
    }
    
    private function getOptions() {
        return array(
            'hp_google_client_id' => get_option('hp_google_client_id', ''),
            'hp_google_client_secret' => get_option('hp_google_client_secret', ''),
            'hp_default_calendar_view' => get_option('hp_default_calendar_view', 'month'),
            'hp_time_zone' => get_option('hp_time_zone', get_option('timezone_string', 'UTC')),
            'hp_date_format' => get_option('hp_date_format', get_option('date_format', 'Y-m-d')),
            'hp_time_format' => get_option('hp_time_format', get_option('time_format', 'H:i')),
            'hp_week_starts_on' => get_option('hp_week_starts_on', 1),
            'hp_enable_conflicts_detection' => get_option('hp_enable_conflicts_detection', 1),
            'hp_auto_sync_interval' => get_option('hp_auto_sync_interval', 60),
            'hp_max_events_display' => get_option('hp_max_events_display', 500),
            'hp_cache_duration' => get_option('hp_cache_duration', 3600),
        );
    }
    
    public function render() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hp_settings_nonce'])) {
            if (wp_verify_nonce($_POST['hp_settings_nonce'], 'save_settings')) {
                $this->saveSettings();
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Paramètres HyperPlanning', 'hyperplanning'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_settings', 'hp_settings_nonce'); ?>
                
                <h2 class="title"><?php _e('API Google Calendar', 'hyperplanning'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hp_google_client_id"><?php _e('Client ID', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="hp_google_client_id" name="settings[hp_google_client_id]" value="<?php echo esc_attr($this->options['hp_google_client_id']); ?>" class="regular-text">
                            <p class="description">
                                <?php _e('Obtenez vos identifiants depuis', 'hyperplanning'); ?>
                                <a href="https://console.developers.google.com" target="_blank">Google Developers Console</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_google_client_secret"><?php _e('Client Secret', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="hp_google_client_secret" name="settings[hp_google_client_secret]" value="<?php echo esc_attr($this->options['hp_google_client_secret']); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php _e('Affichage du calendrier', 'hyperplanning'); ?></h2>
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
                        <th scope="row">
                            <label for="hp_max_events_display"><?php _e('Nombre maximum d\'événements affichés', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="hp_max_events_display" name="settings[hp_max_events_display]" value="<?php echo esc_attr($this->options['hp_max_events_display']); ?>" min="100" max="5000" step="100">
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php _e('Synchronisation', 'hyperplanning'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Détection des conflits', 'hyperplanning'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[hp_enable_conflicts_detection]" value="1" <?php checked($this->options['hp_enable_conflicts_detection'], 1); ?>>
                                <?php _e('Activer la détection automatique des conflits d\'horaires', 'hyperplanning'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_auto_sync_interval"><?php _e('Intervalle de synchronisation automatique', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="hp_auto_sync_interval" name="settings[hp_auto_sync_interval]" value="<?php echo esc_attr($this->options['hp_auto_sync_interval']); ?>" min="15" max="1440">
                            <?php _e('minutes', 'hyperplanning'); ?>
                            <p class="description"><?php _e('Minimum : 15 minutes', 'hyperplanning'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_cache_duration"><?php _e('Durée du cache', 'hyperplanning'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="hp_cache_duration" name="settings[hp_cache_duration]" value="<?php echo esc_attr($this->options['hp_cache_duration']); ?>" min="0" max="86400" step="60">
                            <?php _e('secondes', 'hyperplanning'); ?>
                            <p class="description"><?php _e('0 pour désactiver le cache', 'hyperplanning'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('Enregistrer les paramètres', 'hyperplanning'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    private function saveSettings() {
        $settings = $_POST['settings'];
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        // Replanifier le cron si l'intervalle a changé
        if ($settings['hp_auto_sync_interval'] != $this->options['hp_auto_sync_interval']) {
            wp_clear_scheduled_hook('hp_sync_cron');
            wp_schedule_event(time(), 'hourly', 'hp_sync_cron');
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Paramètres enregistrés.', 'hyperplanning') . '</p></div>';
        
        // Recharger les options
        $this->options = $this->getOptions();
    }
}