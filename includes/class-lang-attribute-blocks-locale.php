<?php
/**
 * Experimental: show pages and posts with their own language in that language, interface included.
 *
 * Off by default. When the nakedcatplugins_lang_attribute_blocks_switch_locale filter returns
 * true, a singular page or post with its own language switches WordPress to the matching locale
 * for the whole frontend request, so the theme's and plugins' texts (dates, comments, post
 * navigation...) come out in that language, as long as WordPress has it installed. Saving a page
 * or post with a language WordPress does not have installs it in the background, and a link on
 * the Plugins page installs any still missing.
 *
 * @package NakedCatPlugins\LangAttr
 * @since   3.4
 */

namespace NakedCatPlugins\LangAttr;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Locale switching and language pack installation.
 */
final class Lang_Attribute_Blocks_Locale {

	/**
	 * The filter that turns the feature on.
	 *
	 * @since 3.4
	 * @var string
	 */
	const FILTER = 'nakedcatplugins_lang_attribute_blocks_switch_locale';

	/**
	 * The admin-post action that installs the missing languages.
	 *
	 * @since 3.4
	 * @var string
	 */
	const INSTALL_ACTION = 'nakedcatplugins_lang_attribute_blocks_install_languages';

	/**
	 * The WP-Cron hook that installs the missing languages in the background.
	 *
	 * @since 3.4
	 * @var string
	 */
	const CRON_HOOK = 'nakedcatplugins_lang_attribute_blocks_install_languages';

	/**
	 * The option holding the plugin and theme translations still to install, between runs.
	 *
	 * @since 3.4
	 * @var string
	 */
	const QUEUE_OPTION = 'nakedcatplugins_lang_attribute_blocks_install_queue';

	/**
	 * How many plugin and theme translations each background run installs.
	 *
	 * @since 3.4
	 * @var int
	 */
	const BATCH = 10;

	/**
	 * The default WordPress locale for language codes with no region, when that is not the code
	 * itself. Verified against https://api.wordpress.org/translations/core/1.0/
	 *
	 * @since 3.4
	 * @var array<string,string>
	 */
	const DEFAULT_LOCALES = array(
		'be' => 'bel',
		'bg' => 'bg_BG',
		'bn' => 'bn_BD',
		'bs' => 'bs_BA',
		'cs' => 'cs_CZ',
		'da' => 'da_DK',
		'de' => 'de_DE',
		'en' => 'en_US',
		'es' => 'es_ES',
		'fa' => 'fa_IR',
		'fr' => 'fr_FR',
		'gl' => 'gl_ES',
		'he' => 'he_IL',
		'hi' => 'hi_IN',
		'hu' => 'hu_HU',
		'id' => 'id_ID',
		'is' => 'is_IS',
		'it' => 'it_IT',
		'jv' => 'jv_ID',
		'ka' => 'ka_GE',
		'ko' => 'ko_KR',
		'lt' => 'lt_LT',
		'mk' => 'mk_MK',
		'ml' => 'ml_IN',
		'ms' => 'ms_MY',
		'my' => 'my_MM',
		'nb' => 'nb_NO',
		'ne' => 'ne_NP',
		'nl' => 'nl_NL',
		'nn' => 'nn_NO',
		'no' => 'nb_NO',
		'pa' => 'pa_IN',
		'pl' => 'pl_PL',
		'pt' => 'pt_PT',
		'ro' => 'ro_RO',
		'ru' => 'ru_RU',
		'si' => 'si_LK',
		'sk' => 'sk_SK',
		'sl' => 'sl_SI',
		'sr' => 'sr_RS',
		'sv' => 'sv_SE',
		'ta' => 'ta_IN',
		'tr' => 'tr_TR',
		'tt' => 'tt_RU',
		'ug' => 'ug_CN',
		'uz' => 'uz_UZ',
		'zh' => 'zh_CN',
	);

	/**
	 * Chinese script subtags, which WordPress expresses as regions.
	 *
	 * @since 3.4
	 * @var array<string,string>
	 */
	const SCRIPT_LOCALES = array(
		'zh_hans' => 'zh_CN',
		'zh_hant' => 'zh_TW',
	);

	/**
	 * The singleton instance.
	 *
	 * @since 3.4
	 * @var Lang_Attribute_Blocks_Locale|null
	 */
	protected static $instance = null;

	/**
	 * The main plugin instance.
	 *
	 * @since 3.4
	 * @var Lang_Attribute_Blocks
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @since 3.4
	 * @param Lang_Attribute_Blocks $plugin The main plugin instance.
	 */
	private function __construct( Lang_Attribute_Blocks $plugin ) {
		$this->plugin = $plugin;
		add_action( 'wp', array( $this, 'switch_to_page_locale' ), 1 );
		add_filter( 'plugin_action_links_' . plugin_basename( NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ), array( $this, 'add_install_link' ), 20 );
		add_action( 'admin_post_' . self::INSTALL_ACTION, array( $this, 'install_languages' ) );
		add_action( 'admin_notices', array( $this, 'install_notice' ) );
		// A page or post saved with a language: install it in the background if it is missing
		add_action( 'added_post_meta', array( $this, 'schedule_install' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'schedule_install' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_install' ) );
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @since 3.4
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the instance.
	 *
	 * @since 3.4
	 * @throws \Exception When attempting to unserialize the singleton instance.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @since 3.4
	 * @param Lang_Attribute_Blocks $plugin The main plugin instance.
	 * @return Lang_Attribute_Blocks_Locale The singleton instance.
	 */
	public static function get_instance( Lang_Attribute_Blocks $plugin ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin );
		}
		return self::$instance;
	}

	/**
	 * Whether the feature is on.
	 *
	 * @since 3.4
	 * @param int    $post_id The page or post being shown, or 0 outside the frontend.
	 * @param string $lang    Its language code, or empty outside the frontend.
	 * @return bool
	 */
	private function is_enabled( int $post_id = 0, string $lang = '' ): bool {
		/**
		 * Experimental: show pages and posts with their own language in that language, with the
		 * theme's and plugins' texts translated, and add a link on the Plugins page to install
		 * the languages they use. Off by default.
		 *
		 * @since 3.4
		 * @param bool   $enabled Whether to switch. Default false.
		 * @param int    $post_id The page or post being shown, or 0 when deciding whether to show the Plugins page link.
		 * @param string $lang    Its language code, e.g. 'pt-PT', or empty when deciding whether to show the Plugins page link.
		 */
		return (bool) apply_filters( self::FILTER, false, $post_id, $lang );
	}

	/**
	 * Switch WordPress to the language of the page or post being shown.
	 *
	 * Runs on 'wp', the first point where the requested page is known; texts translated earlier
	 * in the request stay in the website's language. Nothing happens when WordPress does not have
	 * the language installed, so a page never ends up half translated.
	 *
	 * @since 3.4
	 * @hook wp
	 * @return void
	 */
	public function switch_to_page_locale() {
		if ( is_admin() || wp_doing_ajax() || wp_is_serving_rest_request() || is_feed() || ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		$lang    = $this->plugin->get_post_lang( $post_id );
		if ( '' === $lang || ! $this->is_enabled( $post_id, $lang ) ) {
			return;
		}
		$installed = $this->get_installed_locales();
		$locale    = $this->lang_to_locale( $lang, $installed );
		/**
		 * The locale a page or post with its own language switches to, e.g. to show pt-AO pages in
		 * pt_PT when a theme has no pt_AO translation. Ignored unless the locale is installed.
		 *
		 * @since 3.4
		 * @param string|null $locale  The installed locale matching the language, or null if none.
		 * @param string      $lang    The page's language code, e.g. 'pt-AO'.
		 * @param int         $post_id The page or post ID.
		 */
		$locale = apply_filters( 'nakedcatplugins_lang_attribute_blocks_page_locale', $locale, $lang, $post_id );
		if ( ! is_string( $locale ) || ! in_array( $locale, $installed, true ) || get_locale() === $locale ) {
			return;
		}
		// Read and cache the website's language first: once switched, get_locale() returns the page's.
		$this->plugin->get_website_language();
		switch_to_locale( $locale );
	}

	/**
	 * The locales WordPress can show, the default en_US included.
	 *
	 * @since 3.4
	 * @return string[]
	 */
	private function get_installed_locales(): array {
		return array_merge( array( 'en_US' ), get_available_languages() );
	}

	/**
	 * The WordPress locale for a language code, among the given locales.
	 *
	 * Tries the code as a locale (pt-PT is pt_PT), then Chinese scripts (zh-Hant is zh_TW), then
	 * the language's default locale (pt is pt_PT), then any locale of the same language (pt-AO,
	 * with no pt_AO, falls back to an installed pt_PT or pt_BR).
	 *
	 * @since 3.4
	 * @param string   $lang    Language code, e.g. 'pt-PT'.
	 * @param string[] $locales Locales to choose from.
	 * @return string|null The locale, or null when none matches.
	 */
	public function lang_to_locale( string $lang, array $locales ): ?string {
		$code = strtolower( str_replace( '-', '_', trim( $lang ) ) );
		if ( '' === $code ) {
			return null;
		}
		$by_key = array();
		foreach ( $locales as $locale ) {
			$by_key[ strtolower( $locale ) ] = $locale;
		}
		$parts     = explode( '_', $code );
		$language  = $parts[0];
		$candidate = array( $code );
		if ( isset( self::SCRIPT_LOCALES[ $language . '_' . ( $parts[1] ?? '' ) ] ) ) {
			$candidate[] = strtolower( self::SCRIPT_LOCALES[ $language . '_' . $parts[1] ] );
		}
		// A language and region with a script in between (sr-Latn-RS): try without the script.
		if ( 3 === count( $parts ) ) {
			$candidate[] = $language . '_' . $parts[2];
		}
		$candidate[] = strtolower( self::DEFAULT_LOCALES[ $language ] ?? $language );
		foreach ( $candidate as $key ) {
			if ( isset( $by_key[ $key ] ) ) {
				return $by_key[ $key ];
			}
		}
		foreach ( $by_key as $key => $locale ) {
			if ( explode( '_', $key )[0] === $language ) {
				return $locale;
			}
		}
		return null;
	}

	/**
	 * The locales used by pages and posts (drafts included, so they are ready by the time they are
	 * published) that WordPress does not have installed, among those it can install.
	 *
	 * @since 3.4
	 * @param string[] $available Locales WordPress.org has translations for.
	 * @return string[]
	 */
	public function get_missing_locales( array $available ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One distinct list, only on the Plugins page and the install request.
		$langs     = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_nakedcatplugins_page_lang' AND pm.meta_value <> '' AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )"
		);
		$installed = $this->get_installed_locales();
		$missing   = array();
		foreach ( $langs as $lang ) {
			// The page's own locale, even when a related one is installed: a pt-AO page offers
			// pt_AO although it already switches to an installed pt_PT in the meantime.
			$locale = $this->lang_to_locale( $this->plugin->sanitize_lang_code( $lang ), $available );
			if ( null !== $locale && ! in_array( $locale, $installed, true ) ) {
				$missing[] = $locale;
			}
		}
		$missing = array_values( array_unique( $missing ) );
		sort( $missing );
		return $missing;
	}

	/**
	 * Whether the current user can install languages here.
	 *
	 * @since 3.4
	 * @return bool
	 */
	private function can_install(): bool {
		if ( ! current_user_can( 'install_languages' ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		return wp_can_install_language_pack();
	}

	/**
	 * The locales WordPress.org has core translations for, from WordPress' own cached list.
	 *
	 * @since 3.4
	 * @return string[]
	 */
	private function get_available_locales(): array {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		return array_keys( wp_get_available_translations() );
	}

	/**
	 * Add a link to install the missing languages, next to the plugin on the Plugins page.
	 *
	 * Only when the feature is on, the user can install languages, and a language used by a
	 * page or post is missing.
	 *
	 * @since 3.4
	 * @hook plugin_action_links_{plugin_file}
	 * @param array $links The plugin's action links.
	 * @return array
	 */
	public function add_install_link( $links ) {
		if ( ! $this->is_enabled() || ! $this->can_install() ) {
			return $links;
		}
		if ( null !== $this->get_queue() ) {
			$links[] = esc_html__( 'Installing translations…', 'lang-attribute-blocks' );
			return $links;
		}
		$missing = $this->get_missing_locales( $this->get_available_locales() );
		if ( ! $missing ) {
			return $links;
		}
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::INSTALL_ACTION ), self::INSTALL_ACTION ) ),
			/* translators: %s: comma-separated list of WordPress locales, e.g. pt_PT, pt_BR */
			esc_html( sprintf( __( 'Download translations (%s)', 'lang-attribute-blocks' ), implode( ', ', $missing ) ) )
		);
		return $links;
	}

	/**
	 * When a page or post is saved with a language, install it in the background if WordPress
	 * does not have it.
	 *
	 * Covers every way the language is set (editor panel, classic metabox, Quick Edit, Bulk
	 * Edit), drafts included, so the language is ready by the time the page is published. The
	 * download runs in WP-Cron, not during the save, and does not depend on the user saving.
	 *
	 * @since 3.4
	 * @hook added_post_meta, updated_post_meta
	 * @param int    $meta_id  Meta ID.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public function schedule_install( $meta_id, $post_id, $meta_key ) {
		if ( '_nakedcatplugins_page_lang' !== $meta_key || ! $this->is_enabled() ) {
			return;
		}
		$lang = $this->plugin->sanitize_lang_code( get_post_meta( (int) $post_id, '_nakedcatplugins_page_lang', true ) );
		// Installed exactly: nothing to do, and no need to wake WP-Cron.
		if ( '' === $lang || in_array( str_replace( '-', '_', $lang ), $this->get_installed_locales(), true ) ) {
			return;
		}
		$this->schedule_background_install();
	}

	/**
	 * The translations still to install, or null when no install is running. A queue with no
	 * progress for 15 minutes is dropped: the run carrying it died (fatal error, server restart).
	 *
	 * @since 3.4
	 * @return array|null
	 */
	private function get_queue(): ?array {
		$queue = get_option( self::QUEUE_OPTION );
		if ( ! is_array( $queue ) || ! isset( $queue['time'], $queue['items'] ) || $queue['time'] < time() - 15 * MINUTE_IN_SECONDS ) {
			if ( false !== $queue ) {
				delete_option( self::QUEUE_OPTION );
			}
			return null;
		}
		return $queue['items'];
	}

	/**
	 * Schedule the background install, unless it is already scheduled or running.
	 *
	 * @since 3.4
	 * @return void
	 */
	private function schedule_background_install(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) && null === $this->get_queue() ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/**
	 * The background install, one short step per WP-Cron run so no run comes near a PHP time limit.
	 *
	 * The first run installs the missing core languages (one download each) and queues their
	 * plugin and theme translations; each run installs batches of the queue for as long as its time
	 * budget allows, then schedules the next run if any are left.
	 *
	 * @since 3.4
	 * @hook nakedcatplugins_lang_attribute_blocks_install_languages (WP-Cron)
	 * @param int $run The run number, 0 for the first; follow-up runs need their own number, as
	 *                 WP-Cron drops an identical event scheduled within 10 minutes of another.
	 * @return void
	 */
	public function run_scheduled_install( $run = 0 ): void {
		if ( ! $this->is_enabled() ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( ! wp_can_install_language_pack() ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}

		$started = microtime( true );
		$queue   = $this->get_queue();
		if ( null === $queue ) {
			$queue = $this->start_install();
			if ( ! $queue ) {
				return;
			}
		}
		// As many batches as fit in this run's time budget.
		$budget = $this->get_time_budget();
		while ( $queue && microtime( true ) - $started < $budget ) {
			$batch = array_splice( $queue, 0, self::BATCH );
			// Keep WordPress' update cache: it is cleared once, when the queue is done.
			$upgrader = new \Language_Pack_Upgrader( new \Automatic_Upgrader_Skin() );
			$upgrader->bulk_upgrade( $batch, array( 'clear_update_cache' => false ) );
		}

		if ( $queue ) {
			update_option(
				self::QUEUE_OPTION,
				array(
					'time'  => time(),
					'items' => $queue,
				),
				false
			);
			wp_schedule_single_event( time(), self::CRON_HOOK, array( (int) $run + 1 ) );
			return;
		}
		delete_option( self::QUEUE_OPTION );
		wp_clean_update_cache();
	}

	/**
	 * Seconds a background run may spend installing: half of PHP's time limit, at most 20 seconds,
	 * or 2 minutes where there is no limit, as when a server cron job runs WP-Cron with WP-CLI.
	 *
	 * @since 3.4
	 * @return int
	 */
	private function get_time_budget(): int {
		$limit = (int) ini_get( 'max_execution_time' );
		return $limit > 0 ? (int) min( 20, max( 5, $limit / 2 ) ) : 120;
	}

	/**
	 * First step: install the missing core languages and list their plugin and theme translations.
	 *
	 * @since 3.4
	 * @return array The translations to install, as wp_get_translation_updates() returns them.
	 */
	private function start_install(): array {
		$installed = array();
		foreach ( $this->get_missing_locales( $this->get_available_locales() ) as $locale ) {
			if ( wp_download_language_pack( $locale ) ) {
				$installed[] = $locale;
			}
		}
		if ( ! $installed ) {
			return array();
		}
		// The plugin and theme translations come with WordPress' regular translation updates, which
		// now include the new languages: check now instead of waiting for the next scheduled check.
		wp_clean_update_cache();
		wp_update_plugins();
		wp_update_themes();
		// Only the new languages: the pending updates of the others are WordPress' business.
		return array_values(
			array_filter(
				wp_get_translation_updates(),
				function ( $update ) use ( $installed ) {
					return in_array( $update->language, $installed, true );
				}
			)
		);
	}

	/**
	 * The Plugins page link: start the background install and go back to the Plugins page.
	 *
	 * @since 3.4
	 * @hook admin_post_{action}
	 * @return void
	 */
	public function install_languages() {
		check_admin_referer( self::INSTALL_ACTION );
		if ( ! $this->is_enabled() || ! $this->can_install() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to install languages on this site.', 'lang-attribute-blocks' ), 403 );
		}
		$missing = $this->get_missing_locales( $this->get_available_locales() );
		if ( $missing ) {
			$this->schedule_background_install();
			spawn_cron();
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'nakedcatplugins-lang-installing' => $missing ? implode( ',', $missing ) : 'none',
				),
				admin_url( 'plugins.php' )
			)
		);
		exit;
	}

	/**
	 * Say what the install link started, back on the Plugins page.
	 *
	 * @since 3.4
	 * @hook admin_notices
	 * @return void
	 */
	public function install_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a message.
		$installing = isset( $_GET['nakedcatplugins-lang-installing'] ) ? sanitize_text_field( wp_unslash( $_GET['nakedcatplugins-lang-installing'] ) ) : '';
		if ( '' === $installing || 'plugins' !== get_current_screen()->id ) {
			return;
		}
		if ( 'none' === $installing ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html__( 'All the languages used by your pages and posts are installed.', 'lang-attribute-blocks' )
			);
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			/* translators: %s: comma-separated list of WordPress locales, e.g. pt_PT, pt_BR */
			esc_html( sprintf( __( 'Installing %s in the background. The translations appear within a few minutes, and WordPress keeps them up to date from then on.', 'lang-attribute-blocks' ), $installing ) )
		);
	}
}

/* If you're reading this you must know what you're doing ;-) Greetings from sunny Portugal! */
