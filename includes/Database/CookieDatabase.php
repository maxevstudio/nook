<?php
/**
 * Service de lookup de la base de connaissance locale des cookies
 * (Open Cookie Database, voir ARCHITECTURE.md §5 et data/cookies.json).
 *
 * Algorithme de matching (voir ARCHITECTURE.md « Normalisation des
 * cookies ») :
 *
 * 1. Correspondance exacte (insensible à la casse) — priorité la plus haute.
 * 2. Motif « glob » explicite (ex. `DSP2F_*`) — rare dans la base (quelques
 *    entrées seulement), traité comme une expression régulière ancrée.
 * 3. Préfixe (`wildcard: true` dans la base, ex. `_ga_` matchant `_ga_ABCD123`)
 *    — seulement si le préfixe fait au moins {@see self::MIN_PREFIX_LENGTH}
 *    caractères, pour éviter les faux positifs d'un préfixe trop court
 *    (ex. `_s`, `CT`) qui matcherait n'importe quel cookie par coïncidence.
 *
 * La base est chargée paresseusement et une seule fois par requête (voir
 * PROJECT.md §17 : « éviter de charger inutilement toute la base plusieurs
 * fois »).
 *
 * @package Nook
 */

namespace Nook\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CookieDatabase
 */
class CookieDatabase {

	/**
	 * Longueur minimale d'un préfixe pour être utilisé en correspondance
	 * automatique. En-dessous, le risque de faux positif est jugé trop
	 * élevé (voir ARCHITECTURE.md).
	 *
	 * @var int
	 */
	const MIN_PREFIX_LENGTH = 4;

	/**
	 * Entrées brutes chargées depuis data/cookies.json.
	 *
	 * @var array|null
	 */
	private static $entries = null;

	/**
	 * Index des correspondances exactes, clé = nom de cookie en minuscules.
	 *
	 * @var array<string, array[]>|null
	 */
	private static $exact_index = null;

	/**
	 * Préfixes (`wildcard: true`) triés du plus long au plus court.
	 *
	 * @var array[]|null
	 */
	private static $prefixes = null;

	/**
	 * Motifs « glob » explicites (présence d'un `*` littéral dans le nom).
	 *
	 * @var array[]|null
	 */
	private static $globs = null;

	/**
	 * Charge et indexe la base une seule fois par requête PHP.
	 *
	 * @return void
	 */
	private static function load() {
		if ( null !== self::$entries ) {
			return;
		}

		self::$entries     = array();
		self::$exact_index = array();
		self::$prefixes    = array();
		self::$globs       = array();

		$file = NOOK_PLUGIN_DIR . 'data/cookies.json';

		if ( ! file_exists( $file ) ) {
			return;
		}

		$raw  = file_get_contents( $file );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || empty( $data['entries'] ) || ! is_array( $data['entries'] ) ) {
			return;
		}

		self::$entries = $data['entries'];

		foreach ( self::$entries as $entry ) {
			$name = isset( $entry['cookie'] ) ? (string) $entry['cookie'] : '';

			if ( '' === $name ) {
				continue;
			}

			if ( false !== strpos( $name, '*' ) ) {
				self::$globs[] = array(
					'pattern' => '/^' . str_replace( '\*', '.*', preg_quote( $name, '/' ) ) . '$/i',
					'entry'   => $entry,
				);
				continue;
			}

			if ( ! empty( $entry['wildcard'] ) ) {
				self::$prefixes[] = array(
					'prefix' => strtolower( $name ),
					'entry'  => $entry,
				);
				continue;
			}

			$key = strtolower( $name );

			if ( ! isset( self::$exact_index[ $key ] ) ) {
				self::$exact_index[ $key ] = array();
			}

			self::$exact_index[ $key ][] = $entry;
		}

		// Le préfixe le plus long (donc le plus spécifique) doit gagner.
		usort(
			self::$prefixes,
			function ( $a, $b ) {
				return strlen( $b['prefix'] ) - strlen( $a['prefix'] );
			}
		);
	}

	/**
	 * Recherche un cookie par son nom exact (tel qu'observé sur le site).
	 *
	 * @param string $cookie_name Nom du cookie observé.
	 * @return array|null Entrée normalisée ou null si aucune correspondance
	 *                     suffisamment fiable n'est trouvée.
	 */
	public static function lookup( $cookie_name ) {
		$match = self::match( $cookie_name );

		return $match ? $match['entry'] : null;
	}

	/**
	 * Recherche un cookie et indique le niveau de confiance de la
	 * correspondance trouvée (voir ARCHITECTURE.md « Statut des
	 * résultats ») :
	 *
	 * - `exact` : correspondance exacte ou motif glob explicite de la base.
	 * - `suggested` : correspondance par préfixe (nom dynamique, ex.
	 *   `_ga_ABCD123`) — fiable mais pas une correspondance littérale.
	 *
	 * @param string $cookie_name Nom du cookie observé.
	 * @return array{entry: array, confidence: string}|null
	 */
	public static function match( $cookie_name ) {
		self::load();

		$cookie_name = trim( (string) $cookie_name );

		if ( '' === $cookie_name ) {
			return null;
		}

		$lower = strtolower( $cookie_name );

		if ( isset( self::$exact_index[ $lower ] ) ) {
			return array(
				'entry'      => self::$exact_index[ $lower ][0],
				'confidence' => 'exact',
			);
		}

		foreach ( self::$globs as $glob ) {
			if ( preg_match( $glob['pattern'], $cookie_name ) ) {
				return array(
					'entry'      => $glob['entry'],
					'confidence' => 'exact',
				);
			}
		}

		foreach ( self::$prefixes as $prefix ) {
			if ( strlen( $prefix['prefix'] ) < self::MIN_PREFIX_LENGTH ) {
				continue;
			}

			if ( 0 === strpos( $lower, $prefix['prefix'] ) ) {
				return array(
					'entry'      => $prefix['entry'],
					'confidence' => 'suggested',
				);
			}
		}

		return null;
	}

	/**
	 * Nombre total d'entrées chargées (utile pour les diagnostics/debug).
	 *
	 * @return int
	 */
	public static function count() {
		self::load();

		return count( self::$entries );
	}

	/**
	 * Retourne toutes les entrées associées à un service donné (nom exact,
	 * insensible à la casse). Utile pour l'UI cookies (voir PROJECT.md §20).
	 *
	 * @param string $service Nom du service (ex. « Google Analytics »).
	 * @return array[]
	 */
	public static function get_by_service( $service ) {
		self::load();

		$service = strtolower( trim( (string) $service ) );

		if ( '' === $service ) {
			return array();
		}

		return array_values(
			array_filter(
				self::$entries,
				function ( $entry ) use ( $service ) {
					return isset( $entry['service'] ) && strtolower( $entry['service'] ) === $service;
				}
			)
		);
	}
}
