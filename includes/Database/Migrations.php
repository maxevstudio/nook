<?php
/**
 * Migrations légères du schéma de configuration Nook.
 *
 * Voir ARCHITECTURE.md §3.5/§7 : chaque évolution du schéma de
 * `nook_settings` doit être accompagnée d'une migration ici, indexée par la
 * version de schéma qu'elle produit. Le mécanisme reste volontairement
 * simple (pas de moteur de migration générique) : une V1 n'a besoin que
 * d'un point d'ancrage propre pour la V2.
 *
 * @package Nook
 */

namespace Nook\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrations
 */
class Migrations {

	/**
	 * Applique séquentiellement les migrations nécessaires pour amener des
	 * réglages bruts (potentiellement anciens ou incomplets) au schéma
	 * courant (`NOOK_DB_VERSION`).
	 *
	 * @param array $settings Réglages bruts tels que stockés en base.
	 * @return array Réglages migrés, avec `schemaVersion` à jour.
	 */
	public static function apply( array $settings ) {
		$current_version = isset( $settings['schemaVersion'] ) ? (int) $settings['schemaVersion'] : 0;

		foreach ( self::steps() as $target_version => $migrate ) {
			if ( $current_version < $target_version ) {
				$settings                  = $migrate( $settings );
				$settings['schemaVersion'] = $target_version;
				$current_version           = $target_version;
			}
		}

		return $settings;
	}

	/**
	 * Liste des migrations disponibles, indexées par version de schéma
	 * cible (croissante). Une migration reçoit le tableau de réglages tel
	 * que produit par l'étape précédente et retourne le tableau transformé.
	 *
	 * @return array<int, callable(array): array>
	 */
	private static function steps() {
		return array(
			// Version 1 : schéma initial de la V1 (voir ARCHITECTURE.md §"Modèle
			// de configuration"). Aucune transformation nécessaire : les clés
			// manquantes sont complétées par une fusion avec Settings::get_defaults().
			1 => function ( array $settings ) {
				return $settings;
			},

			// Exemple pour une future V2 (ne pas implémenter avant qu'un besoin
			// réel n'existe) :
			// 2 => function ( array $settings ) {
			//     // ex. renommer une clé, transformer une structure...
			//     return $settings;
			// },
		);
	}
}
