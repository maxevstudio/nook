<?php
/**
 * Chargement du runtime public (bannière, script blocking).
 *
 * @package Nook
 */

namespace Nook\Frontend;

use Nook\Database\Settings;
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

		$this->enqueue_guard();

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

	/**
	 * Charge le garde de blocage dynamique dans le `<head>`, avant tout
	 * autre script, pour intercepter les iframes créées en JavaScript (voir
	 * src/frontend/guard.js et ARCHITECTURE.md §3.4). Rien n'est chargé si
	 * aucun service n'est soumis à consentement.
	 *
	 * @return void
	 */
	private function enqueue_guard() {
		$asset_file = NOOK_PLUGIN_DIR . 'build/frontend/guard.asset.php';
		$config     = self::build_guard_config( Blocklist::build() );

		if ( empty( $config['services'] ) || ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'nook-guard',
			NOOK_PLUGIN_URL . 'build/frontend/guard.js',
			$asset['dependencies'],
			$asset['version'],
			false
		);

		wp_add_inline_script(
			'nook-guard',
			'window.nookGuardConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Construit la configuration du garde : uniquement les signatures
	 * d'iframes des services bloqués, et les textes du placeholder.
	 *
	 * @param array[] $blocklist Liste issue de Blocklist::build().
	 * @return array{configVersion: int, services: array[], i18n: array}
	 */
	public static function build_guard_config( array $blocklist ) {
		$settings = Settings::get();
		$services = array();

		foreach ( $blocklist as $entry ) {
			if ( empty( $entry['iframePatterns'] ) && empty( $entry['domains'] ) ) {
				continue;
			}

			$services[] = array(
				'slug'           => $entry['slug'],
				'category'       => $entry['category'],
				'label'          => Placeholder::get_service_label( $entry['slug'] ),
				'iframePatterns' => array_values( (array) $entry['iframePatterns'] ),
				'domains'        => array_values( (array) $entry['domains'] ),
			);
		}

		return array(
			'configVersion' => $settings['consent']['configVersion'],
			'services'      => $services,
			'i18n'          => array(
				'message' => Placeholder::get_message_template(),
				'button'  => Placeholder::get_button_label(),
			),
		);
	}
}

