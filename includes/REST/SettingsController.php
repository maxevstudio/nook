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
	 * Valeurs autorisées pour certains champs sensibles du schéma. Toute
	 * valeur reçue en dehors de cette liste est ignorée (on ne fait jamais
	 * confiance au client, voir AGENTS.md et ARCHITECTURE.md §10).
	 *
	 * @var array<string, string[]>
	 */
	const ALLOWED_VALUES = array(
		'site.type'      => array( 'business', 'ecommerce', 'other' ),
		'banner.position' => array( 'bottom', 'top' ),
		'banner.buttonStyle' => array( 'solid', 'outline' ),
		'banner.width'   => array( 'compact', 'full', 'contained' ),
		'policy.mode'    => array( 'create', 'existing', 'none' ),
		'scanner.status' => array( 'idle', 'running', 'done', 'error' ),
	);

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
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_settings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
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
	 * Sauvegarde des réglages partiels (fusion profonde avec l'existant,
	 * voir Settings::merge()).
	 *
	 * @param WP_REST_Request $request Requête REST entrante.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$sanitized = $this->sanitize_settings( $body );
		$sanitized = $this->enforce_allowed_values( $sanitized );

		$updated = Settings::update( $sanitized );

		return new WP_REST_Response( $updated );
	}

	/**
	 * Réinitialise entièrement la configuration Nook.
	 *
	 * @return WP_REST_Response
	 */
	public function reset_settings() {
		return new WP_REST_Response( Settings::reset() );
	}

	/**
	 * Nettoie récursivement les données reçues avant sauvegarde.
	 *
	 * Ne fait jamais confiance aux données envoyées par le navigateur (voir
	 * AGENTS.md règle de sécurité et ARCHITECTURE.md §10). Les clés sont
	 * restreintes à un jeu de caractères sûr mais **sans forcer la casse en
	 * minuscules** (contrairement à `sanitize_key()`) : le schéma de
	 * configuration utilise des clés camelCase (`schemaVersion`,
	 * `configVersion`, `backgroundColor`, ...) qui seraient sinon corrompues.
	 *
	 * @param array $data Données brutes reçues.
	 * @return array Données nettoyées.
	 */
	private function sanitize_settings( array $data ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$key = $this->sanitize_key( $key );

			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_settings( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( null === $value ) {
				$sanitized[ $key ] = null;
			} else {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Restreint une clé de réglage à `[A-Za-z0-9_-]`, en conservant la casse.
	 *
	 * @param string $key Clé brute.
	 * @return string
	 */
	private function sanitize_key( $key ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
	}

	/**
	 * Rejette les valeurs de champs sensibles (énumérations) qui ne
	 * correspondent à aucune valeur autorisée (voir self::ALLOWED_VALUES).
	 * Une valeur invalide est simplement retirée de la charge utile : elle
	 * ne remplacera pas la valeur déjà stockée côté serveur.
	 *
	 * @param array $data Données déjà nettoyées par sanitize_settings().
	 * @return array
	 */
	private function enforce_allowed_values( array $data ) {
		foreach ( self::ALLOWED_VALUES as $path => $allowed ) {
			$segments = explode( '.', $path );
			$data     = $this->strip_if_invalid( $data, $segments, $allowed );
		}

		return $data;
	}

	/**
	 * Supprime récursivement une valeur pointée par $segments si elle ne
	 * figure pas dans $allowed.
	 *
	 * @param array    $data     Tableau de données.
	 * @param string[] $segments Chemin (ex. ['banner', 'position']).
	 * @param string[] $allowed  Valeurs autorisées.
	 * @return array
	 */
	private function strip_if_invalid( array $data, array $segments, array $allowed ) {
		$key = array_shift( $segments );

		if ( ! isset( $data[ $key ] ) ) {
			return $data;
		}

		if ( empty( $segments ) ) {
			if ( ! in_array( $data[ $key ], $allowed, true ) ) {
				unset( $data[ $key ] );
			}
			return $data;
		}

		if ( is_array( $data[ $key ] ) ) {
			$data[ $key ] = $this->strip_if_invalid( $data[ $key ], $segments, $allowed );

			// Si la branche est devenue vide après suppression de la valeur
			// invalide, retirer la clé entièrement plutôt que de laisser un
			// tableau vide : Settings::merge() traiterait sinon ce tableau
			// vide comme une liste explicite à substituer (voir
			// Settings::is_list()), écrasant la branche existante au lieu
			// de la laisser intacte.
			if ( array() === $data[ $key ] ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}
}
