<?php
/**
 * Mises à jour automatiques via les releases GitHub publiques.
 *
 * Nook n'est pas distribué sur WordPress.org : ce module branche les releases
 * du dépôt public GitHub sur l'écran natif « Mises à jour » de WordPress.
 *
 * Seule l'API publique GitHub des releases est interrogée (aucun jeton,
 * aucun secret embarqué), et aucune donnée du site n'est envoyée : ce n'est
 * pas de la télémétrie (voir AGENTS.md règle 9 et ARCHITECTURE.md §15).
 * Désactivable via le filtre `nook_github_updater_enabled`.
 *
 * @package Nook
 */

namespace Nook\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GitHubUpdater
 */
class GitHubUpdater {

	const SLUG      = 'nook';
	const CACHE_KEY = 'nook_github_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Accroche les hooks de mise à jour de WordPress.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! apply_filters( 'nook_github_updater_enabled', true ) ) {
			return;
		}

		add_filter( 'site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 0 );
		add_action( 'load-update-core.php', array( $this, 'purge_cache_on_force_check' ) );
	}

	/**
	 * « Vérifier à nouveau » (Tableau de bord → Mises à jour) vide aussi le
	 * cache de Nook : une release tout juste publiée apparaît alors
	 * immédiatement, sans attendre l'expiration des 6 h.
	 *
	 * @return void
	 */
	public function purge_cache_on_force_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- même paramètre que le cœur de WordPress, lecture seule.
		if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) {
			$this->purge_cache();
		}
	}

	/**
	 * Dépôt `owner/repo` interrogé. Filtrable pour un fork ou un dépôt privé
	 * de distribution.
	 *
	 * @return string
	 */
	private function get_repo_slug() {
		return apply_filters( 'nook_github_repo', 'maxevstudio/nook' );
	}

	/**
	 * Récupère (et met en cache) la dernière release publiée.
	 *
	 * Un échec est lui aussi mis en cache (tableau vide) pour ne jamais
	 * réinterroger GitHub à chaque chargement de page admin.
	 *
	 * @return array|null
	 */
	public function get_latest_release() {
		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			return empty( $cached ) ? null : $cached;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->get_repo_slug() ),
			array(
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Nook-Updater',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, array(), self::CACHE_TTL );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['tag_name'] ) ) {
			set_transient( self::CACHE_KEY, array(), self::CACHE_TTL );
			return null;
		}

		// Seul un ZIP joint à la release est un plugin prêt à installer
		// (dossier racine `nook/`) : le zipball automatique de GitHub ne
		// l'est pas, il n'est donc jamais utilisé.
		$download_url = '';
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && '.zip' === substr( $asset['browser_download_url'], -4 ) ) {
					$download_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		$release = array(
			'version'       => ltrim( $body['tag_name'], 'v' ),
			'download_url'  => $download_url,
			'changelog_url' => isset( $body['html_url'] ) ? $body['html_url'] : '',
			'body'          => isset( $body['body'] ) ? $body['body'] : '',
		);

		set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Ajoute Nook au transient de mises à jour si une release plus récente
	 * existe.
	 *
	 * @param object $transient Transient `update_plugins`.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( empty( $release['download_url'] ) || ! version_compare( $release['version'], NOOK_VERSION, '>' ) ) {
			return $transient;
		}

		$item              = new \stdClass();
		$item->slug        = self::SLUG;
		$item->plugin      = NOOK_PLUGIN_BASENAME;
		$item->new_version = $release['version'];
		$item->url         = $release['changelog_url'];
		$item->package     = $release['download_url'];

		$transient->response[ NOOK_PLUGIN_BASENAME ] = $item;

		return $transient;
	}

	/**
	 * Contenu de la fenêtre « Afficher les détails » de l'écran Extensions.
	 *
	 * @param false|object|array $result Résultat existant.
	 * @param string             $action Action demandée.
	 * @param object             $args   Arguments de la requête.
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( empty( $release ) ) {
			return $result;
		}

		$info                = new \stdClass();
		$info->name          = 'Nook';
		$info->slug          = self::SLUG;
		$info->version       = $release['version'];
		$info->author        = 'Maxev';
		$info->homepage      = $release['changelog_url'];
		$info->download_link = $release['download_url'];
		$info->sections      = array(
			'changelog' => wp_kses_post( nl2br( $release['body'] ) ),
		);

		return $info;
	}

	/**
	 * Vide le cache après une mise à jour pour refléter immédiatement l'état
	 * réel lors de la vérification suivante.
	 *
	 * @return void
	 */
	public function purge_cache() {
		delete_transient( self::CACHE_KEY );
	}
}
