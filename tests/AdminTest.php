<?php
/**
 * Admin side: website language, list table column, Quick Edit and Bulk Edit.
 *
 * @package NakedCatPlugins\LangAttr
 */

namespace NakedCatPlugins\LangAttr\Tests;

/**
 * Website language placeholder, and saving the page language from the posts list.
 */
final class AdminTest extends TestCase {

	/**
	 * Clear the cached website language before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->set_private( 'website_language', null );
	}

	/**
	 * Clear it again, so a forced locale does not leak into other tests.
	 */
	protected function tearDown(): void {
		$this->set_private( 'website_language', null );
		parent::tearDown();
	}

	/**
	 * A user whose profile language differs from the website's still sees the website's.
	 */
	public function test_website_language_ignores_the_user_locale(): void {
		$site = function () {
			return 'pt_PT';
		};
		$user = function () {
			return 'en_US';
		};
		add_filter( 'locale', $site );
		add_filter( 'determine_locale', $user );
		$bloginfo = get_bloginfo( 'language' );
		$language = $this->call_private( 'get_website_language' );
		remove_filter( 'locale', $site );
		remove_filter( 'determine_locale', $user );

		$this->assertSame( 'en-US', $bloginfo, 'get_bloginfo() follows the user locale, which is the bug being worked around.' );
		$this->assertSame( 'pt-PT', $language );
	}

	/**
	 * The locale is only switched once per request.
	 */
	public function test_website_language_is_cached(): void {
		$switches = 0;
		$count    = function () use ( &$switches ) {
			++$switches;
		};
		add_action( 'change_locale', $count );
		$first  = $this->call_private( 'get_website_language' );
		$before = $switches;
		$second = $this->call_private( 'get_website_language' );
		remove_action( 'change_locale', $count );

		$this->assertSame( $first, $second );
		$this->assertSame( $before, $switches );
	}

	/**
	 * The column shows the language, and carries the raw values for Quick Edit.
	 */
	public function test_list_table_column(): void {
		$post_id = $this->create_page( 'he', 'rtl' );
		ob_start();
		$this->plugin->render_list_table_column( 'nakedcatplugins_lang', $post_id );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-lang="he" data-dir="rtl"', $html );
		$this->assertStringContainsString( '>he<br/>', $html );
	}

	/**
	 * Quick Edit sets, changes and clears the language.
	 */
	public function test_quick_edit(): void {
		$this->log_in_as_admin();
		$post_id = $this->create_page();

		$this->quick_edit( $post_id, ' ar ', 'rtl' );
		$this->assertMeta( $post_id, 'ar', 'rtl' );

		$this->quick_edit( $post_id, 'fr', 'ltr' );
		$this->assertMeta( $post_id, 'fr', 'ltr' );

		$this->quick_edit( $post_id, '', 'rtl' );
		$this->assertMeta( $post_id, '', '' );
	}

	/**
	 * Without its nonce, Quick Edit data is ignored, and an ordinary save touches nothing.
	 */
	public function test_saves_without_our_nonce_change_nothing(): void {
		$this->log_in_as_admin();
		$post_id = $this->create_page( 'fr', 'ltr' );

		$_POST    = array(
			'nakedcatplugins_page_language_quick_edit_nonce' => 'not-a-nonce',
			'nakedcatplugins_quick_edit_lang' => 'de',
		);
		$_REQUEST = $_POST;
		wp_update_post( array( 'ID' => $post_id ) );
		$this->assertMeta( $post_id, 'fr', 'ltr' );

		$_POST    = array();
		$_REQUEST = array();
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Edited',
			)
		);
		$this->assertMeta( $post_id, 'fr', 'ltr' );
	}

	/**
	 * Bulk Edit: change only what was filled in, never a direction without a language, and remove.
	 */
	public function test_bulk_edit(): void {
		$this->log_in_as_admin();
		$with    = $this->create_page( 'ar', 'rtl' );
		$without = $this->create_page();

		$this->bulk_edit( array( $with, $without ), array( 'nakedcatplugins_bulk_edit_dir' => 'ltr' ) );
		$this->assertMeta( $with, 'ar', 'ltr' );
		$this->assertMeta( $without, '', '' );

		$this->bulk_edit( array( $with, $without ), array( 'nakedcatplugins_bulk_edit_lang' => 'en' ) );
		$this->assertMeta( $with, 'en', 'ltr' );
		$this->assertMeta( $without, 'en', 'ltr' );

		$this->bulk_edit( array( $with, $without ), array() );
		$this->assertMeta( $with, 'en', 'ltr' );

		$this->bulk_edit(
			array( $with, $without ),
			array(
				'nakedcatplugins_bulk_edit_remove' => '1',
				'nakedcatplugins_bulk_edit_lang'   => 'fr',
			)
		);
		$this->assertMeta( $with, '', '' );
		$this->assertMeta( $without, '', '' );
	}

	/**
	 * Save a post the way Quick Edit does.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Language field.
	 * @param string $dir     Direction field.
	 */
	private function quick_edit( int $post_id, string $lang, string $dir ): void {
		$_POST    = array(
			'nakedcatplugins_page_language_quick_edit_nonce' => wp_create_nonce( 'nakedcatplugins_page_language_quick_edit' ),
			'nakedcatplugins_quick_edit_lang' => $lang,
			'nakedcatplugins_quick_edit_dir'  => $dir,
		);
		$_REQUEST = $_POST;
		wp_update_post( array( 'ID' => $post_id ) );
	}

	/**
	 * Save posts the way Bulk Edit does.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @param array $fields   Bulk Edit fields.
	 */
	private function bulk_edit( array $post_ids, array $fields ): void {
		$_POST    = array();
		$_REQUEST = array_merge(
			array( 'nakedcatplugins_page_language_bulk_edit_nonce' => wp_create_nonce( 'nakedcatplugins_page_language_bulk_edit' ) ),
			$fields
		);
		foreach ( $post_ids as $post_id ) {
			wp_update_post( array( 'ID' => $post_id ) );
		}
	}

	/**
	 * Assert the stored meta, read raw so the registered 'ltr' default does not hide a missing value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Expected language, or empty for none.
	 * @param string $dir     Expected direction, or empty for none.
	 */
	private function assertMeta( int $post_id, string $lang, string $dir ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit assertion naming.
		$meta = get_post_meta( $post_id );
		$this->assertSame( $lang, $meta['_nakedcatplugins_page_lang'][0] ?? '', 'Language' );
		$this->assertSame( $dir, $meta['_nakedcatplugins_page_dir'][0] ?? '', 'Direction' );
	}
}
