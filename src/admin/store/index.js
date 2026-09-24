/**
 * Store `@wordpress/data` pour l'administration de Nook.
 *
 * On utilise `@wordpress/data` plutôt qu'une lib de state management tierce :
 * c'est l'outil natif WordPress pour ce besoin (voir ARCHITECTURE.md §8).
 */
import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const STORE_NAME = 'nook';

const DEFAULT_STATE = {
	settings: null,
	isLoading: false,
	error: null,
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
	*saveSettings( settings ) {
		const saved = yield actions.persistSettings( settings );
		return actions.receiveSettings( saved );
	},
	persistSettings( settings ) {
		return {
			type: 'PERSIST_SETTINGS',
			settings,
		};
	},
};

const controls = {
	PERSIST_SETTINGS( { settings } ) {
		return apiFetch( {
			path: '/nook/v1/settings',
			method: 'POST',
			data: settings,
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
};

const selectors = {
	getSettings( state ) {
		return state.settings;
	},
	isConfigured( state ) {
		return Boolean( state.settings && state.settings.configured );
	},
	isLoading( state ) {
		return state.isLoading;
	},
	getError( state ) {
		return state.error;
	},
};

function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'START_LOADING':
			return { ...state, isLoading: true, error: null };
		case 'RECEIVE_SETTINGS':
			return { ...state, settings: action.settings, isLoading: false };
		case 'RECEIVE_ERROR':
			return { ...state, isLoading: false, error: action.error };
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
