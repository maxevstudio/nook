<?php
/**
 * Tests de Nook\Database\Settings.
 *
 * @package Nook
 */

use Nook\Database\Settings;

nook_test(
	'Settings::get_defaults() retourne un schéma complet avec schemaVersion courant',
	function () {
		$defaults = Settings::get_defaults();

		nook_assert_equals( NOOK_DB_VERSION, $defaults['schemaVersion'] );
		nook_assert( false === $defaults['setupCompleted'], 'setupCompleted doit être false par défaut' );
		nook_assert( isset( $defaults['consent']['categories']['necessary'] ), 'catégorie necessary manquante' );
		nook_assert_equals( 'FR', $defaults['site']['country'] );
		nook_assert_equals( 'business', $defaults['site']['type'] );
	}
);

nook_test(
	'Settings::get() complète les clés manquantes avec les valeurs par défaut',
	function () {
		update_option( Settings::OPTION_KEY, array( 'site' => array( 'country' => 'BE' ) ) );

		$settings = Settings::get();

		nook_assert_equals( 'BE', $settings['site']['country'] );
		nook_assert_equals( 'fr', $settings['site']['language'] );
	}
);

nook_test(
	'Settings::update() fusionne en profondeur sans écraser les branches non concernées',
	function () {
		Settings::update( array( 'site' => array( 'country' => 'FR' ) ) );
		Settings::update( array( 'banner' => array( 'title' => 'Titre perso' ) ) );

		$settings = Settings::get();

		nook_assert_equals( 'FR', $settings['site']['country'] );
		nook_assert_equals( 'Titre perso', $settings['banner']['title'] );
		nook_assert_equals( '#ffffff', $settings['banner']['backgroundColor'] );
	}
);

nook_test(
	'Settings::merge() remplace entièrement une liste plutôt que de la fusionner par index',
	function () {
		$base     = array( 'wizard' => array( 'completedSteps' => array( 'site', 'analyse', 'services' ) ) );
		$override = array( 'wizard' => array( 'completedSteps' => array( 'site' ) ) );

		$merged = Settings::merge( $base, $override );

		nook_assert_equals( array( 'site' ), $merged['wizard']['completedSteps'] );
	}
);

nook_test(
	'Settings::merge() fusionne les tableaux associatifs clé par clé',
	function () {
		$base     = array( 'banner' => array( 'title' => 'A', 'text' => 'B' ) );
		$override = array( 'banner' => array( 'title' => 'C' ) );

		$merged = Settings::merge( $base, $override );

		nook_assert_equals( 'C', $merged['banner']['title'] );
		nook_assert_equals( 'B', $merged['banner']['text'] );
	}
);

nook_test(
	'Settings::update() force schemaVersion à NOOK_DB_VERSION',
	function () {
		update_option( Settings::OPTION_KEY, array( 'schemaVersion' => 0 ) );

		$updated = Settings::update( array() );

		nook_assert_equals( NOOK_DB_VERSION, $updated['schemaVersion'] );
	}
);

nook_test(
	'Settings::reset() supprime l’option et retourne les valeurs par défaut',
	function () {
		Settings::update( array( 'setupCompleted' => true ) );

		$reset = Settings::reset();

		nook_assert( false === $reset['setupCompleted'], 'setupCompleted doit revenir à false' );
		nook_assert( false === get_option( Settings::OPTION_KEY, false ), 'l’option doit être supprimée' );
	}
);
