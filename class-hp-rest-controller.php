<?php
/**
 * Contrôleur API REST
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Protection contre les inclusions multiples
if (class_exists('HP_REST_Controller')) {
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
    
    public function createTrainer($request) {
        $trainer = new HP_Trainer();
        
        $trainer->setName($request->get_param('name'));
        $trainer->setEmail($request->get_param('email'));
        $trainer->setPhone($request->get_param('phone'));
        $trainer->setSpecialties($request->get_param('specialties'));
        $trainer->setCalendarColor($request->get_param('calendar_color'));
        $trainer->setSyncEnabled($request->get_param('sync_enabled'));
        $trainer->setUserId($request->get_param('user_id'));
        
        $result = $trainer->save();
        
        if (!$result) {
            return new WP_Error('save_failed', __('Impossible de sauvegarder le formateur', 'hyperplanning'));
        }
        
        return rest_ensure_response(array(
            'id' => $trainer->getId(),
            'message' => __('Formateur créé avec succès', 'hyperplanning'),
        ));
    }
    
    public function updateTrainer($request) {
        $id = $request->get_param('id');
        $trainer = HP_Trainer::find($id);
        
        if (!$trainer) {
            return new WP_Error('not_found', __('Formateur non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        $fields = array('name', 'email', 'phone', 'specialties', 'calendar_color', 'sync_enabled', 'user_id', 'google_calendar_id', 'ical_url');
        
        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $method = 'set' . str_replace('_', '', ucwords($field, '_'));
                if (method_exists($trainer, $method)) {
                    $trainer->$method($value);
                }
            }
        }
        
        $result = $trainer->save();
        
        if (!$result) {
            return new WP_Error('save_failed', __('Impossible de mettre à jour le formateur', 'hyperplanning'));
        }
        
        return rest_ensure_response(array(
            'message' => __('Formateur mis à jour avec succès', 'hyperplanning'),
        ));
    }
    
    public function deleteTrainer($request) {
        $id = $request->get_param('id');
        $trainer = HP_Trainer::find($id);
        
        if (!$trainer) {
            return new WP_Error('not_found', __('Formateur non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        $trainer->delete();
        
        return rest_ensure_response(array(
            'message' => __('Formateur supprimé avec succès', 'hyperplanning'),
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
    
    public function getCalendar($request) {
        $id = $request->get_param('id');
        $calendar = HP_Calendar::find($id);
        
        if (!$calendar) {
            return new WP_Error('not_found', __('Calendrier non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        return rest_ensure_response($calendar->toArray());
    }
    
    public function createCalendar($request) {
        $calendar = new HP_Calendar();
        
        $calendar->setName($request->get_param('name'));
        $calendar->setDescription($request->get_param('description'));
        $calendar->setType($request->get_param('type'));
        $calendar->setVisibility($request->get_param('visibility'));
        $calendar->setTrainerId($request->get_param('trainer_id'));
        $calendar->setSyncSource($request->get_param('sync_source'));
        $calendar->setSyncId($request->get_param('sync_id'));
        
        $result = $calendar->save();
        
        if (!$result) {
            return new WP_Error('save_failed', __('Impossible de sauvegarder le calendrier', 'hyperplanning'));
        }
        
        return rest_ensure_response(array(
            'id' => $calendar->getId(),
            'message' => __('Calendrier créé avec succès', 'hyperplanning'),
        ));
    }
    
    public function updateCalendar($request) {
        $id = $request->get_param('id');
        $calendar = HP_Calendar::find($id);
        
        if (!$calendar) {
            return new WP_Error('not_found', __('Calendrier non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        $fields = array('name', 'description', 'type', 'visibility', 'trainer_id', 'sync_source', 'sync_id');
        
        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $method = 'set' . str_replace('_', '', ucwords($field, '_'));
                if (method_exists($calendar, $method)) {
                    $calendar->$method($value);
                }
            }
        }
        
        $result = $calendar->save();
        
        if (!$result) {
            return new WP_Error('save_failed', __('Impossible de mettre à jour le calendrier', 'hyperplanning'));
        }
        
        return rest_ensure_response(array(
            'message' => __('Calendrier mis à jour avec succès', 'hyperplanning'),
        ));
    }
    
    public function deleteCalendar($request) {
        $id = $request->get_param('id');
        $calendar = HP_Calendar::find($id);
        
        if (!$calendar) {
            return new WP_Error('not_found', __('Calendrier non trouvé', 'hyperplanning'), array('status' => 404));
        }
        
        $calendar->delete();
        
        return rest_ensure_response(array(
            'message' => __('Calendrier supprimé avec succès', 'hyperplanning'),
        ));
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
