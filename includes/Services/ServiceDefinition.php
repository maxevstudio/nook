<?php
/**
 * Contrat commun à toute définition de service tiers (GA4, GTM, Meta Pixel...).
 *
 * Chaque service est une classe dédiée dans includes/Services/ : on évite
 * volontairement une classe monolithique listant tous les services (voir
 * AGENTS.md règle 14 et ARCHITECTURE.md §3.2).
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ServiceDefinition
 */
interface ServiceDefinition {

	/**
	 * Identifiant unique et stable du service.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Nom lisible du service.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Catégorie de consentement associée par défaut.
	 *
	 * @return string necessary|functional|statistics|marketing
	 */
	public function get_category();

	/**
	 * Domaines associés au service (pour la détection par le scanner).
	 *
	 * @return string[]
	 */
	public function get_domains();

	/**
	 * Motifs de noms de cookies posés par ce service (les jokers `*` sont supportés).
	 *
	 * @return string[]
	 */
	public function get_cookie_patterns();

	/**
	 * Signatures de scripts/URLs à intercepter avant consentement.
	 *
	 * @return string[]
	 */
	public function get_script_patterns();

	/**
	 * Domaines d'iframes à remplacer par un placeholder avant consentement.
	 *
	 * @return string[]
	 */
	public function get_iframe_patterns();

	/**
	 * Description humaine du service.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * URL de la politique de confidentialité de l'éditeur du service, si connue.
	 *
	 * @return string|null
	 */
	public function get_privacy_url();
}
