<?php

/**
 * Plugin Name: Alezux Members
 * Plugin URI:  https://alezuxdesign.com/
 * Description: Sistema de gestión de cursos y estudiantes con arquitectura profesional.
 * Version:     2.0.6
 * Author:      Alezux Design
 * License:     GPL v2 or later
 * Text Domain: alezux-members
 * Domain Path: /languages
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// DEFINICIÓN DE RUTAS
define( 'GPTWP_PATH', plugin_dir_path( __FILE__ ) );
define( 'GPTWP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Código que se ejecuta durante la activación del plugin.
 */
function activate_alezux_members() {
	require_once GPTWP_PATH . 'includes/core/class-activator.php';
	Alezux_Members_Activator::activate();
}

/**
 * Código que se ejecuta durante la desactivación del plugin.
 */
function deactivate_alezux_members() {
	require_once GPTWP_PATH . 'includes/core/class-deactivator.php';
	Alezux_Members_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_alezux_members' );
register_deactivation_hook( __FILE__, 'deactivate_alezux_members' );

/**
 * La clase central que se utiliza para definir el dominio de traducción,
 * las acciones del admin y las acciones del frontend.
 */
require GPTWP_PATH . 'includes/class-alezux-members.php';

/**
 * Ejecución del plugin.
 */
function run_alezux_members() {
	$plugin = new Alezux_Members();
	$plugin->run();
}

run_alezux_members();
