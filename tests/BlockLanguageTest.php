<?php
/**
 * Block-level lang and dir on the frontend.
 *
 * @package NakedCatPlugins\LangAttr
 */

namespace NakedCatPlugins\LangAttr\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The language must land on the block's wrapper, whatever element that is, and nowhere else.
 */
final class BlockLanguageTest extends TestCase {

	/**
	 * Block markup and the wrapper element that should carry the language.
	 *
	 * Navigation and Page List are rendered by PHP, so their markup comes from core, not from here.
	 *
	 * @return array
	 */
	public static function wrappers(): array {
		return array(
			'Group'                       => array( '<!-- wp:group {"lang":"fr"} --><div class="wp-block-group"><div class="x"><p>x</p></div></div><!-- /wp:group -->', 'DIV', 'wp-block-group' ),
			'Group as section'            => array( '<!-- wp:group {"lang":"fr","tagName":"section"} --><section class="wp-block-group"><div class="x"><p>x</p></div></section><!-- /wp:group -->', 'SECTION', 'wp-block-group' ),
			'Columns'                     => array( '<!-- wp:columns {"lang":"fr"} --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><p>x</p></div><!-- /wp:column --></div><!-- /wp:columns -->', 'DIV', 'wp-block-columns' ),
			'Cover as section'            => array( '<!-- wp:cover {"lang":"fr","tagName":"section","dimRatio":50} --><section class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div></section><!-- /wp:cover -->', 'SECTION', 'wp-block-cover' ),
			'Navigation, overlay never'   => array( '<!-- wp:navigation {"lang":"fr","overlayMenu":"never"} --><!-- wp:navigation-link {"label":"A","url":"/a"} /--><!-- /wp:navigation -->', 'NAV', 'wp-block-navigation' ),
			'Navigation, overlay default' => array( '<!-- wp:navigation {"lang":"fr"} --><!-- wp:navigation-link {"label":"A","url":"/a"} /--><!-- /wp:navigation -->', 'NAV', 'wp-block-navigation' ),
			'Page List'                   => array( '<!-- wp:page-list {"lang":"fr"} /-->', 'UL', 'wp-block-page-list' ),
		);
	}

	/**
	 * The wrapper gets lang and dir, and it is the only element that does.
	 *
	 * @param string $markup  Block markup.
	 * @param string $tag     Expected wrapper tag name.
	 * @param string $wrapper_class   A class only the wrapper has.
	 */
	#[DataProvider( 'wrappers' )]
	public function test_language_goes_on_the_wrapper( string $markup, string $tag, string $wrapper_class ): void {
		$processor = new \WP_HTML_Tag_Processor( do_blocks( $markup ) );
		$found     = array();
		while ( $processor->next_tag() ) {
			if ( null !== $processor->get_attribute( 'lang' ) ) {
				$found[] = array( $processor->get_tag(), $processor->has_class( $wrapper_class ), $processor->get_attribute( 'lang' ), $processor->get_attribute( 'dir' ) );
			}
		}
		$this->assertSame( array( array( $tag, true, 'fr', 'ltr' ) ), $found );
	}

	/**
	 * Post Content can be any element, and is rendered from the post, so call the filter directly.
	 */
	public function test_post_content_with_a_tag_name(): void {
		$html = $this->plugin->process_blocks(
			'<main class="wp-block-post-content"><div class="wp-block-group"><p>a</p></div><p>b</p></main>',
			array(
				'blockName' => 'core/post-content',
				'attrs'     => array(
					'lang'    => 'fr',
					'tagName' => 'main',
				),
			)
		);
		$this->assertStringStartsWith( '<main dir="ltr" lang="fr" class="wp-block-post-content"><div class="wp-block-group">', $html );
	}

	/**
	 * Submenus are list items inside the navigation.
	 */
	public function test_submenu_gets_its_own_language(): void {
		$html      = do_blocks( '<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:navigation-submenu {"label":"S","url":"/s","lang":"de"} --><!-- wp:navigation-link {"label":"A","url":"/a"} /--><!-- /wp:navigation-submenu --><!-- /wp:navigation -->' );
		$processor = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'wp-block-navigation-submenu' ) ) );
		$this->assertSame( array( 'LI', 'de', 'ltr' ), array( $processor->get_tag(), $processor->get_attribute( 'lang' ), $processor->get_attribute( 'dir' ) ) );
	}

	/**
	 * Values are escaped once, by the tag processor, not twice.
	 */
	public function test_language_is_escaped_once(): void {
		$processor = new \WP_HTML_Tag_Processor( do_blocks( '<!-- wp:group {"lang":"fr\"x"} --><div class="wp-block-group"><p>x</p></div><!-- /wp:group -->' ) );
		$processor->next_tag();
		$this->assertSame( 'fr"x', $processor->get_attribute( 'lang' ) );
	}

	/**
	 * Anything other than rtl is output as ltr.
	 */
	public function test_unknown_direction_becomes_ltr(): void {
		$html = do_blocks( '<!-- wp:group {"lang":"fr","dir":"bogus"} --><div class="wp-block-group"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringContainsString( 'dir="ltr"', $html );
		$this->assertStringNotContainsString( 'bogus', $html );
	}

	/**
	 * Right to left is kept.
	 */
	public function test_rtl_direction_is_kept(): void {
		$html = do_blocks( '<!-- wp:group {"lang":"ar","dir":"rtl"} --><div class="wp-block-group"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringStartsWith( '<div dir="rtl" lang="ar"', $html );
	}

	/**
	 * Empty or whitespace-only languages leave the block alone, rather than output lang="".
	 *
	 * @param string $lang The language attribute.
	 */
	#[DataProvider( 'empty_languages' )]
	public function test_empty_language_changes_nothing( string $lang ): void {
		$markup = '<div class="wp-block-group"><p>x</p></div>';
		$html   = $this->plugin->process_blocks(
			$markup,
			array(
				'blockName' => 'core/group',
				'attrs'     => array( 'lang' => $lang ),
			)
		);
		$this->assertSame( $markup, $html );
	}

	/**
	 * Languages that must not produce any attribute.
	 *
	 * @return array
	 */
	public static function empty_languages(): array {
		return array(
			'empty'  => array( '' ),
			'spaces' => array( '   ' ),
		);
	}
}
