<?php
/**
 * Tests de Nook\Services\ServiceRegistry.
 *
 * @package Nook
 */

use Nook\Services\ServiceRegistry;

nook_test(
	'ServiceRegistry::get_services() enregistre les 7 services V1',
	function () {
		$services = ServiceRegistry::get_services();
		$slugs    = array_map(
			function ( $service ) {
				return $service->get_slug();
			},
			$services
		);

		$expected = array(
			'google-analytics',
			'google-tag-manager',
			'google-ads',
			'meta-pixel',
			'youtube',
			'vimeo',
			'google-maps',
		);

		foreach ( $expected as $slug ) {
			nook_assert( in_array( $slug, $slugs, true ), "le service {$slug} doit être enregistré" );
		}

		nook_assert_equals( count( $expected ), count( array_unique( $slugs ) ), 'chaque slug de service doit être unique' );
	}
);

nook_test(
	'ServiceRegistry::get_service() retrouve un service par son slug',
	function () {
		$service = ServiceRegistry::get_service( 'google-analytics' );

		nook_assert( null !== $service, 'google-analytics doit être trouvé' );
		nook_assert_equals( 'statistics', $service->get_category() );
	}
);

nook_test(
	'ServiceRegistry::get_service() retourne null pour un slug inconnu',
	function () {
		nook_assert( null === ServiceRegistry::get_service( 'ne-existe-pas' ), 'un slug inconnu doit retourner null' );
	}
);

nook_test(
	'ServiceRegistry::get_services() est extensible via le filtre nook/services/register',
	function () {
		add_filter(
			'nook/services/register',
			function ( $services ) {
				$services[] = new class implements \Nook\Services\ServiceDefinition {
					public function get_slug() { return 'custom-service'; }
					public function get_name() { return 'Custom'; }
					public function get_category() { return 'marketing'; }
					public function get_domains() { return array(); }
					public function get_cookie_patterns() { return array(); }
					public function get_script_patterns() { return array(); }
					public function get_iframe_patterns() { return array(); }
					public function get_description() { return ''; }
					public function get_privacy_url() { return null; }
				};
				return $services;
			}
		);

		nook_assert( null !== ServiceRegistry::get_service( 'custom-service' ), 'le filtre doit permettre d’ajouter un service' );
	}
);
