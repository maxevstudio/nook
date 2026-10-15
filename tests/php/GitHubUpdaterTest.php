<?php
/**
 * Tests de Nook\Updater\GitHubUpdater.
 *
 * @package Nook
 */

use Nook\Updater\GitHubUpdater;

/**
 * Dépose une réponse simulée de l'API GitHub `releases/latest`.
 *
 * @param array $body Corps JSON de la release.
 * @param int   $code Code HTTP.
 * @return void
 */
function nook_test_github_release( $body, $code = 200 ) {
	$GLOBALS['__nook_test_http_responses']['https://api.github.com/repos/maxevstudio/nook/releases/latest'] = array(
		'response' => array( 'code' => $code ),
		'body'     => json_encode( $body ),
	);
}

/**
 * Transient `update_plugins` minimal tel que WordPress le fournit.
 *
 * @return object
 */
function nook_test_update_transient() {
	$transient           = new stdClass();
	$transient->checked  = array( NOOK_PLUGIN_BASENAME => NOOK_VERSION );
	$transient->response = array();
	return $transient;
}

nook_test(
	'GitHubUpdater signale une release plus récente avec son ZIP joint',
	function () {
		nook_test_github_release(
			array(
				'tag_name'    => 'v9.9.9',
				'html_url'    => 'https://github.com/maxevstudio/nook/releases/tag/v9.9.9',
				'zipball_url' => 'https://api.github.com/repos/maxevstudio/nook/zipball/v9.9.9',
				'assets'      => array(
					array( 'browser_download_url' => 'https://github.com/maxevstudio/nook/releases/download/v9.9.9/nook-9.9.9.zip' ),
				),
			)
		);

		$transient = ( new GitHubUpdater() )->inject_update( nook_test_update_transient() );
		$item      = $transient->response[ NOOK_PLUGIN_BASENAME ];

		nook_assert_equals( '9.9.9', $item->new_version );
		nook_assert_equals( 'https://github.com/maxevstudio/nook/releases/download/v9.9.9/nook-9.9.9.zip', $item->package );
	}
);

nook_test(
	'GitHubUpdater ignore une release sans ZIP joint (le zipball GitHub n’est pas installable)',
	function () {
		nook_test_github_release(
			array(
				'tag_name'    => 'v9.9.9',
				'zipball_url' => 'https://api.github.com/repos/maxevstudio/nook/zipball/v9.9.9',
				'assets'      => array(),
			)
		);

		$transient = ( new GitHubUpdater() )->inject_update( nook_test_update_transient() );

		nook_assert( empty( $transient->response ), 'aucune mise à jour ne doit être proposée' );
	}
);

nook_test(
	'GitHubUpdater ne propose rien si la release n’est pas plus récente',
	function () {
		nook_test_github_release(
			array(
				'tag_name' => 'v' . NOOK_VERSION,
				'assets'   => array(
					array( 'browser_download_url' => 'https://example.test/nook.zip' ),
				),
			)
		);

		$transient = ( new GitHubUpdater() )->inject_update( nook_test_update_transient() );

		nook_assert( empty( $transient->response ), 'aucune mise à jour ne doit être proposée' );
	}
);

nook_test(
	'GitHubUpdater met en cache un échec réseau sans planter',
	function () {
		$updater = new GitHubUpdater();

		nook_assert( null === $updater->get_latest_release(), 'un échec doit retourner null' );
		nook_assert( array() === get_transient( GitHubUpdater::CACHE_KEY ), 'l’échec doit être mis en cache' );

		// Même si GitHub répond ensuite, le cache d'échec est respecté.
		nook_test_github_release( array( 'tag_name' => 'v9.9.9' ) );
		nook_assert( null === $updater->get_latest_release(), 'le cache d’échec doit être respecté' );
	}
);
