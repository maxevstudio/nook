<?php
/**
 * Tests de Nook\Database\Migrations.
 *
 * @package Nook
 */

use Nook\Database\Migrations;

nook_test(
	'Migrations::apply() attribue schemaVersion=1 à des réglages sans version',
	function () {
		$migrated = Migrations::apply( array( 'setupCompleted' => true ) );

		nook_assert_equals( 1, $migrated['schemaVersion'] );
		nook_assert( true === $migrated['setupCompleted'], 'les données existantes doivent être conservées' );
	}
);

nook_test(
	'Migrations::apply() est un no-op si schemaVersion est déjà à jour',
	function () {
		$input = array(
			'schemaVersion' => NOOK_DB_VERSION,
			'foo'           => 'bar',
		);

		$migrated = Migrations::apply( $input );

		nook_assert_equals( $input, $migrated );
	}
);
