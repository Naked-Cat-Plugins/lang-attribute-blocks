<?php
/**
 * Page-level lang and dir on the <html> element, and the highlight outline.
 *
 * @package NakedCatPlugins\LangAttr
 */

namespace NakedCatPlugins\LangAttr\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What get_language_attributes() prints for a page with its own language.
 */
final class PageLanguageTest extends TestCase {

	/**
	 * Website direction, page language and direction meta, and the expected attributes.
	 *
	 * @return array
	 */
	public static function pages(): array {
		return array(
			'LTR website, no page language'      => array( 'ltr', '', '', 'lang="en-US"' ),
			'LTR website, LTR page'              => array( 'ltr', 'fr', 'ltr', 'lang="fr"' ),
			'LTR website, RTL page'              => array( 'ltr', 'ar', 'rtl', 'lang="ar" dir="rtl"' ),
			'RTL website, no page language'      => array( 'rtl', '', '', 'dir="rtl" lang="en-US"' ),
			'RTL website, LTR page'              => array( 'rtl', 'fr', 'ltr', 'dir="ltr" lang="fr"' ),
			'RTL website, page without dir meta' => array( 'rtl', 'fr', '', 'dir="ltr" lang="fr"' ),
			'RTL website, RTL page'              => array( 'rtl', 'he', 'rtl', 'dir="rtl" lang="he"' ),
			'Direction without a language'       => array( 'ltr', '', 'rtl', 'lang="en-US"' ),
		);
	}

	/**
	 * The page's language and direction replace the website's.
	 *
	 * @param string $site_dir Website text direction.
	 * @param string $lang     Page language meta.
	 * @param string $dir      Page direction meta.
	 * @param string $expected Expected language attributes.
	 */
	#[DataProvider( 'pages' )]
	public function test_html_attributes( string $site_dir, string $lang, string $dir, string $expected ): void {
		add_filter( 'pre_option_WPLANG', array( $this, 'english' ) );
		$GLOBALS['wp_locale']->text_direction = $site_dir;
		$this->go_to_page( $this->create_page( $lang, $dir ) );
		$attributes = get_language_attributes();
		remove_filter( 'pre_option_WPLANG', array( $this, 'english' ) );
		if ( '' === $lang ) {
			// The website language depends on the install, only the direction is ours to check.
			$expected = preg_replace( '/lang="[^"]*"/', 'lang="' . get_bloginfo( 'language' ) . '"', $expected );
		}
		$this->assertSame( $expected, $attributes );
	}

	/**
	 * Filter callback forcing an English website.
	 *
	 * @return string
	 */
	public function english(): string {
		return '';
	}

	/**
	 * No class is added to <html>: themes print their own there, and two class attributes lose one.
	 */
	public function test_no_class_is_added(): void {
		$this->go_to_page( $this->create_page( 'fr', 'ltr' ) );
		$this->assertStringNotContainsString( 'class=', get_language_attributes() );
	}

	/**
	 * A "$" in the language is printed as is, not read as a regular expression back-reference.
	 */
	public function test_dollar_sign_is_literal(): void {
		$this->go_to_page( $this->create_page( 'x$0y', 'ltr' ) );
		$this->assertSame( 'lang="x$0y"', get_language_attributes() );
	}

	/**
	 * Outside singular views nothing changes.
	 */
	public function test_not_singular_is_untouched(): void {
		$this->create_page( 'fr', 'ltr' );
		$GLOBALS['wp_query']     = new \WP_Query( array( 'post_type' => 'page' ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		$this->assertStringNotContainsString( 'lang="fr"', get_language_attributes() );
	}

	/**
	 * The <html> outline is an inline rule, only for editors with highlighting on, only on pages with a language.
	 *
	 * @param bool   $as_admin  Whether an administrator is logged in.
	 * @param string $lang      Page language meta.
	 * @param bool   $expected  Whether the outline should be added.
	 */
	#[DataProvider( 'outline_cases' )]
	public function test_html_outline( bool $as_admin, string $lang, bool $expected ): void {
		$handle = 'nakedcatplugins-lang-attribute-blocks-style';
		add_filter( 'pre_option_nakedcatplugins_lang_attr_highlight_blocks', '__return_true' );
		if ( $as_admin ) {
			$this->log_in_as_admin();
		} else {
			wp_set_current_user( 0 );
		}
		$this->go_to_page( $this->create_page( $lang, 'ltr' ) );
		wp_deregister_style( $handle );
		$this->plugin->enqueue_frontend_assets();
		$after = (array) wp_styles()->get_data( $handle, 'after' );
		wp_deregister_style( $handle );
		remove_filter( 'pre_option_nakedcatplugins_lang_attr_highlight_blocks', '__return_true' );
		$this->assertSame( $expected, false !== strpos( implode( "\n", $after ), 'html {' ) );
	}

	/**
	 * Who sees the outline, and on which pages.
	 *
	 * @return array
	 */
	public static function outline_cases(): array {
		return array(
			'admin, page with a language'    => array( true, 'fr', true ),
			'admin, page without a language' => array( true, '', false ),
			'visitor'                        => array( false, 'fr', false ),
		);
	}
}
