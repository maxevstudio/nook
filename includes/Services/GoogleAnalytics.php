<?php
/**
 * Définition du service Google Analytics / GA4.
 *
 * Sert d'exemple concret du pattern « un service = une classe » décrit dans
 * ARCHITECTURE.md §3.2. Les autres services prioritaires de la V1 (Google Tag
 * Manager, Google Ads, Meta Pixel, YouTube, Vimeo, Google Maps) suivront le
 * même modèle — voir STATUS.md pour l'avancement.
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GoogleAnalytics
 */
class GoogleAnalytics implements ServiceDefinition {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'google-analytics';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Google Analytics', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_category() {
		return 'statistics';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_domains() {
		return array( 'google-analytics.com', 'analytics.google.com', 'googletagmanager.com' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cookie_patterns() {
		return array( '_ga', '_ga_*', '_gid', '_gat', '_gat_*' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_patterns() {
		return array( 'googletagmanager.com/gtag/js', 'google-analytics.com/analytics.js', 'gtag(' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_iframe_patterns() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Mesure d’audience et statistiques de fréquentation du site.', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_privacy_url() {
		return 'https://policies.google.com/privacy';
	}
}
