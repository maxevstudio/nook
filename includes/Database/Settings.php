<?php
/**
 * Accès au modèle de configuration Nook stocké dans `wp_options`.
 *
 * Le schéma complet (voir ARCHITECTURE.md « Modèle de configuration ») est :
 *
 * ```
 * schemaVersion   int
 * setupCompleted  bool
 * site            { country, language, type }
 * consent         { configVersion, duration, categories }
 * banner          { title, text, buttons, position, colors, ... }
 * policy          { mode, pageId, title }
 * scanner         { status, lastScanAt, urls, summary, warnings }
 * services        { <slug>: {...} }  (rempli par le Milestone C)
 * cookies         { <id>: {...} }    (rempli par le Milestone B)
 * wizard          { currentStep, completedSteps }
 * ```
 *
 * @package Nook
 */

namespace Nook\Database;

use Nook\Consent\CategoryRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Clé de l'option WordPress.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'nook_settings';

	/**
	 * Valeurs par défaut du schéma de configuration courant.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'schemaVersion'  => NOOK_DB_VERSION,
			'setupCompleted' => false,
			'site'           => array(
				'country'  => 'FR',
				'language' => 'fr',
				'type'     => 'business',
			),
			'consent'        => array(
				'configVersion' => 1,
				'duration'      => 180,
				'categories'    => CategoryRegistry::get_categories(),
			),
			'banner'         => array(
				'title'           => __( 'Votre vie privée', 'nook' ),
				'text'            => __( 'Nous utilisons des cookies et services similaires pour assurer le fonctionnement du site, mesurer son audience et, avec votre accord, activer certains services externes.', 'nook' ),
				'buttons'         => array(
					'acceptAll' => __( 'Tout accepter', 'nook' ),
					'rejectAll' => __( 'Tout refuser', 'nook' ),
					'customize' => __( 'Personnaliser', 'nook' ),
				),
				'position'        => 'bottom',
				'backgroundColor' => '#ffffff',
				'textColor'       => '#1e1e1e',
				'accentColor'     => '#3858e9',
				'buttonStyle'     => 'solid',
				'radius'          => 12,
				'width'           => 'compact',
				'overlay'         => false,
				'floatingButton'  => true,
				'footerLink'      => false,
			),
			'policy'         => array(
				'mode'   => 'create',
				'pageId' => null,
				'title'  => __( 'Politique de cookies', 'nook' ),
			),
			'scanner'        => array(
				'status'     => 'idle',
				'lastScanAt' => null,
				'urls'       => array(),
				'summary'    => array(
					'cookies'  => 0,
					'services' => 0,
					'toReview' => 0,
				),
				'warnings'   => array(),
			),
			'services'       => array(),
			'cookies'        => array(),
			'wizard'         => array(
				'currentStep'    => 'site',
				'completedSteps' => array(),
			),
			'debug'          => array(
				'enabled' => false,
			),
		);
	}

	/**
	 * Récupère les réglages courants, migrés puis fusionnés avec les
	 * valeurs par défaut (pour compléter toute clé manquante).
	 *
	 * @return array
	 */
	public static function get() {
		return self::merge( self::get_defaults(), self::get_migrated_stored() );
	}

	/**
	 * Met à jour les réglages (fusion avec les valeurs existantes).
	 *
	 * @param array $data Réglages partiels à sauvegarder.
	 * @return array Réglages complets après sauvegarde.
	 */
	public static function update( array $data ) {
		$current = self::merge( self::get_defaults(), self::get_migrated_stored() );

		$updated                  = self::merge( $current, $data );
		$updated['schemaVersion'] = NOOK_DB_VERSION;

		update_option( self::OPTION_KEY, $updated );

		return $updated;
	}

	/**
	 * Réinitialise entièrement la configuration (action destructive,
	 * réservée à la page Paramètres — voir PROJECT.md §57).
	 *
	 * @return array Réglages par défaut après réinitialisation.
	 */
	public static function reset() {
		delete_option( self::OPTION_KEY );

		return self::get_defaults();
	}

	/**
	 * Charge les réglages bruts stockés et applique les migrations de
	 * schéma nécessaires (voir Migrations::apply()).
	 *
	 * @return array
	 */
	private static function get_migrated_stored() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return Migrations::apply( $stored );
	}

	/**
	 * Fusionne récursivement deux tableaux de réglages.
	 *
	 * Contrairement à `array_replace_recursive()`, une liste séquentielle
	 * (ex. `wizard.completedSteps`, `scanner.urls`) est **intégralement
	 * remplacée** par la nouvelle valeur plutôt que fusionnée index par
	 * index : sinon, une liste plus courte que l'ancienne ne pourrait
	 * jamais réellement raccourcir la valeur stockée. Les tableaux
	 * associatifs (objets) continuent d'être fusionnés clé par clé.
	 *
	 * @param array $base     Tableau de base.
	 * @param array $override Tableau de valeurs à appliquer par-dessus.
	 * @return array
	 */
	public static function merge( array $base, array $override ) {
		if ( self::is_list( $override ) ) {
			return $override;
		}

		$merged = $base;

		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = self::merge( $merged[ $key ], $value );
			} else {
				$merged[ $key ] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Détermine si un tableau est une liste séquentielle (indices 0..n-1)
	 * plutôt qu'un tableau associatif. Équivalent maison d'`array_is_list()`
	 * (PHP 8.1+) car Nook supporte PHP 7.4+.
	 *
	 * @param array $array Tableau à tester.
	 * @return bool
	 */
	private static function is_list( array $array ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $array );
		}

		if ( array() === $array ) {
			return true;
		}

		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}
}
