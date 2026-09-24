<?php
/**
 * Contrôleur REST des services (registre natif + détections + corrections
 * manuelles de l'administrateur).
 *
 * @package Nook
 */

namespace Nook\REST;

use Nook\Database\ScanResults;
use Nook\Database\Settings;
use Nook\Services\ServiceRegistry;
use Nook\Services\ServiceState;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ServicesController
 */
class ServicesController {

	/**
	 * Namespace REST du plugin.
	 *
	 * @var string
	 */
	const NAMESPACE_V1 = 'nook/v1';

	/**
	 * Catégories de consentement valides (voir Nook\Consent\CategoryRegistry).
	 *
	 * @var string[]
	 */
	const VALID_CATEGORIES = array( 'necessary', 'functional', 'statistics', 'marketing' );

	/**
	 * Accroche l'enregistrement des routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistre les routes REST `/nook/v1/services` et
	 * `/nook/v1/services/:slug`.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/services',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_services' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/services/(?P<slug>[a-z0-9\-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_service' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'slug' => array(
						'validate_callback' => function ( $value ) {
							return is_string( $value ) && '' !== $value;
						},
					),
				),
			)
		);
	}

	/**
	 * Vérifie que l'utilisateur courant peut lire/modifier les services.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'nook_forbidden',
				__( 'Vous n’avez pas la permission d’accéder aux services Nook.', 'nook' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Retourne la liste fusionnée des services : définitions natives du
	 * ServiceRegistry, informations de détection du dernier scan et
	 * corrections manuelles de l'administrateur (voir ARCHITECTURE.md
	 * « Provenance de la détection »).
	 *
	 * @return WP_REST_Response
	 */
	public function get_services() {
		$settings       = Settings::get();
		$overrides      = $settings['services'];
		$scan_results   = ScanResults::get();
		$detected_index = array();

		foreach ( $scan_results['services'] as $detected ) {
			$detected_index[ $detected['slug'] ] = $detected;
		}

		$services = array();

		foreach ( ServiceRegistry::get_services() as $definition ) {
			$slug     = $definition->get_slug();
			$override = isset( $overrides[ $slug ] ) && is_array( $overrides[ $slug ] ) ? $overrides[ $slug ] : array();
			$detected = isset( $detected_index[ $slug ] ) ? $detected_index[ $slug ] : null;

			$services[] = $this->build_service_payload( $definition, $override, $detected );
		}

		// Services ajoutés manuellement (n'existent pas dans le
		// ServiceRegistry natif — voir PROJECT.md §56).
		foreach ( $overrides as $slug => $override ) {
			if ( ! empty( $override['isCustom'] ) ) {
				$services[] = $this->build_custom_service_payload( $slug, $override );
			}
		}

		return new WP_REST_Response( $services );
	}

	/**
	 * Met à jour la correction manuelle d'un service (activation,
	 * catégorie, confirmation) — voir PROJECT.md §26/§56.
	 *
	 * @param WP_REST_Request $request Requête REST entrante.
	 * @return WP_REST_Response
	 */
	public function update_service( WP_REST_Request $request ) {
		$slug = sanitize_key( $request->get_param( 'slug' ) );
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$override = array();

		if ( array_key_exists( 'enabled', $body ) ) {
			$override['enabled'] = (bool) $body['enabled'];
		}

		if ( array_key_exists( 'confirmed', $body ) ) {
			$override['confirmed'] = (bool) $body['confirmed'];
		}

		if ( isset( $body['category'] ) && in_array( $body['category'], self::VALID_CATEGORIES, true ) ) {
			$override['category'] = $body['category'];
		}

		$is_known_service = null !== ServiceRegistry::get_service( $slug );

		if ( ! $is_known_service ) {
			// Un service inconnu du registre ne peut être créé que comme
			// service personnalisé explicite (voir PROJECT.md §56) : on
			// exige au minimum un nom pour éviter de stocker une entrée
			// muette.
			if ( isset( $body['name'] ) && '' !== trim( (string) $body['name'] ) ) {
				$override['isCustom']   = true;
				$override['name']       = sanitize_text_field( (string) $body['name'] );
				$override['domains']    = $this->sanitize_string_list( isset( $body['domains'] ) ? $body['domains'] : array() );
				$override['cookiePatterns'] = $this->sanitize_string_list( isset( $body['cookiePatterns'] ) ? $body['cookiePatterns'] : array() );
				$override['scriptPatterns'] = $this->sanitize_string_list( isset( $body['scriptPatterns'] ) ? $body['scriptPatterns'] : array() );
				$override['iframePatterns'] = $this->sanitize_string_list( isset( $body['iframePatterns'] ) ? $body['iframePatterns'] : array() );
				$override['description']    = isset( $body['description'] ) ? sanitize_text_field( (string) $body['description'] ) : '';
			} elseif ( empty( $override ) ) {
				return new WP_REST_Response(
					array( 'message' => __( 'Service inconnu.', 'nook' ) ),
					404
				);
			}
		}

		$settings = Settings::update(
			array(
				'services' => array( $slug => $override ),
			)
		);

		$definition = ServiceRegistry::get_service( $slug );
		$stored     = isset( $settings['services'][ $slug ] ) ? $settings['services'][ $slug ] : array();

		if ( $definition ) {
			return new WP_REST_Response( $this->build_service_payload( $definition, $stored, null ) );
		}

		return new WP_REST_Response( $this->build_custom_service_payload( $slug, $stored ) );
	}

	/**
	 * Construit la charge utile REST d'un service natif.
	 *
	 * @param \Nook\Services\ServiceDefinition $definition Définition native.
	 * @param array                            $override   Correction manuelle stockée.
	 * @param array|null                       $detected   Résultat de détection du dernier scan.
	 * @return array
	 */
	private function build_service_payload( $definition, array $override, $detected ) {
		$state      = ServiceState::compute( $definition, $override, $detected );
		$provenance = null !== $detected ? $detected['provenance'] : array();

		return array(
			'slug'            => $definition->get_slug(),
			'name'            => $definition->get_name(),
			'description'     => $definition->get_description(),
			'domains'         => $definition->get_domains(),
			'privacyUrl'      => $definition->get_privacy_url(),
			'defaultCategory' => $definition->get_category(),
			'category'        => $state['category'],
			'detected'        => $state['detected'],
			'provenance'      => $provenance,
			'enabled'         => $state['enabled'],
			'status'          => $state['status'],
			'isCustom'        => false,
		);
	}

	/**
	 * Construit la charge utile REST d'un service ajouté manuellement.
	 *
	 * @param string $slug     Identifiant du service.
	 * @param array  $override Données stockées.
	 * @return array
	 */
	private function build_custom_service_payload( $slug, array $override ) {
		$state = ServiceState::compute_custom( $override );

		return array(
			'slug'            => $slug,
			'name'            => isset( $override['name'] ) ? $override['name'] : $slug,
			'description'     => isset( $override['description'] ) ? $override['description'] : '',
			'domains'         => isset( $override['domains'] ) ? $override['domains'] : array(),
			'privacyUrl'      => null,
			'defaultCategory' => $state['category'],
			'category'        => $state['category'],
			'detected'        => false,
			'provenance'      => array( array( 'type' => 'manual', 'value' => __( 'Ajouté manuellement', 'nook' ) ) ),
			'enabled'         => $state['enabled'],
			'status'          => $state['status'],
			'isCustom'        => true,
		);
	}

	/**
	 * Nettoie une liste de chaînes envoyée par le client (domaines,
	 * motifs...). Ne fait jamais confiance aux données du navigateur (voir
	 * AGENTS.md).
	 *
	 * @param mixed $list Valeur brute reçue.
	 * @return string[]
	 */
	private function sanitize_string_list( $list ) {
		if ( ! is_array( $list ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $list as $item ) {
			$item = sanitize_text_field( (string) $item );

			if ( '' !== $item ) {
				$sanitized[] = $item;
			}
		}

		return array_values( array_unique( $sanitized ) );
	}
}
