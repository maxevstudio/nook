<?php
/**
 * Point d'entrée des tests PHP de Nook : `php tests/php/run.php`.
 *
 * @package Nook
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/framework.php';

foreach ( glob( __DIR__ . '/*Test.php' ) as $file ) {
	require $file;
}

$success = nook_run_tests();

exit( $success ? 0 : 1 );
