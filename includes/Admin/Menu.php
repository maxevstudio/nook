<?php
/**
 * Menu et écran d'administration React de Nook.
 *
 * @package Nook
 */

namespace Nook\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu
 */
class Menu {

	/**
	 * Slug de la page d'administration.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'nook';

	/**
	 * Accroche les hooks WordPress nécessaires.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Ajoute la page d'administration de premier niveau.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Nook', 'nook' ),
			__( 'Nook', 'nook' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield',
			80
		);
	}

	/**
	 * Affiche le conteneur monté par l'application React.
	 *
	 * @return void
	 */
	public function render_page() {
		echo '<div id="nook-admin-root"></div>';
	}

	/**
	 * Charge les assets React uniquement sur l'écran de Nook.
	 *
	 * @param string $hook_suffix Identifiant de l'écran d'administration courant.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = NOOK_PLUGIN_DIR . 'build/admin/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'nook-admin',
			NOOK_PLUGIN_URL . 'build/admin/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'nook-admin', 'nook', NOOK_PLUGIN_DIR . 'languages' );

		wp_add_inline_script(
			'nook-admin',
			'window.nookAdmin = ' . wp_json_encode( array( 'adminUrl' => admin_url() ) ) . ';',
			'before'
		);

		$style_path = NOOK_PLUGIN_DIR . 'build/admin/style-index.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'nook-admin',
				NOOK_PLUGIN_URL . 'build/admin/style-index.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}
	}
}
