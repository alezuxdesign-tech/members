<?php

/**
 * La clase principal del plugin.
 *
 * Esta clase orquesta la carga de todos los archivos de dependencia,
 * inicializa el cargador de hooks y define los componentes del admin y public.
 */
class Alezux_Members {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->plugin_name = 'alezux-members';
		$this->version = '2.0.6';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		require_once GPTWP_PATH . 'includes/core/class-loader.php';
		require_once GPTWP_PATH . 'includes/core/class-auth.php';
		require_once GPTWP_PATH . 'includes/admin/class-admin.php';
		require_once GPTWP_PATH . 'includes/public/class-lessons.php';
		require_once GPTWP_PATH . 'includes/public/class-courses.php';
		require_once GPTWP_PATH . 'includes/models/class-progress.php';
		require_once GPTWP_PATH . 'includes/models/class-achievements.php';
		require_once GPTWP_PATH . 'includes/utilities/class-email.php';
		require_once GPTWP_PATH . 'includes/integrations/elementor/class-elementor-loader.php';

		$this->loader = new Alezux_Members_Loader();
	}

	private function set_locale() {
		// Aquí se podría implementar la internacionalización
	}

	private function define_admin_hooks() {
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_styles' );
	}

	private function define_public_hooks() {
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_styles' );
	}

	public function enqueue_admin_styles() {
		wp_enqueue_style( 'alezux-global', GPTWP_URL . 'assets/css/shared/variables.css', [], $this->version );
		wp_enqueue_style( 'alezux-utilities', GPTWP_URL . 'assets/css/shared/utilities.css', ['alezux-global'], $this->version );
		wp_enqueue_style( 'alezux-admin', GPTWP_URL . 'assets/css/admin/main-admin.css', ['alezux-utilities'], $this->version );
	}

	public function enqueue_public_styles() {
		wp_enqueue_style( 'alezux-global', GPTWP_URL . 'assets/css/shared/variables.css', [], $this->version );
		wp_enqueue_style( 'alezux-utilities', GPTWP_URL . 'assets/css/shared/utilities.css', ['alezux-global'], $this->version );
		wp_enqueue_style( 'alezux-public', GPTWP_URL . 'assets/css/public/main-public.css', ['alezux-utilities'], $this->version );
		// Dashboard styles also needed in public if using dashboard shortcode
		wp_enqueue_style( 'alezux-admin', GPTWP_URL . 'assets/css/admin/main-admin.css', ['alezux-utilities', 'alezux-public'], $this->version );
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
