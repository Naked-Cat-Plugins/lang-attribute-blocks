<?php
/**
 * Experimental: switching WordPress to the language of the page being shown.
 *
 * Needs pt_BR installed on the WordPress install the tests run on, and ja not installed.
 *
 * @package NakedCatPlugins\LangAttr
 */

namespace NakedCatPlugins\LangAttr\Tests;

use NakedCatPlugins\LangAttr\Lang_Attribute_Blocks_Locale;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The locale switch, the language code mapping, and the Plugins page install link.
 */
final class LocaleTest extends TestCase {

	/**
	 * The locale feature under test.
	 *
	 * @var Lang_Attribute_Blocks_Locale
	 */
	private $locale;

	/**
	 * The feature filter's callbacks, put back in tearDown().
	 *
	 * @var mixed
	 */
	private $saved_filter;

	/**
	 * An install in progress on the site, set aside in setUp() and put back in tearDown().
	 *
	 * @var array
	 */
	private $saved_install = array();

	/**
	 * Set up: the feature off, whatever the install turned on.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->locale       = Lang_Attribute_Blocks_Locale::get_instance( $this->plugin );
		$this->saved_filter = $GLOBALS['wp_filter'][ Lang_Attribute_Blocks_Locale::FILTER ] ?? null;
		remove_all_filters( Lang_Attribute_Blocks_Locale::FILTER );
		$this->saved_install = array(
			'queue'  => get_option( Lang_Attribute_Blocks_Locale::QUEUE_OPTION ),
			'events' => array_filter( _get_cron_array(), fn ( $hooks ) => isset( $hooks[ Lang_Attribute_Blocks_Locale::CRON_HOOK ] ) ),
		);
		delete_option( Lang_Attribute_Blocks_Locale::QUEUE_OPTION );
		$this->clear_install_events();
	}

	/**
	 * Remove every scheduled background install, whatever its run number.
	 */
	private function clear_install_events(): void {
		wp_unschedule_hook( Lang_Attribute_Blocks_Locale::CRON_HOOK );
	}

	/**
	 * Undo any switch and put the filter back.
	 */
	protected function tearDown(): void {
		while ( is_locale_switched() ) {
			restore_previous_locale();
		}
		$this->clear_install_events();
		delete_option( Lang_Attribute_Blocks_Locale::QUEUE_OPTION );
		if ( false !== $this->saved_install['queue'] ) {
			update_option( Lang_Attribute_Blocks_Locale::QUEUE_OPTION, $this->saved_install['queue'], false );
		}
		foreach ( $this->saved_install['events'] as $timestamp => $hooks ) {
			foreach ( $hooks[ Lang_Attribute_Blocks_Locale::CRON_HOOK ] as $event ) {
				wp_schedule_single_event( $timestamp, Lang_Attribute_Blocks_Locale::CRON_HOOK, $event['args'] );
			}
		}
		remove_all_filters( Lang_Attribute_Blocks_Locale::FILTER );
		if ( null !== $this->saved_filter ) {
			$GLOBALS['wp_filter'][ Lang_Attribute_Blocks_Locale::FILTER ] = $this->saved_filter;
		}
		parent::tearDown();
	}

	/**
	 * Language codes and the locale each one gets.
	 *
	 * @return array
	 */
	public static function codes(): array {
		$all = array( 'en_US', 'pt_PT', 'pt_PT_ao90', 'pt_BR', 'pt_AO', 'de_DE', 'zh_CN', 'zh_TW', 'sr_RS', 'fr_FR' );
		return array(
			'region'                      => array( 'pt-PT', $all, 'pt_PT' ),
			'region, other case'          => array( 'pt-br', $all, 'pt_BR' ),
			'WordPress style'             => array( 'pt_AO', $all, 'pt_AO' ),
			'no region, default locale'   => array( 'pt', $all, 'pt_PT' ),
			'no region, German'           => array( 'de', $all, 'de_DE' ),
			'English'                     => array( 'en', $all, 'en_US' ),
			'Chinese traditional script'  => array( 'zh-Hant', $all, 'zh_TW' ),
			'Chinese simplified script'   => array( 'zh-Hans', $all, 'zh_CN' ),
			'script and region'           => array( 'sr-Latn-RS', $all, 'sr_RS' ),
			'region missing, same lang'   => array( 'pt-AO', array( 'en_US', 'pt_PT' ), 'pt_PT' ),
			'region missing, other'       => array( 'de-AT', $all, 'de_DE' ),
			'language missing'            => array( 'ja', $all, null ),
			'empty'                       => array( '', $all, null ),
		);
	}

	/**
	 * Each language code gets the expected locale.
	 *
	 * @param string      $lang     Language code.
	 * @param string[]    $locales  Locales to choose from.
	 * @param string|null $expected Expected locale.
	 */
	#[DataProvider( 'codes' )]
	public function test_lang_to_locale( string $lang, array $locales, ?string $expected ): void {
		$this->assertSame( $expected, $this->locale->lang_to_locale( $lang, $locales ) );
	}

	/**
	 * Off by default: a page with its own language does not switch.
	 */
	public function test_off_by_default(): void {
		$this->go_to_page( $this->create_page( 'pt-BR' ) );
		$this->locale->switch_to_page_locale();
		$this->assertFalse( is_locale_switched() );
	}

	/**
	 * On: a page with its own language switches to it, and the website's language stays the website's.
	 */
	public function test_switches_to_the_page_locale(): void {
		$this->assertContains( 'pt_BR', get_available_languages(), 'The install needs pt_BR.' );
		add_filter( Lang_Attribute_Blocks_Locale::FILTER, '__return_true' );
		$this->set_private( 'website_language', null );
		$website = $this->plugin->get_website_language();
		$this->set_private( 'website_language', null );

		$this->go_to_page( $this->create_page( 'pt-BR' ) );
		$this->locale->switch_to_page_locale();

		$this->assertTrue( is_locale_switched() );
		$this->assertSame( 'pt_BR', get_locale() );
		$this->assertSame( $website, $this->plugin->get_website_language() );
	}

	/**
	 * The filter gets the page and its language, and can say no for one page.
	 */
	public function test_filter_gets_the_page(): void {
		$page_id = $this->create_page( 'pt-BR' );
		$seen    = array();
		add_filter(
			Lang_Attribute_Blocks_Locale::FILTER,
			function ( $enabled, $post_id, $lang ) use ( &$seen ) {
				$seen = array( $post_id, $lang );
				return false;
			},
			10,
			3
		);
		$this->go_to_page( $page_id );
		$this->locale->switch_to_page_locale();
		$this->assertSame( array( $page_id, 'pt-BR' ), $seen );
		$this->assertFalse( is_locale_switched() );
	}

	/**
	 * On, but the language is not installed: no switch.
	 */
	public function test_no_switch_when_not_installed(): void {
		$this->assertNotContains( 'ja', get_available_languages(), 'The install must not have ja.' );
		add_filter( Lang_Attribute_Blocks_Locale::FILTER, '__return_true' );
		$this->go_to_page( $this->create_page( 'ja' ) );
		$this->locale->switch_to_page_locale();
		$this->assertFalse( is_locale_switched() );
	}

	/**
	 * On, but the page has no language of its own: no switch.
	 */
	public function test_no_switch_without_a_page_language(): void {
		add_filter( Lang_Attribute_Blocks_Locale::FILTER, '__return_true' );
		$this->go_to_page( $this->create_page() );
		$this->locale->switch_to_page_locale();
		$this->assertFalse( is_locale_switched() );
	}

	/**
	 * Missing languages: those used by published pages, not installed, that WordPress.org has.
	 */
	public function test_missing_locales(): void {
		$this->create_page( 'ja' );
		$this->create_page( 'pt-BR' );
		$missing = $this->locale->get_missing_locales( array( 'ja', 'pt_BR', 'pt_PT' ) );
		$this->assertContains( 'ja', $missing );
		$this->assertNotContains( 'pt_BR', $missing );
	}

	/**
	 * The Plugins page link: only with the feature on, for administrators, when a language is missing.
	 */
	public function test_install_link(): void {
		$this->create_page( 'ja' );
		$links = array( 'deactivate' => 'Deactivate' );

		$this->log_in_as_admin();
		$this->assertSame( $links, $this->locale->add_install_link( $links ), 'Off by default.' );

		add_filter( Lang_Attribute_Blocks_Locale::FILTER, '__return_true' );
		wp_set_current_user( 0 );
		$this->assertSame( $links, $this->locale->add_install_link( $links ), 'Not for visitors.' );

		$this->log_in_as_admin();
		$with_link = $this->locale->add_install_link( $links );
		$this->assertCount( 2, $with_link );
		$this->assertStringContainsString( 'ja', end( $with_link ) );
		$this->assertStringContainsString( Lang_Attribute_Blocks_Locale::INSTALL_ACTION, end( $with_link ) );
	}

	/**
	 * Saving a language WordPress does not have schedules one background install; nothing for an
	 * installed language, and nothing with the feature off.
	 */
	public function test_saving_a_missing_language_schedules_the_install(): void {
		$page_id = $this->create_page();

		update_post_meta( $page_id, '_nakedcatplugins_page_lang', 'ja' );
		$this->assertFalse( wp_next_scheduled( Lang_Attribute_Blocks_Locale::CRON_HOOK ), 'Off by default.' );

		add_filter( Lang_Attribute_Blocks_Locale::FILTER, '__return_true' );
		update_post_meta( $page_id, '_nakedcatplugins_page_lang', 'pt-BR' );
		$this->assertFalse( wp_next_scheduled( Lang_Attribute_Blocks_Locale::CRON_HOOK ), 'Installed already.' );
		update_post_meta( $page_id, '_nakedcatplugins_page_lang', 'pt' );
		$this->assertFalse( wp_next_scheduled( Lang_Attribute_Blocks_Locale::CRON_HOOK ), 'Default locale installed already.' );

		update_post_meta( $page_id, '_nakedcatplugins_page_lang', 'ja' );
		$this->assertIsInt( wp_next_scheduled( Lang_Attribute_Blocks_Locale::CRON_HOOK ) );

		$other = $this->create_page();
		update_post_meta( $other, '_nakedcatplugins_page_lang', 'ja' );
		$this->assertCount( 1, array_filter( _get_cron_array(), fn ( $hooks ) => isset( $hooks[ Lang_Attribute_Blocks_Locale::CRON_HOOK ] ) ), 'Scheduled once.' );
	}

	/**
	 * A site can choose another installed locale for a language, and an uninstalled one is ignored.
	 */
	public function test_page_locale_filter(): void {
		$this->assertContains( 'pt_PT', get_available_languages(), 'The install needs pt_PT.' );
		add_filter( Lang_Attribute_Blocks_Locale::FILTER, '__return_true' );
		$page_id = $this->create_page( 'pt-BR' );
		$choose  = function ( $locale, $lang, $post_id ) use ( $page_id ) {
			return 'pt-BR' === $lang && $page_id === $post_id ? 'pt_PT' : $locale;
		};
		add_filter( 'nakedcatplugins_lang_attribute_blocks_page_locale', $choose, 10, 3 );
		$this->go_to_page( $page_id );
		$this->locale->switch_to_page_locale();
		remove_filter( 'nakedcatplugins_lang_attribute_blocks_page_locale', $choose, 10 );
		$this->assertSame( 'pt_PT', get_locale() );
		restore_previous_locale();

		$uninstalled = fn () => 'ja';
		add_filter( 'nakedcatplugins_lang_attribute_blocks_page_locale', $uninstalled );
		$this->locale->switch_to_page_locale();
		remove_filter( 'nakedcatplugins_lang_attribute_blocks_page_locale', $uninstalled );
		$this->assertFalse( is_locale_switched() );
	}
}
