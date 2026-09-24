<?php
/**
 * Tests de Nook\Frontend\Blocklist.
 *
 * @package Nook
 */

use Nook\Database\ScanResults;
use Nook\Database\Settings;
use Nook\Frontend\Blocklist;
use Nook\REST\ServicesController;

nook_test(
	'Blocklist::build() ne bloque rien tant qu’aucun service n’est activé',
	function () {
		nook_assert_equals( array(), Blocklist::build() );
	}
);

nook_test(
	'Blocklist::build() inclut un service confirmé et exclut un service désactivé',
	function () {
		$controller = new ServicesController();
		$controller->update_service(
			new WP_REST_Request(
				array(
					'enabled'   => true,
					'confirmed' => true,
				),
				array( 'slug' => 'google-analytics' )
			)
		);
		$controller->update_service(
			new WP_REST_Request( array( 'enabled' => false ), array( 'slug' => 'youtube' ) )
		);

		$blocklist = Blocklist::build();
		$slugs     = array_column( $blocklist, 'slug' );

		nook_assert( in_array( 'google-analytics', $slugs, true ), 'google-analytics doit être bloqué' );
		nook_assert( ! in_array( 'youtube', $slugs, true ), 'youtube désactivé ne doit pas être bloqué' );
	}
);

nook_test(
	'Blocklist::build() n’inclut jamais un service classé « necessary »',
	function () {
		$controller = new ServicesController();
		$controller->update_service(
			new WP_REST_Request(
				array(
					'enabled'  => true,
					'category' => 'necessary',
				),
				array( 'slug' => 'google-analytics' )
			)
		);

		$blocklist = Blocklist::build();
		$slugs     = array_column( $blocklist, 'slug' );

		nook_assert( ! in_array( 'google-analytics', $slugs, true ), 'un service « necessary » ne doit jamais être bloqué' );
	}
);

nook_test(
	'Blocklist::build() inclut un service détecté par le scanner sans correction manuelle',
	function () {
		ScanResults::save(
			array(
				'pagesAnalyzed' => 1,
				'urls'          => array(),
				'scripts'       => array(),
				'iframes'       => array(),
				'cookies'       => array(),
				'services'      => array(
					array(
						'slug'        => 'meta-pixel',
						'name'        => 'Meta Pixel',
						'category'    => 'marketing',
						'description' => '',
						'detected'    => true,
						'provenance'  => array(),
					),
				),
				'errors'        => array(),
			)
		);

		$blocklist = Blocklist::build();
		$slugs     = array_column( $blocklist, 'slug' );

		nook_assert( in_array( 'meta-pixel', $slugs, true ), 'un service détecté (même sans confirmation) doit être bloqué par défaut' );
	}
);

nook_test(
	'Blocklist::build() inclut un service personnalisé activé',
	function () {
		Settings::update(
			array(
				'services' => array(
					'mon-service' => array(
						'isCustom'       => true,
						'name'           => 'Mon Service',
						'category'       => 'marketing',
						'domains'        => array( 'example.com' ),
						'scriptPatterns' => array( 'example.com/tracker.js' ),
					),
				),
			)
		);

		$blocklist = Blocklist::build();
		$slugs     = array_column( $blocklist, 'slug' );

		nook_assert( in_array( 'mon-service', $slugs, true ), 'mon-service doit être bloqué' );
	}
);
