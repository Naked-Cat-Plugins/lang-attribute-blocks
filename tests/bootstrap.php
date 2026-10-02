<?php
/**
 * Test bootstrap.
 *
 * Integration tests: what they assert is the markup WordPress ends up printing, so they need
 * a real WordPress with the plugin active. Rather than the wp-phpunit library, which wants the
 * mysql binaries to build a throwaway database, this boots an existing install through
 * wp-load.php. Every test removes what it creates, so the install is left as it was.
 *
 * Point WP_ROOT at the WordPress directory, or let it walk up from here, which works for a
 * plugin living inside a normal wp-content/plugins tree.
 *
 * @package NakedCatPlugins\LangAttr
 */

$nakedcatplugins_lang_attr_root = getenv( 'WP_ROOT' );
if ( ! $nakedcatplugins_lang_attr_root ) {
	$nakedcatplugins_lang_attr_root = dirname( __DIR__, 4 );
}
$nakedcatplugins_lang_attr_root = rtrim( $nakedcatplugins_lang_attr_root, '/' );

if ( ! file_exists( $nakedcatplugins_lang_attr_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Could not find wp-load.php in {$nakedcatplugins_lang_attr_root}.\nSet WP_ROOT to the WordPress directory.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $nakedcatplugins_lang_attr_root . '/wp-load.php';

if ( ! class_exists( '\NakedCatPlugins\LangAttr\Lang_Attribute_Blocks' ) ) {
	fwrite( STDERR, "Language Attribute for Container Blocks and Pages/Posts is not active on this install.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

require_once __DIR__ . '/TestCase.php';
