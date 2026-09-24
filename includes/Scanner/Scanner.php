<?php
/**
 * Scanner serveur : détection des cookies, scripts, iframes et services
 * d'un site à partir d'un petit ensemble d'URLs internes.
 *
 * Voir ARCHITECTURE.md « Architecture du scanner » pour la conception
 * détaillée et PROJECT.md §11-16 pour les limites assumées :
 *
 * - Aucune infrastructure cloud Maxev : tout tourne sur l'installation
 *   WordPress elle-même (`wp_remote_get`), sans envoyer l'URL ni les
 *   résultats à un tiers.
 * - C'est une **analyse serveur** : le HTML brut est inspecté, mais aucun
 *   JavaScript n'est exécuté. Les cookies posés dynamiquement en JavaScript
 *   après exécution ne sont donc pas observables depuis PHP — seuls les
 *   cookies HTTP (`Set-Cookie`) réellement renvoyés par le serveur pour ces
 *   pages sont détectés. Documenté explicitement pour ne jamais faire
 *   croire à une détection plus large que la réalité.
 * - Nombre d'URLs et temps de requête volontairement limités pour ne pas
 *   surcharger le serveur (voir UrlDiscovery::DEFAULT_LIMIT et
 *   self::REQUEST_TIMEOUT).
 *
 * @package Nook
 */

namespace Nook\Scanner;

use Nook\Database\CookieDatabase;
use Nook\Services\ServiceRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scanner
 */
class Scanner {

	/**
	 * Nombre maximal d'URLs traitées en une analyse, même si l'appelant en
	 * fournit davantage (garde-fou indépendant de UrlDiscovery).
	 *
	 * @var int
	 */
	const MAX_URLS = 20;

	/**
	 * Timeout HTTP par URL, en secondes. Reste court pour ne jamais bloquer
	 * longtemps le serveur (voir PROJECT.md §14).
	 *
	 * @var int
	 */
	const REQUEST_TIMEOUT = 8;

	/**
	 * Lance une analyse d'un ensemble d'URLs internes.
	 *
	 * @param string[] $urls URLs à analyser (limitées en nombre, voir ARCHITECTURE.md §3.3).
	 * @return array{
	 *     pagesAnalyzed: int,
	 *     urls: string[],
	 *     scripts: string[],
	 *     iframes: string[],
	 *     cookies: array[],
	 *     services: array[],
	 *     errors: array[]
	 * }
	 */
	public function scan( array $urls ) {
		$urls = array_slice( array_values( array_unique( array_filter( $urls ) ) ), 0, self::MAX_URLS );

		$external_scripts = array();
		$inline_scripts   = array();
		$iframes          = array();
		$cookie_names     = array();
		$errors           = array();
		$pages_analyzed   = 0;

		foreach ( $urls as $url ) {
			$outcome = $this->fetch( $url );

			if ( isset( $outcome['error'] ) ) {
				$errors[] = array(
					'url'     => $url,
					'message' => $outcome['error'],
				);
				continue;
			}

			++$pages_analyzed;

			$this->extract_scripts( $outcome['body'], $external_scripts, $inline_scripts );
			$this->extract_iframes( $outcome['body'], $iframes );

			foreach ( $outcome['cookies'] as $cookie_name ) {
				$cookie_names[ $cookie_name ] = true;
			}
		}

		$active_plugins = $this->get_active_plugin_hints();

		return array(
			'pagesAnalyzed' => $pages_analyzed,
			'urls'          => $urls,
			'scripts'       => array_keys( $external_scripts ),
			'iframes'       => array_keys( $iframes ),
			'cookies'       => $this->match_cookies( array_keys( $cookie_names ) ),
			'services'      => $this->match_services( array_keys( $external_scripts ), $inline_scripts, array_keys( $iframes ), $active_plugins ),
			'errors'        => $errors,
		);
	}

	/**
	 * Récupère une URL et retourne son corps HTML + les cookies HTTP
	 * observés, ou un message d'erreur exploitable.
	 *
	 * @param string $url URL à récupérer.
	 * @return array{body?: string, cookies?: string[], error?: string}
	 */
	private function fetch( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 3,
				'sslverify'   => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 400 ) {
			return array(
				/* translators: %d: code de statut HTTP. */
				'error' => sprintf( __( 'Réponse HTTP %d', 'nook' ), $code ),
			);
		}

		$cookie_names = array();

		foreach ( wp_remote_retrieve_cookies( $response ) as $cookie ) {
			if ( isset( $cookie->name ) && '' !== $cookie->name ) {
				$cookie_names[] = $cookie->name;
			}
		}

		return array(
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'cookies' => $cookie_names,
		);
	}

	/**
	 * Extrait les balises `<script>` (externes et inline) d'un document
	 * HTML. Utilise DOMDocument si disponible (plus robuste), sinon une
	 * extraction par expression régulière en repli.
	 *
	 * @param string $html             Corps HTML de la page.
	 * @param array  $external_scripts Tableau (référence) accumulant les URLs de scripts externes.
	 * @param array  $inline_scripts   Tableau (référence) accumulant le contenu des scripts inline.
	 * @return void
	 */
	private function extract_scripts( $html, array &$external_scripts, array &$inline_scripts ) {
		if ( '' === trim( (string) $html ) ) {
			return;
		}

		if ( class_exists( 'DOMDocument' ) ) {
			$dom = $this->parse_html( $html );

			if ( null !== $dom ) {
				foreach ( $dom->getElementsByTagName( 'script' ) as $node ) {
					$src = $node->getAttribute( 'src' );

					if ( '' !== $src ) {
						$external_scripts[ $src ] = true;
					} elseif ( '' !== trim( $node->textContent ) ) {
						$inline_scripts[] = $node->textContent;
					}
				}

				return;
			}
		}

		if ( preg_match_all( '/<script\b[^>]*\ssrc=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				$external_scripts[ $src ] = true;
			}
		}

		if ( preg_match_all( '/<script\b(?![^>]*\ssrc=)[^>]*>(.*?)<\/script>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $content ) {
				if ( '' !== trim( $content ) ) {
					$inline_scripts[] = $content;
				}
			}
		}
	}

	/**
	 * Extrait les URLs des balises `<iframe>` d'un document HTML.
	 *
	 * @param string $html    Corps HTML de la page.
	 * @param array  $iframes Tableau (référence) accumulant les URLs d'iframes.
	 * @return void
	 */
	private function extract_iframes( $html, array &$iframes ) {
		if ( '' === trim( (string) $html ) ) {
			return;
		}

		if ( class_exists( 'DOMDocument' ) ) {
			$dom = $this->parse_html( $html );

			if ( null !== $dom ) {
				foreach ( $dom->getElementsByTagName( 'iframe' ) as $node ) {
					$src = $node->getAttribute( 'src' );

					if ( '' !== $src ) {
						$iframes[ $src ] = true;
					}
				}

				return;
			}
		}

		if ( preg_match_all( '/<iframe\b[^>]*\ssrc=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				$iframes[ $src ] = true;
			}
		}
	}

	/**
	 * Parse un document HTML avec DOMDocument, en tolérant le HTML5 non
	 * strict et sans jamais faire échouer l'analyse pour un document
	 * malformé (les erreurs libxml sont capturées silencieusement).
	 *
	 * @param string $html Corps HTML.
	 * @return \DOMDocument|null
	 */
	private function parse_html( $html ) {
		$dom = new \DOMDocument();

		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $loaded ? $dom : null;
	}

	/**
	 * Confronte les cookies observés à la base de connaissance locale
	 * (voir CookieDatabase) et attribue un statut à chacun (voir
	 * ARCHITECTURE.md « Statut des résultats »).
	 *
	 * @param string[] $cookie_names Noms de cookies observés (dédupliqués).
	 * @return array[]
	 */
	private function match_cookies( array $cookie_names ) {
		$results = array();

		foreach ( $cookie_names as $name ) {
			$match = CookieDatabase::match( $name );

			if ( null === $match ) {
				$results[] = array(
					'name'        => $name,
					'status'      => 'to_review',
					'category'    => null,
					'service'     => null,
					'description' => null,
					'retention'   => null,
				);
				continue;
			}

			$entry    = $match['entry'];
			$status   = 'exact' === $match['confidence'] ? 'recognized' : 'suggested';
			$results[] = array(
				'name'        => $name,
				'status'      => $status,
				'category'    => $entry['category'],
				'service'     => $entry['service'],
				'description' => $entry['description'],
				'retention'   => $entry['retention'],
			);
		}

		return $results;
	}

	/**
	 * Confronte les scripts/iframes détectés (et quelques indices de
	 * plugins actifs) aux définitions du ServiceRegistry.
	 *
	 * @param string[] $external_scripts URLs de scripts externes.
	 * @param string[] $inline_scripts   Contenus de scripts inline.
	 * @param string[] $iframes          URLs d'iframes.
	 * @param string[] $plugin_hints     Indices de services suggérés par les plugins actifs (slug => raison).
	 * @return array[]
	 */
	private function match_services( array $external_scripts, array $inline_scripts, array $iframes, array $plugin_hints ) {
		$results = array();

		foreach ( ServiceRegistry::get_services() as $service ) {
			$provenance = array();

			foreach ( $service->get_domains() as $domain ) {
				if ( '' === $domain ) {
					continue;
				}

				foreach ( $external_scripts as $src ) {
					if ( false !== stripos( $src, $domain ) ) {
						$provenance[] = array(
							'type'  => 'script',
							'value' => $src,
						);
					}
				}

				foreach ( $iframes as $src ) {
					if ( false !== stripos( $src, $domain ) ) {
						$provenance[] = array(
							'type'  => 'iframe',
							'value' => $src,
						);
					}
				}
			}

			foreach ( $service->get_script_patterns() as $pattern ) {
				if ( '' === $pattern ) {
					continue;
				}

				foreach ( $external_scripts as $src ) {
					if ( false !== stripos( $src, $pattern ) ) {
						$provenance[] = array(
							'type'  => 'script',
							'value' => $src,
						);
					}
				}

				foreach ( $inline_scripts as $content ) {
					if ( false !== stripos( $content, $pattern ) ) {
						$provenance[] = array(
							'type'  => 'inline-script',
							'value' => $pattern,
						);
					}
				}
			}

			foreach ( $service->get_iframe_patterns() as $pattern ) {
				if ( '' === $pattern ) {
					continue;
				}

				foreach ( $iframes as $src ) {
					if ( false !== stripos( $src, $pattern ) ) {
						$provenance[] = array(
							'type'  => 'iframe',
							'value' => $src,
						);
					}
				}
			}

			if ( isset( $plugin_hints[ $service->get_slug() ] ) ) {
				$provenance[] = array(
					'type'  => 'plugin',
					'value' => $plugin_hints[ $service->get_slug() ],
				);
			}

			$results[] = array(
				'slug'        => $service->get_slug(),
				'name'        => $service->get_name(),
				'category'    => $service->get_category(),
				'description' => $service->get_description(),
				'detected'    => ! empty( $provenance ),
				'provenance'  => $this->deduplicate_provenance( $provenance ),
			);
		}

		return $results;
	}

	/**
	 * Retire les entrées de provenance strictement identiques.
	 *
	 * @param array $provenance Liste de { type, value }.
	 * @return array
	 */
	private function deduplicate_provenance( array $provenance ) {
		$seen   = array();
		$unique = array();

		foreach ( $provenance as $item ) {
			$key = $item['type'] . '|' . $item['value'];

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = $item;
		}

		return $unique;
	}

	/**
	 * Indices de services suggérés par les plugins WordPress actifs (voir
	 * PROJECT.md §27 et §48, ex. Google Site Kit implique potentiellement
	 * Google Analytics/GTM). Reste volontairement une liste courte et
	 * documentée plutôt qu'une heuristique large source de faux positifs.
	 *
	 * @return array<string, string> Slug de service => description de l'indice.
	 */
	private function get_active_plugin_hints() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$hints          = array();

		$known = array(
			'google-site-kit/google-site-kit.php' => array(
				'google-analytics'   => __( 'Détecté via le plugin Google Site Kit', 'nook' ),
				'google-tag-manager' => __( 'Détecté via le plugin Google Site Kit', 'nook' ),
			),
		);

		foreach ( $known as $plugin_file => $service_hints ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				foreach ( $service_hints as $slug => $reason ) {
					$hints[ $slug ] = $reason;
				}
			}
		}

		return $hints;
	}
}
