<?php
/**
 * Accès aux réglages du plugin stockés dans wp_options.
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
	 * Valeurs par défaut des réglages.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'configured'           => false,
			'consentConfigVersion' => 1,
			'site'                 => array(
				'name'      => get_bloginfo( 'name' ),
				'countries' => array( 'FR', 'EU' ),
			),
			'categories'           => CategoryRegistry::get_categories(),
			'banner'               => array(
				'position'     => 'bottom',
				'backgroundColor' => '#ffffff',
				'textColor'    => '#1e1e1e',
				'accentColor'  => '#3858e9',
				'radius'       => 4,
			),
		);
	}

	/**
	 * Récupère les réglages courants, fusionnés avec les valeurs par défaut.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_replace_recursive( self::get_defaults(), $stored );
	}

	/**
	 * Met à jour les réglages (fusion avec les valeurs existantes).
	 *
	 * @param array $data Réglages partiels à sauvegarder.
	 * @return array Réglages complets après sauvegarde.
	 */
	public static function update( array $data ) {
		$current = self::get();
		$updated = array_replace_recursive( $current, $data );

		update_option( self::OPTION_KEY, $updated );

		return $updated;
	}
}
