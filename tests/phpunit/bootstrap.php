<?php
declare ( strict_types = 1 );

use Pixelgrade\WPPostNotes\Tests\Framework\PHPUnitUtil;
use Pixelgrade\WPPostNotes\Tests\Framework\TestSuite;
use Psr\Log\NullLogger;

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'Pixelgrade\WPPostNotes\RUNNING_UNIT_TESTS', true );
define( 'Pixelgrade\WPPostNotes\TESTS_DIR', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/Fixture/wp-content/plugins' );

if ( 'Unit' === PHPUnitUtil::get_current_suite() ) {
	// For the Unit suite we shouldn't need WordPress loaded.
	// This keeps them fast.
	return;
}

require_once dirname( __DIR__, 2 ) . '/vendor/antecedent/patchwork/Patchwork.php';

$suite = new TestSuite();

$GLOBALS['wp_tests_options'] = [
	'active_plugins'  => [],
	'timezone_string' => 'Europe/Bucharest',
];

$suite->bootstrap();
