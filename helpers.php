<?php
/**
 * Fonctions utilitaires pour HyperPlanning
 * 
 * @package HyperPlanning
 * @since 1.0.0
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger pour debug
 */
function hp_log($message, $level = 'info') {
    if (!WP_DEBUG || !WP_DEBUG_LOG) {
        return;
    }
    
    $trace = debug_backtrace();
    $caller = isset($trace[1]) ? basename($trace[1]['file']) . ':' . $trace[1]['line'] : 'unknown';
    
    error_log(sprintf('[HyperPlanning] [%s] [%s] %s - %s', 
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $caller,
        is_string($message) ? $message : print_r($message, true)
    ));
}

/**
 * Formater une date pour l'affichage
 */
function hp_format_date($date, $format = null) {
    if (empty($date)) {
        return '';
    }
    
    if (!$format) {
        $format = get_option('date_format') . ' ' . get_option('time_format');
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date_i18n($format, $timestamp);
}

/**
 * Formater une durée en heures et minutes
 */
function hp_format_duration($minutes) {
    if ($minutes < 60) {
        return sprintf(_n('%d minute', '%d minutes', $minutes, 'hyperplanning'), $minutes);
    }
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($mins == 0) {
        return sprintf(_n('%d heure', '%d heures', $hours, 'hyperplanning'), $hours);
    }
    
    return sprintf(__('%d h %d min', 'hyperplanning'), $hours, $mins);
}

/**
 * Obtenir la différence entre deux dates
 */
function hp_date_diff($start, $end, $unit = 'minutes') {
    $start_timestamp = is_numeric($start) ? $start : strtotime($start);
    $end_timestamp = is_numeric($end) ? $end : strtotime($end);
    
    $diff = abs($end_timestamp - $start_timestamp);
    
    switch ($unit) {
        case 'seconds':
            return $diff;
        case 'minutes':
            return floor($diff / 60);
        case 'hours':
            return floor($diff / 3600);
        case 'days':
            return floor($diff / 86400);
        default:
            return $diff;
    }
}

/**
 * Vérifier si deux plages horaires se chevauchent
 */
function hp_dates_overlap($start1, $end1, $start2, $end2) {
    $start1_ts = is_numeric($start1) ? $start1 : strtotime($start1);
    $end1_ts = is_numeric($end1) ? $end1 : strtotime($end1);
    $start2_ts = is_numeric($start2) ? $start2 : strtotime($start2);
    $end2_ts = is_numeric($end2) ? $end2 : strtotime($end2);
    
    return ($start1_ts < $end2_ts) && ($end1_ts > $start2_ts);
}

/**
 * Générer un UUID v4
 */
function hp_generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Nettoyer et valider une couleur hexadécimale
 */
function hp_sanitize_hex_color($color) {
    if (empty($color)) {
        return '';
    }
    
    // Ajouter # si manquant
    if (strpos($color, '#') !== 0) {
        $color = '#' . $color;
    }
    
    // Valider le format
    if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color)) {
        return $color;
    }
    
    return '';
}

/**
 * Obtenir l'URL du plugin
 */
function hp_get_plugin_url($path = '') {
    $url = HYPERPLANNING_PLUGIN_URL;
    
    if ($path) {
        $url .= ltrim($path, '/');
    }
    
    return $url;
}

/**
 * Obtenir le chemin du plugin
 */
function hp_get_plugin_path($path = '') {
    $dir = HYPERPLANNING_PLUGIN_DIR;
    
    if ($path) {
        $dir .= ltrim($path, '/');
    }
    
    return $dir;
}

/**
 * Vérifier si on est sur une page du plugin
 */
function hp_is_plugin_page() {
    if (!is_admin()) {
        return false;
    }
    
    $screen = get_current_screen();
    if (!$screen) {
        return false;
    }
    
    return strpos($screen->id, 'hyperplanning') !== false;
}

/**
 * Obtenir la capacité minimale requise pour une action
 */
function hp_get_required_capability($action = 'view') {
    $capabilities = array(
        'view' => 'hp_view_calendar',
        'create' => 'hp_create_events',
        'edit' => 'hp_edit_own_events',
        'edit_all' => 'hp_edit_all_events',
        'delete' => 'hp_delete_own_events',
        'delete_all' => 'hp_delete_all_events',
        'manage' => 'hp_manage_calendars',
        'settings' => 'hp_manage_settings',
    );
    
    return isset($capabilities[$action]) ? $capabilities[$action] : 'manage_options';
}

/**
 * Vérifier si l'utilisateur peut effectuer une action
 */
function hp_current_user_can($action, $item_id = null) {
    $capability = hp_get_required_capability($action);
    
    if (!current_user_can($capability)) {
        return false;
    }
    
    // Vérifications supplémentaires pour les éléments spécifiques
    if ($item_id && in_array($action, array('edit', 'delete'))) {
        // Si l'utilisateur ne peut pas éditer/supprimer tous les éléments
        if (!current_user_can($capability . '_all')) {
            // Vérifier s'il est propriétaire de l'élément
            // À implémenter selon le type d'élément
        }
    }
    
    return true;
}

/**
 * Envoyer une notification par email
 */
function hp_send_notification($to, $subject, $message, $headers = array()) {
    $default_headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    );
    
    $headers = array_merge($default_headers, $headers);
    
    // Wrapper le message dans un template HTML
    $html_message = hp_get_email_template($subject, $message);
    
    return wp_mail($to, $subject, $html_message, $headers);
}

/**
 * Obtenir le template d'email
 */
function hp_get_email_template($title, $content) {
    $template = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . esc_html($title) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; margin-top: 20px; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . esc_html($title) . '</h1>
            </div>
            <div class="content">
                ' . $content . '
            </div>
            <div class="footer">
                <p>' . sprintf(__('Envoyé depuis %s', 'hyperplanning'), get_bloginfo('name')) . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $template;
}

/**
 * Nettoyer les données du cache
 */
function hp_clear_cache($type = 'all') {
    $cache_groups = array(
        'events' => 'hyperplanning_events',
        'trainers' => 'hyperplanning_trainers',
        'calendars' => 'hyperplanning_calendars',
    );
    
    if ($type === 'all') {
        foreach ($cache_groups as $group) {
            wp_cache_flush_group($group);
        }
    } elseif (isset($cache_groups[$type])) {
        wp_cache_flush_group($cache_groups[$type]);
    }
    
    // Nettoyer aussi les transients
    hp_clear_transients($type);
}

/**
 * Nettoyer les transients
 */
function hp_clear_transients($type = 'all') {
    global $wpdb;
    
    $prefix = 'hp_';
    if ($type !== 'all') {
        $prefix .= $type . '_';
    }
    
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE %s 
        OR option_name LIKE %s",
        '_transient_' . $prefix . '%',
        '_transient_timeout_' . $prefix . '%'
    ));
}

/**
 * Créer un slug à partir d'un texte
 */
function hp_create_slug($text) {
    $slug = sanitize_title($text);
    $slug = str_replace('_', '-', $slug);
    
    return $slug;
}

/**
 * Obtenir l'adresse IP de l'utilisateur
 */
function hp_get_user_ip() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key])) {
            $ip = filter_var($_SERVER[$key], FILTER_VALIDATE_IP);
            if ($ip !== false) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Encoder des données pour stockage sécurisé
 */
function hp_encrypt($data) {
    if (!function_exists('openssl_encrypt')) {
        return base64_encode($data);
    }
    
    $key = wp_salt('auth');
    $iv = substr(md5($key), 0, 16);
    
    return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv));
}

/**
 * Décoder des données stockées
 */
function hp_decrypt($data) {
    if (!function_exists('openssl_decrypt')) {
        return base64_decode($data);
    }
    
    $key = wp_salt('auth');
    $iv = substr(md5($key), 0, 16);
    
    return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, 0, $iv);
}

/**
 * Valider un email
 */
function hp_validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valider une URL
 */
function hp_validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Obtenir les options de fuseau horaire
 */
function hp_get_timezone_options($selected = '') {
    $timezones = timezone_identifiers_list();
    $options = '';
    
    foreach ($timezones as $timezone) {
        $options .= sprintf(
            '<option value="%s" %s>%s</option>',
            esc_attr($timezone),
            selected($selected, $timezone, false),
            esc_html($timezone)
        );
    }
    
    return $options;
}

/**
 * Convertir une date vers un fuseau horaire
 */
function hp_convert_timezone($date, $from_tz = 'UTC', $to_tz = null) {
    if (!$to_tz) {
        $to_tz = get_option('hp_time_zone', 'UTC');
    }
    
    try {
        $dt = new DateTime($date, new DateTimeZone($from_tz));
        $dt->setTimezone(new DateTimeZone($to_tz));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        hp_log('Erreur conversion timezone: ' . $e->getMessage(), 'error');
        return $date;
    }
}

/**
 * Afficher un message admin
 */
function hp_admin_notice($message, $type = 'info', $dismissible = true) {
    $classes = 'notice notice-' . $type;
    if ($dismissible) {
        $classes .= ' is-dismissible';
    }
    
    printf(
        '<div class="%1$s"><p>%2$s</p></div>',
        esc_attr($classes),
        esc_html($message)
    );
}

/**
 * Obtenir la taille de fichier formatée
 */
function hp_format_file_size($bytes) {
    $units = array('B', 'KB', 'MB', 'GB');
    $factor = floor((strlen($bytes) - 1) / 3);
    
    return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}