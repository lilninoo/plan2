<?php
/**
 * Gestionnaire de synchronisation iCal
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

use Sabre\VObject;

class HP_iCal_Sync {
    
    /**
     * Synchroniser un calendrier iCal
     */
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
            hp_log('Erreur parsing iCal: ' . $e->getMessage(), 'error');
            return new WP_Error('ical_parse_error', $e->getMessage());
        }
    }
    
    /**
     * Synchroniser un événement
     */
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
        
        $result = $event->save();
        return !is_wp_error($result);
    }
    
    /**
     * Exporter un calendrier au format iCal
     */
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
            $attendees = $event->getAttendees();
            if (is_array($attendees)) {
                foreach ($attendees as $attendee) {
                    $att = $vevent->add('ATTENDEE', 'mailto:' . $attendee['email']);
                    if (isset($attendee['displayName'])) {
                        $att['CN'] = $attendee['displayName'];
                    }
                    if (isset($attendee['responseStatus'])) {
                        $att['PARTSTAT'] = $attendee['responseStatus'];
                    }
                }
            }
            
            // Récurrence
            if ($event->getRecurrenceRule()) {
                $vevent->RRULE = $event->getRecurrenceRule();
            }
            
            // Timestamps
            $vevent->CREATED = new DateTime();
            $vevent->{'LAST-MODIFIED'} = new DateTime($event->getUpdatedAt());
            $vevent->SEQUENCE = 0;
        }
        
        return $vcalendar->serialize();
    }
    
    /**
     * Exporter vers un fichier
     */
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