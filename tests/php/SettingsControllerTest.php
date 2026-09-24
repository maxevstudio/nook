<?php
/**
 * Tests de Nook\REST\SettingsController.
 *
 * @package Nook
 */

use Nook\Database\Settings;
use Nook\REST\SettingsController;

nook_test(
	'check_permissions() refuse un utilisateur sans manage_options',
	function () {
		$GLOBALS['__nook_test_current_user_can'] = false;

		$controller = new SettingsController();
		$result     = $controller->check_permissions();

		nook_assert( $result instanceof WP_Error, 'un WP_Error est attendu' );
	}
);

nook_test(
	'check_permissions() autorise un administrateur',
	function () {
		$controller = new SettingsController();

		nook_assert( true === $controller->check_permissions(), 'un administrateur doit être autorisé' );
	}
);

nook_test(
	'update_settings() nettoie les clés sans les mettre en minuscule (bug historique sanitize_key)',
	function () {
		$controller = new SettingsController();
		$request    = new WP_REST_Request(
			array(
				'site'    => array( 'country' => 'FR' ),
				'consent' => array( 'configVersion' => 2 ),
			)
		);

		$response = $controller->update_settings( $request );
		$data     = $response->get_data();

		nook_assert_equals( 2, $data['consent']['configVersion'], 'la clé configVersion ne doit pas devenir configversion' );
	}
);

nook_test(
	'update_settings() rejette une valeur hors énumération pour site.type',
	function () {
		$controller = new SettingsController();
		$request    = new WP_REST_Request( array( 'site' => array( 'type' => 'not-a-real-type' ) ) );

		$controller->update_settings( $request );

		$settings = Settings::get();
		nook_assert_equals( 'business', $settings['site']['type'], 'la valeur invalide doit être ignorée, pas stockée' );
	}
);

nook_test(
	'update_settings() accepte une valeur valide pour site.type',
	function () {
		$controller = new SettingsController();
		$request    = new WP_REST_Request( array( 'site' => array( 'type' => 'ecommerce' ) ) );

		$controller->update_settings( $request );

		$settings = Settings::get();
		nook_assert_equals( 'ecommerce', $settings['site']['type'] );
	}
);

nook_test(
	'reset_settings() réinitialise et retourne les valeurs par défaut',
	function () {
		$controller = new SettingsController();
		Settings::update( array( 'setupCompleted' => true ) );

		$response = $controller->reset_settings();

		nook_assert( false === $response->get_data()['setupCompleted'], 'setupCompleted doit revenir à false après reset' );
	}
);
