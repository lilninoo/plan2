<?php
/**
 * Fonctionnalités publiques du plugin
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

class HP_Public {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Hooks initialisés par le plugin principal
    }
    
    /**
     * Enregistrer les styles publics
     */
    public function enqueueStyles() {
        // Style principal
        wp_enqueue_style(
            'hyperplanning-public',
            HYPERPLANNING_PLUGIN_URL . 'assets/css/public.css',
            array(),
            HYPERPLANNING_VERSION
        );
        
        // Style étendu si nécessaire
        if (file_exists(HYPERPLANNING_PLUGIN_DIR . 'assets/css/public-extended.css')) {
            wp_enqueue_style(
                'hyperplanning-public-extended',
                HYPERPLANNING_PLUGIN_URL . 'assets/css/public-extended.css',
                array('hyperplanning-public'),
                HYPERPLANNING_VERSION
            );
        }
        
        // Styles inline pour personnalisation
        $inline_css = $this->getInlineStyles();
        if ($inline_css) {
            wp_add_inline_style('hyperplanning-public', $inline_css);
        }
    }
    
    /**
     * Enregistrer les scripts publics
     */
    public function enqueueScripts() {
        // Enregistrer FullCalendar (sera chargé par les shortcodes si nécessaire)
        wp_register_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
            array(),
            '6.1.8',
            true
        );
        
        // Enregistrer FullCalendar locale française
        wp_register_script(
            'fullcalendar-locale-fr',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/fr.global.min.js',
            array('fullcalendar'),
            '6.1.8',
            true
        );
        
        // Script principal public
        wp_register_script(
            'hyperplanning-public',
            HYPERPLANNING_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            HYPERPLANNING_VERSION,
            true
        );
        
        // Localisation
        wp_localize_script('hyperplanning-public', 'hp_public', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('hyperplanning/v1/'),
            'nonce' => wp_create_nonce('hp_public_nonce'),
            'locale' => get_locale(),
            'date_format' => get_option('hp_date_format', 'Y-m-d'),
            'time_format' => get_option('hp_time_format', 'H:i'),
            'week_starts_on' => get_option('hp_week_starts_on', 1),
            'time_zone' => get_option('hp_time_zone', 'UTC'),
            'i18n' => array(
                'loading' => __('Chargement...', 'hyperplanning'),
                'error' => __('Une erreur est survenue', 'hyperplanning'),
                'no_events' => __('Aucun événement', 'hyperplanning'),
                'all_day' => __('Toute la journée', 'hyperplanning'),
                'week' => __('Semaine', 'hyperplanning'),
                'day' => __('Jour', 'hyperplanning'),
                'month' => __('Mois', 'hyperplanning'),
                'today' => __('Aujourd\'hui', 'hyperplanning'),
                'list' => __('Liste', 'hyperplanning'),
                'more' => __('+ %d autres', 'hyperplanning'),
            ),
        ));
    }
    
    /**
     * Gérer la soumission d'événements
     */
    public function handleEventSubmission() {
        // Vérifier si c'est une soumission de formulaire
        if (!isset($_POST['hp_event_submission']) || !isset($_POST['hp_event_nonce'])) {
            return;
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['hp_event_nonce'], 'hp_submit_event')) {
            wp_die(__('Erreur de sécurité', 'hyperplanning'));
        }
        
        // Vérifier les permissions
        if (!get_option('hp_enable_public_submission', 0) && !is_user_logged_in()) {
            wp_die(__('Les soumissions publiques ne sont pas autorisées', 'hyperplanning'));
        }
        
        // Créer l'événement
        $event = new HP_Event();
        
        // Récupérer et valider les données
        $trainer_id = isset($_POST['trainer_id']) ? intval($_POST['trainer_id']) : 0;
        $calendar_id = isset($_POST['calendar_id']) ? intval($_POST['calendar_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $all_day = isset($_POST['all_day']) ? true : false;
        
        // Email du soumetteur
        $submitter_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $submitter_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        
        // Valider les champs obligatoires
        if (empty($title) || empty($start_date) || empty($end_date)) {
            wp_die(__('Veuillez remplir tous les champs obligatoires', 'hyperplanning'));
        }
        
        // Si pas de calendrier spécifié, essayer de trouver le calendrier principal du formateur
        if (!$calendar_id && $trainer_id) {
            $calendars = HP_Calendar::findByTrainer($trainer_id);
            if (!empty($calendars)) {
                $calendar_id = $calendars[0]->getId();
            }
        }
        
        if (!$calendar_id) {
            wp_die(__('Aucun calendrier disponible', 'hyperplanning'));
        }
        
        // Configurer l'événement
        $event->setCalendarId($calendar_id);
        $event->setTrainerId($trainer_id);
        $event->setTitle($title);
        $event->setDescription($description);
        $event->setLocation($location);
        $event->setStartDate($start_date);
        $event->setEndDate($end_date);
        $event->setAllDay($all_day);
        
        // Statut selon les paramètres
        if (get_option('hp_require_event_approval', 0)) {
            $event->setStatus(HP_Constants::EVENT_STATUS_PENDING);
        } else {
            $event->setStatus(HP_Constants::EVENT_STATUS_TENTATIVE);
        }
        
        // Ajouter le soumetteur comme participant
        if ($submitter_email) {
            $event->addAttendee($submitter_email, $submitter_name, 'pending');
        }
        
        // Métadonnées
        $metadata = array(
            'submitted_by' => is_user_logged_in() ? get_current_user_id() : 'public',
            'submitted_at' => current_time('mysql'),
            'submitter_ip' => hp_get_user_ip(),
            'submitter_email' => $submitter_email,
            'submitter_name' => $submitter_name,
        );
        $event->setMetadata($metadata);
        
        // Sauvegarder l'événement
        $result = $event->save();
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        // Envoyer les notifications
        $this->sendEventNotification($event);
        
        // Redirection ou message de succès
        if (!empty($_POST['redirect_to'])) {
            wp_redirect(esc_url_raw($_POST['redirect_to']));
        } else {
            // Afficher un message de succès
            $this->showSubmissionSuccess($event);
        }
        exit;
    }
    
    /**
     * Envoyer une notification pour un nouvel événement
     */
    private function sendEventNotification($event) {
        if (!get_option('hp_enable_event_notifications', 1)) {
            return;
        }
        
        $trainer = $event->getTrainer();
        $to = get_option('hp_notification_email', get_option('admin_email'));
        
        // Ajouter l'email du formateur si disponible
        if ($trainer && $trainer->getEmail()) {
            $to = array($to, $trainer->getEmail());
        }
        
        $subject = sprintf(__('[%s] Nouvel événement soumis : %s', 'hyperplanning'), get_bloginfo('name'), $event->getTitle());
        
        $message = sprintf(
            __("Un nouvel événement a été soumis :\n\n" .
               "Titre : %s\n" .
               "Date : %s\n" .
               "Lieu : %s\n" .
               "Description : %s\n\n" .
               "Statut : %s\n\n" .
               "Connectez-vous pour gérer cet événement : %s", 'hyperplanning'),
            $event->getTitle(),
            hp_format_date($event->getStartDate()),
            $event->getLocation() ?: __('Non spécifié', 'hyperplanning'),
            $event->getDescription() ?: __('Aucune description', 'hyperplanning'),
            $event->getStatus(),
            admin_url('admin.php?page=hyperplanning-calendar&event=' . $event->getId())
        );
        
        $metadata = $event->getMetadata();
        if (!empty($metadata['submitter_email'])) {
            $message .= sprintf(
                __("\n\nSoumis par : %s (%s)", 'hyperplanning'),
                $metadata['submitter_name'],
                $metadata['submitter_email']
            );
        }
        
        hp_send_notification($to, $subject, nl2br($message));
    }
    
    /**
     * Afficher le message de succès après soumission
     */
    private function showSubmissionSuccess($event) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php _e('Événement soumis avec succès', 'hyperplanning'); ?></title>
            <?php wp_head(); ?>
        </head>
        <body>
            <div class="hp-submission-success">
                <h1><?php _e('Merci !', 'hyperplanning'); ?></h1>
                <p><?php _e('Votre événement a été soumis avec succès.', 'hyperplanning'); ?></p>
                
                <?php if ($event->getStatus() === HP_Constants::EVENT_STATUS_PENDING): ?>
                    <p><?php _e('Il sera examiné et validé par un administrateur.', 'hyperplanning'); ?></p>
                <?php else: ?>
                    <p><?php _e('Il apparaîtra dans le calendrier après validation.', 'hyperplanning'); ?></p>
                <?php endif; ?>
                
                <p><a href="<?php echo esc_url(home_url()); ?>"><?php _e('Retour à l\'accueil', 'hyperplanning'); ?></a></p>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Obtenir les styles inline personnalisés
     */
    private function getInlineStyles() {
        $css = '';
        
        // Couleurs personnalisées
        $color_scheme = get_option('hp_color_scheme', 'default');
        if ($color_scheme !== 'default') {
            // Ajouter des styles selon le schéma de couleurs
        }
        
        // Styles personnalisés additionnels
        $custom_css = get_option('hp_custom_css', '');
        if ($custom_css) {
            $css .= $custom_css;
        }
        
        return $css;
    }
    
    /**
     * Handler AJAX pour obtenir les événements publics
     */
    public function ajaxGetPublicEvents() {
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hp_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $args = array(
            'start_date' => isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '',
            'end_date' => isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '',
            'trainer_id' => isset($_POST['trainer_id']) ? intval($_POST['trainer_id']) : null,
            'calendar_id' => isset($_POST['calendar_id']) ? intval($_POST['calendar_id']) : null,
            'status' => HP_Constants::EVENT_STATUS_CONFIRMED,
        );
        
        $events = HP_Event::all($args);
        $response = array();
        
        foreach ($events as $event) {
            // Vérifier que l'événement peut être vu publiquement
            if (!$event->canView(0)) {
                continue;
            }
            
            $event_data = array(
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start' => $event->getStartDate(),
                'end' => $event->getEndDate(),
                'allDay' => $event->getAllDay(),
                'color' => $event->getDisplayColor(),
                'extendedProps' => array(
                    'location' => $event->getLocation(),
                    'trainer_id' => $event->getTrainerId(),
                    'calendar_id' => $event->getCalendarId(),
                ),
            );
            
            // Ajouter la description si le calendrier est public
            $calendar = $event->getCalendar();
            if ($calendar && $calendar->getVisibility() === HP_Constants::VISIBILITY_PUBLIC) {
                $event_data['extendedProps']['description'] = $event->getDescription();
            }
            
            $response[] = $event_data;
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Handler AJAX pour obtenir les détails d'un événement
     */
    public function ajaxGetEventDetails() {
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hp_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if (!$event_id) {
            wp_send_json_error(__('ID d\'événement manquant', 'hyperplanning'));
        }
        
        $event = HP_Event::find($event_id);
        if (!$event) {
            wp_send_json_error(__('Événement non trouvé', 'hyperplanning'));
        }
        
        // Vérifier les permissions
        if (!$event->canView(0)) {
            wp_send_json_error(__('Vous n\'avez pas la permission de voir cet événement', 'hyperplanning'));
        }
        
        $trainer = $event->getTrainer();
        $calendar = $event->getCalendar();
        
        $response = array(
            'title' => $event->getTitle(),
            'start' => hp_format_date($event->getStartDate()),
            'end' => hp_format_date($event->getEndDate()),
            'allDay' => $event->getAllDay(),
            'location' => $event->getLocation(),
            'duration' => hp_format_duration($event->getDuration()),
            'trainer' => $trainer ? $trainer->getName() : '',
            'calendar' => $calendar ? $calendar->getName() : '',
        );
        
        // Ajouter la description si public
        if ($calendar && $calendar->getVisibility() === HP_Constants::VISIBILITY_PUBLIC) {
            $response['description'] = $event->getDescription();
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Handler pour l'export iCal public
     */
    public function handleIcalExport() {
        if (!isset($_GET['hp_action']) || $_GET['hp_action'] !== 'export_ical') {
            return;
        }
        
        $calendar_id = isset($_GET['calendar_id']) ? intval($_GET['calendar_id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        if (!$calendar_id || !$token) {
            wp_die(__('Paramètres manquants', 'hyperplanning'));
        }
        
        $calendar = HP_Calendar::find($calendar_id);
        if (!$calendar) {
            wp_die(__('Calendrier non trouvé', 'hyperplanning'));
        }
        
        // Vérifier le token
        $expected_token = wp_hash($calendar->getId() . $calendar->getName());
        if ($token !== $expected_token) {
            wp_die(__('Token invalide', 'hyperplanning'));
        }
        
        // Vérifier que l'export est autorisé
        if (!get_option('hp_enable_ical_export', 1)) {
            wp_die(__('L\'export iCal est désactivé', 'hyperplanning'));
        }
        
        // Vérifier la visibilité
        if ($calendar->getVisibility() === HP_Constants::VISIBILITY_PRIVATE && !is_user_logged_in()) {
            wp_die(__('Ce calendrier est privé', 'hyperplanning'));
        }
        
        // Exporter
        $ical_sync = new HP_iCal_Sync();
        $ical_sync->exportToFile($calendar);
    }
    
    /**
     * Ajouter les classes CSS au body
     */
    public function addBodyClasses($classes) {
        if (is_singular() && has_shortcode(get_post()->post_content, 'hyperplanning_calendar')) {
            $classes[] = 'has-hyperplanning-calendar';
        }
        
        return $classes;
    }
    
    /**
     * Gérer les requêtes personnalisées
     */
    public function handleCustomRequests() {
        // Export iCal
        $this->handleIcalExport();
        
        // Autres requêtes personnalisées...
    }
}