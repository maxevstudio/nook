<?php
/**
 * Contrôleur REST de la configuration publique consommée par le runtime
 * front (bannière, centre de préférences) — voir ARCHITECTURE.md §4.
 *
 * Cette route est volontairement publique (`permission_callback` ouvert) :
 * elle n'expose que des données déjà visibles sur le site (textes de
 * bannière, catégories, design), jamais de données sensibles ou de secrets.
 *
 * @package Nook
 */

namespace Nook\REST;

use Nook\Consent\CategoryRegistry;
use Nook\Database\Settings;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PublicConfigController
 */
class PublicConfigController {

	/**
	 * Namespace REST du plugin.
	 *
	 * @var string
	 */
	const NAMESPACE_V1 = 'nook/v1';

	/**
	 * Accroche l'enregistrement des routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistre la route REST `/nook/v1/public-config`.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/public-config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_public_config' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Retourne la configuration publique consommée par le runtime front.
	 *
	 * @return WP_REST_Response
	 */
	public function get_public_config() {
		return new WP_REST_Response( self::build() );
	}

	/**
	 * Construit le tableau de configuration publique, réutilisé aussi bien
	 * par la route REST que par l'injection inline (voir Frontend\Assets).
	 *
	 * @return array
	 */
	public static function build() {
		$settings   = Settings::get();
		$categories = CategoryRegistry::get_categories();

		// Les descriptions/labels peuvent avoir été personnalisés par
		// l'administrateur pendant le wizard (voir Settings::get_defaults()) :
		// on les fusionne par-dessus le registre PHP.
		foreach ( $settings['consent']['categories'] as $slug => $custom ) {
			if ( isset( $categories[ $slug ] ) && is_array( $custom ) ) {
				$categories[ $slug ] = array_merge( $categories[ $slug ], $custom );
			}
		}

		return array(
			'categories' => $categories,
			'banner'     => $settings['banner'],
			'consent'    => array(
				'duration'      => $settings['consent']['duration'],
				'configVersion' => $settings['consent']['configVersion'],
			),
			'policy'     => array(
				'url' => self::get_policy_url( $settings ),
			),
		);
	}

	/**
	 * Résout l'URL de la page de politique de cookies configurée, si elle
	 * existe encore.
	 *
	 * @param array $settings Réglages Nook.
	 * @return string|null
	 */
	private static function get_policy_url( array $settings ) {
		$page_id = isset( $settings['policy']['pageId'] ) ? $settings['policy']['pageId'] : null;

		if ( ! $page_id ) {
			return null;
		}

		$url = get_permalink( $page_id );

		return $url ? $url : null;
	}
}
