<?php
// ==============================================================================
// MÓDULO: AUTENTICACIÓN Y SEGURIDAD
// ==============================================================================

// 🔐 Redirige los intentos fallidos de login a la página personalizada
function gptwp_custom_login_failed( $username ) {
    wp_redirect( home_url( '/login/?login=failed' ) );
    exit;
}
add_action( 'wp_login_failed', 'gptwp_custom_login_failed' );

// 🚪 Redirige el cierre de sesión (logout) a la página de login personalizada
function gptwp_custom_logout_redirect(){
    wp_redirect( home_url( '/login/?login=false' ) );
    exit;
}
add_action( 'wp_logout','gptwp_custom_logout_redirect' );

// 🚫 Redirige el acceso directo a wp-login.php (GET) si no está logueado
function gptwp_redirect_wp_login() {
    $login_page = home_url( '/login/' );
    $page_viewed = basename($_SERVER['REQUEST_URI']);

    if ( $page_viewed === 'wp-login.php' && $_SERVER['REQUEST_METHOD'] === 'GET' && !is_user_logged_in() ) {
        wp_redirect( $login_page );
        exit;
    }
}
add_action('init', 'gptwp_redirect_wp_login');

// 🔒 Bloquea el acceso a wp-admin para usuarios no administradores
function gptwp_block_wp_admin_access() {
    if ( is_admin() && !current_user_can('administrator') && !wp_doing_ajax() ) {
        wp_redirect( home_url('/login/') );
        exit;
    }
}
add_action('init', 'gptwp_block_wp_admin_access');

// 1. BLOQUEAR BARRA DE ADMIN (Seguridad)
add_action('after_setup_theme', function() {
    if (!current_user_can('administrator') && !current_user_can('profesor') && !is_admin()) {
        show_admin_bar(false);
    }
});

// 📢 Muestra mensajes de error o cierre de sesión en la página de login
function gptwp_login_error_message_shortcode() {
    if ( isset($_GET['login']) && $_GET['login'] === 'failed' ) {
        return '<div class="alx-card alx-animate-fade" style="border-color: var(--alx-danger); margin-bottom: 20px;">
                    <span class="alx-text" style="color: var(--alx-danger);">⚠️ Usuario o contraseña incorrectos</span>
                </div>';
    }

    if ( isset($_GET['login']) && $_GET['login'] === 'false' ) {
        return '<div class="alx-card alx-animate-fade" style="border-color: var(--alx-success); margin-bottom: 20px;">
                    <span class="alx-text" style="color: var(--alx-success);">✅ Sesión cerrada correctamente.</span>
                </div>';
    }

    return '';
}
add_shortcode('gptwp_login_message', 'gptwp_login_error_message_shortcode');


function gptwp_logout_url_shortcode() {
    // Redirige al usuario a la página /login/ después del logout
    $logout_url = wp_logout_url( home_url( '/login/?login=false' ) );
    return $logout_url;
}
add_shortcode( 'logout_url', 'gptwp_logout_url_shortcode' );


function proteger_sitio_para_no_logueados() {
    // Página que NO estará protegida
    $pagina_login = 'login'; // slug de la página de login

    // Verifica que NO estemos en la página de login
    if ( !is_page($pagina_login) && !is_user_logged_in() ) {
        // Redirige a la página /login
        wp_redirect( site_url('/login') );
        exit;
    }
}
add_action('template_redirect', 'proteger_sitio_para_no_logueados');
