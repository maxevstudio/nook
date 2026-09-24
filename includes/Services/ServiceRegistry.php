<?php
/**
 * Registre central des services connus.
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ServiceRegistry
 */
class ServiceRegistry {

	/**
	 * Retourne la liste des services natifs de Nook (les 7 services
	 * prioritaires V1 — voir PROJECT.md « Services V1 »).
	 *
	 * Extensible via le filtre `nook/services/register` sans toucher au cœur
	 * du plugin (voir ARCHITECTURE.md §3.2).
	 *
	 * @return ServiceDefinition[]
	 */
	public static function get_services() {
		$services = array(
			new GoogleAnalytics(),
			new GoogleTagManager(),
			new GoogleAds(),
			new MetaPixel(),
			new YouTube(),
			new Vimeo(),
			new GoogleMaps(),
		);

		/**
		 * Filtre la liste des définitions de services.
		 *
		 * @param ServiceDefinition[] $services Services enregistrés.
		 */
		return apply_filters( 'nook/services/register', $services );
	}

	/**
	 * Retourne une définition de service par son slug, ou null si absente.
	 *
	 * @param string $slug Identifiant du service.
	 * @return ServiceDefinition|null
	 */
	public static function get_service( $slug ) {
		foreach ( self::get_services() as $service ) {
			if ( $service->get_slug() === $slug ) {
				return $service;
			}
		}

		return null;
	}
}
