<?php
/**
 * Définition du service Google Tag Manager (GTM).
 *
 * GTM est particulier : il peut charger de nombreux autres services (voir
 * PROJECT.md §49). Il est donc classé prudemment en catégorie « statistics »
 * par défaut mais nécessite un consentement avant exécution — Nook ne
 * suppose jamais qu'un conteneur GTM est neutre. L'administrateur reste
 * libre de recatégoriser ce service depuis l'étape « Services ».
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GoogleTagManager
 */
class GoogleTagManager implements ServiceDefinition {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'google-tag-manager';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Google Tag Manager', 'nook' );
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
		return array( 'googletagmanager.com' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cookie_patterns() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_patterns() {
		return array( 'googletagmanager.com/gtm.js', 'GTM-' );
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
		return __( 'Gestionnaire de balises pouvant déclencher d’autres services tiers. Nook bloque le conteneur GTM lui-même avant consentement ; les services qu’il charge doivent être vérifiés individuellement.', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_privacy_url() {
		return 'https://policies.google.com/privacy';
	}
}
