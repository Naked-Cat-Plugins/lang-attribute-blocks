<?php
/**
 * Language code tidying, on save and on output.
 *
 * @package NakedCatPlugins\LangAttr
 */

namespace NakedCatPlugins\LangAttr\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Codes are tidied (never rejected) wherever they are saved or printed. The JS in
 * assets/js/lang-code.js does the same while editing, and must give identical results.
 */
final class LangCodeTest extends TestCase {

	/**
	 * Input and expected tidied code.
	 *
	 * @return array
	 */
	public static function codes(): array {
		return array(
			'already tidy'                 => array( 'pt-PT', 'pt-PT' ),
			'language only'                => array( 'pt', 'pt' ),
			'spaces'                       => array( ' pt-PT ', 'pt-PT' ),
			'WordPress locale'             => array( 'pt_PT', 'pt-PT' ),
			'lowercase region'             => array( 'pt-pt', 'pt-PT' ),
			'uppercase language'           => array( 'EN-us', 'en-US' ),
			'formal locale suffix'         => array( 'de_DE_formal', 'de-DE' ),
			'informal locale suffix'       => array( 'de_CH_informal', 'de-CH' ),
			'orthography locale suffix'    => array( 'pt_PT_ao90', 'pt-PT' ),
			'script casing'                => array( 'zh-hant-tw', 'zh-Hant-TW' ),
			'all caps'                     => array( 'SR-LATN-RS', 'sr-Latn-RS' ),
			'numeric region'               => array( 'es-419', 'es-419' ),
			'variant'                      => array( 'ca-Valencia', 'ca-valencia' ),
			'extension stays lowercase'    => array( 'en-us-u-CA-gregory', 'en-US-u-ca-gregory' ),
			'private use stays lowercase'  => array( 'X-Klingon', 'x-klingon' ),
			'not valid, still only tidied' => array( 'Portuguese', 'portuguese' ),
			'empty'                        => array( '', '' ),
			'HTML is stripped'             => array( '<b>pt</b>', 'pt' ),
		);
	}

	/**
	 * Tidying a code.
	 *
	 * @param string $input    The code as typed.
	 * @param string $expected The tidied code.
	 */
	#[DataProvider( 'codes' )]
	public function test_sanitize_lang_code( string $input, string $expected ): void {
		$this->assertSame( $expected, $this->plugin->sanitize_lang_code( $input ) );
	}

	/**
	 * Non-string values become empty.
	 */
	public function test_non_string_is_empty(): void {
		$this->assertSame( '', $this->plugin->sanitize_lang_code( array( 'pt' ) ) );
		$this->assertSame( '', $this->plugin->sanitize_lang_code( null ) );
	}

	/**
	 * The registered meta tidies the language and limits the direction, whatever path saves it.
	 */
	public function test_meta_is_tidied_on_save(): void {
		$post_id = $this->create_page();
		update_post_meta( $post_id, '_nakedcatplugins_page_lang', 'pt_pt' );
		update_post_meta( $post_id, '_nakedcatplugins_page_dir', 'bogus' );
		$meta = get_post_meta( $post_id );
		$this->assertSame( 'pt-PT', $meta['_nakedcatplugins_page_lang'][0] );
		$this->assertSame( 'ltr', $meta['_nakedcatplugins_page_dir'][0] );
	}

	/**
	 * Codes saved before tidying existed are printed tidied, on the page and on blocks.
	 */
	public function test_old_values_are_tidied_on_output(): void {
		$post_id = $this->create_page();
		// Bypass the sanitize callback, as a value saved by an older version would have.
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->postmeta,
			array(
				'post_id'    => $post_id,
				'meta_key'   => '_nakedcatplugins_page_lang', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pt_PT', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		wp_cache_delete( $post_id, 'post_meta' );
		$this->go_to_page( $post_id );
		$this->assertSame( 'lang="pt-PT"', get_language_attributes() );

		$html = do_blocks( '<!-- wp:group {"lang":"pt_PT"} --><div class="wp-block-group"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringStartsWith( '<div dir="ltr" lang="pt-PT"', $html );
	}
}
