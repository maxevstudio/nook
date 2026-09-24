<?php
/**
 * Nettoyage à la désinstallation du plugin.
 *
 * @package Nook
 */

// WordPress appelle ce fichier uniquement lors d'une désinstallation réelle
// (pas une simple désactivation) et définit toujours cette constante avant.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Supprime les réglages stockés par Nook. Le consentement des visiteurs vit
// côté navigateur et n'est pas concerné (voir ARCHITECTURE.md §3.5).
delete_option( 'nook_settings' );
delete_option( 'nook_db_version' );
delete_option( 'nook_consent_config_version' );
