<?php
/**
 * Tests de Nook\Consent\CategoryRegistry.
 *
 * @package Nook
 */

use Nook\Consent\CategoryRegistry;

nook_test(
	'CategoryRegistry::get_categories() expose les 4 catégories minimales',
	function () {
		$categories = CategoryRegistry::get_categories();

		foreach ( array( 'necessary', 'functional', 'statistics', 'marketing' ) as $slug ) {
			nook_assert( isset( $categories[ $slug ] ), "catégorie {$slug} manquante" );
		}

		nook_assert( true === $categories['necessary']['required'], 'necessary doit être required' );
		nook_assert( false === $categories['marketing']['required'], 'marketing ne doit pas être required' );
	}
);

nook_test(
	'CategoryRegistry::get_categories() est extensible via le filtre nook/consent/categories',
	function () {
		add_filter(
			'nook/consent/categories',
			function ( $categories ) {
				$categories['custom'] = array(
					'slug'             => 'custom',
					'label'            => 'Custom',
					'description'      => 'Catégorie de test',
					'required'         => false,
					'enabledByDefault' => false,
				);
				return $categories;
			}
		);

		$categories = CategoryRegistry::get_categories();

		nook_assert( isset( $categories['custom'] ), 'le filtre doit permettre d’ajouter une catégorie' );
	}
);
