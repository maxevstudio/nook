<?php
/**
 * Plugin Name:       Nook
 * Plugin URI:        https://github.com/maxevstudio/nook
 * Description:       Gestionnaire de consentement aux cookies léger, moderne et WordPress-natif. Installer → analyser → configurer → publier.
 * Version:           0.1.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Maxev
 * Author URI:        https://maxev.fr
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nook
 * Domain Path:       /languages
 *
 * @package Nook
 */

// Empêche l'accès direct au fichier.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Constantes du plugin -------------------------------------------------

define( 'NOOK_VERSION', '0.1.1' );
define( 'NOOK_DB_VERSION', 1 );
define( 'NOOK_PLUGIN_FILE', __FILE__ );
define( 'NOOK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOOK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NOOK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// --- Exigences minimales ---------------------------------------------------

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Nook nécessite PHP 7.4 ou supérieur. Le plugin a été désactivé.', 'nook' ) .
				'</p></div>';
		}
	);
	return;
}

// --- Autoloading PSR-4 minimal ----------------------------------------------
//
// Aucune dépendance Composer n'est requise à l'exécution : le plugin doit
// rester installable par simple copie du dossier (voir AGENTS.md, règle 4/17
// et ARCHITECTURE.md §3).

spl_autoload_register(
	function ( $class ) {
		$prefix = 'Nook\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
		$file           = NOOK_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative_path;

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// --- Bootstrap ---------------------------------------------------------------

/**
 * Démarre le plugin une fois que tous les plugins sont chargés.
 */
function nook_boot() {
	Nook\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'nook_boot' );
