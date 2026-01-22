<?php
/**
 * Plugin Name: Alezux Members
 * Plugin URI:  https://alezuxdesign.com/
 * Description: Sistema de gestión de cursos y estudiantes.
 * Version:     2.0.6
 * Author:      Alezux Design
 * License:     GPL v2 or later
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// DEFINICIÓN DE RUTAS
define( 'GPTWP_PATH', plugin_dir_path( __FILE__ ) );
define( 'GPTWP_URL', plugin_dir_url( __FILE__ ) );

// INCLUSIÓN DE MÓDULOS
// El orden es importante para dependencias (aunque la mayoría son hooks independientes)

// 1. Autenticación y Seguridad
require_once GPTWP_PATH . 'includes/auth.php';

// 2. Funcionalidades de Lecciones y Topics
require_once GPTWP_PATH . 'includes/lessons.php';

// 3. Funcionalidades de Cursos y Listados
require_once GPTWP_PATH . 'includes/courses.php';

// 4. Progreso, Gamificación y Tracking de Video
require_once GPTWP_PATH . 'includes/progress.php';

// 5. Administración, CRM, Finanzas e Importador
require_once GPTWP_PATH . 'includes/admin.php';

// 6. Email Marketing y Comunicaciones
require_once GPTWP_PATH . 'includes/email.php';

// 7. Logros y Notificaciones
require_once GPTWP_PATH . 'includes/achievements.php';

// 8. Integración con Elementor (Widgets)
require_once GPTWP_PATH . 'includes/elementor/class-elementor-loader.php';

// 9. Encolado de Scripts y Estilos Globales
add_action('wp_enqueue_scripts', function() {
    // Estilos Globales
    wp_enqueue_style('gptwp-main-css', GPTWP_URL . 'assets/css/main.css', [], '2.0.7');
    // Estilos Frontend (Elementos UI, Shortcodes)
    wp_enqueue_style('gptwp-frontend-css', GPTWP_URL . 'assets/css/frontend.css', ['gptwp-main-css'], '2.0.7');
    
    // Estilos Admin/Dashboard (Solo si es necesario, o global si se usa en shortcodes)
    wp_enqueue_style('gptwp-admin-css', GPTWP_URL . 'assets/css/admin.css', ['gptwp-main-css', 'gptwp-frontend-css'], '2.0.7');
});

// 10. Encolado de Estilos para el Panel Admin (Backend)
add_action('admin_enqueue_scripts', function() {
    // Estilos Globales (Variables, etc)
    wp_enqueue_style('gptwp-main-css', GPTWP_URL . 'assets/css/main.css', [], '2.0.7');
    
    // Estilos Admin Específicos
    wp_enqueue_style('gptwp-admin-css', GPTWP_URL . 'assets/css/admin.css', ['gptwp-main-css'], '2.0.7');
});

// FIN DEL DOCUMENTO
