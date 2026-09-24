<?php
/**
 * Contrôleur REST des réglages Nook.
 *
 * @package Nook
 */

namespace Nook\REST;

use Nook\Database\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsController
 */
class SettingsController {

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
	 * Enregistre les routes REST `/nook/v1/settings`.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'configured' => array(
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				),
			)
		);
	}

	/**
	 * Vérifie que l'utilisateur courant peut lire/modifier les réglages.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'nook_forbidden',
				__( 'Vous n’avez pas la permission d’accéder aux réglages de Nook.', 'nook' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Retourne les réglages courants.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return new WP_REST_Response( Settings::get() );
	}

	/**
	 * Sauvegarde des réglages partiels.
	 *
	 * @param WP_REST_Request $request Requête REST entrante.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$updated = Settings::update( $this->sanitize_settings( $body ) );

		return new WP_REST_Response( $updated );
	}

	/**
	 * Nettoie récursivement les données reçues avant sauvegarde.
	 *
	 * Ne fait jamais confiance aux données envoyées par le navigateur (voir
	 * AGENTS.md règle de sécurité et ARCHITECTURE.md §10).
	 *
	 * @param array $data Données brutes reçues.
	 * @return array Données nettoyées.
	 */
	private function sanitize_settings( array $data ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_settings( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = $value;
			} else {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}
}
