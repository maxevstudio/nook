<?php
/**
 * Définition du service Meta Pixel (Facebook Ads).
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MetaPixel
 */
class MetaPixel implements ServiceDefinition {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'meta-pixel';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return __( 'Meta Pixel', 'nook' );
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
		return array( 'connect.facebook.net', 'facebook.com' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cookie_patterns() {
		return array( '_fbp', '_fbc' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_patterns() {
		return array( 'connect.facebook.net', 'fbq(' );
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
		return __( 'Suivi publicitaire et audiences personnalisées pour Meta (Facebook/Instagram) Ads.', 'nook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_privacy_url() {
		return 'https://www.facebook.com/privacy/policy/';
	}
}
