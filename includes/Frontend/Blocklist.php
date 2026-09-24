<?php
/**
 * Construit la liste des services à bloquer avant consentement (voir
 * ARCHITECTURE.md « Script blocking »).
 *
 * @package Nook
 */

namespace Nook\Frontend;

use Nook\Database\ScanResults;
use Nook\Database\Settings;
use Nook\Services\ServiceRegistry;
use Nook\Services\ServiceState;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blocklist
 */
class Blocklist {

	/**
	 * Retourne la liste des services actuellement gérés par Nook et soumis
	 * à consentement (catégorie autre que « necessary »), avec leurs
	 * signatures de détection utilisées pour le blocage.
	 *
	 * @return array[] Liste de { slug, category, domains, scriptPatterns, iframePatterns, cookiePatterns }.
	 */
	public static function build() {
		$settings  = Settings::get();
		$overrides = is_array( $settings['services'] ) ? $settings['services'] : array();
		$detected  = self::index_detected_services();
		$entries   = array();

		foreach ( ServiceRegistry::get_services() as $definition ) {
			$slug     = $definition->get_slug();
			$override = isset( $overrides[ $slug ] ) && is_array( $overrides[ $slug ] ) ? $overrides[ $slug ] : array();
			$state    = ServiceState::compute( $definition, $override, isset( $detected[ $slug ] ) ? $detected[ $slug ] : null );

			if ( ! $state['enabled'] || 'necessary' === $state['category'] ) {
				continue;
			}

			$entries[] = array(
				'slug'           => $slug,
				'category'       => $state['category'],
				'domains'        => $definition->get_domains(),
				'scriptPatterns' => $definition->get_script_patterns(),
				'iframePatterns' => $definition->get_iframe_patterns(),
				'cookiePatterns' => $definition->get_cookie_patterns(),
			);
		}

		foreach ( $overrides as $slug => $override ) {
			if ( empty( $override['isCustom'] ) || ! is_array( $override ) ) {
				continue;
			}

			$state = ServiceState::compute_custom( $override );

			if ( ! $state['enabled'] || 'necessary' === $state['category'] ) {
				continue;
			}

			$entries[] = array(
				'slug'           => $slug,
				'category'       => $state['category'],
				'domains'        => isset( $override['domains'] ) ? $override['domains'] : array(),
				'scriptPatterns' => isset( $override['scriptPatterns'] ) ? $override['scriptPatterns'] : array(),
				'iframePatterns' => isset( $override['iframePatterns'] ) ? $override['iframePatterns'] : array(),
				'cookiePatterns' => isset( $override['cookiePatterns'] ) ? $override['cookiePatterns'] : array(),
			);
		}

		/**
		 * Filtre la liste des services soumis à blocage avant consentement.
		 *
		 * @param array $entries Liste de { slug, category, domains, scriptPatterns, iframePatterns, cookiePatterns }.
		 */
		return apply_filters( 'nook/frontend/blocklist', $entries );
	}

	/**
	 * Indexe les résultats de détection du dernier scan par slug de
	 * service, pour un accès rapide.
	 *
	 * @return array<string, array>
	 */
	private static function index_detected_services() {
		$results = ScanResults::get();
		$index   = array();

		foreach ( $results['services'] as $service ) {
			if ( isset( $service['slug'] ) ) {
				$index[ $service['slug'] ] = $service;
			}
		}

		return $index;
	}
}
