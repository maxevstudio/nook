<?php
/**
 * Tests de Nook\Database\CookieDatabase (Open Cookie Database locale).
 *
 * @package Nook
 */

use Nook\Database\CookieDatabase;

nook_test(
	'CookieDatabase::lookup() trouve une correspondance exacte connue',
	function () {
		$entry = CookieDatabase::lookup( '_ga' );

		nook_assert( null !== $entry, '_ga doit être connu de la base' );
		nook_assert_equals( 'statistics', $entry['category'] );
		nook_assert_equals( 'Google Analytics', $entry['service'] );
	}
);

nook_test(
	'CookieDatabase::lookup() est insensible à la casse',
	function () {
		$entry = CookieDatabase::lookup( 'XSRF-TOKEN' );
		nook_assert( null !== $entry, 'XSRF-TOKEN doit être connu' );
		nook_assert_equals( 'necessary', $entry['category'] );

		$entry_lower = CookieDatabase::lookup( 'xsrf-token' );
		nook_assert( null !== $entry_lower, 'la recherche doit être insensible à la casse' );
	}
);

nook_test(
	'CookieDatabase::lookup() retrouve un cookie dynamique via un préfixe suffisamment long',
	function () {
		// `_ga_` est un préfixe connu (wildcard) de Google Analytics (GA4),
		// utilisé par exemple pour `_ga_ABCD123XYZ`.
		$entry = CookieDatabase::lookup( '_ga_ABCD123XYZ' );

		nook_assert( null !== $entry, 'un cookie _ga_* doit être reconnu via le préfixe _ga_' );
		nook_assert_equals( 'Google Analytics', $entry['service'] );
	}
);

nook_test(
	'CookieDatabase::lookup() retourne null pour un cookie totalement inconnu',
	function () {
		$entry = CookieDatabase::lookup( 'nook_test_cookie_totalement_inexistant_123456' );

		nook_assert( null === $entry, 'un cookie inconnu ne doit jamais être associé à une entrée par erreur' );
	}
);

nook_test(
	'CookieDatabase::lookup() retourne null pour une chaîne vide',
	function () {
		nook_assert( null === CookieDatabase::lookup( '' ), 'chaîne vide' );
		nook_assert( null === CookieDatabase::lookup( '   ' ), 'chaîne uniquement composée d’espaces' );
	}
);

nook_test(
	'CookieDatabase::count() reflète le nombre d’entrées de la base locale',
	function () {
		nook_assert( CookieDatabase::count() > 2000, 'la base locale doit contenir plusieurs milliers d’entrées (Open Cookie Database)' );
	}
);

nook_test(
	'CookieDatabase::get_by_service() retourne les cookies d’un service donné',
	function () {
		$entries = CookieDatabase::get_by_service( 'Google Analytics' );

		nook_assert( count( $entries ) > 0, 'Google Analytics doit avoir au moins un cookie connu' );

		foreach ( $entries as $entry ) {
			nook_assert_equals( 'Google Analytics', $entry['service'] );
		}
	}
);
