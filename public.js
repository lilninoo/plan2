/**
 * HyperPlanning - JavaScript Public
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    let publicCalendars = [];

    $(document).ready(function() {
        // Initialiser tous les calendriers publics
        $('.hp-public-calendar').each(function() {
            initializePublicCalendar(this);
        });

        // Initialiser les formulaires
        initializeForms();

        // Initialiser les filtres
        initializeFilters();
    });

    /**
     * Initialiser un calendrier public
     */
    function initializePublicCalendar(element) {
        const $element = $(element);
        const calendarId = $element.attr('id') || 'hp-calendar-' + Date.now();
        
        if (!element.id) {
            element.id = calendarId;
        }

        // Options du calendrier depuis les attributs data
        const options = {
            view: $element.data('view') || 'dayGridMonth',
            trainerId: $element.data('trainer-id') || '',
            calendarId: $element.data('calendar-id') || '',
            types: $element.data('types') || ''
        };

        // Configuration FullCalendar
        const calendar = new FullCalendar.Calendar(element, {
            initialView: options.view,
            locale: hp_public.locale === 'fr_FR' ? 'fr' : hp_public.locale.substr(0, 2),
            firstDay: parseInt(hp_public.week_starts_on),
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            buttonText: {
                today: hp_public.i18n.today,
                month: hp_public.i18n.month,
                week: hp_public.i18n.week,
                day: hp_public.i18n.day,
                list: hp_public.i18n.list
            },
            events: function(fetchInfo, successCallback, failureCallback) {
                fetchPublicEvents(fetchInfo, successCallback, failureCallback, options);
            },
            eventClick: handleEventClick,
            loading: function(isLoading) {
                if (isLoading) {
                    $element.addClass('hp-loading');
                } else {
                    $element.removeClass('hp-loading');
                }
            },
            eventDisplay: 'block',
            dayMaxEvents: 3,
            moreLinkText: hp_public.i18n.more,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            nowIndicator: true,
            eventDidMount: function(info) {
                // Tooltip sur les événements
                if (info.event.extendedProps.description || info.event.extendedProps.location) {
                    $(info.el).attr('title', formatEventTooltip(info.event));
                }
            }
        });

        calendar.render();
        
        // Stocker la référence
        publicCalendars.push({
            id: calendarId,
            calendar: calendar,
            options: options
        });
    }

    /**
     * Récupérer les événements publics
     */
    function fetchPublicEvents(fetchInfo, successCallback, failureCallback, options) {
        const data = {
            action: 'hp_get_public_events',
            nonce: hp_public.nonce,
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            trainer_id: options.trainerId,
            calendar_id: options.calendarId,
            types: options.types
        };

        $.ajax({
            url: hp_public.ajax_url,
            method: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    successCallback(response.data);
                } else {
                    console.error('Erreur:', response.data);
                    failureCallback();
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX:', error);
                failureCallback();
            }
        });
    }

    /**
     * Gérer le clic sur un événement
     */
    function handleEventClick(info) {
        info.jsEvent.preventDefault();
        
        const event = info.event;
        showEventDetails(event);
    }

    /**
     * Afficher les détails d'un événement
     */
    function showEventDetails(event) {
        // Créer ou récupérer la modal
        let $modal = $('#hp-event-modal');
        if (!$modal.length) {
            $modal = createEventModal();
        }

        // Récupérer les détails complets via AJAX
        $.ajax({
            url: hp_public.ajax_url,
            method: 'POST',
            data: {
                action: 'hp_get_event_details',
                nonce: hp_public.nonce,
                event_id: event.id
            },
            success: function(response) {
                if (response.success) {
                    populateEventModal($modal, response.data);
                    $modal.fadeIn();
                }
            }
        });
    }

    /**
     * Créer la modal d'événement
     */
    function createEventModal() {
        const modalHtml = `
            <div id="hp-event-modal" class="hp-modal" style="display: none;">
                <div class="hp-modal-content">
                    <span class="hp-modal-close">&times;</span>
                    <h2 id="hp-event-title"></h2>
                    <div id="hp-event-details"></div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        const $modal = $('#hp-event-modal');
        
        // Événements de fermeture
        $modal.on('click', '.hp-modal-close, .hp-modal', function(e) {
            if (e.target === this) {
                $modal.fadeOut();
            }
        });
        
        return $modal;
    }

    /**
     * Remplir la modal avec les détails
     */
    function populateEventModal($modal, data) {
        $('#hp-event-title').text(data.title);
        
        let detailsHtml = '<div class="hp-event-details-content">';
        
        // Date et heure
        detailsHtml += '<div class="hp-detail-row">';
        detailsHtml += '<strong>' + hp_public.i18n.date + ':</strong> ';
        detailsHtml += data.start;
        if (data.end && !data.allDay) {
            detailsHtml += ' - ' + data.end;
        }
        detailsHtml += '</div>';
        
        // Lieu
        if (data.location) {
            detailsHtml += '<div class="hp-detail-row">';
            detailsHtml += '<strong>' + hp_public.i18n.location + ':</strong> ';
            detailsHtml += escapeHtml(data.location);
            detailsHtml += '</div>';
        }
        
        // Formateur
        if (data.trainer) {
            detailsHtml += '<div class="hp-detail-row">';
            detailsHtml += '<strong>' + hp_public.i18n.trainer + ':</strong> ';
            detailsHtml += escapeHtml(data.trainer);
            detailsHtml += '</div>';
        }
        
        // Description
        if (data.description) {
            detailsHtml += '<div class="hp-detail-row">';
            detailsHtml += '<strong>' + hp_public.i18n.description + ':</strong><br>';
            detailsHtml += escapeHtml(data.description);
            detailsHtml += '</div>';
        }
        
        detailsHtml += '</div>';
        
        $('#hp-event-details').html(detailsHtml);
    }

    /**
     * Formater le tooltip d'un événement
     */
    function formatEventTooltip(event) {
        let tooltip = event.title;
        
        if (event.extendedProps.location) {
            tooltip += '\n' + hp_public.i18n.location + ': ' + event.extendedProps.location;
        }
        
        if (event.extendedProps.description) {
            tooltip += '\n' + event.extendedProps.description.substr(0, 100) + '...';
        }
        
        return tooltip;
    }

    /**
     * Initialiser les filtres
     */
    function initializeFilters() {
        // Filtre formateur
        $('.hp-filter-trainer').on('change', function() {
            const trainerId = $(this).val();
            const $calendar = $(this).closest('.hp-calendar-wrapper').find('.hp-public-calendar');
            
            if ($calendar.length) {
                const calendarObj = getCalendarById($calendar.attr('id'));
                if (calendarObj) {
                    calendarObj.options.trainerId = trainerId;
                    calendarObj.calendar.refetchEvents();
                }
            }
        });
    }

    /**
     * Initialiser les formulaires
     */
    function initializeForms() {
        // Formulaire de soumission d'événement
        $('.hp-event-submission-form form').on('submit', function(e) {
            const $form = $(this);
            const $submitButton = $form.find('.hp-submit-button');
            const $message = $form.siblings('.hp-form-message');
            
            // Validation basique
            let isValid = true;
            $form.find('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('hp-error');
                    isValid = false;
                } else {
                    $(this).removeClass('hp-error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showFormMessage($message, hp_public.i18n.fill_required, 'error');
                return;
            }
            
            // Si soumission AJAX activée
            if ($form.hasClass('hp-ajax-form')) {
                e.preventDefault();
                
                $submitButton.prop('disabled', true);
                const formData = $form.serialize();
                
                $.ajax({
                    url: hp_public.ajax_url,
                    method: 'POST',
                    data: formData + '&action=hp_submit_event',
                    success: function(response) {
                        if (response.success) {
                            $form[0].reset();
                            showFormMessage($message, response.data.message, 'success');
                        } else {
                            showFormMessage($message, response.data, 'error');
                        }
                    },
                    error: function() {
                        showFormMessage($message, hp_public.i18n.error, 'error');
                    },
                    complete: function() {
                        $submitButton.prop('disabled', false);
                    }
                });
            }
        });

        // Toggle événement toute la journée
        $('input[name="all_day"]').on('change', function() {
            const $timeInputs = $(this).closest('form').find('input[type="datetime-local"]');
            if ($(this).is(':checked')) {
                $timeInputs.attr('type', 'date');
            } else {
                $timeInputs.attr('type', 'datetime-local');
            }
        });
    }

    /**
     * Afficher un message de formulaire
     */
    function showFormMessage($element, message, type) {
        $element
            .removeClass('success error info')
            .addClass(type)
            .html(escapeHtml(message))
            .fadeIn();
        
        setTimeout(function() {
            $element.fadeOut();
        }, 5000);
    }

    /**
     * Obtenir un calendrier par ID
     */
    function getCalendarById(id) {
        return publicCalendars.find(cal => cal.id === id);
    }

    /**
     * Échapper le HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
    }

    // API publique
    window.HyperPlanning = {
        getCalendars: function() {
            return publicCalendars;
        },
        refreshCalendar: function(id) {
            const cal = getCalendarById(id);
            if (cal) {
                cal.calendar.refetchEvents();
            }
        }
    };

})(jQuery);
