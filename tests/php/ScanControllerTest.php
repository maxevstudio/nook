<?php
/**
 * Tests de Nook\REST\ScanController.
 *
 * @package Nook
 */

use Nook\Database\Settings;
use Nook\REST\ScanController;

nook_test(
	'start_scan() lance une analyse et met à jour settings.scanner',
	function () {
		$GLOBALS['__nook_test_http_responses']['https://example.test/'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<html></html>',
			'cookies'  => array(),
		);

		$controller = new ScanController();
		$response   = $controller->start_scan();
		$data       = $response->get_data();

		nook_assert_equals( 'done', $data['status'] );
		nook_assert( isset( $data['summary'] ), 'un résumé doit être retourné' );
		nook_assert( ! empty( $data['scanner']['lastScanAt'] ), 'la réponse doit inclure scanner.lastScanAt (affichage du résumé dans l’admin)' );

		$settings = Settings::get();
		nook_assert_equals( 'done', $settings['scanner']['status'] );
		nook_assert( ! empty( $settings['scanner']['lastScanAt'] ), 'lastScanAt doit être renseigné' );
	}
);

nook_test(
	'start_scan() refuse un second scan concurrent (verrou)',
	function () {
		set_transient( ScanController::LOCK_KEY, true, 60 );

		$controller = new ScanController();
		$response   = $controller->start_scan();

		nook_assert_equals( 409, $response->status );
		nook_assert_equals( 'running', $response->get_data()['status'] );
	}
);

nook_test(
	'get_status() reflète le dernier statut connu',
	function () {
		Settings::update( array( 'scanner' => array( 'status' => 'done' ) ) );

		$controller = new ScanController();
		$response   = $controller->get_status();

		nook_assert_equals( 'done', $response->get_data()['scanner']['status'] );
	}
);
