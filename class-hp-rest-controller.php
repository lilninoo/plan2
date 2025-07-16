<?php
// includes/api/class-hp-rest-controller.php


if ( class_exists( 'HP_REST_Controller' ) ) {
    return;
}

class HP_REST_Controller {
    
    private $namespace = 'hyperplanning/v1';
    
    public function registerRoutes() {
        // Routes pour les événements
        register_rest_route($this->namespace, '/events', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getEvents'),
                'permission_callback' => array($this, 'checkReadPermission'),
                'args' => $this->getEventsArgs(),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'createEvent'),
                'permission_callback' => array($this, 'checkWritePermission'),
            ),
        ));
        
        register_rest_route($this->namespace, '/events/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getEvent'),
                'permission_callback' => array($this, 'checkReadPermission'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'updateEvent'),
                'permission_callback' => array($this, 'checkWritePermission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'deleteEvent'),
                'permission_callback' => array($this, 'checkWritePermission'),
            ),
        ));
        
        // Routes pour les formateurs
        register_rest_route($this->namespace, '/trainers', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getTrainers'),
                'permission_callback' => array($this, 'checkReadPermission'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'createTrainer'),
                'permission_callback' => array($this, 'checkAdminPermission'),
            ),
        ));
        
        register_rest_route($this->namespace, '/trainers/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getTrainer'),
                'permission_callback' => array($this, 'checkReadPermission'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'updateTrainer'),
                'permission_callback' => array($this, 'checkAdminPermission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'deleteTrainer'),
                'permission_callback' => array($this, 'checkAdminPermission'),
            ),
        ));
        
        // Routes pour les calendriers
        register_rest_route($this->namespace, '/calendars', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getCalendars'),
                'permission_callback' => array($this, 'checkReadPermission'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'createCalendar'),
                'permission_callback' => array($this, 'checkWritePermission'),
            ),
        ));
        
        register_rest_route($this->namespace, '/calendars/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getCalendar'),
                'permission_callback' => array($this, 'checkReadPermission'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'updateCalendar'),
                'permission_callback' => array($this, 'checkWritePermission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'deleteCalendar'),
                'permission_callback' => array($this, 'checkAdminPermission'),
            ),
        ));
        
        // Route pour la synchronisation
        register_rest_route($this->namespace, '/sync/run', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'runSync'),
            'permission_callback' => array($this, 'checkAdminPermission'),
        ));
        
        register_rest_route($this->namespace, '/sync/calendar/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'syncCalendar'),
            'permission_callback' => array($this, 'checkWritePermission'),
        ));
        
        // Routes publiques
        register_rest_route($this->namespace, '/public/events', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'getPublicEvents'),
            'permission_callback' => '__return_true',
            'args' => $this->getEventsArgs(),
        ));
        
        register_rest_route($this->namespace, '/public/trainers', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'getPublicTrainers'),
            'permission_callback' => '__return_true',
        ));
        
        // Route pour l'export iCal
        register_rest_route($this->namespace, '/export/ical/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'exportIcal'),
            'permission_callback' => '__return_true',
        ));
    }
    
    // Permissions
    public function checkReadPermission() {
        return current_user_can('hp_view_calendar');
    }
    
    public function checkWritePermission() {
        return current_user_can('hp_create_events');
    }
    
    public function checkAdminPermission() {
        return current_user_can('hp_manage_calendars');
    }
    
    // Arguments pour les requêtes
    private function getEventsArgs() {
        return array(
            'start' => array(
                'type' => 'string',
                'format' => 'date-time',
                'required' => false,
            ),
            'end' => array(
                'type' => 'string',
                'format' => 'date-time',
                'required' => false,
            ),
            'trainer_id' => array(
                'type' => 'integer',
                'required' => false,
            ),
            'calendar_id' => array(
                'type' => 'integer',
                'required' => false,
            ),
            'status' => array(
                'type' => 'string',
                'enum' => array('confirmed', 'tentative', 'cancelled'),
                'required' => false,
            ),
            'limit' => array(
                'type' => 'integer',
                'default' => 100,
                'minimum' => 1,
                'maximum' => 500,
            ),
            'offset' => array(
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0,
            ),
        );
    }
    
    // Endpoints pour les événements
    public function getEvents($request) {
        $args = array(
            'start_date' => $request->get_param('start'),
            'end_date' => $request->get_param('end'),
            'trainer_id' => $request->get_param('trainer_id'),
            'calendar_id' => $request->get_param('calendar_id'),
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
        );
        
        $events = HP_Event::all(array_filter($args));
        $response = array();
        
        foreach ($events as $event) {
            $event_data = $event->toArray();
            
            // Ajouter le nom du formateur
            $trainer = $event->getTrainer();
            if ($trainer) {
                $event_data['trainer_name'] = $trainer->getName();
            }
            
            $response[] = $event_data;
        }
        
        return rest_ensure_response($response);
    }
    
    public function getEvent($request) {
        $id = $request->get_param('id');
        $event = HP_Event::find($id);
        
        if (!$event) {
            return new WP_Error('not_found', __('Événement non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        return rest_ensure_response($event->toArray());
    }
    
    public function createEvent($request) {
        $event = new HP_Event();
        
        $event->setCalendarId($request->get_param('calendar_id'));
        $event->setTrainerId($request->get_param('trainer_id'));
        $event->setTitle($request->get_param('title'));
        $event->setDescription($request->get_param('description'));
        $event->setLocation($request->get_param('location'));
        $event->setStartDate($request->get_param('start_date'));
        $event->setEndDate($request->get_param('end_date'));
        $event->setAllDay($request->get_param('all_day'));
        $event->setStatus($request->get_param('status') ?: 'confirmed');
        $event->setAttendees($request->get_param('attendees'));
        $event->setRecurrenceRule($request->get_param('recurrence_rule'));
        $event->setColor($request->get_param('color'));
        
        $result = $event->save();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'id' => $event->getId(),
            'message' => __('Événement créé avec succès', 'hyperplanning'),
        ));
    }
    
    public function updateEvent($request) {
        $id = $request->get_param('id');
        $event = HP_Event::find($id);
        
        if (!$event) {
            return new WP_Error('not_found', __('Événement non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        // Vérifier les permissions spécifiques
        if (!current_user_can('hp_edit_all_events') && $event->getTrainerId() != get_current_user_id()) {
            return new WP_Error('forbidden', __('Vous n\'avez pas la permission de modifier cet événement', 'hyperplanning'), array('status' => 403));
        }
        
        // Mettre à jour les champs fournis
        $fields = array('title', 'description', 'location', 'start_date', 'end_date', 'all_day', 'status', 'attendees', 'recurrence_rule', 'color');
        
        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $method = 'set' . str_replace('_', '', ucwords($field, '_'));
                if (method_exists($event, $method)) {
                    $event->$method($value);
                }
            }
        }
        
        // Cas spécial pour les mises à jour rapides (drag & drop)
        if ($request->get_param('start') !== null) {
            $event->setStartDate($request->get_param('start'));
        }
        if ($request->get_param('end') !== null) {
            $event->setEndDate($request->get_param('end'));
        }
        if ($request->get_param('allDay') !== null) {
            $event->setAllDay($request->get_param('allDay'));
        }
        
        $result = $event->save();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'message' => __('Événement mis à jour avec succès', 'hyperplanning'),
        ));
    }
    
    public function deleteEvent($request) {
        $id = $request->get_param('id');
        $event = HP_Event::find($id);
        
        if (!$event) {
            return new WP_Error('not_found', __('Événement non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        // Vérifier les permissions spécifiques
        if (!current_user_can('hp_delete_all_events') && $event->getTrainerId() != get_current_user_id()) {
            return new WP_Error('forbidden', __('Vous n\'avez pas la permission de supprimer cet événement', 'hyperplanning'), array('status' => 403));
        }
        
        $event->delete();
        
        return rest_ensure_response(array(
            'message' => __('Événement supprimé avec succès', 'hyperplanning'),
        ));
    }
    
    // Endpoints pour les formateurs
    public function getTrainers($request) {
        $trainers = HP_Trainer::all();
        $response = array();
        
        foreach ($trainers as $trainer) {
            $response[] = array(
                'id' => $trainer->getId(),
                'name' => $trainer->getName(),
                'email' => $trainer->getEmail(),
                'phone' => $trainer->getPhone(),
                'specialties' => $trainer->getSpecialties(),
                'calendar_color' => $trainer->getCalendarColor(),
                'sync_enabled' => $trainer->getSyncEnabled(),
            );
        }
        
        return rest_ensure_response($response);
    }
    
    public function getTrainer($request) {
        $id = $request->get_param('id');
        $trainer = HP_Trainer::find($id);
        
        if (!$trainer) {
            return new WP_Error('not_found', __('Formateur non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'id' => $trainer->getId(),
            'name' => $trainer->getName(),
            'email' => $trainer->getEmail(),
            'phone' => $trainer->getPhone(),
            'specialties' => $trainer->getSpecialties(),
            'calendar_color' => $trainer->getCalendarColor(),
            'sync_enabled' => $trainer->getSyncEnabled(),
        ));
    }
    
    // Endpoints pour les calendriers
    public function getCalendars($request) {
        $args = array();
        
        if ($request->get_param('trainer_id')) {
            $args['trainer_id'] = $request->get_param('trainer_id');
        }
        
        $calendars = HP_Calendar::all($args);
        $response = array();
        
        foreach ($calendars as $calendar) {
            $response[] = array(
                'id' => $calendar->getId(),
                'name' => $calendar->getName(),
                'description' => $calendar->getDescription(),
                'type' => $calendar->getType(),
                'visibility' => $calendar->getVisibility(),
                'sync_source' => $calendar->getSyncSource(),
                'last_sync' => $calendar->getLastSync(),
                'trainer_id' => $calendar->getTrainerId(),
            );
        }
        
        return rest_ensure_response($response);
    }
    
    // Endpoints de synchronisation
    public function runSync($request) {
        $sync_manager = new HP_Sync_Manager();
        $sync_manager->runSync();
        
        return rest_ensure_response(array(
            'message' => __('Synchronisation lancée avec succès', 'hyperplanning'),
        ));
    }
    
    public function syncCalendar($request) {
        $id = $request->get_param('id');
        $calendar = HP_Calendar::find($id);
        
        if (!$calendar) {
            return new WP_Error('not_found', __('Calendrier non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        $sync_manager = new HP_Sync_Manager();
        $result = $sync_manager->syncCalendar($calendar);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'message' => sprintf(__('Calendrier synchronisé : %d événements traités', 'hyperplanning'), $result),
        ));
    }
    
    // Endpoints publics
    public function getPublicEvents($request) {
        $args = array(
            'start_date' => $request->get_param('start'),
            'end_date' => $request->get_param('end'),
            'trainer_id' => $request->get_param('trainer_id'),
            'calendar_id' => $request->get_param('calendar_id'),
            'status' => 'confirmed', // Seulement les événements confirmés en public
        );
        
        // Vérifier la visibilité du calendrier
        if ($args['calendar_id']) {
            $calendar = HP_Calendar::find($args['calendar_id']);
            if (!$calendar || $calendar->getVisibility() === 'private') {
                return rest_ensure_response(array());
            }
        }
        
        $events = HP_Event::all(array_filter($args));
        $response = array();
        
        foreach ($events as $event) {
            $calendar = $event->getCalendar();
            
            // Vérifier la visibilité
            if ($calendar && $calendar->getVisibility() === 'private') {
                continue;
            }
            
            $event_data = array(
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start' => $event->getStartDate(),
                'end' => $event->getEndDate(),
                'allDay' => $event->getAllDay(),
                'color' => $event->getColor(),
            );
            
            // Informations limitées en public
            if ($calendar && $calendar->getVisibility() === 'public') {
                $event_data['description'] = $event->getDescription();
                $event_data['location'] = $event->getLocation();
            }
            
            $response[] = $event_data;
        }
        
        return rest_ensure_response($response);
    }
    
    public function getPublicTrainers($request) {
        $trainers = HP_Trainer::all();
        $response = array();
        
        foreach ($trainers as $trainer) {
            // Vérifier si le formateur a des calendriers publics
            $calendars = $trainer->getCalendars();
            $has_public_calendar = false;
            
            foreach ($calendars as $calendar) {
                if ($calendar->getVisibility() !== 'private') {
                    $has_public_calendar = true;
                    break;
                }
            }
            
            if ($has_public_calendar) {
                $response[] = array(
                    'id' => $trainer->getId(),
                    'name' => $trainer->getName(),
                    'specialties' => $trainer->getSpecialties(),
                    'calendar_color' => $trainer->getCalendarColor(),
                );
            }
        }
        
        return rest_ensure_response($response);
    }
    
    public function exportIcal($request) {
        $id = $request->get_param('id');
        $calendar = HP_Calendar::find($id);
        
        if (!$calendar) {
            return new WP_Error('not_found', __('Calendrier non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        // Vérifier la visibilité
        if ($calendar->getVisibility() === 'private' && !is_user_logged_in()) {
            return new WP_Error('forbidden', __('Ce calendrier est privé', 'hyperplanning'), array('status' => 403));
        }
        
        $ical_sync = new HP_iCal_Sync();
        $ical_content = $ical_sync->export($calendar);
        
        // Retourner le contenu iCal avec les headers appropriés
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($calendar->getName()) . '.ics"');
        echo $ical_content;
        exit;
    }
}

// public/class-hp-shortcodes.php

class HP_Shortcodes {
    
    public function registerShortcodes() {
        add_shortcode('hyperplanning_calendar', array($this, 'calendarShortcode'));
        add_shortcode('hyperplanning_trainer_calendar', array($this, 'trainerCalendarShortcode'));
        add_shortcode('hyperplanning_trainers_list', array($this, 'trainersListShortcode'));
        add_shortcode('hyperplanning_upcoming_events', array($this, 'upcomingEventsShortcode'));
        add_shortcode('hyperplanning_trainer_availability', array($this, 'trainerAvailabilityShortcode'));
        add_shortcode('hyperplanning_event_submission', array($this, 'eventSubmissionShortcode'));
    }
    
    /**
     * Shortcode pour afficher le calendrier principal
     * [hyperplanning_calendar view="month" height="600"]
     */
    public function calendarShortcode($atts) {
        $atts = shortcode_atts(array(
            'view' => get_option('hp_default_calendar_view', 'month'),
            'height' => '600',
            'trainers' => '', // IDs séparés par des virgules
            'types' => '', // Types séparés par des virgules
            'show_filters' => 'yes',
            'show_legend' => 'yes',
        ), $atts, 'hyperplanning_calendar');
        
        // Enqueue des scripts nécessaires
        wp_enqueue_script('fullcalendar');
        wp_enqueue_script('hyperplanning-public');
        wp_enqueue_style('fullcalendar');
        wp_enqueue_style('hyperplanning-public');
        
        ob_start();
        ?>
        <div class="hp-calendar-wrapper">
            <?php if ($atts['show_filters'] === 'yes'): ?>
                <div class="hp-calendar-public-filters">
                    <select class="hp-filter-trainer">
                        <option value=""><?php _e('Tous les formateurs', 'hyperplanning'); ?></option>
                        <?php
                        $trainers = HP_Trainer::all();
                        foreach ($trainers as $trainer):
                        ?>
                            <option value="<?php echo esc_attr($trainer->getId()); ?>">
                                <?php echo esc_html($trainer->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="hp-public-calendar" 
                 data-view="<?php echo esc_attr($atts['view']); ?>"
                 data-trainers="<?php echo esc_attr($atts['trainers']); ?>"
                 data-types="<?php echo esc_attr($atts['types']); ?>"
                 style="height: <?php echo esc_attr($atts['height']); ?>px;">
            </div>
            
            <?php if ($atts['show_legend'] === 'yes'): ?>
                <div class="hp-calendar-legend">
                    <?php $this->renderLegend(); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher le calendrier d'un formateur spécifique
     * [hyperplanning_trainer_calendar trainer="1" show_info="yes"]
     */
    public function trainerCalendarShortcode($atts) {
        $atts = shortcode_atts(array(
            'trainer' => '',
            'show_info' => 'yes',
            'view' => 'month',
            'height' => '500',
        ), $atts, 'hyperplanning_trainer_calendar');
        
        if (!$atts['trainer']) {
            return '<p>' . __('Veuillez spécifier un ID de formateur.', 'hyperplanning') . '</p>';
        }
        
        $trainer = HP_Trainer::find($atts['trainer']);
        if (!$trainer) {
            return '<p>' . __('Formateur non trouvé.', 'hyperplanning') . '</p>';
        }
        
        wp_enqueue_script('fullcalendar');
        wp_enqueue_script('hyperplanning-public');
        wp_enqueue_style('fullcalendar');
        wp_enqueue_style('hyperplanning-public');
        
        ob_start();
        ?>
        <div class="hp-trainer-calendar-wrapper">
            <?php if ($atts['show_info'] === 'yes'): ?>
                <div class="hp-trainer-card">
                    <div class="hp-trainer-avatar">
                        <?php echo substr($trainer->getName(), 0, 1); ?>
                    </div>
                    <div class="hp-trainer-info">
                        <h3><?php echo esc_html($trainer->getName()); ?></h3>
                        <div class="hp-trainer-meta">
                            <?php if ($trainer->getEmail()): ?>
                                <div><strong><?php _e('Email:', 'hyperplanning'); ?></strong> <?php echo esc_html($trainer->getEmail()); ?></div>
                            <?php endif; ?>
                            <?php if ($trainer->getPhone()): ?>
                                <div><strong><?php _e('Téléphone:', 'hyperplanning'); ?></strong> <?php echo esc_html($trainer->getPhone()); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($trainer->getSpecialties()): ?>
                            <div class="hp-trainer-specialties">
                                <?php foreach ($trainer->getSpecialties() as $specialty): ?>
                                    <span><?php echo esc_html($specialty); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="hp-public-calendar" 
                 data-trainer-id="<?php echo esc_attr($trainer->getId()); ?>"
                 data-view="<?php echo esc_attr($atts['view']); ?>"
                 style="height: <?php echo esc_attr($atts['height']); ?>px;">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher la liste des formateurs
     * [hyperplanning_trainers_list columns="3" show_calendar_link="yes"]
     */
    public function trainersListShortcode($atts) {
        $atts = shortcode_atts(array(
            'columns' => '3',
            'show_calendar_link' => 'yes',
            'show_specialties' => 'yes',
            'show_contact' => 'no',
        ), $atts, 'hyperplanning_trainers_list');
        
        $trainers = HP_Trainer::all();
        
        if (empty($trainers)) {
            return '<p>' . __('Aucun formateur disponible.', 'hyperplanning') . '</p>';
        }
        
        wp_enqueue_style('hyperplanning-public');
        
        ob_start();
        ?>
        <div class="hp-trainers-grid" style="grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr);">
            <?php foreach ($trainers as $trainer): ?>
                <div class="hp-trainer-card">
                    <div class="hp-trainer-avatar">
                        <?php echo substr($trainer->getName(), 0, 1); ?>
                    </div>
                    <div class="hp-trainer-info">
                        <h3><?php echo esc_html($trainer->getName()); ?></h3>
                        
                        <?php if ($atts['show_contact'] === 'yes'): ?>
                            <div class="hp-trainer-contact">
                                <?php if ($trainer->getEmail()): ?>
                                    <a href="mailto:<?php echo esc_attr($trainer->getEmail()); ?>">
                                        <?php echo esc_html($trainer->getEmail()); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($trainer->getPhone()): ?>
                                    <div><?php echo esc_html($trainer->getPhone()); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_specialties'] === 'yes' && $trainer->getSpecialties()): ?>
                            <div class="hp-trainer-specialties">
                                <?php foreach ($trainer->getSpecialties() as $specialty): ?>
                                    <span><?php echo esc_html($specialty); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_calendar_link'] === 'yes'): ?>
                            <a href="<?php echo esc_url(add_query_arg('trainer_id', $trainer->getId(), get_permalink())); ?>" class="hp-trainer-calendar-link">
                                <?php _e('Voir le calendrier', 'hyperplanning'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les prochains événements
     * [hyperplanning_upcoming_events limit="5" trainer="1" days="30"]
     */
    public function upcomingEventsShortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => '5',
            'trainer' => '',
            'days' => '30',
            'show_description' => 'no',
            'show_location' => 'yes',
            'show_trainer' => 'yes',
        ), $atts, 'hyperplanning_upcoming_events');
        
        $args = array(
            'start_date' => current_time('mysql'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+' . intval($atts['days']) . ' days')),
            'status' => 'confirmed',
            'orderby' => 'start_date',
            'order' => 'ASC',
            'limit' => intval($atts['limit']),
        );
        
        if ($atts['trainer']) {
            $args['trainer_id'] = intval($atts['trainer']);
        }
        
        $events = HP_Event::all($args);
        
        if (empty($events)) {
            return '<p>' . __('Aucun événement à venir.', 'hyperplanning') . '</p>';
        }
        
        wp_enqueue_style('hyperplanning-public');
        
        ob_start();
        ?>
        <div class="hp-upcoming-events">
            <?php foreach ($events as $event): ?>
                <div class="hp-event-item">
                    <div class="hp-event-date">
                        <div class="hp-event-day"><?php echo date_i18n('j', strtotime($event->getStartDate())); ?></div>
                        <div class="hp-event-month"><?php echo date_i18n('M', strtotime($event->getStartDate())); ?></div>
                    </div>
                    <div class="hp-event-details">
                        <h4><?php echo esc_html($event->getTitle()); ?></h4>
                        
                        <?php if ($atts['show_description'] === 'yes' && $event->getDescription()): ?>
                            <p><?php echo esc_html($event->getDescription()); ?></p>
                        <?php endif; ?>
                        
                        <div class="hp-event-meta">
                            <span class="hp-event-time">
                                <?php echo date_i18n(get_option('time_format'), strtotime($event->getStartDate())); ?>
                                <?php if ($event->getEndDate() && !$event->getAllDay()): ?>
                                    - <?php echo date_i18n(get_option('time_format'), strtotime($event->getEndDate())); ?>
                                <?php endif; ?>
                            </span>
                            
                            <?php if ($atts['show_location'] === 'yes' && $event->getLocation()): ?>
                                <span class="hp-event-location">
                                    <?php echo esc_html($event->getLocation()); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($atts['show_trainer'] === 'yes'): ?>
                                <?php $trainer = $event->getTrainer(); ?>
                                <?php if ($trainer): ?>
                                    <span class="hp-event-trainer">
                                        <?php echo esc_html($trainer->getName()); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher la disponibilité d'un formateur
     * [hyperplanning_trainer_availability trainer="1" weeks="4"]
     */
    public function trainerAvailabilityShortcode($atts) {
        $atts = shortcode_atts(array(
            'trainer' => '',
            'weeks' => '4',
            'show_booked' => 'no',
        ), $atts, 'hyperplanning_trainer_availability');
        
        if (!$atts['trainer']) {
            return '<p>' . __('Veuillez spécifier un ID de formateur.', 'hyperplanning') . '</p>';
        }
        
        $trainer = HP_Trainer::find($atts['trainer']);
        if (!$trainer) {
            return '<p>' . __('Formateur non trouvé.', 'hyperplanning') . '</p>';
        }
        
        wp_enqueue_style('hyperplanning-public');
        
        // Générer le tableau de disponibilités
        $weeks = intval($atts['weeks']);
        $start_date = new DateTime();
        $end_date = clone $start_date;
        $end_date->modify('+' . $weeks . ' weeks');
        
        // Récupérer les événements du formateur
        $events = $trainer->getEvents(array(
            'start_date' => $start_date->format('Y-m-d'),
            'end_date' => $end_date->format('Y-m-d'),
            'status' => 'confirmed',
        ));
        
        // Créer un tableau des jours occupés
        $busy_days = array();
        foreach ($events as $event) {
            $event_start = new DateTime($event->getStartDate());
            $event_end = new DateTime($event->getEndDate());
            
            while ($event_start <= $event_end) {
                $busy_days[$event_start->format('Y-m-d')] = true;
                $event_start->modify('+1 day');
            }
        }
        
        ob_start();
        ?>
        <div class="hp-availability-calendar">
            <h3><?php echo sprintf(__('Disponibilité de %s', 'hyperplanning'), esc_html($trainer->getName())); ?></h3>
            
            <div class="hp-availability-grid">
                <?php
                $current_date = clone $start_date;
                while ($current_date < $end_date):
                    $date_key = $current_date->format('Y-m-d');
                    $is_busy = isset($busy_days[$date_key]);
                    $is_past = $current_date < new DateTime();
                    ?>
                    <div class="hp-availability-day <?php echo $is_busy ? 'busy' : 'available'; ?> <?php echo $is_past ? 'past' : ''; ?>">
                        <div class="hp-day-number"><?php echo $current_date->format('j'); ?></div>
                        <div class="hp-day-month"><?php echo date_i18n('M', $current_date->getTimestamp()); ?></div>
                        <?php if ($atts['show_booked'] === 'yes' && $is_busy): ?>
                            <div class="hp-day-status"><?php _e('Occupé', 'hyperplanning'); ?></div>
                        <?php elseif (!$is_busy && !$is_past): ?>
                            <div class="hp-day-status"><?php _e('Disponible', 'hyperplanning'); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php
                    $current_date->modify('+1 day');
                endwhile;
                ?>
            </div>
            
            <div class="hp-availability-legend">
                <span class="available"><?php _e('Disponible', 'hyperplanning'); ?></span>
                <span class="busy"><?php _e('Occupé', 'hyperplanning'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour permettre la soumission d'événements
     * [hyperplanning_event_submission trainer="1" calendar="1"]
     */
    public function eventSubmissionShortcode($atts) {
        $atts = shortcode_atts(array(
            'trainer' => '',
            'calendar' => '',
            'require_login' => 'yes',
            'success_message' => __('Votre événement a été soumis avec succès.', 'hyperplanning'),
            'redirect' => '',
        ), $atts, 'hyperplanning_event_submission');
        
        if ($atts['require_login'] === 'yes' && !is_user_logged_in()) {
            return '<p>' . __('Vous devez être connecté pour soumettre un événement.', 'hyperplanning') . '</p>';
        }
        
        wp_enqueue_style('hyperplanning-public');
        wp_enqueue_script('hyperplanning-public');
        
        ob_start();
        ?>
        <div class="hp-event-submission-form">
            <form method="post" action="" class="hp-form">
                <?php wp_nonce_field('hp_submit_event', 'hp_event_nonce'); ?>
                
                <?php if ($atts['trainer']): ?>
                    <input type="hidden" name="trainer_id" value="<?php echo esc_attr($atts['trainer']); ?>">
                <?php else: ?>
                    <div class="hp-form-group">
                        <label for="hp_trainer_id"><?php _e('Formateur', 'hyperplanning'); ?></label>
                        <select name="trainer_id" id="hp_trainer_id" required>
                            <option value=""><?php _e('Sélectionner un formateur', 'hyperplanning'); ?></option>
                            <?php foreach (HP_Trainer::all() as $trainer): ?>
                                <option value="<?php echo esc_attr($trainer->getId()); ?>">
                                    <?php echo esc_html($trainer->getName()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['calendar']): ?>
                    <input type="hidden" name="calendar_id" value="<?php echo esc_attr($atts['calendar']); ?>">
                <?php endif; ?>
                
                <div class="hp-form-group">
                    <label for="hp_event_title"><?php _e('Titre de l\'événement', 'hyperplanning'); ?></label>
                    <input type="text" name="title" id="hp_event_title" required>
                </div>
                
                <div class="hp-form-group">
                    <label for="hp_event_description"><?php _e('Description', 'hyperplanning'); ?></label>
                    <textarea name="description" id="hp_event_description" rows="4"></textarea>
                </div>
                
                <div class="hp-form-row">
                    <div class="hp-form-group">
                        <label for="hp_event_start_date"><?php _e('Date de début', 'hyperplanning'); ?></label>
                        <input type="datetime-local" name="start_date" id="hp_event_start_date" required>
                    </div>
                    
                    <div class="hp-form-group">
                        <label for="hp_event_end_date"><?php _e('Date de fin', 'hyperplanning'); ?></label>
                        <input type="datetime-local" name="end_date" id="hp_event_end_date" required>
                    </div>
                </div>
                
                <div class="hp-form-group">
                    <label for="hp_event_location"><?php _e('Lieu', 'hyperplanning'); ?></label>
                    <input type="text" name="location" id="hp_event_location">
                </div>
                
                <div class="hp-form-group">
                    <label>
                        <input type="checkbox" name="all_day" value="1">
                        <?php _e('Événement sur toute la journée', 'hyperplanning'); ?>
                    </label>
                </div>
                
                <div class="hp-form-actions">
                    <button type="submit" class="hp-submit-button">
                        <?php _e('Soumettre l\'événement', 'hyperplanning'); ?>
                    </button>
                </div>
            </form>
            
            <div class="hp-form-message" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function renderLegend() {
        $trainers = HP_Trainer::all();
        
        if (empty($trainers)) {
            return;
        }
        
        echo '<h4>' . __('Légende', 'hyperplanning') . '</h4>';
        echo '<div class="hp-legend-items">';
        
        foreach ($trainers as $trainer) {
            echo '<div class="hp-legend-item">';
            echo '<span class="hp-legend-color" style="background-color: ' . esc_attr($trainer->getCalendarColor()) . ';"></span>';
            echo '<span class="hp-legend-label">' . esc_html($trainer->getName()) . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
    }
}

// public/class-hp-public.php

class HP_Public {
    
    public function enqueueStyles() {
        wp_enqueue_style(
            'hyperplanning-public',
            HYPERPLANNING_PLUGIN_URL . 'assets/css/public.css',
            array(),
            HYPERPLANNING_VERSION
        );
    }
    
    public function enqueueScripts() {
        // Enregistrer les scripts (ils seront chargés par les shortcodes si nécessaire)
        wp_register_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
            array(),
            '6.1.8',
            true
        );
        
        wp_register_script(
            'hyperplanning-public',
            HYPERPLANNING_PLUGIN_URL . 'assets/js/public.js',
            array('jquery', 'fullcalendar'),
            HYPERPLANNING_VERSION,
            true
        );
        
        wp_localize_script('hyperplanning-public', 'hp_public', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('hyperplanning/v1/'),
            'locale' => get_locale(),
            'date_format' => get_option('hp_date_format', 'Y-m-d'),
            'time_format' => get_option('hp_time_format', 'H:i'),
            'week_starts_on' => get_option('hp_week_starts_on', 1),
        ));
    }
    
    public function handleEventSubmission() {
        if (!isset($_POST['hp_event_nonce']) || !wp_verify_nonce($_POST['hp_event_nonce'], 'hp_submit_event')) {
            return;
        }
        
        $event = new HP_Event();
        
        $event->setTrainerId(intval($_POST['trainer_id']));
        $event->setCalendarId(intval($_POST['calendar_id']));
        $event->setTitle(sanitize_text_field($_POST['title']));
        $event->setDescription(sanitize_textarea_field($_POST['description']));
        $event->setLocation(sanitize_text_field($_POST['location']));
        $event->setStartDate(sanitize_text_field($_POST['start_date']));
        $event->setEndDate(sanitize_text_field($_POST['end_date']));
        $event->setAllDay(isset($_POST['all_day']));
        $event->setStatus('tentative'); // En attente de validation
        
        $result = $event->save();
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        // Notification par email
        $this->sendEventNotification($event);
        
        // Redirection ou message de succès
        if (!empty($_POST['redirect'])) {
            wp_redirect(esc_url_raw($_POST['redirect']));
            exit;
        }
    }
    
    private function sendEventNotification($event) {
        $trainer = $event->getTrainer();
        if (!$trainer) {
            return;
        }
        
        $subject = sprintf(__('Nouvel événement soumis : %s', 'hyperplanning'), $event->getTitle());
        
        $message = sprintf(
            __("Un nouvel événement a été soumis :\n\nTitre : %s\nDate : %s\nLieu : %s\nDescription : %s\n\nConnectez-vous pour valider cet événement.", 'hyperplanning'),
            $event->getTitle(),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->getStartDate())),
            $event->getLocation(),
            $event->getDescription()
        );
        
        wp_mail($trainer->getEmail(), $subject, $message);
    }
}