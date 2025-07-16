<?php
// includes/models/class-hp-trainer.php


if ( class_exists( 'HP_Trainer' ) ) {
    return;
}


class HP_Trainer {
    private $id;
    private $user_id;
    private $name;
    private $email;
    private $phone;
    private $specialties;
    private $calendar_color;
    private $google_calendar_id;
    private $ical_url;
    private $sync_enabled;
    
    public function __construct($data = array()) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    private function hydrate($data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }
    
    // Getters et Setters
    public function getId() { return $this->id; }
    public function setId($id) { $this->id = (int) $id; }
    
    public function getUserId() { return $this->user_id; }
    public function setUserId($user_id) { $this->user_id = (int) $user_id; }
    
    public function getName() { return $this->name; }
    public function setName($name) { $this->name = sanitize_text_field($name); }
    
    public function getEmail() { return $this->email; }
    public function setEmail($email) { $this->email = sanitize_email($email); }
    
    public function getPhone() { return $this->phone; }
    public function setPhone($phone) { $this->phone = sanitize_text_field($phone); }
    
    public function getSpecialties() { return $this->specialties; }
    public function setSpecialties($specialties) { 
        $this->specialties = is_array($specialties) ? $specialties : unserialize($specialties);
    }
    
    public function getCalendarColor() { return $this->calendar_color; }
    public function setCalendarColor($color) { 
        $this->calendar_color = sanitize_hex_color($color) ?: '#0073aa';
    }
    
    public function getGoogleCalendarId() { return $this->google_calendar_id; }
    public function setGoogleCalendarId($id) { $this->google_calendar_id = sanitize_text_field($id); }
    
    public function getIcalUrl() { return $this->ical_url; }
    public function setIcalUrl($url) { $this->ical_url = esc_url_raw($url); }
    
    public function getSyncEnabled() { return $this->sync_enabled; }
    public function setSyncEnabled($enabled) { $this->sync_enabled = (bool) $enabled; }
    
    // Méthodes CRUD
    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_trainers';
        
        $data = array(
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'specialties' => serialize($this->specialties),
            'calendar_color' => $this->calendar_color,
            'google_calendar_id' => $this->google_calendar_id,
            'ical_url' => $this->ical_url,
            'sync_enabled' => $this->sync_enabled ? 1 : 0,
        );
        
        if ($this->id) {
            $wpdb->update($table, $data, array('id' => $this->id));
        } else {
            $wpdb->insert($table, $data);
            $this->id = $wpdb->insert_id;
        }
        
        return $this->id;
    }
    
    public static function find($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_trainers';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        
        return $row ? new self($row) : null;
    }
    
    public static function findByUserId($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_trainers';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id), ARRAY_A);
        
        return $row ? new self($row) : null;
    }
    
    public static function all($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_trainers';
        
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM $table ORDER BY {$args['orderby']} {$args['order']}";
        
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        $trainers = array();
        
        foreach ($results as $row) {
            $trainers[] = new self($row);
        }
        
        return $trainers;
    }
    
    public function delete() {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_trainers';
        
        if ($this->id) {
            // Supprimer aussi les calendriers et événements associés
            $this->deleteRelatedData();
            
            return $wpdb->delete($table, array('id' => $this->id));
        }
        
        return false;
    }
    
    private function deleteRelatedData() {
        global $wpdb;
        
        // Supprimer les événements
        $wpdb->delete($wpdb->prefix . 'hp_events', array('trainer_id' => $this->id));
        
        // Supprimer les calendriers
        $wpdb->delete($wpdb->prefix . 'hp_calendars', array('trainer_id' => $this->id));
    }
    
    public function getCalendars() {
        return HP_Calendar::findByTrainer($this->id);
    }
    
    public function getEvents($args = array()) {
        return HP_Event::findByTrainer($this->id, $args);
    }
    
    public function hasConflict($start_date, $end_date, $exclude_event_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_events';
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE trainer_id = %d 
            AND status = 'confirmed'
            AND ((start_date <= %s AND end_date > %s) 
                OR (start_date < %s AND end_date >= %s)
                OR (start_date >= %s AND end_date <= %s))",
            $this->id,
            $start_date, $start_date,
            $end_date, $end_date,
            $start_date, $end_date
        );
        
        if ($exclude_event_id) {
            $sql .= $wpdb->prepare(" AND id != %d", $exclude_event_id);
        }
        
        return (bool) $wpdb->get_var($sql);
    }
}

// includes/models/class-hp-calendar.php

class HP_Calendar {
    private $id;
    private $trainer_id;
    private $name;
    private $description;
    private $type;
    private $visibility;
    private $sync_source;
    private $sync_id;
    private $last_sync;
    
    public function __construct($data = array()) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    private function hydrate($data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }
    
    // Getters et Setters
    public function getId() { return $this->id; }
    public function setId($id) { $this->id = (int) $id; }
    
    public function getTrainerId() { return $this->trainer_id; }
    public function setTrainerId($trainer_id) { $this->trainer_id = (int) $trainer_id; }
    
    public function getName() { return $this->name; }
    public function setName($name) { $this->name = sanitize_text_field($name); }
    
    public function getDescription() { return $this->description; }
    public function setDescription($description) { $this->description = sanitize_textarea_field($description); }
    
    public function getType() { return $this->type; }
    public function setType($type) { 
        $allowed_types = array('training', 'availability', 'personal', 'resource');
        $this->type = in_array($type, $allowed_types) ? $type : 'training';
    }
    
    public function getVisibility() { return $this->visibility; }
    public function setVisibility($visibility) { 
        $allowed = array('public', 'private', 'internal');
        $this->visibility = in_array($visibility, $allowed) ? $visibility : 'public';
    }
    
    public function getSyncSource() { return $this->sync_source; }
    public function setSyncSource($source) { $this->sync_source = sanitize_text_field($source); }
    
    public function getSyncId() { return $this->sync_id; }
    public function setSyncId($id) { $this->sync_id = sanitize_text_field($id); }
    
    public function getLastSync() { return $this->last_sync; }
    public function setLastSync($date) { $this->last_sync = $date; }
    
    // Méthodes CRUD
    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_calendars';
        
        $data = array(
            'trainer_id' => $this->trainer_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'visibility' => $this->visibility,
            'sync_source' => $this->sync_source,
            'sync_id' => $this->sync_id,
            'last_sync' => $this->last_sync,
        );
        
        if ($this->id) {
            $wpdb->update($table, $data, array('id' => $this->id));
        } else {
            $wpdb->insert($table, $data);
            $this->id = $wpdb->insert_id;
        }
        
        return $this->id;
    }
    
    public static function find($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_calendars';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        
        return $row ? new self($row) : null;
    }
    
    public static function findByTrainer($trainer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_calendars';
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE trainer_id = %d ORDER BY name", $trainer_id),
            ARRAY_A
        );
        
        $calendars = array();
        foreach ($results as $row) {
            $calendars[] = new self($row);
        }
        
        return $calendars;
    }
    
    public static function all($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_calendars';
        
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'trainer_id' => null,
            'type' => null,
            'visibility' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if ($args['trainer_id']) {
            $where[] = $wpdb->prepare("trainer_id = %d", $args['trainer_id']);
        }
        
        if ($args['type']) {
            $where[] = $wpdb->prepare("type = %s", $args['type']);
        }
        
        if ($args['visibility']) {
            $where[] = $wpdb->prepare("visibility = %s", $args['visibility']);
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        $calendars = array();
        
        foreach ($results as $row) {
            $calendars[] = new self($row);
        }
        
        return $calendars;
    }
    
    public function delete() {
        global $wpdb;
        
        if ($this->id) {
            // Supprimer les événements associés
            $wpdb->delete($wpdb->prefix . 'hp_events', array('calendar_id' => $this->id));
            
            // Supprimer le calendrier
            return $wpdb->delete($wpdb->prefix . 'hp_calendars', array('id' => $this->id));
        }
        
        return false;
    }
    
    public function getEvents($args = array()) {
        $defaults = array('calendar_id' => $this->id);
        $args = wp_parse_args($args, $defaults);
        
        return HP_Event::all($args);
    }
    
    public function getTrainer() {
        return $this->trainer_id ? HP_Trainer::find($this->trainer_id) : null;
    }
    
    public function needsSync() {
        if (!$this->sync_source || !$this->sync_id) {
            return false;
        }
        
        if (!$this->last_sync) {
            return true;
        }
        
        $sync_interval = (int) get_option('hp_auto_sync_interval', 60);
        $last_sync_time = strtotime($this->last_sync);
        $current_time = current_time('timestamp');
        
        return ($current_time - $last_sync_time) > ($sync_interval * 60);
    }
    
    public function updateLastSync() {
        $this->last_sync = current_time('mysql');
        $this->save();
    }
    
    public function getIcalUrl() {
        return add_query_arg(array(
            'hp_action' => 'export_ical',
            'calendar_id' => $this->id,
            'token' => wp_hash($this->id . $this->name),
        ), home_url());
    }
}

// includes/models/class-hp-event.php

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
    
    public function __construct($data = array()) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    private function hydrate($data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }
    
    // Getters et Setters
    public function getId() { return $this->id; }
    public function setId($id) { $this->id = (int) $id; }
    
    public function getCalendarId() { return $this->calendar_id; }
    public function setCalendarId($calendar_id) { $this->calendar_id = (int) $calendar_id; }
    
    public function getTrainerId() { return $this->trainer_id; }
    public function setTrainerId($trainer_id) { $this->trainer_id = (int) $trainer_id; }
    
    public function getExternalId() { return $this->external_id; }
    public function setExternalId($external_id) { $this->external_id = sanitize_text_field($external_id); }
    
    public function getTitle() { return $this->title; }
    public function setTitle($title) { $this->title = sanitize_text_field($title); }
    
    public function getDescription() { return $this->description; }
    public function setDescription($description) { $this->description = sanitize_textarea_field($description); }
    
    public function getLocation() { return $this->location; }
    public function setLocation($location) { $this->location = sanitize_text_field($location); }
    
    public function getStartDate() { return $this->start_date; }
    public function setStartDate($date) { $this->start_date = $date; }
    
    public function getEndDate() { return $this->end_date; }
    public function setEndDate($date) { $this->end_date = $date; }
    
    public function getAllDay() { return $this->all_day; }
    public function setAllDay($all_day) { $this->all_day = (bool) $all_day; }
    
    public function getStatus() { return $this->status; }
    public function setStatus($status) { 
        $allowed = array('confirmed', 'tentative', 'cancelled');
        $this->status = in_array($status, $allowed) ? $status : 'confirmed';
    }
    
    public function getAttendees() { return $this->attendees; }
    public function setAttendees($attendees) { 
        $this->attendees = is_array($attendees) ? $attendees : json_decode($attendees, true);
    }
    
    public function getRecurrenceRule() { return $this->recurrence_rule; }
    public function setRecurrenceRule($rule) { $this->recurrence_rule = $rule; }
    
    public function getParentEventId() { return $this->parent_event_id; }
    public function setParentEventId($id) { $this->parent_event_id = (int) $id; }
    
    public function getColor() { return $this->color; }
    public function setColor($color) { $this->color = sanitize_hex_color($color); }
    
    public function getMetadata() { return $this->metadata; }
    public function setMetadata($metadata) { 
        $this->metadata = is_array($metadata) ? $metadata : json_decode($metadata, true);
    }
    
    // Méthodes CRUD
    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_events';
        
        // Validation
        if (!$this->validate()) {
            return false;
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
            $wpdb->update($table, $data, array('id' => $this->id));
        } else {
            $wpdb->insert($table, $data);
            $this->id = $wpdb->insert_id;
        }
        
        // Hook pour actions après sauvegarde
        do_action('hp_event_saved', $this);
        
        return $this->id;
    }
    
    private function validate() {
        if (empty($this->title)) {
            return false;
        }
        
        if (empty($this->start_date) || empty($this->end_date)) {
            return false;
        }
        
        if (strtotime($this->start_date) > strtotime($this->end_date)) {
            return false;
        }
        
        return true;
    }
    
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
    
    public static function find($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_events';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        
        return $row ? new self($row) : null;
    }
    
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
    
    public static function findByTrainer($trainer_id, $args = array()) {
        $defaults = array('trainer_id' => $trainer_id);
        $args = wp_parse_args($args, $defaults);
        
        return self::all($args);
    }
    
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
        
        $where = array('1=1');
        
        if ($args['calendar_id']) {
            $where[] = $wpdb->prepare("calendar_id = %d", $args['calendar_id']);
        }
        
        if ($args['trainer_id']) {
            $where[] = $wpdb->prepare("trainer_id = %d", $args['trainer_id']);
        }
        
        if ($args['status']) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if ($args['start_date']) {
            $where[] = $wpdb->prepare("end_date >= %s", $args['start_date']);
        }
        
        if ($args['end_date']) {
            $where[] = $wpdb->prepare("start_date <= %s", $args['end_date']);
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        $events = array();
        
        foreach ($results as $row) {
            $events[] = new self($row);
        }
        
        return $events;
    }
    
    public function delete() {
        global $wpdb;
        
        if ($this->id) {
            // Hook avant suppression
            do_action('hp_before_event_delete', $this);
            
            // Supprimer les événements récurrents enfants
            if (!$this->parent_event_id) {
                $wpdb->delete($wpdb->prefix . 'hp_events', array('parent_event_id' => $this->id));
            }
            
            return $wpdb->delete($wpdb->prefix . 'hp_events', array('id' => $this->id));
        }
        
        return false;
    }
    
    public function getCalendar() {
        return $this->calendar_id ? HP_Calendar::find($this->calendar_id) : null;
    }
    
    public function getTrainer() {
        return $this->trainer_id ? HP_Trainer::find($this->trainer_id) : null;
    }
    
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
        );
    }
    
    private function getDisplayColor() {
        if ($this->color) {
            return $this->color;
        }
        
        $trainer = $this->getTrainer();
        if ($trainer) {
            return $trainer->getCalendarColor();
        }
        
        return '#0073aa';
    }
    
    public function generateRecurrences($until_date = null) {
        if (!$this->recurrence_rule || $this->parent_event_id) {
            return array();
        }
        
        // Parser la règle de récurrence (format RRULE)
        // Implémentation simplifiée pour démonstration
        // En production, utiliser une librairie comme php-rrule
        
        $recurrences = array();
        // ... logique de génération des récurrences
        
        return $recurrences;
    }
}