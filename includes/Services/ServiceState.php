<?php
/**
 * Calcul de l'état effectif d'un service (activé, catégorie, statut) à
 * partir de sa définition, d'une éventuelle correction manuelle et du
 * dernier résultat de détection.
 *
 * Centralise une logique auparavant dupliquée entre l'API REST des services
 * (Nook\REST\ServicesController) et le blocage front (Nook\Frontend\Blocklist)
 * — voir AGENTS.md règle 14 (pas de logique dispersée).
 *
 * @package Nook
 */

namespace Nook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ServiceState
 */
class ServiceState {

	/**
	 * Calcule l'état effectif d'un service natif.
	 *
	 * @param ServiceDefinition $definition Définition native du service.
	 * @param array             $override   Correction manuelle stockée (nook_settings.services[slug]).
	 * @param array|null        $detected   Entrée de détection du dernier scan (ScanResults), ou null.
	 * @return array{enabled: bool, category: string, status: string, detected: bool}
	 */
	public static function compute( ServiceDefinition $definition, array $override, $detected ) {
		$is_detected = null !== $detected ? ! empty( $detected['detected'] ) : false;
		$enabled     = array_key_exists( 'enabled', $override ) ? (bool) $override['enabled'] : $is_detected;

		return array(
			'enabled'  => $enabled,
			'category' => isset( $override['category'] ) ? $override['category'] : $definition->get_category(),
			'status'   => self::compute_status( $enabled, $override, $is_detected ),
			'detected' => $is_detected,
		);
	}

	/**
	 * Calcule l'état effectif d'un service personnalisé (ajouté
	 * manuellement, absent du ServiceRegistry natif).
	 *
	 * @param array $override Données stockées (nook_settings.services[slug]).
	 * @return array{enabled: bool, category: string, status: string, detected: bool}
	 */
	public static function compute_custom( array $override ) {
		$enabled = ! array_key_exists( 'enabled', $override ) || (bool) $override['enabled'];

		return array(
			'enabled'  => $enabled,
			'category' => isset( $override['category'] ) ? $override['category'] : 'marketing',
			'status'   => $enabled ? 'confirmed' : 'disabled',
			'detected' => false,
		);
	}

	/**
	 * Traduit (activé, correction, détection) en statut lisible (voir
	 * ARCHITECTURE.md « Statut des résultats »).
	 *
	 * @param bool  $enabled     Service activé (géré par Nook).
	 * @param array $override    Correction manuelle stockée.
	 * @param bool  $is_detected Le service a-t-il été détecté au dernier scan.
	 * @return string
	 */
	private static function compute_status( $enabled, array $override, $is_detected ) {
		if ( ! $enabled ) {
			return 'disabled';
		}

		if ( ! empty( $override['confirmed'] ) ) {
			return 'confirmed';
		}

		if ( $is_detected ) {
			return 'detected';
		}

		return 'to_review';
	}
}
