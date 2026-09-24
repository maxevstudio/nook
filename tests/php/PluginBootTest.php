<?php
/**
 * Test de fumée : reproduit Nook\Plugin::init() pour vérifier que chaque
 * module s'instancie et s'accroche sans erreur fatale (classes manquantes,
 * dépendances cassées) — mêmes vérifications que le bootstrap réel du
 * plugin (voir nook.php et includes/Plugin.php).
 *
 * @package Nook
 */

use Nook\Admin\Menu;
use Nook\Frontend\Assets;
use Nook\Frontend\ContentFilter;
use Nook\Frontend\Shortcodes;
use Nook\REST\CookiesController;
use Nook\REST\PublicConfigController;
use Nook\REST\ScanController;
use Nook\REST\ServicesController;
use Nook\REST\SettingsController;

nook_test(
	'Tous les modules accrochés par Plugin::init() s’instancient et s’enregistrent sans erreur',
	function () {
		( new Menu() )->register();
		( new SettingsController() )->register();
		( new ScanController() )->register();
		( new ServicesController() )->register();
		( new CookiesController() )->register();
		( new PublicConfigController() )->register();
		( new Assets() )->register();
		( new ContentFilter() )->register();
		( new Shortcodes() )->register();

		nook_assert( true, 'aucune exception ne doit être levée pendant le bootstrap' );
	}
);

nook_test(
	'PublicConfigController::build() retourne une configuration publique cohérente',
	function () {
		$config = PublicConfigController::build();

		nook_assert( isset( $config['categories']['necessary'] ), 'catégorie necessary manquante' );
		nook_assert( isset( $config['banner']['title'] ), 'titre de bannière manquant' );
		nook_assert( isset( $config['consent']['duration'] ), 'durée de consentement manquante' );
		nook_assert( array_key_exists( 'url', $config['policy'] ), 'clé policy.url manquante' );
	}
);
