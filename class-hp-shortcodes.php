<?php
/**
 * Gestion des shortcodes
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

class HP_Shortcodes {
    
    /**
     * Enregistrer tous les shortcodes
     */
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
        if (get_locale() === 'fr_FR') {
            wp_enqueue_script('fullcalendar-locale-fr');
        }
        wp_enqueue_script('hyperplanning-public');
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
        if (get_locale() === 'fr_FR') {
            wp_enqueue_script('fullcalendar-locale-fr');
        }
        wp_enqueue_script('hyperplanning-public');
        wp_enqueue_style('hyperplanning-public');
        
        ob_start();
        ?>
        <div class="hp-trainer-calendar-wrapper">
            <?php if ($atts['show_info'] === 'yes'): ?>
                <div class="hp-trainer-card">
                    <div class="hp-trainer-avatar">
                        <?php echo mb_substr($trainer->getName(), 0, 1); ?>
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
                                    <span class="hp-specialty-tag"><?php echo esc_html($specialty); ?></span>
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
            return '<p class="hp-no-trainers">' . __('Aucun formateur disponible.', 'hyperplanning') . '</p>';
        }
        
        wp_enqueue_style('hyperplanning-public');
        
        ob_start();
        ?>
        <div class="hp-trainers-grid" style="grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr);">
            <?php foreach ($trainers as $trainer): ?>
                <div class="hp-trainer-card">
                    <div class="hp-trainer-avatar">
                        <?php echo mb_substr($trainer->getName(), 0, 1); ?>
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
                                    <span class="hp-specialty-tag"><?php echo esc_html($specialty); ?></span>
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
            return '<p class="hp-no-events">' . __('Aucun événement à venir.', 'hyperplanning') . '</p>';
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
                            <p class="hp-event-description"><?php echo esc_html($event->getDescription()); ?></p>
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
                    $is_weekend = in_array($current_date->format('w'), array(0, 6));
                    ?>
                    <div class="hp-availability-day <?php echo $is_busy ? 'busy' : 'available'; ?> <?php echo $is_past ? 'past' : ''; ?> <?php echo $is_weekend ? 'weekend' : ''; ?>">
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
                <span class="past"><?php _e('Passé', 'hyperplanning'); ?></span>
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
            return '<p class="hp-login-required">' . __('Vous devez être connecté pour soumettre un événement.', 'hyperplanning') . '</p>';
        }
        
        wp_enqueue_style('hyperplanning-public');
        wp_enqueue_script('hyperplanning-public');
        
        ob_start();
        ?>
        <div class="hp-event-submission-form">
            <form method="post" action="" class="hp-form">
                <?php wp_nonce_field('hp_submit_event', 'hp_event_nonce'); ?>
                <input type="hidden" name="hp_event_submission" value="1">
                
                <?php if ($atts['trainer']): ?>
                    <input type="hidden" name="trainer_id" value="<?php echo esc_attr($atts['trainer']); ?>">
                <?php else: ?>
                    <div class="hp-form-group">
                        <label for="hp_trainer_id"><?php _e('Formateur', 'hyperplanning'); ?> <span class="required">*</span></label>
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
                
                <?php if ($atts['redirect']): ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                <?php endif; ?>
                
                <div class="hp-form-group">
                    <label for="hp_event_title"><?php _e('Titre de l\'événement', 'hyperplanning'); ?> <span class="required">*</span></label>
                    <input type="text" name="title" id="hp_event_title" required>
                </div>
                
                <div class="hp-form-group">
                    <label for="hp_event_description"><?php _e('Description', 'hyperplanning'); ?></label>
                    <textarea name="description" id="hp_event_description" rows="4"></textarea>
                </div>
                
                <div class="hp-form-row">
                    <div class="hp-form-group">
                        <label for="hp_event_start_date"><?php _e('Date de début', 'hyperplanning'); ?> <span class="required">*</span></label>
                        <input type="datetime-local" name="start_date" id="hp_event_start_date" required>
                    </div>
                    
                    <div class="hp-form-group">
                        <label for="hp_event_end_date"><?php _e('Date de fin', 'hyperplanning'); ?> <span class="required">*</span></label>
                        <input type="datetime-local" name="end_date" id="hp_event_end_date" required>
                    </div>
                </div>
                
                <div class="hp-form-group">
                    <label for="hp_event_location"><?php _e('Lieu', 'hyperplanning'); ?></label>
                    <input type="text" name="location" id="hp_event_location">
                </div>
                
                <div class="hp-form-group">
                    <label class="hp-checkbox-label">
                        <input type="checkbox" name="all_day" value="1">
                        <?php _e('Événement sur toute la journée', 'hyperplanning'); ?>
                    </label>
                </div>
                
                <?php if (!is_user_logged_in()): ?>
                    <div class="hp-form-row">
                        <div class="hp-form-group">
                            <label for="hp_submitter_name"><?php _e('Votre nom', 'hyperplanning'); ?> <span class="required">*</span></label>
                            <input type="text" name="name" id="hp_submitter_name" required>
                        </div>
                        
                        <div class="hp-form-group">
                            <label for="hp_submitter_email"><?php _e('Votre email', 'hyperplanning'); ?> <span class="required">*</span></label>
                            <input type="email" name="email" id="hp_submitter_email" required>
                        </div>
                    </div>
                <?php endif; ?>
                
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
    
    /**
     * Afficher la légende du calendrier
     */
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
