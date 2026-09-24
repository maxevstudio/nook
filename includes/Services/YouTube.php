<?php
/**
 * Définition du service YouTube (contenu vidéo embarqué).
 *
 * Catégorisé « marketing » par défaut car YouTube dépose des cookies de
 * suivi publicitaire dès la simple présence d'une iframe classique
 * (`youtube.com/embed/`). Le mode `youtube-nocookie.com` réduit ce suivi
 * mais Nook continue d'afficher un placeholder par précaution (voir
 * PROJECT.md §51 et §16 sur les limites de détection).
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class YouTube
 */
class YouTube implements ServiceDefinition {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'youtube';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'YouTube', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_category() {
		return 'marketing';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_domains() {
		return array( 'youtube.com', 'youtube-nocookie.com', 'ytimg.com' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cookie_patterns() {
		return array( 'VISITOR_INFO1_LIVE', 'YSC', 'PREF', 'GPS', 'LOGIN_INFO' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_patterns() {
		return array( 'youtube.com/iframe_api', 'youtube.com/s/player' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_iframe_patterns() {
		return array( 'youtube.com/embed/', 'youtube-nocookie.com/embed/' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Lecture de vidéos YouTube intégrées.', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_privacy_url() {
		return 'https://policies.google.com/privacy';
	}
}
