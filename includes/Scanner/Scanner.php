<?php
/**
 * Scanner serveur : détection des cookies, scripts et services d'un site.
 *
 * Implémentation à venir (voir ARCHITECTURE.md §3.3 pour la conception
 * détaillée et STATUS.md pour l'avancement). Cette classe pose uniquement la
 * forme de l'API afin que le REST controller et l'admin React puissent déjà
 * être développés contre une interface stable.
 *
 * @package Nook
 */

namespace Nook\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scanner
 */
class Scanner {

	/**
	 * Lance une analyse d'un ensemble d'URLs internes.
	 *
	 * @param string[] $urls URLs à analyser (limitées en nombre, voir ARCHITECTURE.md §3.3).
	 * @return array{cookies: array, scripts: array, iframes: array, services: array}
	 */
	public function scan( array $urls ) {
		// TODO: crawl via wp_remote_get(), extraction des scripts/iframes,
		// confrontation à data/cookies.json et au ServiceRegistry.
		return array(
			'cookies'  => array(),
			'scripts'  => array(),
			'iframes'  => array(),
			'services' => array(),
		);
	}
}
