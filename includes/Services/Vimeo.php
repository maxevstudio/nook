<?php
/**
 * Définition du service Vimeo (contenu vidéo embarqué).
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Vimeo
 */
class Vimeo implements ServiceDefinition {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'vimeo';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Vimeo', 'nook' );
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
		return array( 'vimeo.com', 'player.vimeo.com' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cookie_patterns() {
		return array( 'vuid', 'sd_client_id', 'sd_identity' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_patterns() {
		return array( 'player.vimeo.com/api/player.js' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_iframe_patterns() {
		return array( 'player.vimeo.com' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Lecture de vidéos Vimeo intégrées et mesure d’audience associée.', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_privacy_url() {
		return 'https://vimeo.com/privacy';
	}
}
