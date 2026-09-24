/**
 * Store `@wordpress/data` pour l'administration de Nook.
 *
 * On utilise `@wordpress/data` plutôt qu'une lib de state management tierce :
 * c'est l'outil natif WordPress pour ce besoin (voir ARCHITECTURE.md §8).
 * L'état d'un formulaire ponctuel (brouillon d'une étape du wizard) reste en
 * revanche local aux composants — voir ARCHITECTURE.md §98.
 */
import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const STORE_NAME = 'nook';

const DEFAULT_STATE = {
	settings: null,
	isLoading: false,
	error: null,
	services: null,
	isLoadingServices: false,
	servicesError: null,
	cookies: null,
	isLoadingCookies: false,
	cookiesError: null,
	scan: {
		isRunning: false,
		error: null,
		lastResults: null,
	},
};

const actions = {
	startLoading() {
		return { type: 'START_LOADING' };
	},
	receiveSettings( settings ) {
		return { type: 'RECEIVE_SETTINGS', settings };
	},
	receiveError( error ) {
		return { type: 'RECEIVE_ERROR', error };
	},

	/**
	 * Sauvegarde un fragment de réglages (fusion profonde côté serveur, voir
	 * Nook\Database\Settings::merge()) et met à jour le store avec la
	 * réponse complète.
	 *
	 * @param {Object} patch Fragment de réglages à fusionner.
	 */
	*saveSettings( patch ) {
		try {
			const saved = yield actions.persistSettings( patch );
			return actions.receiveSettings( saved );
		} catch ( error ) {
			return actions.receiveError( error );
		}
	},
	persistSettings( patch ) {
		return {
			type: 'PERSIST_SETTINGS',
			patch,
		};
	},

	/**
	 * Réinitialise entièrement la configuration Nook (action destructive).
	 */
	*resetSettings() {
		try {
			const reset = yield actions.persistReset();
			return actions.receiveSettings( reset );
		} catch ( error ) {
			return actions.receiveError( error );
		}
	},
	persistReset() {
		return { type: 'PERSIST_RESET' };
	},

	startLoadingServices() {
		return { type: 'START_LOADING_SERVICES' };
	},
	receiveServices( services ) {
		return { type: 'RECEIVE_SERVICES', services };
	},
	receiveServicesError( error ) {
		return { type: 'RECEIVE_SERVICES_ERROR', error };
	},

	/**
	 * Recharge la liste des services depuis le serveur (registre + dernière
	 * détection + corrections manuelles).
	 */
	*fetchServices() {
		yield actions.startLoadingServices();
		try {
			const services = yield actions.requestServices();
			return actions.receiveServices( services );
		} catch ( error ) {
			return actions.receiveServicesError( error );
		}
	},
	requestServices() {
		return { type: 'REQUEST_SERVICES' };
	},

	/**
	 * Met à jour un service (activation, catégorie, confirmation) et
	 * rafraîchit son entrée dans le store.
	 *
	 * @param {string} slug  Identifiant du service.
	 * @param {Object} patch Champs à modifier.
	 */
	*updateService( slug, patch ) {
		try {
			const service = yield actions.persistServiceUpdate( slug, patch );
			return actions.receiveServiceUpdate( service );
		} catch ( error ) {
			return actions.receiveServicesError( error );
		}
	},
	persistServiceUpdate( slug, patch ) {
		return { type: 'PERSIST_SERVICE_UPDATE', slug, patch };
	},
	receiveServiceUpdate( service ) {
		return { type: 'RECEIVE_SERVICE_UPDATE', service };
	},

	startLoadingCookies() {
		return { type: 'START_LOADING_COOKIES' };
	},
	receiveCookies( cookies ) {
		return { type: 'RECEIVE_COOKIES', cookies };
	},
	receiveCookiesError( error ) {
		return { type: 'RECEIVE_COOKIES_ERROR', error };
	},

	/**
	 * Recharge la liste des cookies depuis le serveur (dernier scan +
	 * corrections manuelles).
	 */
	*fetchCookies() {
		yield actions.startLoadingCookies();
		try {
			const cookies = yield actions.requestCookies();
			return actions.receiveCookies( cookies );
		} catch ( error ) {
			return actions.receiveCookiesError( error );
		}
	},
	requestCookies() {
		return { type: 'REQUEST_COOKIES' };
	},

	/**
	 * Met à jour (ou crée) la correction manuelle d'un cookie.
	 *
	 * @param {string} id    Identifiant du cookie.
	 * @param {Object} patch Champs à modifier.
	 */
	*updateCookie( id, patch ) {
		try {
			const cookie = yield actions.persistCookieUpdate( id, patch );
			return actions.receiveCookieUpdate( cookie );
		} catch ( error ) {
			return actions.receiveCookiesError( error );
		}
	},
	persistCookieUpdate( id, patch ) {
		return { type: 'PERSIST_COOKIE_UPDATE', id, patch };
	},
	receiveCookieUpdate( cookie ) {
		return { type: 'RECEIVE_COOKIE_UPDATE', cookie };
	},

	scanStarted() {
		return { type: 'SCAN_STARTED' };
	},
	scanCompleted( payload ) {
		return { type: 'SCAN_COMPLETED', payload };
	},
	scanFailed( error ) {
		return { type: 'SCAN_FAILED', error };
	},

	/**
	 * Lance une analyse du site (synchrone côté serveur, voir
	 * ARCHITECTURE.md « Architecture du scanner »). Met à jour à la fois
	 * l'état du scan et `settings.scanner`/`services` avec les résultats.
	 */
	*runScan() {
		yield actions.scanStarted();
		try {
			const response = yield actions.requestScan();
			return actions.scanCompleted( response );
		} catch ( error ) {
			return actions.scanFailed( error );
		}
	},
	requestScan() {
		return { type: 'REQUEST_SCAN' };
	},
};

const controls = {
	PERSIST_SETTINGS( { patch } ) {
		return apiFetch( {
			path: '/nook/v1/settings',
			method: 'POST',
			data: patch,
		} );
	},
	PERSIST_RESET() {
		return apiFetch( {
			path: '/nook/v1/settings/reset',
			method: 'POST',
		} );
	},
	REQUEST_SERVICES() {
		return apiFetch( { path: '/nook/v1/services' } );
	},
	PERSIST_SERVICE_UPDATE( { slug, patch } ) {
		return apiFetch( {
			path: `/nook/v1/services/${ slug }`,
			method: 'POST',
			data: patch,
		} );
	},
	REQUEST_COOKIES() {
		return apiFetch( { path: '/nook/v1/cookies' } );
	},
	PERSIST_COOKIE_UPDATE( { id, patch } ) {
		return apiFetch( {
			path: `/nook/v1/cookies/${ id }`,
			method: 'POST',
			data: patch,
		} );
	},
	REQUEST_SCAN() {
		return apiFetch( {
			path: '/nook/v1/scan',
			method: 'POST',
		} );
	},
};

const resolvers = {
	*getSettings() {
		yield actions.startLoading();
		try {
			const settings = yield apiFetch( { path: '/nook/v1/settings' } );
			return actions.receiveSettings( settings );
		} catch ( error ) {
			return actions.receiveError( error );
		}
	},
	getServices: actions.fetchServices,
	getCookies: actions.fetchCookies,
};

const selectors = {
	getSettings( state ) {
		return state.settings;
	},
	isSetupCompleted( state ) {
		return Boolean( state.settings && state.settings.setupCompleted );
	},
	isLoading( state ) {
		return state.isLoading;
	},
	getError( state ) {
		return state.error;
	},
	getServices( state ) {
		return state.services || [];
	},
	isLoadingServices( state ) {
		return state.isLoadingServices;
	},
	getServicesError( state ) {
		return state.servicesError;
	},
	getCookies( state ) {
		return state.cookies || [];
	},
	isLoadingCookies( state ) {
		return state.isLoadingCookies;
	},
	getCookiesError( state ) {
		return state.cookiesError;
	},
	isScanRunning( state ) {
		return state.scan.isRunning;
	},
	getScanError( state ) {
		return state.scan.error;
	},
	getLastScanResults( state ) {
		return state.scan.lastResults;
	},
};

function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'START_LOADING':
			return { ...state, isLoading: true, error: null };
		case 'RECEIVE_SETTINGS':
			return {
				...state,
				settings: action.settings,
				isLoading: false,
				error: null,
			};
		case 'RECEIVE_ERROR':
			return { ...state, isLoading: false, error: action.error };

		case 'START_LOADING_SERVICES':
			return { ...state, isLoadingServices: true, servicesError: null };
		case 'RECEIVE_SERVICES':
			return {
				...state,
				services: action.services,
				isLoadingServices: false,
			};
		case 'RECEIVE_SERVICES_ERROR':
			return {
				...state,
				isLoadingServices: false,
				servicesError: action.error,
			};
		case 'RECEIVE_SERVICE_UPDATE': {
			const services = ( state.services || [] ).some(
				( service ) => service.slug === action.service.slug
			)
				? state.services.map( ( service ) =>
						service.slug === action.service.slug
							? action.service
							: service
					)
				: [ ...( state.services || [] ), action.service ];
			return { ...state, services };
		}

		case 'START_LOADING_COOKIES':
			return { ...state, isLoadingCookies: true, cookiesError: null };
		case 'RECEIVE_COOKIES':
			return {
				...state,
				cookies: action.cookies,
				isLoadingCookies: false,
			};
		case 'RECEIVE_COOKIES_ERROR':
			return {
				...state,
				isLoadingCookies: false,
				cookiesError: action.error,
			};
		case 'RECEIVE_COOKIE_UPDATE': {
			const cookies = ( state.cookies || [] ).some(
				( cookie ) => cookie.id === action.cookie.id
			)
				? state.cookies.map( ( cookie ) =>
						cookie.id === action.cookie.id ? action.cookie : cookie
					)
				: [ ...( state.cookies || [] ), action.cookie ];
			return { ...state, cookies };
		}

		case 'SCAN_STARTED':
			return {
				...state,
				scan: { ...state.scan, isRunning: true, error: null },
			};
		case 'SCAN_COMPLETED':
			return {
				...state,
				scan: {
					isRunning: false,
					error: null,
					lastResults: action.payload,
				},
				settings: state.settings
					? {
							...state.settings,
							scanner: {
								...state.settings.scanner,
								status: action.payload.status,
								summary: action.payload.summary,
							},
						}
					: state.settings,
			};
		case 'SCAN_FAILED':
			return {
				...state,
				scan: { ...state.scan, isRunning: false, error: action.error },
			};

		default:
			return state;
	}
}

export const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
	resolvers,
	controls,
} );

register( store );

export const STORE_KEY = STORE_NAME;
