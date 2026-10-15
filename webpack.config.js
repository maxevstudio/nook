/**
 * Configuration webpack personnalisée pour Nook.
 *
 * Nook a besoin de deux points d'entrée totalement séparés :
 *  - `admin`    : l'application React de l'administration (jamais livrée au front public).
 *  - `frontend` : le runtime public (vanilla JS, sans React), très léger,
 *    plus son garde de blocage dynamique `frontend/guard`.
 *
 * On repart de la configuration par défaut de @wordpress/scripts (gestion du
 * CSS, minification, extraction des dépendances @wordpress/*, i18n, ...) et on
 * remplace uniquement `entry`/`output` pour obtenir cette séparation.
 *
 * Voir https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/#advanced-usage
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin/index': path.resolve( __dirname, 'src/admin/index.js' ),
		'frontend/index': path.resolve( __dirname, 'src/frontend/index.js' ),
		// Garde de blocage dynamique, chargé seul dans le <head> (voir
		// src/frontend/guard.js et ARCHITECTURE.md §3.4).
		'frontend/guard': path.resolve( __dirname, 'src/frontend/guard.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
};
