<?php
/**
 * Stockage des résultats bruts du dernier scan (cookies/services détectés).
 *
 * Séparé de `nook_settings` pour ne pas alourdir l'option de configuration
 * avec des résultats potentiellement plus volumineux (voir PROJECT.md §65 —
 * éviter de transférer des volumes inutiles) et pour permettre une
 * réinitialisation indépendante (« réinitialiser les résultats du scanner »,
 * voir PROJECT.md §57).
 *
 * @package Nook
 */

namespace Nook\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ScanResults
 */
class ScanResults {

	/**
	 * Clé de l'option WordPress.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'nook_scan_results';

	/**
	 * Retourne les derniers résultats de scan bruts (cookies/services/
	 * scripts/iframes/erreurs), ou une structure vide si aucun scan n'a
	 * encore été effectué.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			return self::get_empty();
		}

		return array_merge( self::get_empty(), $stored );
	}

	/**
	 * Sauvegarde les résultats bruts d'un scan.
	 *
	 * @param array $results Résultats produits par Scanner::scan().
	 * @return array
	 */
	public static function save( array $results ) {
		update_option( self::OPTION_KEY, $results, false );

		return $results;
	}

	/**
	 * Réinitialise les résultats du scanner (sans toucher aux corrections
	 * manuelles conservées dans `nook_settings.cookies`/`services`).
	 *
	 * @return void
	 */
	public static function reset() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Structure vide par défaut.
	 *
	 * @return array
	 */
	private static function get_empty() {
		return array(
			'pagesAnalyzed' => 0,
			'urls'          => array(),
			'scripts'       => array(),
			'iframes'       => array(),
			'cookies'       => array(),
			'services'      => array(),
			'errors'        => array(),
		);
	}
}
