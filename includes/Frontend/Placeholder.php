<?php
/**
 * Rendu du placeholder affiché à la place d'une iframe tierce bloquée avant
 * consentement (voir PROJECT.md §51-52).
 *
 * @package Nook
 */

namespace Nook\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Placeholder
 */
class Placeholder {

	/**
	 * Construit le HTML du placeholder pour une iframe bloquée.
	 *
	 * @param array                $entry Entrée de la blocklist ({ slug, category, ... }).
	 * @param array<string, string> $attrs  Attributs de l'iframe d'origine (déjà parsés).
	 * @return string
	 */
	public static function render( array $entry, array $attrs ) {
		$width  = isset( $attrs['width'] ) ? $attrs['width'] : '';
		$height = isset( $attrs['height'] ) ? $attrs['height'] : '';
		$title  = isset( $attrs['title'] ) ? $attrs['title'] : '';

		$style = 'max-width:100%;';

		if ( '' !== $width && '' !== $height && is_numeric( $width ) && is_numeric( $height ) ) {
			$style .= sprintf( 'aspect-ratio:%d/%d;max-width:%dpx;', (int) $width, (int) $height, (int) $width );
		}

		$service_name = self::get_service_label( $entry['slug'] );

		return sprintf(
			'<div class="nook-placeholder" data-nook-category="%1$s" data-nook-service="%2$s" data-nook-src="%3$s" data-nook-width="%4$s" data-nook-height="%5$s" data-nook-title="%6$s" style="%7$s" role="group" aria-label="%8$s">'
			. '<p class="nook-placeholder__message">%9$s</p>'
			. '<button type="button" class="nook-placeholder__button" data-nook-action="allow">%10$s</button>'
			. '</div>',
			esc_attr( $entry['category'] ),
			esc_attr( $entry['slug'] ),
			esc_attr( $attrs['src'] ),
			esc_attr( $width ),
			esc_attr( $height ),
			esc_attr( $title ),
			esc_attr( $style ),
			esc_attr( $title ? $title : $service_name ),
			sprintf( esc_html( self::get_message_template() ), esc_html( $service_name ) ),
			esc_html( self::get_button_label() )
		);
	}

	/**
	 * Message du placeholder, `%s` étant le nom du service. Partagé avec le
	 * garde navigateur pour que les deux placeholders soient identiques.
	 *
	 * @return string
	 */
	public static function get_message_template() {
		/* translators: %s: nom du service tiers (ex. YouTube) */
		return __( 'Ce contenu est fourni par %s. Pour l’afficher, vous devez autoriser la catégorie de cookies correspondante.', 'nook' );
	}

	/**
	 * Libellé du bouton du placeholder.
	 *
	 * @return string
	 */
	public static function get_button_label() {
		return __( 'Autoriser et afficher', 'nook' );
	}

	/**
	 * Traduit un slug de service en libellé lisible pour le message du
	 * placeholder, sans dépendre du ServiceRegistry complet (le rendu doit
	 * rester léger et fonctionner aussi pour un service personnalisé).
	 *
	 * Utilisé aussi par Assets::build_guard_config() pour les placeholders
	 * créés côté navigateur (src/frontend/guard.js).
	 *
	 * @param string $slug Slug du service.
	 * @return string
	 */
	public static function get_service_label( $slug ) {
		$labels = array(
			'youtube'            => 'YouTube',
			'vimeo'              => 'Vimeo',
			'google-maps'        => 'Google Maps',
			'google-analytics'   => 'Google Analytics',
			'google-tag-manager' => 'Google Tag Manager',
			'google-ads'         => 'Google Ads',
			'meta-pixel'         => 'Meta Pixel',
		);

		if ( isset( $labels[ $slug ] ) ) {
			return $labels[ $slug ];
		}

		return ucwords( str_replace( '-', ' ', $slug ) );
	}
}
