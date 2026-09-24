<?php
/**
 * Shortcodes publics de Nook : politique de cookies dynamique et bouton de
 * réouverture du centre de préférences (voir PROJECT.md §33-35).
 *
 * @package Nook
 */

namespace Nook\Frontend;

use Nook\Consent\CategoryRegistry;
use Nook\Database\ScanResults;
use Nook\Database\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcodes
 */
class Shortcodes {

	/**
	 * Accroche l'enregistrement des shortcodes et du lien de préférences en
	 * pied de page.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_footer_link' ) );
	}

	/**
	 * Enregistre les shortcodes `[nook_cookie_policy]` et
	 * `[nook_cookie_settings]`.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'nook_cookie_policy', array( $this, 'render_policy' ) );
		add_shortcode( 'nook_cookie_settings', array( $this, 'render_settings_button' ) );
	}

	/**
	 * Rendu de `[nook_cookie_policy]` : politique de cookies générée
	 * dynamiquement à partir des catégories, services et cookies connus.
	 * Nook est un outil technique facilitant la conformité, pas une
	 * garantie juridique (voir PROJECT.md « Politique de cookies »).
	 *
	 * @return string
	 */
	public function render_policy() {
		$categories = $this->get_effective_categories();
		$grouped    = $this->group_cookies_by_category();

		ob_start();
		?>
		<div class="nook-policy">
			<p class="nook-policy__intro">
				<?php
				esc_html_e(
					'Cette politique décrit les catégories de cookies et services utilisés par ce site, telles que détectées et configurées via Nook. Cette page est un outil d’information technique, elle ne constitue pas un conseil juridique.',
					'nook'
				);
				?>
			</p>

			<?php foreach ( $categories as $category ) : ?>
				<section class="nook-policy__category">
					<h3 class="nook-policy__category-title"><?php echo esc_html( $category['label'] ); ?></h3>
					<p class="nook-policy__category-description"><?php echo esc_html( $category['description'] ); ?></p>

					<?php $items = isset( $grouped[ $category['slug'] ] ) ? $grouped[ $category['slug'] ] : array(); ?>

					<?php if ( $items ) : ?>
						<table class="nook-policy__table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Nom', 'nook' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Service', 'nook' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Finalité', 'nook' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Durée', 'nook' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $items as $item ) : ?>
									<tr>
										<td><?php echo esc_html( $item['name'] ); ?></td>
										<td><?php echo esc_html( $item['service'] ? $item['service'] : '—' ); ?></td>
										<td><?php echo esc_html( $item['description'] ? $item['description'] : '—' ); ?></td>
										<td><?php echo esc_html( $item['retention'] ? $item['retention'] : '—' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="nook-policy__empty">
							<?php esc_html_e( 'Aucun cookie connu recensé pour cette catégorie pour le moment.', 'nook' ); ?>
						</p>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Rendu de `[nook_cookie_settings]` : bouton rouvrant le centre de
	 * préférences (voir PROJECT.md §35).
	 *
	 * @param array|string $atts Attributs du shortcode.
	 * @return string
	 */
	public function render_settings_button( $atts ) {
		$atts = shortcode_atts(
			array( 'label' => __( 'Gérer mes cookies', 'nook' ) ),
			$atts,
			'nook_cookie_settings'
		);

		return $this->render_preferences_button( $atts['label'] );
	}

	/**
	 * Affiche automatiquement un lien de réouverture des préférences en
	 * pied de page, désactivable via `banner.footerLink` (voir PROJECT.md
	 * « Retrait / modification du consentement »).
	 *
	 * @return void
	 */
	public function maybe_render_footer_link() {
		$settings = Settings::get();

		if ( empty( $settings['banner']['footerLink'] ) ) {
			return;
		}

		echo '<div class="nook-footer-link">' . $this->render_preferences_button( __( 'Gérer mes cookies', 'nook' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- déjà échappé dans render_preferences_button().
	}

	/**
	 * Construit le bouton HTML de réouverture des préférences, intercepté
	 * par le runtime front (voir src/frontend/index.js).
	 *
	 * @param string $label Libellé du bouton.
	 * @return string
	 */
	private function render_preferences_button( $label ) {
		return sprintf(
			'<button type="button" class="nook-preferences-trigger" data-nook-action="open-preferences">%s</button>',
			esc_html( $label )
		);
	}

	/**
	 * Catégories de consentement fusionnées avec les personnalisations de
	 * l'administrateur (mêmes règles que PublicConfigController::build()).
	 *
	 * @return array
	 */
	private function get_effective_categories() {
		$settings   = Settings::get();
		$categories = CategoryRegistry::get_categories();

		foreach ( $settings['consent']['categories'] as $slug => $custom ) {
			if ( isset( $categories[ $slug ] ) && is_array( $custom ) ) {
				$categories[ $slug ] = array_merge( $categories[ $slug ], $custom );
			}
		}

		return $categories;
	}

	/**
	 * Regroupe les cookies du dernier scan par catégorie.
	 *
	 * @return array<string, array[]>
	 */
	private function group_cookies_by_category() {
		$results = ScanResults::get();
		$grouped = array();

		foreach ( $results['cookies'] as $cookie ) {
			$category = ! empty( $cookie['category'] ) ? $cookie['category'] : 'functional';
			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}
			$grouped[ $category ][] = $cookie;
		}

		return $grouped;
	}
}
