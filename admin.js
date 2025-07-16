/**
 * HyperPlanning - JavaScript Administration
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Variables globales
    let calendar = null;
    let currentView = 'dayGridMonth';
    let currentFilters = {
        trainer: '',
        type: ''
    };

    /**
     * Initialisation
     */
    $(document).ready(function() {
        // Initialiser le calendrier si présent
        if ($('#hp-calendar-container').length) {
            initializeCalendar();
        }

        // Initialiser les événements
        initializeEvents();

        // Initialiser la synchronisation
        initializeSync();

        // Initialiser les formulaires
        initializeForms();
    });

    /**
     * Initialiser le calendrier FullCalendar
     */
    function initializeCalendar() {
        const calendarEl = document.getElementById('hp-calendar-container');
        if (!calendarEl) return;

        // Configuration du calendrier
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: hp_admin.default_view || 'dayGridMonth',
            locale: hp_admin.locale === 'fr_FR' ? 'fr' : hp_admin.locale,
            firstDay: parseInt(hp_admin.week_starts_on),
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: hp_admin.i18n.today || 'Today',
                month: hp_admin.i18n.month || 'Month',
                week: hp_admin.i18n.week || 'Week',
                day: hp_admin.i18n.day || 'Day',
                list: hp_admin.i18n.list || 'List'
            },
            events: fetchEvents,
            eventClick: handleEventClick,
            dateClick: handleDateClick,
            eventDrop: handleEventDrop,
            eventResize: handleEventResize,
            loading: function(isLoading) {
                if (isLoading) {
                    showLoading();
                } else {
                    hideLoading();
                }
            },
            editable: true,
            droppable: true,
            eventDisplay: 'block',
            dayMaxEvents: 3,
            moreLinkClick: 'popover',
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            businessHours: {
                daysOfWeek: [1, 2, 3, 4, 5],
                startTime: '08:00',
                endTime: '18:00'
            },
            nowIndicator: true,
            eventDidMount: function(info) {
                // Ajouter des tooltips
                $(info.el).tooltip({
                    title: info.event.extendedProps.description || info.event.title,
                    placement: 'top',
                    container: 'body',
                    trigger: 'hover'
                });
            }
        });

        calendar.render();
    }

    /**
     * Récupérer les événements
     */
    function fetchEvents(fetchInfo, successCallback, failureCallback) {
        const data = {
            action: 'hp_get_events',
            nonce: hp_admin.nonce,
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            trainer_id: currentFilters.trainer,
            type: currentFilters.type
        };

        $.ajax({
            url: hp_admin.rest_url + 'events',
            method: 'GET',
            headers: {
                'X-WP-Nonce': hp_admin.nonce
            },
            data: {
                start: fetchInfo.startStr,
                end: fetchInfo.endStr,
                trainer_id: currentFilters.trainer || undefined,
                type: currentFilters.type || undefined
            },
            success: function(events) {
                successCallback(events.map(formatEventForCalendar));
            },
            error: function(xhr, status, error) {
                console.error('Erreur chargement événements:', error);
                failureCallback(error);
                showError(hp_admin.i18n.error);
            }
        });
    }

    /**
     * Formater un événement pour FullCalendar
     */
    function formatEventForCalendar(event) {
        return {
            id: event.id,
            title: event.title,
            start: event.start,
            end: event.end,
            allDay: event.allDay,
            color: event.color,
            extendedProps: {
                description: event.description,
                location: event.location,
                trainer_id: event.trainer_id,
                trainer_name: event.trainer_name,
                status: event.status,
                attendees: event.attendees
            }
        };
    }

    /**
     * Gérer le clic sur un événement
     */
    function handleEventClick(info) {
        info.jsEvent.preventDefault();
        showEventModal(info.event);
    }

    /**
     * Gérer le clic sur une date
     */
    function handleDateClick(info) {
        if (!confirm('Créer un nouvel événement ?')) {
            return;
        }

        // Ouvrir le formulaire de création avec la date pré-remplie
        const createUrl = hp_admin.admin_url + 'admin.php?page=hyperplanning-events&action=new&date=' + info.dateStr;
        window.location.href = createUrl;
    }

    /**
     * Gérer le déplacement d'un événement
     */
    function handleEventDrop(info) {
        updateEventDates(info.event, info.revert);
    }

    /**
     * Gérer le redimensionnement d'un événement
     */
    function handleEventResize(info) {
        updateEventDates(info.event, info.revert);
    }

    /**
     * Mettre à jour les dates d'un événement
     */
    function updateEventDates(event, revertFunc) {
        const data = {
            id: event.id,
            start: event.start.toISOString(),
            end: event.end ? event.end.toISOString() : event.start.toISOString(),
            allDay: event.allDay
        };

        $.ajax({
            url: hp_admin.rest_url + 'events/' + event.id,
            method: 'PUT',
            headers: {
                'X-WP-Nonce': hp_admin.nonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(data),
            success: function(response) {
                showSuccess('Événement mis à jour');
            },
            error: function(xhr, status, error) {
                console.error('Erreur mise à jour événement:', error);
                showError('Erreur lors de la mise à jour');
                revertFunc();
            }
        });
    }

    /**
     * Afficher la modal d'événement
     */
    function showEventModal(event) {
        const modal = $('#hp-event-modal');
        const title = $('#hp-event-title');
        const details = $('#hp-event-details');

        title.text(event.title);

        let detailsHtml = '';
        
        // Date et heure
        detailsHtml += '<div class="hp-detail-row">';
        detailsHtml += '<span class="hp-detail-label">Date :</span>';
        detailsHtml += '<span>' + formatEventDate(event) + '</span>';
        detailsHtml += '</div>';

        // Lieu
        if (event.extendedProps.location) {
            detailsHtml += '<div class="hp-detail-row">';
            detailsHtml += '<span class="hp-detail-label">Lieu :</span>';
            detailsHtml += '<span>' + escapeHtml(event.extendedProps.location) + '</span>';
            detailsHtml += '</div>';
        }

        // Formateur
        if (event.extendedProps.trainer_name) {
            detailsHtml += '<div class="hp-detail-row">';
            detailsHtml += '<span class="hp-detail-label">Formateur :</span>';
            detailsHtml += '<span>' + escapeHtml(event.extendedProps.trainer_name) + '</span>';
            detailsHtml += '</div>';
        }

        // Description
        if (event.extendedProps.description) {
            detailsHtml += '<div class="hp-detail-row">';
            detailsHtml += '<span class="hp-detail-label">Description :</span>';
            detailsHtml += '<span>' + escapeHtml(event.extendedProps.description) + '</span>';
            detailsHtml += '</div>';
        }

        // Participants
        if (event.extendedProps.attendees && event.extendedProps.attendees.length > 0) {
            detailsHtml += '<div class="hp-detail-row">';
            detailsHtml += '<span class="hp-detail-label">Participants :</span>';
            detailsHtml += '<span>' + event.extendedProps.attendees.length + ' personnes</span>';
            detailsHtml += '</div>';
        }

        // Actions
        detailsHtml += '<div class="hp-modal-actions" style="margin-top: 20px;">';
        detailsHtml += '<a href="' + hp_admin.admin_url + 'admin.php?page=hyperplanning-events&action=edit&id=' + event.id + '" class="button button-primary">Modifier</a> ';
        detailsHtml += '<button class="button hp-delete-event" data-id="' + event.id + '">Supprimer</button>';
        detailsHtml += '</div>';

        details.html(detailsHtml);
        modal.fadeIn(200);
    }

    /**
     * Formater la date d'un événement
     */
    function formatEventDate(event) {
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };

        if (event.allDay) {
            return event.start.toLocaleDateString('fr-FR', options);
        } else {
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit'
            };
            
            let result = event.start.toLocaleDateString('fr-FR', options);
            result += ' à ' + event.start.toLocaleTimeString('fr-FR', timeOptions);
            
            if (event.end) {
                result += ' - ' + event.end.toLocaleTimeString('fr-FR', timeOptions);
            }
            
            return result;
        }
    }

    /**
     * Initialiser les événements
     */
    function initializeEvents() {
        // Filtres du calendrier
        $('#hp-trainer-filter').on('change', function() {
            currentFilters.trainer = $(this).val();
            if (calendar) {
                calendar.refetchEvents();
            }
        });

        $('#hp-type-filter').on('change', function() {
            currentFilters.type = $(this).val();
            if (calendar) {
                calendar.refetchEvents();
            }
        });

        // Bouton aujourd'hui
        $('#hp-calendar-today').on('click', function() {
            if (calendar) {
                calendar.today();
            }
        });

        // Changement de vue
        $('.hp-calendar-views button').on('click', function() {
            const view = $(this).data('view');
            if (calendar && view) {
                calendar.changeView(view);
                $('.hp-calendar-views button').removeClass('active');
                $(this).addClass('active');
            }
        });

        // Fermer la modal
        $('.hp-modal-close, .hp-modal').on('click', function(e) {
            if (e.target === this) {
                $('.hp-modal').fadeOut(200);
            }
        });

        // Supprimer un événement
        $(document).on('click', '.hp-delete-event', function() {
            if (!confirm(hp_admin.i18n.confirm_delete)) {
                return;
            }

            const eventId = $(this).data('id');
            deleteEvent(eventId);
        });

        // Formulaire de synchronisation
        $('#hp-sync-now').on('click', function() {
            runSync();
        });
    }

    /**
     * Supprimer un événement
     */
    function deleteEvent(eventId) {
        $.ajax({
            url: hp_admin.rest_url + 'events/' + eventId,
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': hp_admin.nonce
            },
            success: function(response) {
                $('.hp-modal').fadeOut(200);
                if (calendar) {
                    const event = calendar.getEventById(eventId);
                    if (event) {
                        event.remove();
                    }
                }
                showSuccess('Événement supprimé');
            },
            error: function(xhr, status, error) {
                console.error('Erreur suppression événement:', error);
                showError('Erreur lors de la suppression');
            }
        });
    }

    /**
     * Initialiser la synchronisation
     */
    function initializeSync() {
        // Auto-refresh du statut de synchronisation
        if ($('.hp-sync-status').length) {
            setInterval(checkSyncStatus, 60000); // Toutes les minutes
        }
    }

    /**
     * Lancer la synchronisation
     */
    function runSync() {
        const button = $('#hp-sync-now');
        const progress = $('#hp-sync-progress');

        button.prop('disabled', true);
        progress.show();

        $.ajax({
            url: hp_admin.rest_url + 'sync/run',
            method: 'POST',
            headers: {
                'X-WP-Nonce': hp_admin.nonce
            },
            success: function(response) {
                showSuccess('Synchronisation terminée');
                // Recharger la page pour afficher les nouveaux logs
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            },
            error: function(xhr, status, error) {
                console.error('Erreur synchronisation:', error);
                showError('Erreur lors de la synchronisation');
            },
            complete: function() {
                button.prop('disabled', false);
                progress.hide();
            }
        });
    }

    /**
     * Vérifier le statut de synchronisation
     */
    function checkSyncStatus() {
        // Implémenter si nécessaire
    }

    /**
     * Initialiser les formulaires
     */
    function initializeForms() {
        // Validation des formulaires
        $('form.hp-form').on('submit', function(e) {
            const form = $(this);
            const requiredFields = form.find('[required]');
            let isValid = true;

            requiredFields.each(function() {
                const field = $(this);
                if (!field.val().trim()) {
                    field.addClass('error');
                    isValid = false;
                } else {
                    field.removeClass('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                showError('Veuillez remplir tous les champs obligatoires');
            }
        });

        // Sélecteur de couleur
        $('input[type="color"]').on('change', function() {
            const color = $(this).val();
            $(this).css('background-color', color);
        });

        // Toggle synchronisation
        $('input[name="trainer[sync_enabled]"]').on('change', function() {
            const syncFields = $('.sync-fields');
            if ($(this).is(':checked')) {
                syncFields.slideDown();
            } else {
                syncFields.slideUp();
            }
        });
    }

    /**
     * Afficher un message de succès
     */
    function showSuccess(message) {
        showNotification(message, 'success');
    }

    /**
     * Afficher un message d'erreur
     */
    function showError(message) {
        showNotification(message, 'error');
    }

    /**
     * Afficher une notification
     */
    function showNotification(message, type) {
        const notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
        
        $('.wrap > h1').after(notification);
        
        // Auto-hide après 5 secondes
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Rendre dismissible
        makeNoticeDismissible(notification);
    }

    /**
     * Rendre une notice dismissible
     */
    function makeNoticeDismissible(notice) {
        notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
        
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    /**
     * Afficher le loader
     */
    function showLoading() {
        $('#hp-calendar-container').addClass('hp-loading');
    }

    /**
     * Masquer le loader
     */
    function hideLoading() {
        $('#hp-calendar-container').removeClass('hp-loading');
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

    /**
     * Débounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Export des événements
     */
    window.exportEvents = function(format) {
        const params = new URLSearchParams({
            format: format,
            trainer: currentFilters.trainer || '',
            type: currentFilters.type || '',
            start: calendar ? calendar.view.activeStart.toISOString() : '',
            end: calendar ? calendar.view.activeEnd.toISOString() : ''
        });

        window.location.href = hp_admin.admin_url + 'admin.php?page=hyperplanning-export&' + params.toString();
    };

    /**
     * Impression du calendrier
     */
    window.printCalendar = function() {
        window.print();
    };

})(jQuery);