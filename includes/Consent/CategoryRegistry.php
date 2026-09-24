<?php
/**
 * Registre des catégories de consentement.
 *
 * @package Nook
 */

namespace Nook\Consent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CategoryRegistry
 */
class CategoryRegistry {

	/**
	 * Retourne les catégories de consentement disponibles.
	 *
	 * Extensible via le filtre `nook/consent/categories` (voir
	 * ARCHITECTURE.md §3.1). Les cookies réellement nécessaires sont toujours
	 * actifs ; les autres catégories restent inactives tant que le visiteur
	 * n'a pas fait de choix explicite.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_categories() {
		$categories = array(
			'necessary'  => array(
				'slug'        => 'necessary',
				'label'       => __( 'Nécessaires', 'nook' ),
				'description' => __( 'Indispensables au fonctionnement du site. Toujours actifs.', 'nook' ),
				'required'    => true,
				'enabledByDefault' => true,
			),
			'functional' => array(
				'slug'        => 'functional',
				'label'       => __( 'Fonctionnels', 'nook' ),
				'description' => __( 'Améliorent l’expérience (préférences, contenus intégrés, etc.).', 'nook' ),
				'required'    => false,
				'enabledByDefault' => false,
			),
			'statistics' => array(
				'slug'        => 'statistics',
				'label'       => __( 'Statistiques', 'nook' ),
				'description' => __( 'Mesurent l’audience et l’usage du site de façon agrégée.', 'nook' ),
				'required'    => false,
				'enabledByDefault' => false,
			),
			'marketing'  => array(
				'slug'        => 'marketing',
				'label'       => __( 'Marketing', 'nook' ),
				'description' => __( 'Utilisés à des fins publicitaires et de suivi cross-site.', 'nook' ),
				'required'    => false,
				'enabledByDefault' => false,
			),
		);

		/**
		 * Filtre la liste des catégories de consentement.
		 *
		 * @param array $categories Catégories indexées par slug.
		 */
		return apply_filters( 'nook/consent/categories', $categories );
	}
}
