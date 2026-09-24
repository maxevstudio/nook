<?php
/**
 * Mini framework de test sans dépendance (voir tests/php/README.md).
 *
 * Nook n'installe pas PHPUnit par défaut (pas de dépendance Composer
 * obligatoire à l'exécution — voir AGENTS.md). Ce framework volontairement
 * minimal suffit à valider les modules PHP critiques en environnement de
 * développement ou de CI simple (`php tests/php/run.php`).
 *
 * @package Nook
 */

$GLOBALS['__nook_tests'] = array();

/**
 * Enregistre un cas de test.
 *
 * @param string   $name     Description lisible du test.
 * @param callable $callback Fonction du test (lève une exception en cas d'échec).
 * @return void
 */
function nook_test( $name, callable $callback ) {
	$GLOBALS['__nook_tests'][] = array(
		'name'     => $name,
		'callback' => $callback,
	);
}

/**
 * Vérifie qu'une condition est vraie.
 *
 * @param bool   $condition Condition à vérifier.
 * @param string $message   Message d'erreur si la condition est fausse.
 * @return void
 * @throws RuntimeException Si la condition est fausse.
 */
function nook_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Vérifie l'égalité stricte de deux valeurs.
 *
 * @param mixed  $expected Valeur attendue.
 * @param mixed  $actual   Valeur obtenue.
 * @param string $message  Message d'erreur optionnel.
 * @return void
 * @throws RuntimeException Si les valeurs diffèrent.
 */
function nook_assert_equals( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		$details = sprintf(
			"%s\nAttendu : %s\nObtenu  : %s",
			$message ?: 'Valeurs différentes',
			print_r( $expected, true ),
			print_r( $actual, true )
		);
		throw new RuntimeException( $details );
	}
}

/**
 * Exécute tous les tests enregistrés et affiche un résumé.
 *
 * @return bool True si tous les tests réussissent.
 */
function nook_run_tests() {
	$failures = 0;
	$total    = count( $GLOBALS['__nook_tests'] );

	foreach ( $GLOBALS['__nook_tests'] as $test ) {
		nook_test_reset_state();

		try {
			call_user_func( $test['callback'] );
			echo "  [OK]   {$test['name']}\n";
		} catch ( Throwable $e ) {
			++$failures;
			echo "  [FAIL] {$test['name']}\n";
			echo '         ' . str_replace( "\n", "\n         ", $e->getMessage() ) . "\n";
		}
	}

	echo "\n" . ( $total - $failures ) . '/' . $total . " tests réussis.\n";

	return 0 === $failures;
}
