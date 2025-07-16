<?php
/**
 * Modèle Calendrier
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */
if ( class_exists( 'HP_Calendar' ) ) {
    return;
}

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

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
    public function getTrainerId() { return $this->trainer_id; }
    public function getName() { return $this->name; }
    public function getDescription() { return $this->description; }
    public function getType() { return $this->type; }
    public function getVisibility() { return $this->visibility; }
    public function getSyncSource() { return $this->sync_source; }
    public function getSyncId() { return $this->sync_id; }
    public function getLastSync() { return $this->last_sync; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
    
    // Setters
    public function setId($id) { 
        $this->id = (int) $id; 
    }
    
    public function setTrainerId($trainer_id) { 
        $this->trainer_id = (int) $trainer_id; 
    }
    
    public function setName($name) { 
        $this->name = sanitize_text_field($name); 
    }
    
    public function setDescription($description) { 
        $this->description = sanitize_textarea_field($description); 
    }
    
    public function setType($type) { 
        if (HP_Constants::isValidEventType($type)) {
            $this->type = $type;
        } else {
            $this->type = HP_Constants::CALENDAR_TYPE_TRAINING;
        }
    }
    
    public function setVisibility($visibility) { 
        if (HP_Constants::isValidVisibility($visibility)) {
            $this->visibility = $visibility;
        } else {
            $this->visibility = HP_Constants::VISIBILITY_PUBLIC;
        }
    }
    
    public function setSyncSource($source) { 
        $this->sync_source = sanitize_text_field($source); 
    }
    
    public function setSyncId($id) { 
        $this->sync_id = sanitize_text_field($id); 
    }
    
    public function setLastSync($date) { 
        $this->last_sync = $date; 
    }
    
    public function setCreatedAt($date) { 
        $this->created_at = $date; 
    }
    
    public function setUpdatedAt($date) { 
        $this->updated_at = $date; 
    }
    
    /**
     * Sauvegarder le calendrier
     */
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
            // Mise à jour
            $result = $wpdb->update($table, $data, array('id' => $this->id));
            
            if ($result === false) {
                hp_log('Erreur mise à jour calendrier: ' . $wpdb->last_error, 'error');
                return false;
            }
        } else {
            // Insertion
            $result = $wpdb->insert($table, $data);
            
            if ($result === false) {
                hp_log('Erreur insertion calendrier: ' . $wpdb->last_error, 'error');
                return false;
            }
            
            $this->id = $wpdb->insert_id;
        }
        
        // Nettoyer le cache
        wp_cache_delete('calendar_' . $this->id, HP_Constants::CACHE_GROUP);
        hp_clear_cache('calendars');
        
        // Hook après sauvegarde
        do_action('hp_calendar_saved', $this);
        
        return $this->id;
    }
    
    /**
     * Supprimer le calendrier
     */
    public function delete() {
        global $wpdb;
        
        if (!$this->id) {
            return false;
        }
        
        // Hook avant suppression
        do_action('hp_before_calendar_delete', $this);
        
        // Supprimer les événements associés
        $wpdb->delete($wpdb->prefix . 'hp_events', array('calendar_id' => $this->id));
        
        // Supprimer les logs de synchronisation
        $wpdb->delete($wpdb->prefix . 'hp_sync_log', array('calendar_id' => $this->id));
        
        // Supprimer le calendrier
        $result = $wpdb->delete($wpdb->prefix . 'hp_calendars', array('id' => $this->id));
        
        if ($result) {
            // Nettoyer le cache
            wp_cache_delete('calendar_' . $this->id, HP_Constants::CACHE_GROUP);
            hp_clear_cache('calendars');
            
            // Hook après suppression
            do_action('hp_calendar_deleted', $this->id);
        }
        
        return $result !== false;
    }
    
    /**
     * Trouver un calendrier par ID
     */
    public static function find($id) {
        global $wpdb;
        
        // Vérifier le cache
        $cached = wp_cache_get('calendar_' . $id, HP_Constants::CACHE_GROUP);
        if ($cached !== false) {
            return new self($cached);
        }
        
        $table = $wpdb->prefix . 'hp_calendars';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        // Mettre en cache
        wp_cache_set('calendar_' . $id, $row, HP_Constants::CACHE_GROUP, HP_Constants::CACHE_EXPIRATION);
        
        return new self($row);
    }
    
    /**
     * Trouver les calendriers par formateur
     */
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
    
    /**
     * Trouver par source de synchronisation
     */
    public static function findBySyncId($sync_source, $sync_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_calendars';
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE sync_source = %s AND sync_id = %s",
            $sync_source,
            $sync_id
        ), ARRAY_A);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Obtenir tous les calendriers
     */
    public static function all($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_calendars';
        
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'trainer_id' => null,
            'type' => null,
            'visibility' => null,
            'sync_source' => null,
            'limit' => -1,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Construire la requête
        $where = array('1=1');
        $values = array();
        
        if ($args['trainer_id']) {
            $where[] = 'trainer_id = %d';
            $values[] = $args['trainer_id'];
        }
        
        if ($args['type']) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }
        
        if ($args['visibility']) {
            $where[] = 'visibility = %s';
            $values[] = $args['visibility'];
        }
        
        if ($args['sync_source']) {
            $where[] = 'sync_source = %s';
            $values[] = $args['sync_source'];
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
        $calendars = array();
        
        foreach ($results as $row) {
            $calendars[] = new self($row);
        }
        
        return $calendars;
    }
    
    /**
     * Obtenir les événements du calendrier
     */
    public function getEvents($args = array()) {
        $defaults = array('calendar_id' => $this->id);
        $args = wp_parse_args($args, $defaults);
        
        return HP_Event::all($args);
    }
    
    /**
     * Obtenir le formateur associé
     */
    public function getTrainer() {
        return $this->trainer_id ? HP_Trainer::find($this->trainer_id) : null;
    }
    
    /**
     * Vérifier si le calendrier nécessite une synchronisation
     */
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
    
    /**
     * Mettre à jour la dernière synchronisation
     */
    public function updateLastSync() {
        $this->last_sync = current_time('mysql');
        $this->save();
    }
    
    /**
     * Obtenir l'URL d'export iCal
     */
    public function getIcalUrl() {
        return add_query_arg(array(
            'hp_action' => 'export_ical',
            'calendar_id' => $this->id,
            'token' => wp_hash($this->id . $this->name),
        ), home_url());
    }
    
    /**
     * Obtenir l'URL publique du calendrier
     */
    public function getPublicUrl() {
        $page_id = get_option('hp_calendar_page_id');
        if (!$page_id) {
            return home_url();
        }
        
        return add_query_arg('calendar', $this->id, get_permalink($page_id));
    }
    
    /**
     * Vérifier si l'utilisateur peut voir ce calendrier
     */
    public function canView($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Calendriers publics
        if ($this->visibility === HP_Constants::VISIBILITY_PUBLIC) {
            return true;
        }
        
        // Utilisateur non connecté
        if (!$user_id) {
            return false;
        }
        
        // Administrateurs
        if (user_can($user_id, 'hp_view_all_calendars')) {
            return true;
        }
        
        // Propriétaire du calendrier
        $trainer = $this->getTrainer();
        if ($trainer && $trainer->getUserId() == $user_id) {
            return true;
        }
        
        // Calendriers internes pour les utilisateurs connectés
        if ($this->visibility === HP_Constants::VISIBILITY_INTERNAL) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Vérifier si l'utilisateur peut éditer ce calendrier
     */
    public function canEdit($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Administrateurs
        if (user_can($user_id, 'hp_manage_calendars')) {
            return true;
        }
        
        // Propriétaire du calendrier
        $trainer = $this->getTrainer();
        if ($trainer && $trainer->getUserId() == $user_id) {
            return user_can($user_id, 'hp_manage_own_calendar');
        }
        
        return false;
    }
    
    /**
     * Obtenir les statistiques du calendrier
     */
    public function getStats() {
        global $wpdb;
        
        $stats = array(
            'total_events' => 0,
            'upcoming_events' => 0,
            'past_events' => 0,
            'total_hours' => 0,
        );
        
        // Total des événements
        $stats['total_events'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hp_events WHERE calendar_id = %d",
            $this->id
        ));
        
        // Événements à venir
        $stats['upcoming_events'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hp_events 
            WHERE calendar_id = %d AND start_date > %s",
            $this->id,
            current_time('mysql')
        ));
        
        // Événements passés
        $stats['past_events'] = $stats['total_events'] - $stats['upcoming_events'];
        
        // Total des heures
        $total_minutes = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(TIMESTAMPDIFF(MINUTE, start_date, end_date)) 
            FROM {$wpdb->prefix}hp_events 
            WHERE calendar_id = %d",
            $this->id
        ));
        
        $stats['total_hours'] = round($total_minutes / 60, 1);
        
        return $stats;
    }
    
    /**
     * Convertir en tableau
     */
    public function toArray() {
        return array(
            'id' => $this->id,
            'trainer_id' => $this->trainer_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'visibility' => $this->visibility,
            'sync_source' => $this->sync_source,
            'sync_id' => $this->sync_id,
            'last_sync' => $this->last_sync,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        );
    }
}