<?php
// includes/sync/class-hp-sync-manager.php

if ( class_exists( 'HP_Sync_Manager' ) ) {
    return;
}

class HP_Sync_Manager {
    
    private $sync_handlers = array();
    
    public function __construct() {
        $this->registerSyncHandlers();
    }
    
    private function registerSyncHandlers() {
        $this->sync_handlers['google'] = new HP_Google_Sync();
        $this->sync_handlers['ical'] = new HP_iCal_Sync();
        
        // Hook pour ajouter des handlers personnalisés
        $this->sync_handlers = apply_filters('hp_sync_handlers', $this->sync_handlers);
    }
    
    public function runSync() {
        $calendars = HP_Calendar::all();
        
        foreach ($calendars as $calendar) {
            if ($calendar->needsSync()) {
                $this->syncCalendar($calendar);
            }
        }
    }
    
    public function syncCalendar(HP_Calendar $calendar) {
        $sync_source = $calendar->getSyncSource();
        
        if (!$sync_source || !isset($this->sync_handlers[$sync_source])) {
            return new WP_Error('invalid_sync_source', __('Source de synchronisation invalide', 'hyperplanning'));
        }
        
        $handler = $this->sync_handlers[$sync_source];
        
        // Démarrer le log de synchronisation
        $log_id = $this->startSyncLog($calendar->getId(), $sync_source);
        
        try {
            $result = $handler->sync($calendar);
            
            if (is_wp_error($result)) {
                $this->completeSyncLog($log_id, 'error', $result->get_error_message());
                return $result;
            }
            
            $calendar->updateLastSync();
            $this->completeSyncLog($log_id, 'success', sprintf(__('%d événements synchronisés', 'hyperplanning'), $result));
            
            return $result;
            
        } catch (Exception $e) {
            $this->completeSyncLog($log_id, 'error', $e->getMessage());
            return new WP_Error('sync_error', $e->getMessage());
        }
    }
    
    private function startSyncLog($calendar_id, $sync_type) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'hp_sync_log',
            array(
                'calendar_id' => $calendar_id,
                'sync_type' => $sync_type,
                'status' => 'in_progress',
                'started_at' => current_time('mysql'),
            )
        );
        
        return $wpdb->insert_id;
    }
    
    private function completeSyncLog($log_id, $status, $message = '', $events_synced = 0) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'hp_sync_log',
            array(
                'status' => $status,
                'message' => $message,
                'events_synced' => $events_synced,
                'completed_at' => current_time('mysql'),
            ),
            array('id' => $log_id)
        );
    }
    
    public function getSyncLogs($calendar_id = null, $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'hp_sync_log';
        
        $sql = "SELECT * FROM $table";
        
        if ($calendar_id) {
            $sql .= $wpdb->prepare(" WHERE calendar_id = %d", $calendar_id);
        }
        
        $sql .= " ORDER BY started_at DESC";
        
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }
        
        return $wpdb->get_results($sql);
    }
}

// includes/sync/class-hp-google-sync.php

require_once HYPERPLANNING_PLUGIN_DIR . 'vendor/google/apiclient/autoload.php';

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
        
        $this->client = new Google_Client();
        $this->client->setClientId($client_id);
        $this->client->setClientSecret($client_secret);
        $this->client->setRedirectUri(admin_url('admin.php?page=hyperplanning-google-auth'));
        $this->client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
        $this->client->addScope(Google_Service_Calendar::CALENDAR_EVENTS);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        
        return true;
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
            update_option('hp_google_token', $token, false);
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('auth_exception', $e->getMessage());
        }
    }
    
    public function sync(HP_Calendar $calendar) {
        // Changé pour utiliser ensureAuthentication()
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
            return new WP_Error('sync_exception', $e->getMessage());
        }
    }
    
    // Renommé de authenticate() à ensureAuthentication()
    private function ensureAuthentication() {
        $token = get_option('hp_google_token');
        
        if (!$token || !$this->client) {
            return false;
        }
        
        $this->client->setAccessToken($token);
        
        // Rafraîchir le token si nécessaire
        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                update_option('hp_google_token', $this->client->getAccessToken(), false);
            } else {
                return false;
            }
        }
        
        return true;
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
        // Changé pour utiliser ensureAuthentication()
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
            return new WP_Error('export_exception', $e->getMessage());
        }
    }
}

// includes/sync/class-hp-ical-sync.php

use Sabre\VObject;

class HP_iCal_Sync {
    
    public function sync(HP_Calendar $calendar) {
        $ical_url = $calendar->getSyncId();
        
        if (!$ical_url) {
            return new WP_Error('no_ical_url', __('URL iCal manquante', 'hyperplanning'));
        }
        
        // Récupérer le contenu iCal
        $response = wp_remote_get($ical_url, array(
            'timeout' => 30,
            'sslverify' => apply_filters('hp_ical_ssl_verify', true),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $ical_content = wp_remote_retrieve_body($response);
        
        if (empty($ical_content)) {
            return new WP_Error('empty_ical', __('Contenu iCal vide', 'hyperplanning'));
        }
        
        try {
            $vcalendar = VObject\Reader::read($ical_content);
            $synced_count = 0;
            
            foreach ($vcalendar->VEVENT as $vevent) {
                if ($this->syncEvent($vevent, $calendar)) {
                    $synced_count++;
                }
            }
            
            return $synced_count;
            
        } catch (Exception $e) {
            return new WP_Error('ical_parse_error', $e->getMessage());
        }
    }
    
    private function syncEvent($vevent, HP_Calendar $calendar) {
        // Identifiant unique de l'événement
        $uid = (string) $vevent->UID;
        
        if (!$uid) {
            return false;
        }
        
        // Vérifier si l'événement existe déjà
        $event = HP_Event::findByExternalId($uid, $calendar->getId());
        
        if (!$event) {
            $event = new HP_Event();
            $event->setCalendarId($calendar->getId());
            $event->setExternalId($uid);
        }
        
        // Titre
        $event->setTitle((string) $vevent->SUMMARY ?: __('Sans titre', 'hyperplanning'));
        
        // Description
        if (isset($vevent->DESCRIPTION)) {
            $event->setDescription((string) $vevent->DESCRIPTION);
        }
        
        // Lieu
        if (isset($vevent->LOCATION)) {
            $event->setLocation((string) $vevent->LOCATION);
        }
        
        // Dates
        $start = $vevent->DTSTART->getDateTime();
        $event->setStartDate($start->format('Y-m-d H:i:s'));
        
        if (isset($vevent->DTEND)) {
            $end = $vevent->DTEND->getDateTime();
            $event->setEndDate($end->format('Y-m-d H:i:s'));
        } else if (isset($vevent->DURATION)) {
            $duration = new DateInterval((string) $vevent->DURATION);
            $end = clone $start;
            $end->add($duration);
            $event->setEndDate($end->format('Y-m-d H:i:s'));
        } else {
            $event->setEndDate($event->getStartDate());
        }
        
        // Événement sur toute la journée
        $event->setAllDay(!$vevent->DTSTART->hasTime());
        
        // Status
        if (isset($vevent->STATUS)) {
            $status_map = array(
                'CONFIRMED' => 'confirmed',
                'TENTATIVE' => 'tentative',
                'CANCELLED' => 'cancelled',
            );
            $event->setStatus($status_map[(string) $vevent->STATUS] ?? 'confirmed');
        }
        
        // Attendees
        $attendees = array();
        if (isset($vevent->ATTENDEE)) {
            foreach ($vevent->ATTENDEE as $attendee) {
                $email = str_replace('mailto:', '', (string) $attendee);
                $attendees[] = array(
                    'email' => $email,
                    'displayName' => $attendee['CN'] ?? $email,
                    'responseStatus' => $attendee['PARTSTAT'] ?? 'NEEDS-ACTION',
                );
            }
        }
        $event->setAttendees($attendees);
        
        // Récurrence
        if (isset($vevent->RRULE)) {
            $event->setRecurrenceRule((string) $vevent->RRULE);
        }
        
        // Métadonnées
        $metadata = array(
            'ical_uid' => $uid,
            'ical_sequence' => (string) ($vevent->SEQUENCE ?? 0),
            'ical_created' => isset($vevent->CREATED) ? $vevent->CREATED->getDateTime()->format('c') : null,
            'ical_last_modified' => isset($vevent->{'LAST-MODIFIED'}) ? $vevent->{'LAST-MODIFIED'}->getDateTime()->format('c') : null,
        );
        $event->setMetadata($metadata);
        
        // Associer au formateur si possible
        $trainer = $calendar->getTrainer();
        if ($trainer) {
            $event->setTrainerId($trainer->getId());
        }
        
        return $event->save();
    }
    
    public function export(HP_Calendar $calendar) {
        $vcalendar = new VObject\Component\VCalendar();
        
        // Propriétés du calendrier
        $vcalendar->PRODID = '-//HyperPlanning//WordPress Plugin//EN';
        $vcalendar->VERSION = '2.0';
        $vcalendar->CALSCALE = 'GREGORIAN';
        $vcalendar->METHOD = 'PUBLISH';
        $vcalendar->{'X-WR-CALNAME'} = $calendar->getName();
        $vcalendar->{'X-WR-CALDESC'} = $calendar->getDescription();
        $vcalendar->{'X-WR-TIMEZONE'} = get_option('hp_time_zone', 'UTC');
        
        // Récupérer les événements
        $events = $calendar->getEvents(array(
            'status' => 'confirmed',
            'start_date' => date('Y-m-d', strtotime('-1 year')),
        ));
        
        foreach ($events as $event) {
            $vevent = $vcalendar->add('VEVENT');
            
            // UID
            $uid = $event->getExternalId() ?: 'hp-' . $event->getId() . '@' . parse_url(home_url(), PHP_URL_HOST);
            $vevent->UID = $uid;
            
            // Dates
            $dtstart = new DateTime($event->getStartDate());
            $dtend = new DateTime($event->getEndDate());
            
            if ($event->getAllDay()) {
                $vevent->DTSTART = $dtstart;
                $vevent->DTSTART['VALUE'] = 'DATE';
                $vevent->DTEND = $dtend;
                $vevent->DTEND['VALUE'] = 'DATE';
            } else {
                $vevent->DTSTART = $dtstart;
                $vevent->DTEND = $dtend;
            }
            
            // Propriétés
            $vevent->SUMMARY = $event->getTitle();
            
            if ($event->getDescription()) {
                $vevent->DESCRIPTION = $event->getDescription();
            }
            
            if ($event->getLocation()) {
                $vevent->LOCATION = $event->getLocation();
            }
            
            $vevent->STATUS = strtoupper($event->getStatus());
            
            // Attendees
            foreach ($event->getAttendees() as $attendee) {
                $att = $vevent->add('ATTENDEE', 'mailto:' . $attendee['email']);
                if (isset($attendee['displayName'])) {
                    $att['CN'] = $attendee['displayName'];
                }
                if (isset($attendee['responseStatus'])) {
                    $att['PARTSTAT'] = $attendee['responseStatus'];
                }
            }
            
            // Récurrence
            if ($event->getRecurrenceRule()) {
                $vevent->RRULE = $event->getRecurrenceRule();
            }
            
            // Timestamps
            $vevent->CREATED = new DateTime();
            $vevent->{'LAST-MODIFIED'} = new DateTime($event->updated_at);
            $vevent->SEQUENCE = 0;
        }
        
        return $vcalendar->serialize();
    }
    
    public function exportToFile(HP_Calendar $calendar, $filename = null) {
        if (!$filename) {
            $filename = sanitize_file_name($calendar->getName()) . '.ics';
        }
        
        $ical_content = $this->export($calendar);
        
        // Headers pour le téléchargement
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($ical_content));
        
        echo $ical_content;
        exit;
    }
}