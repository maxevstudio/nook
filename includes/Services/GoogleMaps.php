<?php
/**
 * Définition du service Google Maps (cartes embarquées).
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GoogleMaps
 */
class GoogleMaps implements ServiceDefinition {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'google-maps';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Google Maps', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_category() {
		return 'functional';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_domains() {
		return array( 'maps.google.com', 'google.com/maps', 'maps.googleapis.com' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cookie_patterns() {
		return array( 'OGP', 'OGPC' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_patterns() {
		return array( 'maps.googleapis.com/maps/api' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_iframe_patterns() {
		return array( 'maps.google.com', 'google.com/maps/embed' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Affichage de cartes Google Maps intégrées.', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_privacy_url() {
		return 'https://policies.google.com/privacy';
	}
}
