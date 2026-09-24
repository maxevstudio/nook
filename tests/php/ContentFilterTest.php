<?php
/**
 * Tests de Nook\Frontend\ContentFilter — fonctionnalité la plus critique de
 * Nook (voir PROJECT.md §45 : « Ne pas considérer la V1 comme terminée si
 * GA4 part avant consentement… »).
 *
 * @package Nook
 */

use Nook\Frontend\ContentFilter;
use Nook\REST\ServicesController;

/**
 * Blocklist de test reflétant fidèlement les signatures réelles de
 * Google Analytics, Google Tag Manager et YouTube (voir includes/Services/).
 *
 * @return array[]
 */
function nook_test_sample_blocklist() {
	return array(
		array(
			'slug'           => 'google-analytics',
			'category'       => 'statistics',
			'domains'        => array( 'google-analytics.com', 'analytics.google.com', 'googletagmanager.com' ),
			'scriptPatterns' => array( 'googletagmanager.com/gtag/js', 'google-analytics.com/analytics.js', 'gtag(' ),
			'iframePatterns' => array(),
		),
		array(
			'slug'           => 'google-tag-manager',
			'category'       => 'statistics',
			'domains'        => array( 'googletagmanager.com' ),
			'scriptPatterns' => array( 'googletagmanager.com/gtm.js', 'GTM-' ),
			'iframePatterns' => array(),
		),
		array(
			'slug'           => 'youtube',
			'category'       => 'marketing',
			'domains'        => array( 'youtube.com', 'youtube-nocookie.com', 'ytimg.com' ),
			'scriptPatterns' => array( 'youtube.com/iframe_api', 'youtube.com/s/player' ),
			'iframePatterns' => array( 'youtube.com/embed/', 'youtube-nocookie.com/embed/' ),
		),
	);
}

nook_test(
	'filter_output() neutralise un script externe GA4 (src renommé, type=text/plain)',
	function () {
		$filter = new ContentFilter();
		$filter->set_blocklist( nook_test_sample_blocklist() );

		$html   = '<script src="https://www.googletagmanager.com/gtag/js?id=G-XXXX" async></script>';
		$output = $filter->filter_output( $html );

		nook_assert( false !== strpos( $output, 'type="text/plain"' ), 'le type doit devenir text/plain' );
		nook_assert( false !== strpos( $output, 'data-nook-category="statistics"' ), 'catégorie statistics attendue' );
		nook_assert( false !== strpos( $output, 'data-nook-service="google-analytics"' ), 'service google-analytics attendu' );
		nook_assert( false !== strpos( $output, 'data-nook-src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"' ), 'src original conservé dans data-nook-src' );
		nook_assert( false === strpos( $output, ' src="https://www.googletagmanager.com' ), 'l’attribut src original doit être retiré pour empêcher tout chargement par le navigateur' );
		nook_assert( false !== strpos( $output, 'async' ), 'les autres attributs doivent être conservés' );
	}
);

nook_test(
	'filter_output() désambiguïse Google Analytics et Google Tag Manager sur un domaine partagé',
	function () {
		$filter = new ContentFilter();
		$filter->set_blocklist( nook_test_sample_blocklist() );

		$html   = '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-ABC123"></script>';
		$output = $filter->filter_output( $html );

		nook_assert( false !== strpos( $output, 'data-nook-service="google-tag-manager"' ), 'gtm.js doit être attribué à google-tag-manager, pas google-analytics' );
	}
);

nook_test(
	'filter_output() neutralise un script inline correspondant à une signature connue sans altérer son contenu',
	function () {
		$filter = new ContentFilter();
		$filter->set_blocklist( nook_test_sample_blocklist() );

		$html   = '<head><script>gtag("config", "G-XXXX");</script></head>';
		$output = $filter->filter_output( $html );

		nook_assert( false !== strpos( $output, 'type="text/plain"' ), 'le script inline doit être neutralisé' );
		nook_assert( false !== strpos( $output, 'data-nook-category="statistics"' ), 'catégorie statistics attendue' );
		nook_assert( false !== strpos( $output, 'gtag("config", "G-XXXX");' ), 'le contenu inline ne doit pas être altéré' );
	}
);

nook_test(
	'filter_output() remplace une iframe YouTube par un placeholder conservant l’URL et les dimensions',
	function () {
		$filter = new ContentFilter();
		$filter->set_blocklist( nook_test_sample_blocklist() );

		$html   = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315" title="Ma vidéo"></iframe>';
		$output = $filter->filter_output( $html );

		nook_assert( false !== strpos( $output, 'nook-placeholder' ), 'un placeholder doit remplacer l’iframe' );
		nook_assert( false !== strpos( $output, 'data-nook-category="marketing"' ), 'catégorie marketing attendue' );
		nook_assert( false !== strpos( $output, 'data-nook-service="youtube"' ), 'service youtube attendu' );
		nook_assert( false !== strpos( $output, 'data-nook-src="https://www.youtube.com/embed/dQw4w9WgXcQ"' ), 'URL d’origine conservée dans data-nook-src' );
		nook_assert( false !== strpos( $output, 'data-nook-width="560"' ), 'largeur conservée' );
		nook_assert( false === strpos( $output, '<iframe' ), 'l’iframe elle-même ne doit plus apparaître dans la sortie (pas seulement masquée en CSS)' );
	}
);

nook_test(
	'filter_output() ne modifie jamais un script ou une iframe sans rapport avec un service bloqué',
	function () {
		$filter = new ContentFilter();
		$filter->set_blocklist( nook_test_sample_blocklist() );

		$html = '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>'
			. '<iframe src="https://example.com/widget"></iframe>'
			. '<p>Hello</p>';

		nook_assert_equals( $html, $filter->filter_output( $html ) );
	}
);

nook_test(
	'filter_output() laisse la sortie intacte lorsque la blocklist est vide',
	function () {
		$filter = new ContentFilter();
		$filter->set_blocklist( array() );

		$html = '<script src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>';

		nook_assert_equals( $html, $filter->filter_output( $html ) );
	}
);

nook_test(
	'maybe_start_buffer() ne démarre aucun buffer si aucun service n’est à bloquer',
	function () {
		$filter        = new ContentFilter();
		$level_before  = ob_get_level();

		try {
			$filter->maybe_start_buffer();
			nook_assert_equals( $level_before, ob_get_level(), 'aucun buffer ne doit démarrer sans service à bloquer' );
		} finally {
			while ( ob_get_level() > $level_before ) {
				ob_end_clean();
			}
		}
	}
);

nook_test(
	'maybe_start_buffer() ignore les pages d’administration même avec un service activé',
	function () {
		$controller = new ServicesController();
		$controller->update_service(
			new WP_REST_Request(
				array(
					'enabled'   => true,
					'confirmed' => true,
				),
				array( 'slug' => 'google-analytics' )
			)
		);

		$GLOBALS['__nook_test_is_admin'] = true;

		$filter       = new ContentFilter();
		$level_before = ob_get_level();

		try {
			$filter->maybe_start_buffer();
			nook_assert_equals( $level_before, ob_get_level(), 'is_admin() doit empêcher tout traitement de la sortie' );
		} finally {
			while ( ob_get_level() > $level_before ) {
				ob_end_clean();
			}
		}
	}
);
