<?php
/**
 * Shared base for the plugin's tests.
 *
 * @package NakedCatPlugins\LangAttr
 */

namespace NakedCatPlugins\LangAttr\Tests;

use NakedCatPlugins\LangAttr\Lang_Attribute_Blocks;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Creates fixtures and guarantees they are removed again, and puts back the global state
 * (current user, query, text direction, request superglobals) the tests change.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * The plugin instance under test.
	 *
	 * @var Lang_Attribute_Blocks
	 */
	protected $plugin;

	/**
	 * Post IDs created during a test.
	 *
	 * @var int[]
	 */
	private $created_posts = array();

	/**
	 * Global state saved in setUp() and put back in tearDown().
	 *
	 * @var array
	 */
	private $saved = array();

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->plugin = Lang_Attribute_Blocks::get_instance();
		$this->saved  = array(
			'user'           => get_current_user_id(),
			'wp_query'       => $GLOBALS['wp_query'] ?? null,
			'wp_the_query'   => $GLOBALS['wp_the_query'] ?? null,
			'text_direction' => $GLOBALS['wp_locale']->text_direction,
			'post'           => $_POST, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'request'        => $_REQUEST, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	/**
	 * Remove every fixture and put the global state back.
	 */
	protected function tearDown(): void {
		foreach ( array_reverse( $this->created_posts ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->created_posts = array();

		wp_set_current_user( $this->saved['user'] );
		$GLOBALS['wp_query']                  = $this->saved['wp_query'];
		$GLOBALS['wp_the_query']              = $this->saved['wp_the_query'];
		$GLOBALS['wp_locale']->text_direction = $this->saved['text_direction'];
		$_POST                                = $this->saved['post'];
		$_REQUEST                             = $this->saved['request'];
		parent::tearDown();
	}

	/**
	 * Create a published page, removed again in tearDown().
	 *
	 * @param string $lang Page language meta, or empty for none.
	 * @param string $dir  Page direction meta, or empty for none.
	 * @return int The page ID.
	 */
	protected function create_page( string $lang = '', string $dir = '' ): int {
		$meta = array();
		if ( '' !== $lang ) {
			$meta['_nakedcatplugins_page_lang'] = $lang;
		}
		if ( '' !== $dir ) {
			$meta['_nakedcatplugins_page_dir'] = $dir;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Language Attribute test page',
				'post_status' => 'publish',
				'meta_input'  => $meta,
			)
		);
		$this->assertIsInt( $post_id );
		$this->created_posts[] = $post_id;
		return $post_id;
	}

	/**
	 * Make a page the main query, as on its frontend view.
	 *
	 * @param int $post_id The page ID.
	 */
	protected function go_to_page( int $post_id ): void {
		$GLOBALS['wp_query']     = new \WP_Query( array( 'page_id' => $post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	}

	/**
	 * Log in as an administrator from the install.
	 */
	protected function log_in_as_admin(): void {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$this->assertNotEmpty( $admins, 'The install needs an administrator.' );
		wp_set_current_user( (int) $admins[0] );
	}

	/**
	 * Call one of the plugin's private methods.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed The method's return value.
	 */
	protected function call_private( string $method, array $args = array() ) {
		$reflection = new \ReflectionMethod( $this->plugin, $method );
		return $reflection->invokeArgs( $this->plugin, $args );
	}

	/**
	 * Set one of the plugin's private properties.
	 *
	 * @param string $property Property name.
	 * @param mixed  $value    Value.
	 */
	protected function set_private( string $property, $value ): void {
		$reflection = new \ReflectionProperty( $this->plugin, $property );
		$reflection->setValue( $this->plugin, $value );
	}
}
