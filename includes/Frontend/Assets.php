<?php
/**
 * Chargement du runtime public (bannière, script blocking).
 *
 * @package Nook
 */

namespace Nook\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
class Assets {

	/**
	 * Accroche les hooks WordPress nécessaires.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Charge le runtime front (JS natif, sans React — voir AGENTS.md règle 6).
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$asset_file = NOOK_PLUGIN_DIR . 'build/frontend/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'nook-frontend',
			NOOK_PLUGIN_URL . 'build/frontend/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Le rendu de la bannière et le blocage réel des scripts sont un
		// chantier à venir (voir ARCHITECTURE.md §3.4 et §9, STATUS.md).
		wp_add_inline_script(
			'nook-frontend',
			'window.nookFrontend = ' . wp_json_encode( array( 'version' => NOOK_VERSION ) ) . ';',
			'before'
		);

		$style_path = NOOK_PLUGIN_DIR . 'build/frontend/index.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style( 'nook-frontend', NOOK_PLUGIN_URL . 'build/frontend/index.css', array(), $asset['version'] );
		}
	}
}
