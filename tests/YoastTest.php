<?php
/**
 * The page's language in Yoast SEO's schema and Open Graph locale.
 *
 * @package NakedCatPlugins\LangAttr
 */

namespace NakedCatPlugins\LangAttr\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Yoast SEO declares every page in the website's language; these filters correct it.
 */
final class YoastTest extends TestCase {

	/**
	 * A graph shaped like Yoast's, with the pieces that carry inLanguage.
	 *
	 * @return array
	 */
	private function graph(): array {
		return array(
			array( '@type' => 'WebPage', 'inLanguage' => 'en-US' ),
			array( '@type' => 'Article', 'inLanguage' => 'en-US' ),
			array( '@type' => 'ImageObject', 'inLanguage' => 'en-US' ),
			array( '@type' => array( 'WebPage', 'FAQPage' ), 'inLanguage' => 'en-US' ),
			array( '@type' => 'BreadcrumbList' ),
			array( '@type' => 'WebSite', 'inLanguage' => 'en-US' ),
			array( '@type' => array( 'Organization', 'Brand' ), 'inLanguage' => 'en-US' ),
		);
	}

	/**
	 * Page pieces take the page's language; website pieces and pieces without a language don't change.
	 */
	public function test_schema_uses_page_language(): void {
		$this->go_to_page( $this->create_page( 'pt-PT', 'ltr' ) );
		$graph = $this->plugin->apply_page_lang_to_yoast_schema( $this->graph() );
		$this->assertSame( array( 'pt-PT', 'pt-PT', 'pt-PT', 'pt-PT' ), array_column( array_slice( $graph, 0, 4 ), 'inLanguage' ) );
		$this->assertArrayNotHasKey( 'inLanguage', $graph[4] );
		$this->assertSame( 'en-US', $graph[5]['inLanguage'] );
		$this->assertSame( 'en-US', $graph[6]['inLanguage'] );
	}

	/**
	 * Without a page language, the graph is returned untouched.
	 */
	public function test_schema_without_page_language(): void {
		$this->go_to_page( $this->create_page( '', '' ) );
		$this->assertSame( $this->graph(), $this->plugin->apply_page_lang_to_yoast_schema( $this->graph() ) );
	}

	/**
	 * Page language and the Open Graph locale it gives.
	 *
	 * @return array
	 */
	public static function locales(): array {
		return array(
			'Language and region'      => array( 'pt-PT', 'pt_PT' ),
			'Brazilian Portuguese'     => array( 'pt-BR', 'pt_BR' ),
			'Language only'            => array( 'pt', 'en_US' ),
			'Script, not a region'     => array( 'zh-Hant', 'en_US' ),
			'No page language'         => array( '', 'en_US' ),
		);
	}

	/**
	 * The Open Graph locale follows the page language when it has a region.
	 *
	 * @param string $lang     Page language meta.
	 * @param string $expected Expected Open Graph locale.
	 */
	#[DataProvider( 'locales' )]
	public function test_og_locale( string $lang, string $expected ): void {
		$this->go_to_page( $this->create_page( $lang, 'ltr' ) );
		$this->assertSame( $expected, $this->plugin->apply_page_lang_to_yoast_og_locale( 'en_US' ) );
	}
}
