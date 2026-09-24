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
	 * Retourne la liste des services natifs de Nook.
	 *
	 * Extensible via le filtre `nook/services/register` sans toucher au cœur
	 * du plugin (voir ARCHITECTURE.md §3.2).
	 *
	 * D'autres services prioritaires V1 (Google Tag Manager, Google Ads, Meta
	 * Pixel, YouTube, Vimeo, Google Maps) restent à implémenter — voir
	 * STATUS.md.
	 *
	 * @return ServiceDefinition[]
	 */
	public static function get_services() {
		$services = array(
			new GoogleAnalytics(),
		);

		/**
		 * Filtre la liste des définitions de services.
		 *
		 * @param ServiceDefinition[] $services Services enregistrés.
		 */
		return apply_filters( 'nook/services/register', $services );
	}
}
