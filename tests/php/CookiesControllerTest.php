<?php
/**
 * Tests de Nook\REST\CookiesController.
 *
 * @package Nook
 */

use Nook\Database\ScanResults;
use Nook\REST\CookiesController;

/**
 * Prépare des résultats de scan avec deux cookies connus.
 *
 * @return void
 */
function nook_test_seed_scan_cookies() {
	ScanResults::save(
		array(
			'pagesAnalyzed' => 1,
			'urls'          => array(),
			'scripts'       => array(),
			'iframes'       => array(),
			'cookies'       => array(
				array(
					'name'        => '_ga',
					'status'      => 'recognized',
					'category'    => 'statistics',
					'service'     => 'Google Analytics',
					'description' => 'Distingue les utilisateurs.',
					'retention'   => '2 years',
				),
				array(
					'name'        => 'mystere_cookie',
					'status'      => 'to_review',
					'category'    => null,
					'service'     => null,
					'description' => null,
					'retention'   => null,
				),
			),
			'services'      => array(),
			'errors'        => array(),
		)
	);
}

nook_test(
	'get_cookies() retourne les cookies détectés par le dernier scan',
	function () {
		nook_test_seed_scan_cookies();

		$controller = new CookiesController();
		$cookies    = $controller->get_cookies()->get_data();

		nook_assert_equals( 2, count( $cookies ) );

		$by_name = array();
		foreach ( $cookies as $cookie ) {
			$by_name[ $cookie['name'] ] = $cookie;
		}

		nook_assert_equals( 'recognized', $by_name['_ga']['status'] );
		nook_assert_equals( 'statistics', $by_name['_ga']['category'] );
		nook_assert_equals( 'scan', $by_name['_ga']['source'] );
		nook_assert_equals( 'to_review', $by_name['mystere_cookie']['status'] );
	}
);

nook_test(
	'update_cookie() applique une correction manuelle sans être écrasée par un nouveau scan',
	function () {
		nook_test_seed_scan_cookies();

		$controller = new CookiesController();
		$request    = new WP_REST_Request(
			array(
				'category' => 'functional',
				'status'   => 'ignored',
			),
			array( 'id' => 'mystere-cookie' )
		);

		$response = $controller->update_cookie( $request );
		$data     = $response->get_data();

		nook_assert_equals( 'functional', $data['category'] );
		nook_assert_equals( 'ignored', $data['status'] );
		nook_assert( true === $data['hasOverride'], 'hasOverride doit être vrai après une correction' );

		// Un nouveau scan redétecte le même cookie sans catégorie connue :
		// la correction manuelle doit être conservée.
		nook_test_seed_scan_cookies();

		$cookies = $controller->get_cookies()->get_data();
		$by_name = array();
		foreach ( $cookies as $cookie ) {
			$by_name[ $cookie['name'] ] = $cookie;
		}

		nook_assert_equals( 'functional', $by_name['mystere_cookie']['category'] );
		nook_assert_equals( 'ignored', $by_name['mystere_cookie']['status'] );
	}
);

nook_test(
	'update_cookie() crée une entrée manuelle pour un cookie inconnu du scan',
	function () {
		nook_test_seed_scan_cookies();

		$controller = new CookiesController();
		$request    = new WP_REST_Request(
			array(
				'name'     => 'mon_cookie_perso',
				'category' => 'marketing',
			),
			array( 'id' => 'mon-cookie-perso' )
		);

		$response = $controller->update_cookie( $request );
		$data     = $response->get_data();

		nook_assert_equals( 'mon_cookie_perso', $data['name'] );
		nook_assert_equals( 'marketing', $data['category'] );
		nook_assert_equals( 'manual', $data['source'] );

		$cookies = $controller->get_cookies()->get_data();
		nook_assert_equals( 3, count( $cookies ), 'le cookie manuel doit apparaître en plus des 2 cookies détectés' );
	}
);

nook_test(
	'update_cookie() rejette un identifiant inconnu sans nom fourni',
	function () {
		$controller = new CookiesController();
		$response   = $controller->update_cookie( new WP_REST_Request( array(), array( 'id' => 'inexistant' ) ) );

		nook_assert_equals( 404, $response->status );
	}
);
