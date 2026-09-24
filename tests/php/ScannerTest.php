<?php
/**
 * Tests de Nook\Scanner\Scanner.
 *
 * @package Nook
 */

use Nook\Scanner\Scanner;

nook_test(
	'Scanner::scan() détecte des services et cookies dans une page HTML simulée',
	function () {
		$html = '<html><head>'
			. '<script src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>'
			. '<script>gtag("config", "G-XXXX");</script>'
			. '</head><body>'
			. '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'
			. '</body></html>';

		$GLOBALS['__nook_test_http_responses']['https://example.test/'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => $html,
			'cookies'  => array(
				new WP_Http_Cookie( array( 'name' => '_ga' ) ),
				new WP_Http_Cookie( array( 'name' => 'totally_unknown_cookie_xyz' ) ),
			),
		);

		$scanner = new Scanner();
		$results = $scanner->scan( array( 'https://example.test/' ) );

		nook_assert_equals( 1, $results['pagesAnalyzed'] );
		nook_assert( in_array( 'https://www.googletagmanager.com/gtag/js?id=G-XXXX', $results['scripts'], true ), 'le script GTM doit être détecté' );
		nook_assert( in_array( 'https://www.youtube.com/embed/dQw4w9WgXcQ', $results['iframes'], true ), 'l’iframe YouTube doit être détectée' );

		$services = array();
		foreach ( $results['services'] as $service ) {
			$services[ $service['slug'] ] = $service;
		}

		nook_assert( true === $services['google-analytics']['detected'], 'Google Analytics doit être détecté (script gtag)' );
		nook_assert( true === $services['youtube']['detected'], 'YouTube doit être détecté (iframe embed)' );
		nook_assert( false === $services['meta-pixel']['detected'], 'Meta Pixel ne doit pas être détecté ici' );

		$cookies = array();
		foreach ( $results['cookies'] as $cookie ) {
			$cookies[ $cookie['name'] ] = $cookie;
		}

		nook_assert_equals( 'recognized', $cookies['_ga']['status'] );
		nook_assert_equals( 'statistics', $cookies['_ga']['category'] );
		nook_assert_equals( 'to_review', $cookies['totally_unknown_cookie_xyz']['status'] );
	}
);

nook_test(
	'Scanner::scan() consigne une erreur exploitable pour une URL injoignable',
	function () {
		$scanner = new Scanner();
		$results = $scanner->scan( array( 'https://example.test/url-sans-fixture' ) );

		nook_assert_equals( 0, $results['pagesAnalyzed'] );
		nook_assert_equals( 1, count( $results['errors'] ) );
		nook_assert_equals( 'https://example.test/url-sans-fixture', $results['errors'][0]['url'] );
	}
);

nook_test(
	'Scanner::scan() consigne une erreur pour une réponse HTTP en échec',
	function () {
		$GLOBALS['__nook_test_http_responses']['https://example.test/500'] = array(
			'response' => array( 'code' => 500 ),
			'body'     => 'Internal Server Error',
			'cookies'  => array(),
		);

		$scanner = new Scanner();
		$results = $scanner->scan( array( 'https://example.test/500' ) );

		nook_assert_equals( 0, $results['pagesAnalyzed'] );
		nook_assert_equals( 1, count( $results['errors'] ) );
	}
);

nook_test(
	'Scanner::scan() plafonne le nombre d’URLs traitées à MAX_URLS',
	function () {
		$urls = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$urls[] = 'https://example.test/page-' . $i;
		}

		$scanner = new Scanner();
		$results = $scanner->scan( $urls );

		nook_assert_equals( 20, count( $results['urls'] ), 'le scanner ne doit jamais traiter plus de 20 URLs (Scanner::MAX_URLS)' );
	}
);
