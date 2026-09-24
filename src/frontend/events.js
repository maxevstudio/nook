/**
 * Petite API d'événements JavaScript permettant à des intégrations tierces
 * de réagir au consentement (voir PROJECT.md §44). Reste volontairement
 * minimale : de simples `CustomEvent` sur `document`.
 *
 * Événements émis :
 * - `nook:consent-ready`    (consentement déjà valide au chargement)
 * - `nook:consent-updated`  (nouveau choix enregistré)
 * - `nook:category-enabled` (une catégorie vient d'être activée)
 * - `nook:category-disabled` (une catégorie vient d'être désactivée)
 */

/**
 * Émet un événement Nook sur `document`.
 *
 * @param {string} name   Nom de l'événement (ex. `nook:consent-updated`).
 * @param {Object} detail Données associées à l'événement.
 */
export function dispatchNookEvent( name, detail ) {
	document.dispatchEvent( new CustomEvent( name, { detail } ) );
}
