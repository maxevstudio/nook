<?php
/**
 * Découverte des URLs internes à analyser par le scanner.
 *
 * Sélectionne un petit nombre d'URLs représentatives du site plutôt que de
 * tenter un crawl exhaustif (voir PROJECT.md §13 : « Ne pas crawler des
 * centaines ou milliers d'URLs »).
 *
 * @package Nook
 */

namespace Nook\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UrlDiscovery
 */
class UrlDiscovery {

	/**
	 * Nombre maximal d'URLs retournées par défaut (voir PROJECT.md §13).
	 *
	 * @var int
	 */
	const DEFAULT_LIMIT = 15;

	/**
	 * Sélectionne une liste d'URLs internes à analyser : page d'accueil,
	 * page de contact si identifiable, quelques pages et articles récents,
	 * pages WooCommerce clés si WooCommerce est actif.
	 *
	 * @param int $limit Nombre maximal d'URLs à retourner.
	 * @return string[] Liste d'URLs uniques, dans l'ordre de priorité.
	 */
	public static function discover( $limit = self::DEFAULT_LIMIT ) {
		$urls = array();

		$urls[] = home_url( '/' );

		$contact_page = self::find_contact_page();
		if ( $contact_page ) {
			$urls[] = $contact_page;
		}

		if ( self::is_woocommerce_active() ) {
			foreach ( self::get_woocommerce_urls() as $wc_url ) {
				$urls[] = $wc_url;
			}
		}

		foreach ( self::get_recent_permalinks( 'page', 5 ) as $page_url ) {
			$urls[] = $page_url;
		}

		foreach ( self::get_recent_permalinks( 'post', 5 ) as $post_url ) {
			$urls[] = $post_url;
		}

		$urls = array_values( array_unique( array_filter( $urls ) ) );

		return array_slice( $urls, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Tente d'identifier une page de contact par son slug puis, à défaut,
	 * par une recherche textuelle sur le titre.
	 *
	 * @return string|false
	 */
	private static function find_contact_page() {
		if ( function_exists( 'get_page_by_path' ) ) {
			$page = get_page_by_path( 'contact' );

			if ( $page ) {
				return get_permalink( $page );
			}
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'posts_per_page' => 1,
				's'              => 'contact',
				'post_status'    => 'publish',
			)
		);

		if ( ! empty( $pages ) ) {
			return get_permalink( $pages[0] );
		}

		return false;
	}

	/**
	 * Retourne les permaliens des publications récentes d'un type donné.
	 *
	 * @param string $post_type Type de publication (`page`, `post`, ...).
	 * @param int    $count     Nombre maximal de publications.
	 * @return string[]
	 */
	private static function get_recent_permalinks( $post_type, $count ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => $count,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return array_filter( array_map( 'get_permalink', $posts ) );
	}

	/**
	 * Détermine si WooCommerce est actif sur le site.
	 *
	 * @return bool
	 */
	private static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Retourne les URLs des pages WooCommerce clés (boutique, panier) si
	 * elles sont configurées.
	 *
	 * @return string[]
	 */
	private static function get_woocommerce_urls() {
		$urls = array();

		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return $urls;
		}

		foreach ( array( 'shop', 'cart' ) as $page_key ) {
			$page_id = wc_get_page_id( $page_key );

			if ( $page_id && $page_id > 0 ) {
				$permalink = get_permalink( $page_id );

				if ( $permalink ) {
					$urls[] = $permalink;
				}
			}
		}

		return $urls;
	}
}
