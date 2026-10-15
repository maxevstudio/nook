#!/usr/bin/env node
/**
 * Script de release Nook.
 *
 * Usage :
 *   npm run release
 *   npm run release -- --version=1.2.0
 *   npm run release -- --package-only   (CI : empaquette la version déjà
 *                                        commitée, sans toucher aux fichiers)
 *
 * Voir RELEASE.md pour la description complète du processus. Ce script
 * s'arrête (code de sortie non nul) dès qu'une étape critique échoue : aucune
 * release invalide n'est produite (voir AGENTS.md, règle 16).
 */
import { execSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, cpSync, readFileSync, writeFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) );
const PKG_PATH = path.join( ROOT, 'package.json' );
const PLUGIN_FILE = path.join( ROOT, 'nook.php' );
const README_TXT = path.join( ROOT, 'readme.txt' );
const CHANGELOG = path.join( ROOT, 'CHANGELOG.md' );
const DIST_DIR = path.join( ROOT, 'dist' );
const DIST_PLUGIN_DIR = path.join( DIST_DIR, 'nook' );

const args = process.argv.slice( 2 );
const versionArg = args
	.map( ( arg ) => arg.match( /^--version=(.+)$/ ) )
	.find( Boolean );
const packageOnly = args.includes( '--package-only' );

function log( step, message ) {
	console.log( `\n\u001b[1m[${ step }]\u001b[0m ${ message }` );
}

function fail( message ) {
	console.error( `\n\u001b[31mErreur release : ${ message }\u001b[0m` );
	process.exit( 1 );
}

function run( command ) {
	execSync( command, { cwd: ROOT, stdio: 'inherit' } );
}

function readJSON( file ) {
	return JSON.parse( readFileSync( file, 'utf8' ) );
}

/**
 * 1. Vérifie l'état du projet (best-effort si git est indisponible).
 */
function verifyProject() {
	log( '1/12', 'Vérification du projet' );

	try {
		execSync( 'git rev-parse --is-inside-work-tree', { cwd: ROOT, stdio: 'ignore' } );
		const status = execSync( 'git status --porcelain', { cwd: ROOT } ).toString().trim();
		if ( status ) {
			console.warn( '  Avertissement : des modifications non commitées sont présentes.' );
		}
	} catch ( error ) {
		console.warn( '  Avertissement : git indisponible, vérification ignorée.' );
	}
}

/**
 * 2. Lint / tests (best-effort tant que la suite de tests n'est pas complète).
 */
function lintAndTest() {
	log( '2/12', 'Lint et tests' );

	const pkg = readJSON( PKG_PATH );

	if ( pkg.scripts && pkg.scripts[ 'lint:js' ] ) {
		try {
			run( 'npm run lint:js' );
		} catch ( error ) {
			fail( 'Le lint JavaScript a échoué.' );
		}
	}

	if ( pkg.scripts && pkg.scripts[ 'test:php' ] ) {
		try {
			run( 'npm run test:php' );
		} catch ( error ) {
			fail( 'Les tests PHP ont échoué.' );
		}
	}

	const hasJsTests = hasTestFiles( path.join( ROOT, 'tests', 'js' ) );

	if ( pkg.scripts && pkg.scripts.test && hasJsTests ) {
		try {
			run( 'npm test' );
		} catch ( error ) {
			fail( 'Les tests ont échoué.' );
		}
	} else if ( pkg.scripts && pkg.scripts.test ) {
		console.log( '  Aucun test JS trouvé dans tests/js — étape ignorée (voir STATUS.md).' );
	}
}

/**
 * Vérifie s'il existe au moins un fichier de test dans un dossier.
 */
function hasTestFiles( dir ) {
	if ( ! existsSync( dir ) ) {
		return false;
	}
	return readdirSync( dir, { recursive: true } ).some( ( file ) =>
		/\.(test|spec)\.jsx?$/.test( file )
	);
}

/**
 * 3. Build de production.
 */
function buildProduction() {
	log( '3/12', 'Build de production' );

	try {
		run( 'npm run build' );
	} catch ( error ) {
		fail( 'Le build de production a échoué.' );
	}

	if ( ! existsSync( path.join( ROOT, 'build', 'admin', 'index.js' ) ) ) {
		fail( 'Le build admin est introuvable après compilation.' );
	}

	if ( ! existsSync( path.join( ROOT, 'build', 'frontend', 'index.js' ) ) ) {
		fail( 'Le build frontend est introuvable après compilation.' );
	}
}

/**
 * Calcule la version finale (argument explicite ou incrément patch).
 */
function resolveVersion( currentVersion ) {
	if ( versionArg ) {
		return versionArg[ 1 ];
	}

	const [ major, minor, patch ] = currentVersion
		.replace( /-dev$/, '' )
		.split( '.' )
		.map( Number );

	return `${ major }.${ minor }.${ patch + 1 }`;
}

/**
 * 4-6. Applique la version dans package.json, nook.php et readme.txt.
 */
function applyVersion( version ) {
	log( '4-6/12', `Application de la version ${ version }` );

	const pkg = readJSON( PKG_PATH );
	pkg.version = version;
	writeFileSync( PKG_PATH, JSON.stringify( pkg, null, 2 ) + '\n' );

	let pluginContents = readFileSync( PLUGIN_FILE, 'utf8' );
	pluginContents = pluginContents.replace(
		/(\* Version:\s+).+/,
		`$1${ version }`
	);
	pluginContents = pluginContents.replace(
		/define\(\s*'NOOK_VERSION',\s*'.+?'\s*\);/,
		`define( 'NOOK_VERSION', '${ version }' );`
	);
	writeFileSync( PLUGIN_FILE, pluginContents );

	if ( existsSync( README_TXT ) ) {
		let readme = readFileSync( README_TXT, 'utf8' );
		readme = readme.replace( /(Stable tag:\s+).+/, `$1${ version }` );
		writeFileSync( README_TXT, readme );
	}

	return version;
}

/**
 * 7-8. Met à jour CHANGELOG.md (transforme [Unreleased] en section datée).
 */
function updateChangelog( version ) {
	log( '7-8/12', 'Mise à jour de CHANGELOG.md' );

	let changelog = readFileSync( CHANGELOG, 'utf8' );
	const unreleasedMatch = changelog.match( /## \[Unreleased\]\n([\s\S]*?)(?=\n## |\n?$)/ );

	if ( ! unreleasedMatch || ! unreleasedMatch[ 1 ].trim() ) {
		fail( 'La section [Unreleased] de CHANGELOG.md est vide : rien à publier.' );
	}

	const date = new Date().toLocaleDateString( 'fr-FR', {
		year: 'numeric',
		month: 'long',
		day: 'numeric',
	} );

	const newSection = `## [Unreleased]\n\n## ${ version } — ${ date }\n${ unreleasedMatch[ 1 ] }`;
	changelog = changelog.replace( unreleasedMatch[ 0 ], newSection.trimEnd() + '\n' );
	writeFileSync( CHANGELOG, changelog );

	return date;
}

/**
 * 9-10. Prépare dist/nook/ et copie uniquement les fichiers utiles.
 */
const DIST_INCLUDES = [
	'nook.php',
	'uninstall.php',
	'includes',
	'build',
	'data',
	'assets',
	'languages',
	'readme.txt',
	'LICENSE',
];

function prepareDistribution() {
	log( '9-10/12', 'Préparation du dossier de distribution' );

	rmSync( DIST_DIR, { recursive: true, force: true } );
	mkdirSync( DIST_PLUGIN_DIR, { recursive: true } );

	for ( const entry of DIST_INCLUDES ) {
		const source = path.join( ROOT, entry );
		if ( ! existsSync( source ) ) {
			continue;
		}
		cpSync( source, path.join( DIST_PLUGIN_DIR, entry ), { recursive: true } );
	}
}

/**
 * 11. Vérifie que la distribution ne contient rien d'indésirable.
 */
const FORBIDDEN_ENTRIES = [ 'node_modules', '.git', 'src', 'tests', 'dist', 'package.json', 'package-lock.json' ];

function verifyDistribution() {
	log( '11/12', 'Vérification de la distribution' );

	if ( ! existsSync( path.join( DIST_PLUGIN_DIR, 'nook.php' ) ) ) {
		fail( 'nook.php est absent de la distribution.' );
	}

	for ( const forbidden of FORBIDDEN_ENTRIES ) {
		if ( existsSync( path.join( DIST_PLUGIN_DIR, forbidden ) ) ) {
			fail( `L'élément interdit "${ forbidden }" est présent dans la distribution.` );
		}
	}
}

/**
 * Écrit build-manifest.json dans dist/nook/.
 */
function writeBuildManifest( version, date ) {
	const manifest = {
		name: 'Nook',
		version,
		buildDate: new Date().toISOString().slice( 0, 10 ),
		environment: 'production',
	};
	writeFileSync(
		path.join( DIST_PLUGIN_DIR, 'build-manifest.json' ),
		JSON.stringify( manifest, null, 2 ) + '\n'
	);
}

/**
 * 12. Génère le ZIP final via la commande `zip` du système (pas de
 * dépendance npm supplémentaire pour un besoin aussi simple).
 */
function createZip( version ) {
	log( '12/12', 'Génération du ZIP' );

	const zipName = `nook-${ version }.zip`;
	const zipPath = path.join( DIST_DIR, zipName );

	if ( existsSync( zipPath ) ) {
		rmSync( zipPath );
	}

	try {
		execSync( `zip -r -q -X "${ zipName }" "nook"`, { cwd: DIST_DIR, stdio: 'inherit' } );
	} catch ( error ) {
		fail( 'La commande "zip" a échoué ou est indisponible sur ce système.' );
	}

	if ( ! existsSync( zipPath ) ) {
		fail( 'Le ZIP de release n’a pas été généré.' );
	}

	return zipPath;
}

/**
 * En mode --package-only (CI), vérifie que le tag Git poussé correspond à la
 * version déjà inscrite dans les fichiers : un ZIP ne doit jamais annoncer
 * une autre version que celle du tag de la release GitHub.
 */
function verifyTagMatchesVersion( version ) {
	const tag = process.env.GITHUB_REF_TYPE === 'tag' ? process.env.GITHUB_REF_NAME : '';

	if ( tag && tag.replace( /^v/, '' ) !== version ) {
		fail( `Le tag ${ tag } ne correspond pas à la version ${ version } de package.json.` );
	}

	const pluginContents = readFileSync( PLUGIN_FILE, 'utf8' );
	if ( ! pluginContents.includes( `define( 'NOOK_VERSION', '${ version }' );` ) ) {
		fail( `NOOK_VERSION dans nook.php ne correspond pas à ${ version }.` );
	}
}

async function main() {
	const pkg = readJSON( PKG_PATH );
	const version = packageOnly ? pkg.version : resolveVersion( pkg.version );

	verifyProject();
	if ( packageOnly ) {
		verifyTagMatchesVersion( version );
	}
	lintAndTest();
	buildProduction();
	let date = null;
	if ( ! packageOnly ) {
		applyVersion( version );
		date = updateChangelog( version );
	}
	prepareDistribution();
	verifyDistribution();
	writeBuildManifest( version, date );
	const zipPath = await createZip( version );

	console.log( `\n\u001b[32mRelease ${ version } prête : ${ path.relative( ROOT, zipPath ) }\u001b[0m` );
}

main().catch( ( error ) => {
	fail( error.message || String( error ) );
} );
