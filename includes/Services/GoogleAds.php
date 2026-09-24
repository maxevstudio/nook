<?php
/**
 * Définition du service Google Ads (remarketing, suivi de conversion).
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GoogleAds
 */
class GoogleAds implements ServiceDefinition {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'google-ads';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Google Ads', 'nook' );
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
		return array( 'googleadservices.com', 'googlesyndication.com', 'doubleclick.net' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cookie_patterns() {
		return array( '_gcl_au', '_gcl_aw', '_gcl_gb', '_gcl_gs', '_gac_*' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_patterns() {
		return array( 'googleadservices.com', 'googlesyndication.com', 'googletagmanager.com/gtag/js?id=AW-' );
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
		return __( 'Suivi de conversion et remarketing publicitaire Google.', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_privacy_url() {
		return 'https://policies.google.com/privacy';
	}
}
