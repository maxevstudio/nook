<?php
/**
 * Chargement du runtime public (bannière, script blocking).
 *
 * @package Nook
 */

namespace Nook\Frontend;

use Nook\REST\PublicConfigController;

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
	 * La configuration publique (catégories, textes/design de la bannière)
	 * est injectée directement en inline plutôt que rechargée via un appel
	 * REST supplémentaire au chargement de chaque page (voir PROJECT.md
	 * §66, performance front) : elle est déjà calculée côté serveur pour
	 * cette même requête.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

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

		wp_set_script_translations( 'nook-frontend', 'nook', NOOK_PLUGIN_DIR . 'languages' );

		$config            = PublicConfigController::build();
		$config['version'] = NOOK_VERSION;

		wp_add_inline_script(
			'nook-frontend',
			'window.nookFrontend = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		$style_path = NOOK_PLUGIN_DIR . 'build/frontend/style-index.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style( 'nook-frontend', NOOK_PLUGIN_URL . 'build/frontend/style-index.css', array(), $asset['version'] );
		}
	}
}

