<?php
/**
 * Blocage réel des scripts et iframes tiers avant consentement (voir
 * ARCHITECTURE.md « Script blocking » et PROJECT.md §45-47 : c'est la
 * fonctionnalité la plus critique de Nook).
 *
 * Fonctionnement : la sortie HTML complète de la page est capturée via
 * `ob_start()` puis réécrite par expressions régulières ciblées (jamais par
 * un aller-retour DOMDocument → saveHTML, qui risquerait de corrompre la
 * page — encodage, balises auto-fermantes, etc.). Seules les balises
 * `<script>`/`<iframe>` correspondant à un service géré par Nook et soumis à
 * consentement sont réécrites ; tout le reste du document est laissé
 * strictement intact.
 *
 * - Un `<script src="...">` correspondant devient
 *   `<script type="text/plain" data-nook-category="..." data-nook-service="..." data-nook-src="...">`
 *   (le navigateur ne l'exécute plus, `src` étant renommé).
 * - Un script inline dont le contenu correspond à une signature connue
 *   (ex. `gtag(`, `fbq(`) est neutralisé de la même façon, sans toucher à
 *   son contenu.
 * - Un `<iframe src="...">` correspondant est remplacé par un placeholder
 *   (voir Placeholder::render()) conservant l'URL d'origine, réactivé par le
 *   runtime front après consentement (voir src/frontend/blocker.js).
 *
 * Ce mécanisme reste un filet de sécurité serveur : certaines extensions
 * peuvent injecter des scripts très tôt ou de façon dynamique côté client,
 * voir PROJECT.md §47 pour les limites assumées.
 *
 * @package Nook
 */

namespace Nook\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentFilter
 */
class ContentFilter {

	/**
	 * Liste des services à bloquer pour la requête courante, calculée une
	 * fois avant de démarrer la bufferisation.
	 *
	 * @var array[]
	 */
	private $blocklist = array();

	/**
	 * Accroche les hooks WordPress nécessaires.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ), 0 );
	}

	/**
	 * Définit explicitement la blocklist à utiliser par filter_output().
	 * Utile pour les tests (voir tests/php/ContentFilterTest.php) ou un
	 * usage direct hors du cycle `template_redirect`/`ob_start()` habituel.
	 *
	 * @param array[] $blocklist Liste de { slug, category, domains, scriptPatterns, iframePatterns }.
	 * @return void
	 */
	public function set_blocklist( array $blocklist ) {
		$this->blocklist = $blocklist;
	}

	/**
	 * Démarre la bufferisation de sortie si la requête courante est une
	 * page front-end classique et qu'au moins un service est à bloquer.
	 *
	 * @return void
	 */
	public function maybe_start_buffer() {
		if ( is_admin() ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return;
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return;
		}

		$this->blocklist = Blocklist::build();

		if ( empty( $this->blocklist ) ) {
			// Rien à bloquer : ne pas bufferiser inutilement toute la sortie
			// (voir PROJECT.md §66, performance front).
			return;
		}

		ob_start( array( $this, 'filter_output' ) );
	}

	/**
	 * Callback de `ob_start()` : réécrit les balises `<script>`/`<iframe>`
	 * correspondant aux services de `$this->blocklist`.
	 *
	 * @param string $html Sortie HTML complète de la page.
	 * @return string
	 */
	public function filter_output( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return $html;
		}

		$html = $this->transform_scripts( $html );
		$html = $this->transform_iframes( $html );

		return $html;
	}

	/**
	 * Réécrit les balises `<script>` (externes et inline) correspondant à
	 * un service bloqué.
	 *
	 * @param string $html Sortie HTML.
	 * @return string
	 */
	private function transform_scripts( $html ) {
		return preg_replace_callback(
			'#<script\b(?P<attrs>[^>]*)>(?P<content>.*?)</script\s*>#is',
			array( $this, 'replace_script_match' ),
			$html
		);
	}

	/**
	 * Callback de remplacement pour une balise `<script>` individuelle.
	 *
	 * @param array $matches Groupes capturés par transform_scripts().
	 * @return string
	 */
	private function replace_script_match( array $matches ) {
		$attrs   = $this->parse_attributes( $matches['attrs'] );
		$content = $matches['content'];

		if ( isset( $attrs['src'] ) && '' !== $attrs['src'] ) {
			$entry = $this->find_matching_entry( $attrs['src'], 'scriptPatterns' );

			if ( null === $entry ) {
				return $matches[0];
			}

			$original_src = $attrs['src'];
			unset( $attrs['src'] );
			$attrs['type']              = 'text/plain';
			$attrs['data-nook-category'] = $entry['category'];
			$attrs['data-nook-service']  = $entry['slug'];
			$attrs['data-nook-src']      = $original_src;

			return '<script' . $this->build_attributes( $attrs ) . '>' . $content . '</script>';
		}

		if ( '' === trim( $content ) ) {
			return $matches[0];
		}

		$entry = $this->find_matching_entry( $content, 'scriptPatterns' );

		if ( null === $entry ) {
			return $matches[0];
		}

		$attrs['type']               = 'text/plain';
		$attrs['data-nook-category'] = $entry['category'];
		$attrs['data-nook-service']  = $entry['slug'];

		return '<script' . $this->build_attributes( $attrs ) . '>' . $content . '</script>';
	}

	/**
	 * Réécrit les balises `<iframe>` correspondant à un service bloqué en
	 * placeholder (voir Placeholder::render()).
	 *
	 * @param string $html Sortie HTML.
	 * @return string
	 */
	private function transform_iframes( $html ) {
		return preg_replace_callback(
			'#<iframe\b(?P<attrs>[^>]*)(?:/>|>(?:.*?</iframe\s*>)?)#is',
			array( $this, 'replace_iframe_match' ),
			$html
		);
	}

	/**
	 * Callback de remplacement pour une balise `<iframe>` individuelle.
	 *
	 * @param array $matches Groupes capturés par transform_iframes().
	 * @return string
	 */
	private function replace_iframe_match( array $matches ) {
		$attrs = $this->parse_attributes( $matches['attrs'] );

		if ( ! isset( $attrs['src'] ) || '' === $attrs['src'] ) {
			return $matches[0];
		}

		$entry = $this->find_matching_entry( $attrs['src'], 'iframePatterns' );

		if ( null === $entry ) {
			return $matches[0];
		}

		return Placeholder::render( $entry, $attrs );
	}

	/**
	 * Cherche, dans la blocklist, le premier service dont un motif
	 * spécifique ($pattern_key) ou, à défaut, un domaine correspond à la
	 * chaîne observée. Vérifier les motifs spécifiques en priorité évite
	 * les ambiguïtés entre services partageant un même domaine (ex.
	 * `googletagmanager.com` est utilisé à la fois par Google Analytics et
	 * Google Tag Manager : le motif `gtm.js` ou `gtag/js` désambiguïse).
	 *
	 * @param string $haystack     URL ou contenu à tester.
	 * @param string $pattern_key  Clé du motif spécifique (`scriptPatterns` ou `iframePatterns`).
	 * @return array|null
	 */
	private function find_matching_entry( $haystack, $pattern_key ) {
		$entry = $this->match_against( $haystack, $pattern_key );

		if ( null !== $entry ) {
			return $entry;
		}

		return $this->match_against( $haystack, 'domains' );
	}

	/**
	 * Teste une chaîne contre une liste de motifs pour chaque entrée de la
	 * blocklist.
	 *
	 * @param string $haystack Chaîne à tester.
	 * @param string $key      Clé du tableau de motifs dans l'entrée.
	 * @return array|null
	 */
	private function match_against( $haystack, $key ) {
		foreach ( $this->blocklist as $entry ) {
			if ( empty( $entry[ $key ] ) ) {
				continue;
			}

			foreach ( $entry[ $key ] as $pattern ) {
				if ( '' !== $pattern && false !== stripos( $haystack, $pattern ) ) {
					return $entry;
				}
			}
		}

		return null;
	}

	/**
	 * Parse les attributs d'une balise à partir de la chaîne capturée entre
	 * le nom de la balise et le `>` fermant.
	 *
	 * @param string $attr_string Attributs bruts (ex. ` src="..." async`).
	 * @return array<string, string>
	 */
	private function parse_attributes( $attr_string ) {
		$attrs = array();

		if ( preg_match_all( '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', $attr_string, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$name  = strtolower( $match[1] );
				$value = isset( $match[2] ) ? $match[2] : '';
				$value = trim( $value, '"\'' );
				$attrs[ $name ] = $value;
			}
		}

		return $attrs;
	}

	/**
	 * Reconstruit une chaîne d'attributs HTML à partir d'un tableau
	 * associatif, en échappant les valeurs pour rester dans un attribut
	 * double-quote.
	 *
	 * @param array<string, string> $attrs Attributs à sérialiser.
	 * @return string Chaîne commençant par un espace si non vide, sinon vide.
	 */
	private function build_attributes( array $attrs ) {
		$parts = array();

		foreach ( $attrs as $name => $value ) {
			if ( '' === $value && in_array( $name, array( 'async', 'defer' ), true ) ) {
				$parts[] = $name;
				continue;
			}

			$parts[] = $name . '="' . esc_attr( $value ) . '"';
		}

		return $parts ? ' ' . implode( ' ', $parts ) : '';
	}
}
