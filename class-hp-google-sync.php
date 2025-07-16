<?php
// includes/sync/class-hp-google-sync.php

/**
 * Gestionnaire de synchronisation Google Calendar
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */

// Note: Les classes Google sont chargées via l'autoloader Composer principal
if ( class_exists( 'HP_Google_Sync' ) ) {
    return;
}

class HP_Google_Sync {
    
    private $client;
    private $service;
    
    public function __construct() {
        $this->initializeClient();
    }
    
    private function initializeClient() {
        $client_id = get_option('hp_google_client_id');
        $client_secret = get_option('hp_google_client_secret');
        
        if (!$client_id || !$client_secret) {
            return false;
        }
        
        // Vérifier que les classes Google sont disponibles
        if (!class_exists('Google_Client')) {
            hp_log('Google API Client non trouvé. Assurez-vous d\'avoir exécuté composer install.', 'error');
            return false;
        }
        
        try {
            $this->client = new Google_Client();
            $this->client->setClientId($client_id);
            $this->client->setClientSecret($client_secret);
            $this->client->setRedirectUri(admin_url('admin.php?page=hyperplanning-google-auth'));
            $this->client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
            $this->client->addScope(Google_Service_Calendar::CALENDAR_EVENTS);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');
            
            return true;
        } catch (Exception $e) {
            hp_log('Erreur initialisation Google Client: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    public function getAuthUrl() {
        if (!$this->client) {
            return false;
        }
        
        return $this->client->createAuthUrl();
    }
    
    public function authenticate($code) {
        if (!$this->client) {
            return new WP_Error('no_client', __('Client Google non initialisé', 'hyperplanning'));
        }
        
        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                return new WP_Error('auth_error', $token['error_description']);
            }
            
            // Stocker le token de manière sécurisée
            update_option('hp_google_token', hp_encrypt(json_encode($token)), false);
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('auth_exception', $e->getMessage());
        }
    }
    
    public function sync(HP_Calendar $calendar) {
        // Utilisation de ensureAuthentication() au lieu de authenticate()
        if (!$this->ensureAuthentication()) {
            return new WP_Error('auth_failed', __('Authentification Google échouée', 'hyperplanning'));
        }
        
        $google_calendar_id = $calendar->getSyncId();
        if (!$google_calendar_id) {
            return new WP_Error('no_calendar_id', __('ID de calendrier Google manquant', 'hyperplanning'));
        }
        
        try {
            $this->service = new Google_Service_Calendar($this->client);
            
            // Récupérer les événements depuis la dernière synchronisation
            $optParams = array(
                'timeMin' => $calendar->getLastSync() ?: date('c', strtotime('-1 month')),
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'maxResults' => 250,
            );
            
            $events = $this->service->events->listEvents($google_calendar_id, $optParams);
            $synced_count = 0;
            
            foreach ($events->getItems() as $google_event) {
                if ($this->syncEvent($google_event, $calendar)) {
                    $synced_count++;
                }
            }
            
            return $synced_count;
            
        } catch (Exception $e) {
            hp_log('Erreur sync Google: ' . $e->getMessage(), 'error');
            return new WP_Error('sync_exception', $e->getMessage());
        }
    }
    
    // Renommé de authenticate() à ensureAuthentication()
    private function ensureAuthentication() {
        $encrypted_token = get_option('hp_google_token');
        
        if (!$encrypted_token || !$this->client) {
            return false;
        }
        
        try {
            $token = json_decode(hp_decrypt($encrypted_token), true);
            $this->client->setAccessToken($token);
            
            // Rafraîchir le token si nécessaire
            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    update_option('hp_google_token', hp_encrypt(json_encode($this->client->getAccessToken())), false);
                } else {
                    return false;
                }
            }
            
            return true;
        } catch (Exception $e) {
            hp_log('Erreur authentification Google: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    private function syncEvent(Google_Service_Calendar_Event $google_event, HP_Calendar $calendar) {
        // Vérifier si l'événement existe déjà
        $event = HP_Event::findByExternalId($google_event->getId(), $calendar->getId());
        
        if (!$event) {
            $event = new HP_Event();
            $event->setCalendarId($calendar->getId());
            $event->setExternalId($google_event->getId());
        }
        
        // Mapper les données Google vers notre modèle
        $event->setTitle($google_event->getSummary() ?: __('Sans titre', 'hyperplanning'));
        $event->setDescription($google_event->getDescription());
        $event->setLocation($google_event->getLocation());
        
        // Gérer les dates
        $start = $google_event->getStart();
        $end = $google_event->getEnd();
        
        if ($start->getDateTime()) {
            $event->setStartDate(date('Y-m-d H:i:s', strtotime($start->getDateTime())));
            $event->setEndDate(date('Y-m-d H:i:s', strtotime($end->getDateTime())));
            $event->setAllDay(false);
        } else {
            $event->setStartDate($start->getDate() . ' 00:00:00');
            $event->setEndDate($end->getDate() . ' 00:00:00');
            $event->setAllDay(true);
        }
        
        // Status
        $status_map = array(
            'confirmed' => 'confirmed',
            'tentative' => 'tentative',
            'cancelled' => 'cancelled',
        );
        $event->setStatus($status_map[$google_event->getStatus()] ?? 'confirmed');
        
        // Attendees
        $attendees = array();
        if ($google_event->getAttendees()) {
            foreach ($google_event->getAttendees() as $attendee) {
                $attendees[] = array(
                    'email' => $attendee->getEmail(),
                    'displayName' => $attendee->getDisplayName(),
                    'responseStatus' => $attendee->getResponseStatus(),
                );
            }
        }
        $event->setAttendees($attendees);
        
        // Couleur
        if ($google_event->getColorId()) {
            $colors = $this->getGoogleColors();
            $event->setColor($colors[$google_event->getColorId()] ?? null);
        }
        
        // Récurrence
        if ($google_event->getRecurrence()) {
            $event->setRecurrenceRule(implode("\n", $google_event->getRecurrence()));
        }
        
        // Métadonnées supplémentaires
        $metadata = array(
            'google_event_id' => $google_event->getId(),
            'google_calendar_id' => $calendar->getSyncId(),
            'google_link' => $google_event->getHtmlLink(),
            'google_etag' => $google_event->getEtag(),
            'google_created' => $google_event->getCreated(),
            'google_updated' => $google_event->getUpdated(),
        );
        $event->setMetadata($metadata);
        
        // Associer au formateur si possible
        $trainer = $calendar->getTrainer();
        if ($trainer) {
            $event->setTrainerId($trainer->getId());
        }
        
        return $event->save();
    }
    
    private function getGoogleColors() {
        // Couleurs prédéfinies de Google Calendar
        return array(
            '1' => '#7986cb',  // Lavande
            '2' => '#33b679',  // Vert
            '3' => '#8e24aa',  // Violet
            '4' => '#e67c73',  // Rouge flamingo
            '5' => '#f6bf26',  // Jaune
            '6' => '#f4511e',  // Orange
            '7' => '#039be5',  // Bleu cyan
            '8' => '#616161',  // Gris
            '9' => '#3f51b5',  // Bleu
            '10' => '#0b8043', // Vert foncé
            '11' => '#d50000', // Rouge
        );
    }
    
    public function exportEvent(HP_Event $event, $google_calendar_id) {
        // Utilisation de ensureAuthentication() au lieu de authenticate()
        if (!$this->ensureAuthentication()) {
            return new WP_Error('auth_failed', __('Authentification Google échouée', 'hyperplanning'));
        }
        
        try {
            $this->service = new Google_Service_Calendar($this->client);
            
            $google_event = new Google_Service_Calendar_Event();
            $google_event->setSummary($event->getTitle());
            $google_event->setDescription($event->getDescription());
            $google_event->setLocation($event->getLocation());
            
            // Dates
            $start = new Google_Service_Calendar_EventDateTime();
            $end = new Google_Service_Calendar_EventDateTime();
            
            if ($event->getAllDay()) {
                $start->setDate(date('Y-m-d', strtotime($event->getStartDate())));
                $end->setDate(date('Y-m-d', strtotime($event->getEndDate())));
            } else {
                $start->setDateTime(date('c', strtotime($event->getStartDate())));
                $end->setDateTime(date('c', strtotime($event->getEndDate())));
                $start->setTimeZone(get_option('hp_time_zone', 'UTC'));
                $end->setTimeZone(get_option('hp_time_zone', 'UTC'));
            }
            
            $google_event->setStart($start);
            $google_event->setEnd($end);
            
            // Attendees
            if ($event->getAttendees()) {
                $attendees = array();
                foreach ($event->getAttendees() as $attendee) {
                    $google_attendee = new Google_Service_Calendar_EventAttendee();
                    $google_attendee->setEmail($attendee['email']);
                    $attendees[] = $google_attendee;
                }
                $google_event->setAttendees($attendees);
            }
            
            // Créer ou mettre à jour l'événement
            if ($event->getExternalId()) {
                $result = $this->service->events->update($google_calendar_id, $event->getExternalId(), $google_event);
            } else {
                $result = $this->service->events->insert($google_calendar_id, $google_event);
                $event->setExternalId($result->getId());
                $event->save();
            }
            
            return $result;
            
        } catch (Exception $e) {
            hp_log('Erreur export Google: ' . $e->getMessage(), 'error');
            return new WP_Error('export_exception', $e->getMessage());
        }
    }
    
    /**
     * Vérifier la connexion Google
     */
    public function isConnected() {
        return $this->ensureAuthentication(); // Mise à jour ici aussi
    }
    
    /**
     * Déconnecter Google
     */
    public function disconnect() {
        delete_option('hp_google_token');
        
        if ($this->client) {
            $this->client->revokeToken();
        }
    }
    
    /**
     * Obtenir la liste des calendriers Google
     */
    public function getCalendarList() {
        if (!$this->ensureAuthentication()) { // Mise à jour ici aussi
            return array();
        }
        
        try {
            $this->service = new Google_Service_Calendar($this->client);
            $calendarList = $this->service->calendarList->listCalendarList();
            
            $calendars = array();
            foreach ($calendarList->getItems() as $calendar) {
                $calendars[] = array(
                    'id' => $calendar->getId(),
                    'summary' => $calendar->getSummary(),
                    'primary' => $calendar->getPrimary(),
                );
            }
            
            return $calendars;
            
        } catch (Exception $e) {
            hp_log('Erreur liste calendriers Google: ' . $e->getMessage(), 'error');
            return array();
        }
    }
}