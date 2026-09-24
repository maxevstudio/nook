<?php
/**
 * Tests de Nook\REST\ServicesController.
 *
 * @package Nook
 */

use Nook\Database\ScanResults;
use Nook\Database\Settings;
use Nook\REST\ServicesController;

nook_test(
	'get_services() retourne les 7 services natifs, désactivés par défaut sans détection',
	function () {
		$controller = new ServicesController();
		$response   = $controller->get_services();
		$services   = $response->get_data();

		nook_assert_equals( 7, count( $services ) );

		$by_slug = array();
		foreach ( $services as $service ) {
			$by_slug[ $service['slug'] ] = $service;
		}

		nook_assert_equals( false, $by_slug['google-analytics']['detected'] );
		nook_assert_equals( false, $by_slug['google-analytics']['enabled'] );
		nook_assert_equals( 'disabled', $by_slug['google-analytics']['status'] );
	}
);

nook_test(
	'get_services() reflète la détection du dernier scan',
	function () {
		ScanResults::save(
			array(
				'pagesAnalyzed' => 1,
				'urls'          => array(),
				'scripts'       => array(),
				'iframes'       => array(),
				'cookies'       => array(),
				'services'      => array(
					array(
						'slug'        => 'google-analytics',
						'name'        => 'Google Analytics',
						'category'    => 'statistics',
						'description' => '',
						'detected'    => true,
						'provenance'  => array( array( 'type' => 'script', 'value' => 'gtag(' ) ),
					),
				),
				'errors'        => array(),
			)
		);

		$controller = new ServicesController();
		$response   = $controller->get_services();

		$by_slug = array();
		foreach ( $response->get_data() as $service ) {
			$by_slug[ $service['slug'] ] = $service;
		}

		nook_assert_equals( true, $by_slug['google-analytics']['detected'] );
		nook_assert_equals( true, $by_slug['google-analytics']['enabled'] );
		nook_assert_equals( 'detected', $by_slug['google-analytics']['status'] );
	}
);

nook_test(
	'update_service() confirme et recatégorise un service natif',
	function () {
		$controller = new ServicesController();
		$request    = new WP_REST_Request(
			array(
				'enabled'   => true,
				'confirmed' => true,
				'category'  => 'marketing',
			),
			array( 'slug' => 'google-analytics' )
		);

		$response = $controller->update_service( $request );
		$data     = $response->get_data();

		nook_assert_equals( 'marketing', $data['category'] );
		nook_assert_equals( 'confirmed', $data['status'] );

		$settings = Settings::get();
		nook_assert_equals( 'marketing', $settings['services']['google-analytics']['category'] );
	}
);

nook_test(
	'update_service() conserve une désactivation manuelle malgré une nouvelle détection',
	function () {
		$controller = new ServicesController();
		$controller->update_service( new WP_REST_Request( array( 'enabled' => false ), array( 'slug' => 'google-analytics' ) ) );

		ScanResults::save(
			array(
				'pagesAnalyzed' => 1,
				'urls'          => array(),
				'scripts'       => array(),
				'iframes'       => array(),
				'cookies'       => array(),
				'services'      => array(
					array(
						'slug'        => 'google-analytics',
						'name'        => 'GA',
						'category'    => 'statistics',
						'description' => '',
						'detected'    => true,
						'provenance'  => array(),
					),
				),
				'errors'        => array(),
			)
		);

		$response = $controller->get_services();
		$by_slug  = array();
		foreach ( $response->get_data() as $service ) {
			$by_slug[ $service['slug'] ] = $service;
		}

		nook_assert_equals( false, $by_slug['google-analytics']['enabled'], 'la désactivation manuelle doit être conservée malgré une nouvelle détection' );
		nook_assert_equals( 'disabled', $by_slug['google-analytics']['status'] );
	}
);

nook_test(
	'update_service() crée un service personnalisé pour un slug inconnu accompagné d’un nom',
	function () {
		$controller = new ServicesController();
		$request    = new WP_REST_Request(
			array(
				'name'    => 'Mon Service Perso',
				'category' => 'marketing',
				'domains' => array( 'example.com' ),
			),
			array( 'slug' => 'mon-service-perso' )
		);

		$response = $controller->update_service( $request );
		$data     = $response->get_data();

		nook_assert_equals( true, $data['isCustom'] );
		nook_assert_equals( 'Mon Service Perso', $data['name'] );
		nook_assert_equals( array( 'example.com' ), $data['domains'] );
	}
);

nook_test(
	'update_service() rejette un slug inconnu sans nom fourni',
	function () {
		$controller = new ServicesController();
		$response   = $controller->update_service( new WP_REST_Request( array(), array( 'slug' => 'inexistant' ) ) );

		nook_assert_equals( 404, $response->status );
	}
);
