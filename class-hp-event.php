<?php
/**
 * Modèle Événement
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */
 
 if ( class_exists( 'HP_Event' ) ) {
    return;
}


// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

class HP_Event {
    private $id;
    private $calendar_id;
    private $trainer_id;
    private $external_id;
    private $title;
    private $description;
    private $location;
    private $start_date;
    private $end_date;
    private $all_day;
    private $status;
    private $attendees;
    private $recurrence_rule;
    private $parent_event_id;
    private $color;
    private $metadata;
    private $created_at;
    private $updated_at;
    
    /**
     * Constructeur
     */
    public function __construct($data = array()) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    /**
     * Hydrater l'objet avec des données
     */
    private function hydrate($data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getCalendarId() { return $this->calendar_id; }
    public function getTrainerId() { return $this->trainer_id; }
    public function getExternalId() { return $this->external_id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getLocation() { return $this->location; }
    public function getStartDate() { return $this->start_date; }
    public function getEndDate() { return $this->end_date; }
    public function getAllDay() { return $this->all_day; }
    public function getStatus() { return $this->status; }
    public function getAttendees() { return $this->attendees; }
    public function getRecurrenceRule() { return $this->recurrence_rule; }
    public function getParentEventId() { return $this->parent_event_id; }
    public function getColor() { return $this->color; }
    public function getMetadata() { return $this->metadata; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
    
    // Setters
    public function setId($id) { 
        $this->id = (int) $id; 
    }
    
    public function setCalendarId($calendar_id) { 
        $this->calendar_id = (int) $calendar_id; 
    }
    
    public function setTrainerId($trainer_id) { 
        $this->trainer_id = (int) $trainer_id; 
    }
    
    public function setExternalId($external_id) { 
        $this->external_id = sanitize_text_field($external_id); 
    }
    
    public function setTitle($title) { 
        $this->title = sanitize_text_field($title); 
    }
    
    public function setDescription($description) { 
        $this->description = sanitize_textarea_field($description); 
    }
    
    public function setLocation($location) { 
        $this->location = sanitize_text_field($location); 
    }
    
    public function setStartDate($date) { 
        $this->start_date = $date; 
    }
    
    public function setEndDate($date) { 
        $this->end_date = $date; 
    }
    
    public function setAllDay($all_day) { 
        $this->all_day = (bool) $all_day; 
    }
    
    public function setStatus($status) { 
        if (HP_Constants::isValidEventStatus($status)) {
            $this->status = $status;
        } else {
            $this->status = HP_Constants::EVENT_STATUS_CONFIRMED;
        }
    }
    
    public function setAttendees($attendees) { 
        if (is_string($attendees)) {
            $this->attendees = json_decode($attendees, true);
        } else {
            $this->attendees = $attendees;
        }
    }
    
    public function setRecurrenceRule($rule) { 
        $this->recurrence_rule = $rule; 
    }
    
    public function setParentEventId($id) { 
        $this->parent_event_id = (int) $id; 
    }
    
    public function setColor($color) { 
        $this->color = hp_sanitize_hex_color($color); 
    }
    
    public function setMetadata($metadata) { 
        if (is_string($metadata)) {
            $this->metadata = json_decode($metadata, true);
        } else {
            $this->metadata = $metadata;
        }
    }
    
    public function setCreatedAt($date) { 
        $this->created_at = $date; 
    }
    
    public function setUpdatedAt($date) { 
        $this->updated_at = $date; 
    }
    
    /**
     * Valider les données de l'événement
     */
    private function validate() {
        // Titre obligatoire
        if (empty($this->title)) {
            return new WP_Error('empty_title', __('Le titre est obligatoire', 'hyperplanning'));
        }
        
        // Dates obligatoires
        if (empty($this->start_date) || empty($this->end_date)) {
            return new WP_Error('empty_dates', __('Les dates sont obligatoires', 'hyperplanning'));
        }
        
        // Vérifier que la date de fin est après la date de début
        if (strtotime($this->start_date) > strtotime($this->end_date)) {
            return new WP_Error('invalid_dates', __('La date de fin doit être après la date de début', 'hyperplanning'));
        }
        
        // Calendrier obligatoire
        if (empty($this->calendar_id)) {
            return new WP_Error('empty_calendar', __('Le calendrier est obligatoire', 'hyperplanning'));
        }
        
        return true;
    }
    
    /**
     * Vérifier les conflits
     */
    private function hasConflict() {
        if (!$this->trainer_id) {
            return false;
        }
        
        $trainer = HP_Trainer::find($this->trainer_id);
        if (!$trainer) {
            return false;
        }
        
        return $trainer->hasConflict($this->start_date, $this->end_date, $this->id);
    }
    
    /**
     * Sauvegarder l'événement
     */
    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_events';
        
        // Validation
        $valid = $this->validate();
        if (is_wp_error($valid)) {
            return $valid;
        }
        
        // Vérifier les conflits si activé
        if (get_option('hp_enable_conflicts_detection', 1) && $this->hasConflict()) {
            return new WP_Error('conflict', __('Cet horaire entre en conflit avec un autre événement.', 'hyperplanning'));
        }
        
        $data = array(
            'calendar_id' => $this->calendar_id,
            'trainer_id' => $this->trainer_id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'all_day' => $this->all_day ? 1 : 0,
            'status' => $this->status,
            'attendees' => json_encode($this->attendees),
            'recurrence_rule' => $this->recurrence_rule,
            'parent_event_id' => $this->parent_event_id,
            'color' => $this->color,
            'metadata' => json_encode($this->metadata),
        );
        
        if ($this->id) {
            // Mise à jour
            $result = $wpdb->update($table, $data, array('id' => $this->id));
            
            if ($result === false) {
                hp_log('Erreur mise à jour événement: ' . $wpdb->last_error, 'error');
                return new WP_Error('db_error', __('Erreur lors de la mise à jour', 'hyperplanning'));
            }
        } else {
            // Insertion
            $result = $wpdb->insert($table, $data);
            
            if ($result === false) {
                hp_log('Erreur insertion événement: ' . $wpdb->last_error, 'error');
                return new WP_Error('db_error', __('Erreur lors de la création', 'hyperplanning'));
            }
            
            $this->id = $wpdb->insert_id;
        }
        
        // Nettoyer le cache
        wp_cache_delete('event_' . $this->id, HP_Constants::CACHE_GROUP);
        hp_clear_cache('events');
        
        // Hook après sauvegarde
        do_action('hp_event_saved', $this);
        
        return $this->id;
    }
    
    /**
     * Supprimer l'événement
     */
    public function delete() {
        global $wpdb;
        
        if (!$this->id) {
            return false;
        }
        
        // Hook avant suppression
        do_action('hp_before_event_delete', $this);
        
        // Supprimer les événements récurrents enfants
        if (!$this->parent_event_id) {
            $wpdb->delete($wpdb->prefix . 'hp_events', array('parent_event_id' => $this->id));
        }
        
        // Supprimer les conflits associés
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}hp_conflicts 
            WHERE event_id = %d OR conflicting_event_id = %d",
            $this->id,
            $this->id
        ));
        
        // Supprimer l'événement
        $result = $wpdb->delete($wpdb->prefix . 'hp_events', array('id' => $this->id));
        
        if ($result) {
            // Nettoyer le cache
            wp_cache_delete('event_' . $this->id, HP_Constants::CACHE_GROUP);
            hp_clear_cache('events');
            
            // Hook après suppression
            do_action('hp_event_deleted', $this->id);
        }
        
        return $result !== false;
    }
    
    /**
     * Trouver un événement par ID
     */
    public static function find($id) {
        global $wpdb;
        
        // Vérifier le cache
        $cached = wp_cache_get('event_' . $id, HP_Constants::CACHE_GROUP);
        if ($cached !== false) {
            return new self($cached);
        }
        
        $table = $wpdb->prefix . 'hp_events';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        // Mettre en cache
        wp_cache_set('event_' . $id, $row, HP_Constants::CACHE_GROUP, HP_Constants::CACHE_EXPIRATION);
        
        return new self($row);
    }
    
    /**
     * Trouver par ID externe
     */
    public static function findByExternalId($external_id, $calendar_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_events';
        
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE external_id = %s", $external_id);
        
        if ($calendar_id) {
            $sql .= $wpdb->prepare(" AND calendar_id = %d", $calendar_id);
        }
        
        $row = $wpdb->get_row($sql, ARRAY_A);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Trouver par formateur
     */
    public static function findByTrainer($trainer_id, $args = array()) {
        $defaults = array('trainer_id' => $trainer_id);
        $args = wp_parse_args($args, $defaults);
        
        return self::all($args);
    }
    
    /**
     * Obtenir tous les événements
     */
    public static function all($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_events';
        
        $defaults = array(
            'calendar_id' => null,
            'trainer_id' => null,
            'status' => null,
            'start_date' => null,
            'end_date' => null,
            'orderby' => 'start_date',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Construire la requête
        $where = array('1=1');
        $values = array();
        
        if ($args['calendar_id']) {
            $where[] = 'calendar_id = %d';
            $values[] = $args['calendar_id'];
        }
        
        if ($args['trainer_id']) {
            $where[] = 'trainer_id = %d';
            $values[] = $args['trainer_id'];
        }
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if ($args['start_date']) {
            $where[] = 'end_date >= %s';
            $values[] = $args['start_date'];
        }
        
        if ($args['end_date']) {
            $where[] = 'start_date <= %s';
            $values[] = $args['end_date'];
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $values[] = $args['limit'];
            $values[] = $args['offset'];
        }
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        $events = array();
        
        foreach ($results as $row) {
            $events[] = new self($row);
        }
        
        return $events;
    }
    
    /**
     * Obtenir le calendrier associé
     */
    public function getCalendar() {
        return $this->calendar_id ? HP_Calendar::find($this->calendar_id) : null;
    }
    
    /**
     * Obtenir le formateur associé
     */
    public function getTrainer() {
        return $this->trainer_id ? HP_Trainer::find($this->trainer_id) : null;
    }
    
    /**
     * Obtenir l'événement parent (pour les récurrences)
     */
    public function getParentEvent() {
        return $this->parent_event_id ? self::find($this->parent_event_id) : null;
    }
    
    /**
     * Obtenir les événements enfants (pour les récurrences)
     */
    public function getChildEvents() {
        if (!$this->id) {
            return array();
        }
        
        return self::all(array('parent_event_id' => $this->id));
    }
    
    /**
     * Vérifier si l'utilisateur peut voir cet événement
     */
    public function canView($user_id = null) {
        $calendar = $this->getCalendar();
        if (!$calendar) {
            return false;
        }
        
        return $calendar->canView($user_id);
    }
    
    /**
     * Vérifier si l'utilisateur peut éditer cet événement
     */
    public function canEdit($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Administrateurs
        if (user_can($user_id, 'hp_edit_all_events')) {
            return true;
        }
        
        // Propriétaire de l'événement
        if ($this->trainer_id) {
            $trainer = HP_Trainer::find($this->trainer_id);
            if ($trainer && $trainer->getUserId() == $user_id) {
                return user_can($user_id, 'hp_edit_own_events');
            }
        }
        
        return false;
    }
    
    /**
     * Obtenir la durée en minutes
     */
    public function getDuration() {
        return hp_date_diff($this->start_date, $this->end_date, 'minutes');
    }
    
    /**
     * Obtenir la couleur d'affichage
     */
    public function getDisplayColor() {
        if ($this->color) {
            return $this->color;
        }
        
        $trainer = $this->getTrainer();
        if ($trainer) {
            return $trainer->getCalendarColor();
        }
        
        $calendar = $this->getCalendar();
        if ($calendar) {
            return HP_Constants::getDefaultColorForType($calendar->getType());
        }
        
        return '#0073aa';
    }
    
    /**
     * Ajouter un participant
     */
    public function addAttendee($email, $name = '', $response = 'pending') {
        if (!is_array($this->attendees)) {
            $this->attendees = array();
        }
        
        // Vérifier si le participant existe déjà
        foreach ($this->attendees as $attendee) {
            if ($attendee['email'] === $email) {
                return false;
            }
        }
        
        $this->attendees[] = array(
            'email' => sanitize_email($email),
            'displayName' => sanitize_text_field($name),
            'responseStatus' => $response,
        );
        
        return true;
    }
    
    /**
     * Supprimer un participant
     */
    public function removeAttendee($email) {
        if (!is_array($this->attendees)) {
            return false;
        }
        
        $filtered = array();
        $removed = false;
        
        foreach ($this->attendees as $attendee) {
            if ($attendee['email'] !== $email) {
                $filtered[] = $attendee;
            } else {
                $removed = true;
            }
        }
        
        $this->attendees = $filtered;
        return $removed;
    }
    
    /**
     * Générer les récurrences
     */
    public function generateRecurrences($until_date = null) {
        if (!$this->recurrence_rule || $this->parent_event_id) {
            return array();
        }
        
        // Implémentation basique pour démonstration
        // En production, utiliser une librairie comme php-rrule
        
        $recurrences = array();
        
        // Parser la règle RRULE simple
        if (preg_match('/FREQ=DAILY;COUNT=(\d+)/', $this->recurrence_rule, $matches)) {
            $count = intval($matches[1]);
            $current_date = new DateTime($this->start_date);
            $duration = $this->getDuration();
            
            for ($i = 1; $i < $count; $i++) {
                $current_date->modify('+1 day');
                
                $recurrence = new self();
                $recurrence->setCalendarId($this->calendar_id);
                $recurrence->setTrainerId($this->trainer_id);
                $recurrence->setTitle($this->title);
                $recurrence->setDescription($this->description);
                $recurrence->setLocation($this->location);
                $recurrence->setStartDate($current_date->format('Y-m-d H:i:s'));
                
                $end_date = clone $current_date;
                $end_date->modify('+' . $duration . ' minutes');
                $recurrence->setEndDate($end_date->format('Y-m-d H:i:s'));
                
                $recurrence->setAllDay($this->all_day);
                $recurrence->setStatus($this->status);
                $recurrence->setAttendees($this->attendees);
                $recurrence->setParentEventId($this->id);
                $recurrence->setColor($this->color);
                
                $recurrences[] = $recurrence;
            }
        }
        
        return $recurrences;
    }
    
    /**
     * Convertir en tableau pour l'API
     */
    public function toArray() {
        return array(
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'start' => $this->start_date,
            'end' => $this->end_date,
            'allDay' => $this->all_day,
            'color' => $this->getDisplayColor(),
            'location' => $this->location,
            'status' => $this->status,
            'attendees' => $this->attendees,
            'trainer_id' => $this->trainer_id,
            'calendar_id' => $this->calendar_id,
            'external_id' => $this->external_id,
            'recurrence_rule' => $this->recurrence_rule,
            'parent_event_id' => $this->parent_event_id,
            'metadata' => $this->metadata,
        );
    }
    
    /**
     * Convertir en format iCal
     */
    public function toIcal() {
        $ical = "BEGIN:VEVENT\r\n";
        
        // UID
        $uid = $this->external_id ?: 'hp-' . $this->id . '@' . parse_url(home_url(), PHP_URL_HOST);
        $ical .= "UID:$uid\r\n";
        
        // Dates
        if ($this->all_day) {
            $ical .= "DTSTART;VALUE=DATE:" . date('Ymd', strtotime($this->start_date)) . "\r\n";
            $ical .= "DTEND;VALUE=DATE:" . date('Ymd', strtotime($this->end_date)) . "\r\n";
        } else {
            $ical .= "DTSTART:" . gmdate('Ymd\THis\Z', strtotime($this->start_date)) . "\r\n";
            $ical .= "DTEND:" . gmdate('Ymd\THis\Z', strtotime($this->end_date)) . "\r\n";
        }
        
        // Propriétés
        $ical .= "SUMMARY:" . $this->escapeIcal($this->title) . "\r\n";
        
        if ($this->description) {
            $ical .= "DESCRIPTION:" . $this->escapeIcal($this->description) . "\r\n";
        }
        
        if ($this->location) {
            $ical .= "LOCATION:" . $this->escapeIcal($this->location) . "\r\n";
        }
        
        $ical .= "STATUS:" . strtoupper($this->status) . "\r\n";
        
        // Participants
        if (is_array($this->attendees)) {
            foreach ($this->attendees as $attendee) {
                $ical .= "ATTENDEE;CN=" . $this->escapeIcal($attendee['displayName'] ?? '') . ":mailto:" . $attendee['email'] . "\r\n";
            }
        }
        
        // Récurrence
        if ($this->recurrence_rule) {
            $ical .= "RRULE:" . $this->recurrence_rule . "\r\n";
        }
        
        // Timestamps
        $ical .= "CREATED:" . gmdate('Ymd\THis\Z', strtotime($this->created_at)) . "\r\n";
        $ical .= "LAST-MODIFIED:" . gmdate('Ymd\THis\Z', strtotime($this->updated_at)) . "\r\n";
        
        $ical .= "END:VEVENT\r\n";
        
        return $ical;
    }
    
    /**
     * Échapper le texte pour iCal
     */
    private function escapeIcal($text) {
        $text = str_replace(array("\r\n", "\n", "\r"), "\\n", $text);
        $text = str_replace(array(",", ";", "\\"), array("\\,", "\\;", "\\\\"), $text);
        return $text;
    }
}