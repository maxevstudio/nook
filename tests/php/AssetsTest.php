<?php
/**
 * Tests de Nook\Frontend\Assets (configuration du garde de blocage
 * dynamique, voir src/frontend/guard.js).
 *
 * @package Nook
 */

use Nook\Frontend\Assets;

nook_test(
	'Assets::build_guard_config() expose les signatures d’iframes des services bloqués et les textes du placeholder',
	function () {
		$config = Assets::build_guard_config(
			array(
				array(
					'slug'           => 'youtube',
					'category'       => 'marketing',
					'domains'        => array( 'youtube.com', 'youtube-nocookie.com' ),
					'scriptPatterns' => array( 'youtube.com/iframe_api' ),
					'iframePatterns' => array( 'youtube-nocookie.com/embed/' ),
					'cookiePatterns' => array( 'YSC' ),
				),
			)
		);

		nook_assert_equals( 1, $config['configVersion'] );
		nook_assert_equals( 'youtube', $config['services'][0]['slug'] );
		nook_assert_equals( 'YouTube', $config['services'][0]['label'] );
		nook_assert_equals( array( 'youtube-nocookie.com/embed/' ), $config['services'][0]['iframePatterns'] );
		nook_assert( ! isset( $config['services'][0]['scriptPatterns'] ), 'seules les signatures d’iframes sont exposées' );
		nook_assert( false !== strpos( $config['i18n']['message'], '%s' ), 'le message doit contenir %s pour le nom du service' );
	}
);

nook_test(
	'Assets::build_guard_config() ne retient aucun service sans signature',
	function () {
		$config = Assets::build_guard_config(
			array(
				array(
					'slug'           => 'custom',
					'category'       => 'statistics',
					'domains'        => array(),
					'scriptPatterns' => array( 'tracker.js' ),
					'iframePatterns' => array(),
					'cookiePatterns' => array(),
				),
			)
		);

		nook_assert_equals( array(), $config['services'] );
	}
);
