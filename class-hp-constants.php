<?php
/**
 * Constantes globales du plugin HyperPlanning
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */
 
 if ( class_exists( 'HP_Constants' ) ) {
    return;
}


// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe des constantes HyperPlanning
 */
class HP_Constants {
    
    /**
     * Types d'événements
     */
    const EVENT_TYPE_TRAINING = 'training';
    const EVENT_TYPE_AVAILABILITY = 'availability';
    const EVENT_TYPE_PERSONAL = 'personal';
    const EVENT_TYPE_RESOURCE = 'resource';
    const EVENT_TYPE_MEETING = 'meeting';
    const EVENT_TYPE_OTHER = 'other';
    
    /**
     * Statuts d'événements
     */
    const EVENT_STATUS_CONFIRMED = 'confirmed';
    const EVENT_STATUS_TENTATIVE = 'tentative';
    const EVENT_STATUS_CANCELLED = 'cancelled';
    const EVENT_STATUS_PENDING = 'pending';
    
    /**
     * Types de calendrier
     */
    const CALENDAR_TYPE_TRAINING = 'training';
    const CALENDAR_TYPE_AVAILABILITY = 'availability';
    const CALENDAR_TYPE_PERSONAL = 'personal';
    const CALENDAR_TYPE_RESOURCE = 'resource';
    
    /**
     * Visibilité des calendriers
     */
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_INTERNAL = 'internal';
    
    /**
     * Sources de synchronisation
     */
    const SYNC_SOURCE_GOOGLE = 'google';
    const SYNC_SOURCE_ICAL = 'ical';
    const SYNC_SOURCE_OUTLOOK = 'outlook';
    const SYNC_SOURCE_CALDAV = 'caldav';
    const SYNC_SOURCE_MANUAL = 'manual';
    
    /**
     * Types de conflits
     */
    const CONFLICT_TYPE_OVERLAP = 'overlap';
    const CONFLICT_TYPE_TRAVEL_TIME = 'travel_time';
    const CONFLICT_TYPE_RESOURCE = 'resource';
    const CONFLICT_TYPE_AVAILABILITY = 'availability';
    
    /**
     * Statuts de synchronisation
     */
    const SYNC_STATUS_SUCCESS = 'success';
    const SYNC_STATUS_ERROR = 'error';
    const SYNC_STATUS_IN_PROGRESS = 'in_progress';
    const SYNC_STATUS_PENDING = 'pending';
    
    /**
     * Couleurs par défaut
     */
    const DEFAULT_COLORS = array(
        '#0073aa' => 'Bleu WordPress',
        '#33b679' => 'Vert',
        '#f4511e' => 'Orange',
        '#e67c73' => 'Rouge',
        '#f6bf26' => 'Jaune',
        '#8e24aa' => 'Violet',
        '#039be5' => 'Bleu clair',
        '#616161' => 'Gris',
    );
    
    /**
     * Durées par défaut (en minutes)
     */
    const DEFAULT_EVENT_DURATION = 60;
    const MIN_EVENT_DURATION = 15;
    const MAX_EVENT_DURATION = 480; // 8 heures
    
    /**
     * Limites
     */
    const MAX_EVENTS_PER_PAGE = 100;
    const MAX_SYNC_EVENTS = 500;
    const MAX_ATTENDEES_PER_EVENT = 100;
    const MAX_DESCRIPTION_LENGTH = 5000;
    
    /**
     * Intervalles de temps
     */
    const TIME_SLOT_DURATION = 30; // minutes
    const BUSINESS_START_HOUR = 8;
    const BUSINESS_END_HOUR = 18;
    
    /**
     * Cache
     */
    const CACHE_GROUP = 'hyperplanning';
    const CACHE_EVENTS_KEY = 'hp_events_';
    const CACHE_TRAINERS_KEY = 'hp_trainers_';
    const CACHE_CALENDARS_KEY = 'hp_calendars_';
    const CACHE_EXPIRATION = 3600; // 1 heure
    
    /**
     * API
     */
    const API_NAMESPACE = 'hyperplanning/v1';
    const API_VERSION = '1.0';
    
    /**
     * Permissions
     */
    const MIN_CAPABILITY_VIEW = 'hp_view_calendar';
    const MIN_CAPABILITY_CREATE = 'hp_create_events';
    const MIN_CAPABILITY_EDIT = 'hp_edit_own_events';
    const MIN_CAPABILITY_DELETE = 'hp_delete_own_events';
    const MIN_CAPABILITY_ADMIN = 'hp_manage_calendars';
    
    /**
     * Formats de date
     */
    const DATE_FORMAT_DB = 'Y-m-d H:i:s';
    const DATE_FORMAT_ISO = 'c';
    const DATE_FORMAT_ICAL = 'Ymd\THis\Z';
    
    /**
     * Messages d'erreur
     */
    const ERROR_MESSAGES = array(
        'no_permission' => 'Vous n\'avez pas la permission d\'effectuer cette action.',
        'event_not_found' => 'Événement non trouvé.',
        'trainer_not_found' => 'Formateur non trouvé.',
        'calendar_not_found' => 'Calendrier non trouvé.',
        'sync_failed' => 'La synchronisation a échoué.',
        'invalid_date' => 'Date invalide.',
        'conflict_detected' => 'Un conflit d\'horaire a été détecté.',
    );
    
    /**
     * Obtenir tous les types d'événements
     */
    public static function getEventTypes() {
        return array(
            self::EVENT_TYPE_TRAINING => __('Formation', 'hyperplanning'),
            self::EVENT_TYPE_AVAILABILITY => __('Disponibilité', 'hyperplanning'),
            self::EVENT_TYPE_PERSONAL => __('Personnel', 'hyperplanning'),
            self::EVENT_TYPE_RESOURCE => __('Ressource', 'hyperplanning'),
            self::EVENT_TYPE_MEETING => __('Réunion', 'hyperplanning'),
            self::EVENT_TYPE_OTHER => __('Autre', 'hyperplanning'),
        );
    }
    
    /**
     * Obtenir tous les statuts d'événements
     */
    public static function getEventStatuses() {
        return array(
            self::EVENT_STATUS_CONFIRMED => __('Confirmé', 'hyperplanning'),
            self::EVENT_STATUS_TENTATIVE => __('Provisoire', 'hyperplanning'),
            self::EVENT_STATUS_CANCELLED => __('Annulé', 'hyperplanning'),
            self::EVENT_STATUS_PENDING => __('En attente', 'hyperplanning'),
        );
    }
    
    /**
     * Obtenir toutes les visibilités
     */
    public static function getVisibilityOptions() {
        return array(
            self::VISIBILITY_PUBLIC => __('Public', 'hyperplanning'),
            self::VISIBILITY_PRIVATE => __('Privé', 'hyperplanning'),
            self::VISIBILITY_INTERNAL => __('Interne', 'hyperplanning'),
        );
    }
    
    /**
     * Obtenir toutes les sources de synchronisation
     */
    public static function getSyncSources() {
        return array(
            self::SYNC_SOURCE_GOOGLE => __('Google Calendar', 'hyperplanning'),
            self::SYNC_SOURCE_ICAL => __('iCal', 'hyperplanning'),
            self::SYNC_SOURCE_OUTLOOK => __('Outlook', 'hyperplanning'),
            self::SYNC_SOURCE_CALDAV => __('CalDAV', 'hyperplanning'),
            self::SYNC_SOURCE_MANUAL => __('Manuel', 'hyperplanning'),
        );
    }
    
    /**
     * Vérifier si un type d'événement est valide
     */
    public static function isValidEventType($type) {
        return array_key_exists($type, self::getEventTypes());
    }
    
    /**
     * Vérifier si un statut d'événement est valide
     */
    public static function isValidEventStatus($status) {
        return array_key_exists($status, self::getEventStatuses());
    }
    
    /**
     * Vérifier si une visibilité est valide
     */
    public static function isValidVisibility($visibility) {
        return array_key_exists($visibility, self::getVisibilityOptions());
    }
    
    /**
     * Obtenir la couleur par défaut pour un type d'événement
     */
    public static function getDefaultColorForType($type) {
        $colors = array(
            self::EVENT_TYPE_TRAINING => '#0073aa',
            self::EVENT_TYPE_AVAILABILITY => '#33b679',
            self::EVENT_TYPE_PERSONAL => '#616161',
            self::EVENT_TYPE_RESOURCE => '#f6bf26',
            self::EVENT_TYPE_MEETING => '#039be5',
            self::EVENT_TYPE_OTHER => '#8e24aa',
        );
        
        return isset($colors[$type]) ? $colors[$type] : '#0073aa';
    }
}