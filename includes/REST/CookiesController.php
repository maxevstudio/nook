<?php
/**
 * Contrôleur REST des cookies (résultats du scanner + corrections
 * manuelles de l'administrateur) — voir PROJECT.md §20-22.
 *
 * @package Nook
 */

namespace Nook\REST;

use Nook\Database\ScanResults;
use Nook\Database\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CookiesController
 */
class CookiesController {

	/**
	 * Namespace REST du plugin.
	 *
	 * @var string
	 */
	const NAMESPACE_V1 = 'nook/v1';

	/**
	 * Catégories de consentement valides.
	 *
	 * @var string[]
	 */
	const VALID_CATEGORIES = array( 'necessary', 'functional', 'statistics', 'marketing' );

	/**
	 * Statuts valides pour une correction manuelle de cookie (voir
	 * ARCHITECTURE.md « Statut des résultats »).
	 *
	 * @var string[]
	 */
	const VALID_STATUSES = array( 'recognized', 'suggested', 'to_review', 'ignored' );

	/**
	 * Accroche l'enregistrement des routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistre les routes REST `/nook/v1/cookies` et
	 * `/nook/v1/cookies/:id`.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/cookies',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cookies' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/cookies/(?P<id>[a-zA-Z0-9_\-]+)',
			array(
				'methods'             => array( 'POST', 'PUT' ),
				'callback'            => array( $this, 'update_cookie' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Vérifie que l'utilisateur courant peut lire/modifier les cookies.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'nook_forbidden',
				__( 'Vous n’avez pas la permission d’accéder aux cookies Nook.', 'nook' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Retourne la liste fusionnée des cookies : résultats du dernier scan
	 * et corrections/ajouts manuels de l'administrateur (voir PROJECT.md
	 * §22 : les corrections manuelles ne sont jamais écrasées par un
	 * re-scan).
	 *
	 * @return WP_REST_Response
	 */
	public function get_cookies() {
		$scan_results = ScanResults::get();
		$settings     = Settings::get();
		$overrides    = is_array( $settings['cookies'] ) ? $settings['cookies'] : array();

		$cookies = array();
		$seen    = array();

		foreach ( $scan_results['cookies'] as $cookie ) {
			$id = $this->make_id( $cookie['name'] );

			if ( isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;
			$override    = isset( $overrides[ $id ] ) && is_array( $overrides[ $id ] ) ? $overrides[ $id ] : array();

			$cookies[] = $this->merge_cookie( $id, $cookie, $override, 'scan' );
		}

		foreach ( $overrides as $id => $override ) {
			if ( isset( $seen[ $id ] ) || empty( $override['isManual'] ) ) {
				continue;
			}

			$cookies[] = $this->merge_cookie(
				$id,
				array(
					'name'        => isset( $override['name'] ) ? $override['name'] : $id,
					'category'    => null,
					'service'     => null,
					'description' => null,
					'retention'   => null,
				),
				$override,
				'manual'
			);
		}

		return new WP_REST_Response( $cookies );
	}

	/**
	 * Met à jour ou crée une correction manuelle pour un cookie.
	 *
	 * @param WP_REST_Request $request Requête REST entrante.
	 * @return WP_REST_Response
	 */
	public function update_cookie( WP_REST_Request $request ) {
		$id   = $this->make_id( $request->get_param( 'id' ) );
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$override = array();

		if ( isset( $body['category'] ) && in_array( $body['category'], self::VALID_CATEGORIES, true ) ) {
			$override['category'] = $body['category'];
		}

		if ( isset( $body['status'] ) && in_array( $body['status'], self::VALID_STATUSES, true ) ) {
			$override['status'] = $body['status'];
		}

		if ( isset( $body['description'] ) ) {
			$override['description'] = sanitize_text_field( (string) $body['description'] );
		}

		if ( isset( $body['retention'] ) ) {
			$override['retention'] = sanitize_text_field( (string) $body['retention'] );
		}

		if ( isset( $body['service'] ) ) {
			$override['service'] = sanitize_text_field( (string) $body['service'] );
		}

		$is_known = $this->cookie_exists_in_scan( $id );

		if ( ! $is_known ) {
			if ( isset( $body['name'] ) && '' !== trim( (string) $body['name'] ) ) {
				$override['isManual'] = true;
				$override['name']     = sanitize_text_field( (string) $body['name'] );
			} elseif ( empty( $override ) ) {
				return new WP_REST_Response(
					array( 'message' => __( 'Cookie inconnu.', 'nook' ) ),
					404
				);
			}
		}

		$settings = Settings::update(
			array(
				'cookies' => array( $id => $override ),
			)
		);

		$stored       = isset( $settings['cookies'][ $id ] ) ? $settings['cookies'][ $id ] : array();
		$scan_results = ScanResults::get();
		$scanned      = null;

		foreach ( $scan_results['cookies'] as $cookie ) {
			if ( $this->make_id( $cookie['name'] ) === $id ) {
				$scanned = $cookie;
				break;
			}
		}

		if ( $scanned ) {
			return new WP_REST_Response( $this->merge_cookie( $id, $scanned, $stored, 'scan' ) );
		}

		return new WP_REST_Response(
			$this->merge_cookie(
				$id,
				array(
					'name'        => isset( $stored['name'] ) ? $stored['name'] : $id,
					'category'    => null,
					'service'     => null,
					'description' => null,
					'retention'   => null,
				),
				$stored,
				'manual'
			)
		);
	}

	/**
	 * Fusionne un résultat de scan avec sa correction manuelle éventuelle.
	 *
	 * @param string $id       Identifiant normalisé du cookie.
	 * @param array  $scanned  Résultat brut du scanner ({ name, category, service, description, retention, status? }).
	 * @param array  $override Correction manuelle stockée.
	 * @param string $source   `scan` ou `manual`.
	 * @return array
	 */
	private function merge_cookie( $id, array $scanned, array $override, $source ) {
		$status = isset( $override['status'] )
			? $override['status']
			: ( isset( $scanned['status'] ) ? $scanned['status'] : 'to_review' );

		return array(
			'id'          => $id,
			'name'        => $scanned['name'],
			'category'    => isset( $override['category'] ) ? $override['category'] : $scanned['category'],
			'service'     => isset( $override['service'] ) ? $override['service'] : $scanned['service'],
			'description' => isset( $override['description'] ) ? $override['description'] : $scanned['description'],
			'retention'   => isset( $override['retention'] ) ? $override['retention'] : $scanned['retention'],
			'status'      => $status,
			'source'      => $source,
			'hasOverride' => ! empty( $override ),
		);
	}

	/**
	 * Détermine si un cookie (par id normalisé) fait partie des derniers
	 * résultats de scan.
	 *
	 * @param string $id Identifiant normalisé.
	 * @return bool
	 */
	private function cookie_exists_in_scan( $id ) {
		foreach ( ScanResults::get()['cookies'] as $cookie ) {
			if ( $this->make_id( $cookie['name'] ) === $id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalise un nom de cookie en identifiant stable (clé de stockage),
	 * en préservant la casse d'origine dans le nom affiché séparément.
	 *
	 * @param string $name Nom brut du cookie.
	 * @return string
	 */
	private function make_id( $name ) {
		$slug = strtolower( (string) $name );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );

		return trim( $slug, '-' );
	}
}
