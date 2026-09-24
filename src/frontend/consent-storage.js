/**
 * Stockage du consentement côté navigateur (voir ARCHITECTURE.md §6).
 *
 * Choix : cookie first-party (source de vérité, lisible côté serveur si
 * besoin futur) + miroir localStorage (lecture rapide, tolérance si les
 * cookies sont bloqués par ailleurs). Ce cookie lui-même est un cookie
 * strictement nécessaire : il ne mémorise qu'un choix de préférences, pas un
 * identifiant de suivi (voir PROJECT.md §40).
 */

const COOKIE_NAME = 'nook_consent';
const STORAGE_KEY = 'nookConsent';
const FORMAT_VERSION = 1;

/**
 * Lit un cookie par son nom.
 *
 * @param {string} name Nom du cookie.
 * @return {string|null} Valeur décodée, ou null si absent.
 */
function readCookie( name ) {
	const escaped = name.replace( /([.$?*|{}()[\]\\/+^])/g, '\\$1' );
	const match = document.cookie.match(
		new RegExp( '(?:^|; )' + escaped + '=([^;]*)' )
	);

	return match ? decodeURIComponent( match[ 1 ] ) : null;
}

/**
 * Écrit un cookie first-party.
 *
 * @param {string} name  Nom du cookie.
 * @param {string} value Valeur (sera encodée).
 * @param {number} days  Durée de conservation, en jours.
 */
function writeCookie( name, value, days ) {
	const date = new Date();
	date.setTime( date.getTime() + days * 24 * 60 * 60 * 1000 );
	const secure = 'https:' === window.location.protocol ? '; Secure' : '';

	document.cookie =
		name +
		'=' +
		encodeURIComponent( value ) +
		'; expires=' +
		date.toUTCString() +
		'; path=/; SameSite=Lax' +
		secure;
}

/**
 * Supprime un cookie.
 *
 * @param {string} name Nom du cookie.
 */
function deleteCookie( name ) {
	document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
}

/**
 * Récupère le consentement mémorisé (localStorage en priorité, cookie en
 * repli), ou null si aucun choix n'a encore été fait ou si les données
 * stockées sont invalides.
 *
 * @return {Object|null} Enregistrement de consentement, ou null.
 */
export function getConsent() {
	let raw = null;

	try {
		raw = window.localStorage.getItem( STORAGE_KEY );
	} catch {
		// localStorage indisponible (navigation privée stricte, etc.) : on se
		// rabat sur le cookie ci-dessous.
	}

	if ( ! raw ) {
		raw = readCookie( COOKIE_NAME );
	}

	if ( ! raw ) {
		return null;
	}

	try {
		const parsed = JSON.parse( raw );

		if ( parsed && 'object' === typeof parsed.categories ) {
			return parsed;
		}
	} catch {
		return null;
	}

	return null;
}

/**
 * Enregistre un nouveau choix de consentement.
 *
 * @param {Object} categories    Catégories acceptées/refusées ({ slug: bool }).
 * @param {number} configVersion Version de configuration de consentement courante.
 * @param {number} durationDays  Durée de conservation, en jours.
 * @return {Object} L'enregistrement de consentement sauvegardé.
 */
export function setConsent( categories, configVersion, durationDays ) {
	const record = {
		version: FORMAT_VERSION,
		configVersion,
		date: new Date().toISOString(),
		categories,
	};

	const serialized = JSON.stringify( record );

	writeCookie( COOKIE_NAME, serialized, durationDays || 180 );

	try {
		window.localStorage.setItem( STORAGE_KEY, serialized );
	} catch {
		// Le cookie reste la source de vérité si localStorage échoue.
	}

	return record;
}

/**
 * Efface le consentement mémorisé (retrait complet).
 */
export function clearConsent() {
	deleteCookie( COOKIE_NAME );

	try {
		window.localStorage.removeItem( STORAGE_KEY );
	} catch {
		// no-op
	}
}

/**
 * Détermine si un consentement mémorisé est encore valide pour la
 * configuration courante (voir ARCHITECTURE.md §7 — `isConsentValid()`
 * centralisé ici plutôt que dispersé).
 *
 * @param {Object|null} consent              Consentement mémorisé.
 * @param {number}      currentConfigVersion Version de configuration courante du site.
 * @return {boolean} True si le consentement est encore valide.
 */
export function isConsentValid( consent, currentConfigVersion ) {
	if ( ! consent || 'object' !== typeof consent.categories ) {
		return false;
	}

	return consent.configVersion === currentConfigVersion;
}
