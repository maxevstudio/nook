<?php
/**
 * Contrôleur REST du scanner Nook.
 *
 * @package Nook
 */

namespace Nook\REST;

use Nook\Database\ScanResults;
use Nook\Database\Settings;
use Nook\Scanner\Scanner;
use Nook\Scanner\UrlDiscovery;
use WP_Error;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ScanController
 */
class ScanController {

	/**
	 * Namespace REST du plugin.
	 *
	 * @var string
	 */
	const NAMESPACE_V1 = 'nook/v1';

	/**
	 * Clé du verrou empêchant deux analyses simultanées (voir PROJECT.md
	 * §14/§101).
	 *
	 * @var string
	 */
	const LOCK_KEY = 'nook_scan_lock';

	/**
	 * Durée maximale du verrou, en secondes. Sert de garde-fou si un scan
	 * précédent s'est arrêté anormalement (crash PHP, timeout serveur) sans
	 * libérer le verrou.
	 *
	 * @var int
	 */
	const LOCK_TTL = 180;

	/**
	 * Accroche l'enregistrement des routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistre les routes REST `/nook/v1/scan` et `/nook/v1/scan/status`.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/scan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'start_scan' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/scan/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Vérifie que l'utilisateur courant peut lancer/consulter une analyse.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'nook_forbidden',
				__( 'Vous n’avez pas la permission de lancer une analyse Nook.', 'nook' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Démarre une analyse synchrone (l'analyse porte sur un nombre limité
	 * d'URLs avec un timeout court par requête, voir Scanner::MAX_URLS et
	 * Scanner::REQUEST_TIMEOUT — elle reste donc rapide, typiquement
	 * quelques secondes).
	 *
	 * @return WP_REST_Response
	 */
	public function start_scan() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'running',
					'message' => __( 'Une analyse est déjà en cours. Veuillez patienter.', 'nook' ),
				),
				409
			);
		}

		set_transient( self::LOCK_KEY, true, self::LOCK_TTL );
		Settings::update( array( 'scanner' => array( 'status' => 'running' ) ) );

		try {
			$urls    = UrlDiscovery::discover();
			$scanner = new Scanner();
			$results = $scanner->scan( $urls );

			ScanResults::save( $results );

			$summary = $this->build_summary( $results );

			$settings = Settings::update(
				array(
					'scanner' => array(
						'status'     => 'done',
						'lastScanAt' => current_time( 'mysql', true ),
						'urls'       => $results['urls'],
						'summary'    => $summary,
					),
				)
			);

			// `scanner` complet renvoyé pour que l'admin mette à jour son
			// état (dont `lastScanAt`) sans recharger tous les réglages.
			return new WP_REST_Response(
				array(
					'status'  => 'done',
					'summary' => $summary,
					'scanner' => $settings['scanner'],
					'results' => $results,
				)
			);
		} catch ( \Throwable $e ) {
			Settings::update( array( 'scanner' => array( 'status' => 'error' ) ) );

			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => __( 'L’analyse a échoué de façon inattendue.', 'nook' ),
				),
				500
			);
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Retourne l'état courant du scanner (dernier statut connu, sans
	 * relancer d'analyse) — voir ARCHITECTURE.md §4.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status() {
		$settings = Settings::get();

		return new WP_REST_Response(
			array(
				'scanner' => $settings['scanner'],
				'results' => ScanResults::get(),
			)
		);
	}

	/**
	 * Construit le résumé (compteurs) affiché à l'issue d'une analyse (voir
	 * PROJECT.md §11).
	 *
	 * @param array $results Résultats bruts de Scanner::scan().
	 * @return array{cookies: int, services: int, toReview: int}
	 */
	private function build_summary( array $results ) {
		$to_review = 0;

		foreach ( $results['cookies'] as $cookie ) {
			if ( 'to_review' === $cookie['status'] ) {
				++$to_review;
			}
		}

		$detected_services = 0;

		foreach ( $results['services'] as $service ) {
			if ( ! empty( $service['detected'] ) ) {
				++$detected_services;
			}
		}

		return array(
			'cookies'  => count( $results['cookies'] ),
			'services' => $detected_services,
			'toReview' => $to_review,
		);
	}
}
