<?php
/**
 * Orchestrateur principal du plugin.
 *
 * Cette classe est le seul point d'orchestration : elle instancie les
 * modules et accroche les hooks WordPress. Les modules ne s'auto-bootstrapent
 * jamais seuls (voir ARCHITECTURE.md §3).
 *
 * @package Nook
 */

namespace Nook;

use Nook\Admin\Menu;
use Nook\REST\SettingsController;
use Nook\Frontend\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Instance unique.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Retourne l'instance unique du plugin.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructeur privé (singleton).
	 */
	private function __construct() {}

	/**
	 * Initialise le plugin : charge les traductions et accroche les modules.
	 *
	 * @return void
	 */
	public function init() {
		load_plugin_textdomain( 'nook', false, dirname( NOOK_PLUGIN_BASENAME ) . '/languages' );

		( new Menu() )->register();
		( new SettingsController() )->register();
		( new Assets() )->register();
	}
}
