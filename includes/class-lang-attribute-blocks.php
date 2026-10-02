<?php
/**
 * Main plugin class for Language Attribute for Container Blocks and Pages/Posts
 *
 * This file contains the core functionality for adding language and direction
 * attributes to WordPress container blocks.
 * The plugin enhances accessibility by allowing content creators to specify
 * language changes at the block level, helping websites comply with WCAG
 * guidelines.
 *
 * The plugin follows WordPress coding standards and implements the singleton
 * pattern to ensure only one instance exists throughout the request lifecycle.
 *
 * @since 2.0
 */

namespace NakedCatPlugins\LangAttr;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Our main class
 */
final class Lang_Attribute_Blocks {

	/**
	 * The singleton instance.
	 *
	 * @since 1.0
	 * @var Lang_Attribute_Blocks|null
	 */
	protected static $instance = null;

	/**
	 * The Language column key on the posts list tables.
	 *
	 * @since 3.3
	 * @var string
	 */
	const LIST_TABLE_COLUMN = 'nakedcatplugins_lang';

	/**
	 * The website's language tag, once worked out.
	 *
	 * @since 3.3
	 * @var string|null
	 */
	private $website_language = null;

	/**
	 * Defines the WordPress blocks that will support language attributes.
	 *
	 * This property stores an array of block type identifiers that the plugin will
	 * extend with language and direction attributes. The blocks listed here will
	 * receive additional controls in the block editor sidebar for setting:
	 *
	 * - 'lang': Language code attribute (e.g., 'en', 'fr', 'pt-PT')
	 * - 'dir': Text direction attribute ('ltr' for left-to-right, 'rtl' for right-to-left)
	 *
	 * Currently supported blocks:
	 * - 'core/group': The Group block and all its variations (Stack, Row)
	 * - 'core/columns': The Columns block for multi-column layouts
	 * - 'core/column': Individual Column blocks used within Columns
	 * - 'core/cover': The Cover block for full-width background images with overlays
	 * - 'core/navigation': The Navigation block for site menus
	 * - 'core/navigation-submenu': Navigation submenu items
	 * - 'core/page-list': Automatic page listing blocks
	 *
	 * This property is used throughout the plugin to:
	 * - Register custom attributes during block registration
	 * - Add editor controls in the Inspector sidebar
	 * - Apply frontend rendering modifications
	 * - Determine which blocks receive visual highlighting (when enabled)
	 *
	 * In the future, this should be filterable to allow developers to add or remove supported blocks.
	 *
	 * @var array List of block type names that support language attributes
	 */
	private $blocks = array(
		// Container blocks
		'core/group',
		'core/columns',
		'core/column',
		'core/cover',
		// Navigation blocks
		'core/navigation',
		'core/navigation-submenu',
		'core/page-list',
		// Special blocks
		'core/post-content',
	);

	/**
	 * Constructor
	 *
	 * @since 2.0
	 */
	private function __construct() {
		// Hooks
		$this->init_hooks();
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @since 2.0
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the instance.
	 *
	 * @since 2.0
	 * @throws \Exception When attempting to unserialize the singleton instance.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @since 2.0
	 * @return Lang_Attribute_Blocks The singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize WordPress hooks and filters.
	 *
	 * This method sets up all the necessary WordPress hooks and filters for the plugin
	 * to function properly.
	 *
	 * This method is called during object construction to ensure all hooks are
	 * registered when the singleton instance is created.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function init_hooks() {
		// Register block attributes
		add_filter( 'register_block_type_args', array( $this, 'register_block_attributes' ), 10, 2 );
		// Applies language attributes to block content in the frontend
		foreach ( $this->blocks as $block_name ) {
			add_filter( 'render_block_' . $block_name, array( $this, 'process_blocks' ), 10, 2 );
		}
		// Register post meta for page-level language settings
		add_action( 'init', array( $this, 'register_page_lang_meta' ) );
		// Apply page-level language attribute to the <html> element
		add_filter( 'language_attributes', array( $this, 'apply_page_lang_attribute' ) );
		// Enqueues JavaScript and CSS assets for the WordPress block editor
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		// Enqueue block styles in editor iframe context.
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ) );
		// Expose template language/direction via REST so the block editor can read and save it.
		add_action( 'rest_api_init', array( $this, 'register_template_lang_rest_field' ) );
		// Enqueues CSS assets for the frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		// Add settings section to Settings > Writing page
		add_action( 'admin_init', array( $this, 'add_writing_settings' ) );
		// Add settings link to the plugin action links
		add_filter( 'plugin_action_links_' . plugin_basename( NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ), array( $this, 'add_plugin_action_links' ) );
		// Add classic editor metabox for page-level language settings
		add_action( 'add_meta_boxes', array( $this, 'add_classic_editor_metabox' ) );
		add_action( 'save_post', array( $this, 'save_classic_editor_metabox' ), 10, 2 );
		// Add a Language column and Quick Edit / Bulk Edit fields to the posts list tables
		add_action( 'admin_init', array( $this, 'add_list_table_hooks' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_table_assets' ) );
		add_action( 'save_post', array( $this, 'save_quick_and_bulk_edit' ), 10, 2 );
	}

	/**
	 * Register post meta fields for page-level language settings.
	 *
	 * Registers '_nakedcatplugins_page_lang' and '_nakedcatplugins_page_dir' meta
	 * for all public post types, exposed via the REST API so the block editor
	 * can read and write them.
	 *
	 * @since 3.0
	 * @hook init
	 * @return void
	 */
	public function register_page_lang_meta() {
		$args_lang = array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => array( $this, 'sanitize_lang_code' ),
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		);
		$args_dir  = array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'default'           => 'ltr',
			'sanitize_callback' => function ( $value ) {
				return 'rtl' === $value ? 'rtl' : 'ltr';
			},
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		);
		// Register for all public post types and templates.
		foreach ( $this->get_page_lang_meta_post_types() as $post_type ) {
			register_post_meta( $post_type, '_nakedcatplugins_page_lang', $args_lang );
			register_post_meta( $post_type, '_nakedcatplugins_page_dir', $args_dir );
		}
	}

	/**
	 * Get post types where page language metadata is registered.
	 *
	 * @since 3.1
	 * @return array Registered post types.
	 */
	private function get_page_lang_meta_post_types() {
		$post_types   = get_post_types( array( 'public' => true ) );
		$post_types[] = 'wp_template';
		return array_values( array_unique( $post_types ) );
	}

	/**
	 * Get the website's language tag, as output on the frontend <html> element.
	 *
	 * WordPress' get_bloginfo( 'language' ) uses the current user's locale in wp-admin, so on a
	 * pt_PT website edited by a user with en_US set on their profile it returns "en-US".
	 * This mirrors its logic using the website's locale instead.
	 *
	 * @since 3.3
	 * @return string Language tag, e.g. 'pt-PT'.
	 */
	private function get_website_language() {
		// Switching locale reloads the loaded text domains, so only do it once per request.
		if ( null !== $this->website_language ) {
			return $this->website_language;
		}
		$locale   = get_locale();
		$switched = switch_to_locale( $locale );
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, see get_bloginfo().
		$output = __( 'html_lang_attribute' );
		if ( $switched ) {
			restore_previous_locale();
		}
		if ( 'html_lang_attribute' === $output || preg_match( '/[^a-zA-Z0-9-]/', $output ) ) {
			$output = str_replace( '_', '-', $locale );
		}
		$this->website_language = $output;
		return $output;
	}

	/**
	 * Override the HTML lang (and optionally dir) attribute for singular pages/posts.
	 *
	 * When a page or post has the '_nakedcatplugins_page_lang' meta set, or else its
	 * template has one, this method replaces the lang attribute on the <html> element
	 * with that value, and applies the direction set alongside it. On RTL websites,
	 * core outputs dir="rtl", which is replaced so left-to-right pages are not left as RTL.
	 *
	 * @since 3.0
	 * @hook language_attributes
	 * @param string $output The existing language attributes string, e.g. 'lang="en-US"'.
	 * @return string Modified language attributes string.
	 */
	public function apply_page_lang_attribute( $output ) {
		if ( ! is_singular() ) {
			return $output;
		}
		list( 'lang' => $page_lang, 'dir' => $page_dir ) = $this->get_page_lang_and_dir( get_queried_object_id() );
		if ( empty( $page_lang ) ) {
			return $output;
		}

		$lang_attribute = 'lang="' . esc_attr( $page_lang ) . '"';
		if ( strpos( $output, 'lang=' ) !== false ) {
			// A callback, so a "$" or "\" in the language code is not read as a back-reference.
			$output = preg_replace_callback(
				'/lang="[^"]*"/',
				function () use ( $lang_attribute ) {
					return $lang_attribute;
				},
				$output
			);
		} else {
			$output .= ' ' . $lang_attribute;
		}

		// Core only outputs dir on RTL websites, so replace it whatever the page direction is,
		// and only add it when the page is RTL.
		if ( strpos( $output, 'dir=' ) !== false ) {
			$output = preg_replace( '/dir="[^"]*"/', 'dir="' . $page_dir . '"', $output );
		} elseif ( 'rtl' === $page_dir ) {
			$output .= ' dir="rtl"';
		}

		return $output;
	}

	/**
	 * Tidy up a language code.
	 *
	 * Trims, turns WordPress locale underscores into hyphens, drops the WordPress-only locale
	 * suffixes (de_DE_formal, pt_PT_ao90...) and applies the conventional casing (pt-PT,
	 * zh-Hant-TW). Codes that are not valid are kept, only tidied: the editor fields show an
	 * error for those instead. Mirrors normalize() in assets/js/lang-code.js.
	 *
	 * @since 3.3
	 * @param mixed $value The language code.
	 * @return string The tidied language code.
	 */
	public function sanitize_lang_code( $value ) {
		$code = is_string( $value ) ? sanitize_text_field( $value ) : '';
		$code = preg_replace( '/-(?:formal|informal|ao90)$/i', '', str_replace( '_', '-', trim( $code ) ) );
		if ( '' === $code ) {
			return '';
		}
		$subtags         = explode( '-', $code );
		$after_singleton = false;
		foreach ( $subtags as $index => $subtag ) {
			if ( 0 === $index || $after_singleton || 1 === strlen( $subtag ) ) {
				$after_singleton   = $after_singleton || 1 === strlen( $subtag );
				$subtags[ $index ] = strtolower( $subtag );
			} elseif ( preg_match( '/^[a-z]{4}$/i', $subtag ) ) {
				$subtags[ $index ] = ucfirst( strtolower( $subtag ) );
			} elseif ( preg_match( '/^[a-z]{2}$/i', $subtag ) ) {
				$subtags[ $index ] = strtoupper( $subtag );
			} else {
				$subtags[ $index ] = strtolower( $subtag );
			}
		}
		return implode( '-', $subtags );
	}

	/**
	 * Get the language and direction that apply to a singular page/post.
	 *
	 * The post's own language wins, or else its template's. The direction always comes
	 * from the same place as the language: the dir meta has a registered 'ltr' default,
	 * so it can't be used to tell whether the post set one.
	 *
	 * @since 3.3
	 * @param int $post_id The singular post ID.
	 * @return array{lang:string,dir:string} Language (empty if none applies) and direction ('ltr' or 'rtl').
	 */
	private function get_page_lang_and_dir( int $post_id ) {
		$lang = trim( get_post_meta( $post_id, '_nakedcatplugins_page_lang', true ) );
		$dir  = trim( get_post_meta( $post_id, '_nakedcatplugins_page_dir', true ) );
		if ( empty( $lang ) ) {
			list( 'lang' => $lang, 'dir' => $dir ) = $this->get_template_lang_and_dir( $post_id );
		}
		return array(
			// Tidied on output too, for values saved before codes were tidied on save.
			'lang' => $this->sanitize_lang_code( $lang ),
			'dir'  => 'rtl' === $dir ? 'rtl' : 'ltr',
		);
	}

	/**
	 * Expose template language/direction as a REST field so the block editor
	 * entity store can read and save it via useEntityProp.
	 *
	 * WP_REST_Templates_Controller does not expose registered post meta in its
	 * responses, so register_post_meta with show_in_rest has no effect for
	 * wp_template. This method registers a dedicated REST field instead.
	 *
	 * @since 3.1
	 * @hook rest_api_init
	 * @return void
	 */
	public function register_template_lang_rest_field() {
		register_rest_field(
			'wp_template',
			'nakedcatplugins_lang_meta',
			array(
				'get_callback'    => function ( $item ) {
					$wp_id = $item['wp_id'] ?? 0;
					$dir   = trim( get_post_meta( $wp_id, '_nakedcatplugins_page_dir', true ) );
					return array(
						'lang' => trim( get_post_meta( $wp_id, '_nakedcatplugins_page_lang', true ) ),
						'dir'  => $dir ? $dir : 'ltr',
					);
				},
				'update_callback' => function ( $value, $post_obj ) {
					$wp_id = is_object( $post_obj ) && isset( $post_obj->wp_id ) ? (int) $post_obj->wp_id : 0;
					if ( ! $wp_id ) {
						return;
					}
					$lang = trim( sanitize_text_field( $value['lang'] ?? '' ) );
					$dir  = in_array( $value['dir'] ?? 'ltr', array( 'ltr', 'rtl' ), true ) ? $value['dir'] : 'ltr';
					if ( ! empty( $lang ) ) {
						update_post_meta( $wp_id, '_nakedcatplugins_page_lang', $lang );
						update_post_meta( $wp_id, '_nakedcatplugins_page_dir', $dir );
					} else {
						delete_post_meta( $wp_id, '_nakedcatplugins_page_lang' );
						delete_post_meta( $wp_id, '_nakedcatplugins_page_dir' );
					}
				},
				'schema'          => array(
					'type'       => 'object',
					'properties' => array(
						'lang' => array(
							'type'    => 'string',
							'default' => '',
						),
						'dir'  => array(
							'type'    => 'string',
							'default' => 'ltr',
							'enum'    => array( 'ltr', 'rtl' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Get language and direction metadata from the assigned block template.
	 *
	 * @since 3.1
	 * @param int $post_id The singular post ID.
	 * @return array{lang:string,dir:string} Template language and direction values.
	 */
	private function get_template_lang_and_dir( int $post_id ) {
		// In block themes, locate_block_template() (hooked to template_include) sets
		// $_wp_current_template_id to the resolved template ID (e.g. 'theme//single-product')
		// before template-canvas.php calls language_attributes(). Use it as the primary
		// source so templates applied via the hierarchy (not assigned per-post) are found.
		global $_wp_current_template_id;
		if ( ! empty( $_wp_current_template_id ) ) {
			$template = get_block_template( $_wp_current_template_id, 'wp_template' );
			if ( $template && ! empty( $template->wp_id ) ) {
				return array(
					'lang' => trim( get_post_meta( $template->wp_id, '_nakedcatplugins_page_lang', true ) ),
					'dir'  => trim( get_post_meta( $template->wp_id, '_nakedcatplugins_page_dir', true ) ),
				);
			}
			return array(
				'lang' => '',
				'dir'  => '',
			);
		}

		// Fallback: explicit page template assigned to this post (_wp_page_template meta).
		$template_slug = get_page_template_slug( $post_id );
		if ( empty( $template_slug ) || 'default' === $template_slug ) {
			return array(
				'lang' => '',
				'dir'  => '',
			);
		}

		$template_slug = preg_replace( '#^templates/#', '', $template_slug );
		$template_slug = preg_replace( '#\.html$#', '', $template_slug );
		if ( empty( $template_slug ) ) {
			return array(
				'lang' => '',
				'dir'  => '',
			);
		}

		$theme_slugs = array_unique(
			array_filter(
				array(
					get_stylesheet(),
					get_template(),
				)
			)
		);

		foreach ( $theme_slugs as $theme_slug ) {
			$template = get_block_template( $theme_slug . '//' . $template_slug, 'wp_template' );
			if ( ! $template || empty( $template->wp_id ) ) {
				continue;
			}

			return array(
				'lang' => trim( get_post_meta( $template->wp_id, '_nakedcatplugins_page_lang', true ) ),
				'dir'  => trim( get_post_meta( $template->wp_id, '_nakedcatplugins_page_dir', true ) ),
			);
		}

		return array(
			'lang' => '',
			'dir'  => '',
		);
	}

	/**
	 * Register block attributes for language settings.
	 *
	 * This function adds custom 'lang' and 'dir' attributes to specified WordPress blocks
	 * defined in the NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_BLOCKS constant. These attributes allow users
	 * to set language-specific properties on block elements:
	 *
	 * - 'lang': Specifies the language code (e.g., 'en', 'fr', 'es')
	 * - 'dir': Sets text direction, defaulting to 'ltr' (left-to-right)
	 *
	 * @since 1.0
	 * @hook register_block_type_args
	 * @param array  $args       The arguments being passed to register_block_type.
	 * @param string $block_type The block type being registered.
	 * @return array Modified arguments with added language attributes if applicable.
	 */
	public function register_block_attributes( $args, $block_type ) {
		if ( in_array( $block_type, $this->blocks, true ) ) {
			$args['attributes']['lang'] = array(
				'type'    => 'string',
				'default' => '',
			);
			$args['attributes']['dir']  = array(
				'type'    => 'string',
				'default' => 'ltr',
			);
		}
		return $args;
	}

	/**
	 * Applies language attributes to block content in the frontend.
	 *
	 * This function processes blocks with language settings and inserts the appropriate
	 * 'lang' and 'dir' HTML attributes to their markup. It:
	 *
	 * 1. Checks if a block has a language attribute specified
	 * 2. Tidies the language code and limits the direction to 'ltr' or 'rtl'
	 * 3. Uses WP_HTML_Tag_Processor to safely modify the HTML
	 * 4. Targets the block's wrapper, the first element in the block content
	 * 5. Returns the modified HTML with language attributes applied
	 *
	 * @since 1.0
	 * @hook render_block_{block_name}
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The block data including attributes.
	 * @return string Modified block content with language attributes applied.
	 */
	public function process_blocks( $block_content, $block ) {
		$lang = isset( $block['attrs']['lang'] ) ? $this->sanitize_lang_code( $block['attrs']['lang'] ) : '';
		if ( '' !== $lang ) {
			// No esc_attr(): set_attribute() escapes, and escaping twice garbles the value.
			$dir           = isset( $block['attrs']['dir'] ) && 'rtl' === $block['attrs']['dir'] ? 'rtl' : 'ltr';
			$tag_processor = new \WP_HTML_Tag_Processor( $block_content );
			// The first tag is the block's wrapper for every supported block, whatever its name:
			// <nav> for Navigation, <li> for Submenu, <ul> for Page List, or the tagName chosen
			// on Group, Cover and Post Content.
			if ( $tag_processor->next_tag() ) {
				$tag_processor->set_attribute( 'lang', $lang );
				$tag_processor->set_attribute( 'dir', $dir );
			}
			return $tag_processor->get_updated_html();
		}
		return $block_content;
	}

	/**
	 * The error shown under a language code field when the code is not valid.
	 *
	 * @since 3.3
	 * @return string The message.
	 */
	private function get_lang_code_error_message() {
		return __( 'This is not a valid language code. Use a code like “fr”, “pt” or “pt-PT”.', 'lang-attribute-blocks' );
	}

	/**
	 * Register the shared language code script, and its error message style for the admin screens.
	 *
	 * @since 3.3
	 * @return string The script handle.
	 */
	private function register_lang_code_assets() {
		$handle = 'nakedcatplugins-lang-attribute-blocks-lang-code';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script(
				$handle,
				plugins_url( 'assets/js/lang-code.js', NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ),
				array(),
				filemtime( plugin_dir_path( NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ) . 'assets/js/lang-code.js' ),
				true
			);
			wp_add_inline_style( 'wp-admin', '.nakedcatplugins-lang-code-error:not([hidden]) { display: block; color: #d63638; }' );
		}
		return $handle;
	}

	/**
	 * Enqueues JavaScript and CSS assets for the WordPress block editor.
	 *
	 * This function loads the necessary scripts and styles for the Language Attribute Blocks
	 * plugin to operate within the Gutenberg editor. It registers:
	 *
	 * 1. The main plugin JavaScript file with appropriate dependencies
	 * 2. Translation support for the script
	 * 3. The plugin's CSS styles
	 *
	 * Each asset is versioned using the file's last modification time for cache busting.
	 *
	 * @since 1.2
	 * @hook enqueue_block_editor_assets
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		// Enqueue the main JavaScript file for the block editor
		wp_enqueue_script(
			'nakedcatplugins-lang-attribute-blocks-script',
			plugins_url( 'build/index.js', NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ),
			array( 'wp-blocks', 'wp-dom', 'wp-dom-ready', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-plugins', 'wp-data', 'wp-core-data', $this->register_lang_code_assets() ),
			filemtime( plugin_dir_path( NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ) . 'build/index.js' ),
			true
		);

		// Localize script to pass the blocks array to JavaScript
		wp_localize_script(
			'nakedcatplugins-lang-attribute-blocks-script',
			'nakedCatPluginsLangAttributeBlocks',
			array(
				'supportedBlocks'   => $this->blocks,
				'siteLanguage'      => $this->get_website_language(), // The website language (e.g., 'pt-PT'), regardless of the user's locale
				'currentTheme'      => get_stylesheet(),
				'editablePostTypes' => $this->get_page_lang_meta_post_types(),
				'highlightEnabled'  => get_option( 'nakedcatplugins_lang_attr_highlight_blocks', false ),
				'placeholderText'   => sprintf(
					/* translators: %s: The website's default language code */
					__( '%s (default website language)', 'lang-attribute-blocks' ),
					$this->get_website_language()
				),
			)
		);

		// Set script translations
		wp_set_script_translations( 'nakedcatplugins-lang-attribute-blocks-script', 'lang-attribute-blocks' );
	}

	/**
	 * Enqueue block CSS in editor/iframe context.
	 *
	 * This keeps styles loaded inside the editor iframe via enqueue_block_assets,
	 * avoiding iframe style warnings from enqueue_block_editor_assets-only loading.
	 *
	 * @since 3.1
	 * @hook enqueue_block_assets
	 * @return void
	 */
	public function enqueue_block_assets() {
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'nakedcatplugins-lang-attribute-blocks-style',
			plugins_url( 'build/index.css', NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ),
			array(),
			filemtime( plugin_dir_path( NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ) . 'build/index.css' )
		);
		// Add custom color override if specified
		$this->maybe_add_custom_highlight_color();
	}

	/**
	 * Enqueues CSS assets for the frontend.
	 *
	 * This function loads the plugin's CSS styles on the frontend to provide
	 * visual highlighting of blocks with language attributes.
	 *
	 * @since 1.2
	 * @hook wp_enqueue_scripts
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		// Enqueue the CSS styles for the frontend
		if ( current_user_can( 'edit_others_posts' ) ) {
			$highlight_enabled = get_option( 'nakedcatplugins_lang_attr_highlight_blocks', false );
			if ( $highlight_enabled ) {
				wp_enqueue_style(
					'nakedcatplugins-lang-attribute-blocks-style',
					plugins_url( 'build/index.css', NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ),
					array(),
					filemtime( plugin_dir_path( NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ) . 'build/index.css' )
				);
				// Add custom color override if specified
				$this->maybe_add_custom_highlight_color();
				// Outline the whole page when it has its own (or its template's) language. Done here rather
				// than with a class on <html>, which would clash with the class many themes print there.
				if ( is_singular() ) {
					$page_lang_and_dir = $this->get_page_lang_and_dir( get_queried_object_id() );
					if ( ! empty( $page_lang_and_dir['lang'] ) ) {
						wp_add_inline_style(
							'nakedcatplugins-lang-attribute-blocks-style',
							'html { outline: 3px dashed var(--nakedcatplugins-lang-attr-highlight-color) !important; outline-offset: -3px; }'
						);
					}
				}
			}
		}
	}

	/**
	 * Add custom highlight color override if specified via filter.
	 *
	 * @since 2.0
	 * @return void
	 */
	private function maybe_add_custom_highlight_color() {
		/**
		 * Filter the highlight color for blocks with language attributes.
		 *
		 * @since 2.0
		 * @param string $color The highlight color in any valid CSS format.
		 *                      Default: 'rgba(255, 0, 0, 0.75)'
		 */
		$custom_color = apply_filters( 'nakedcatplugins_lang_attr_highlight_color', '' );

		if ( ! empty( $custom_color ) ) {
			wp_add_inline_style(
				'nakedcatplugins-lang-attribute-blocks-style',
				":root { --nakedcatplugins-lang-attr-highlight-color: {$custom_color}; }"
			);
		}
	}

	/**
	 * Add settings section to Settings > Writing page.
	 *
	 * This function registers a new settings section for the Language Attribute Blocks
	 * plugin on the WordPress Settings > Writing admin page.
	 *
	 * @since 1.2
	 * @hook admin_init
	 * @return void
	 */
	public function add_writing_settings() {
		// Register the setting
		register_setting(
			'writing',
			'nakedcatplugins_lang_attr_highlight_blocks',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		// Add settings section
		add_settings_section(
			'nakedcatplugins_lang_attr_section',
			__( 'Language Attribute for Container Blocks and Pages/Posts', 'lang-attribute-blocks' ),
			array( $this, 'settings_section_callback' ),
			'writing'
		);

		// Add settings field
		add_settings_field(
			'nakedcatplugins_lang_attr_highlight_blocks',
			__( 'Highlight blocks with lang attribute', 'lang-attribute-blocks' ),
			array( $this, 'highlight_blocks_field_callback' ),
			'writing',
			'nakedcatplugins_lang_attr_section'
		);
	}

	/**
	 * Settings section callback.
	 *
	 * @since 1.2
	 * @return void
	 */
	public function settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure Language Attribute for Container Blocks and Pages/Posts plugin settings.', 'lang-attribute-blocks' ) . '</p>';
	}
	/**
	 * Highlight blocks field callback.
	 *
	 * @since 1.2
	 * @return void
	 */
	public function highlight_blocks_field_callback() {
		$option = get_option( 'nakedcatplugins_lang_attr_highlight_blocks', false );
		?>
		<label for="nakedcatplugins_lang_attr_highlight_blocks">
			<input type="checkbox" id="nakedcatplugins_lang_attr_highlight_blocks" name="nakedcatplugins_lang_attr_highlight_blocks" value="1" <?php checked( $option, true ); ?> />
			<?php esc_html_e( 'Show visual outline around blocks that have a language attribute set', 'lang-attribute-blocks' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, blocks with a language attribute will be visually highlighted with a red dashed outline in both the editor and frontend (only for Administrators and Editors).', 'lang-attribute-blocks' ); ?>
		</p>
		<?php
	}

	/**
	 * Register the page language metabox for the classic editor.
	 *
	 * Added to all public post types so editors can set the page-level
	 * lang and dir meta without the block editor.
	 *
	 * @since 3.0
	 * @hook add_meta_boxes
	 * @return void
	 */
	public function add_classic_editor_metabox() {
		// Only register the metabox in the classic editor; the block editor
		// provides its own Document Settings panel for the same fields.
		$screen = get_current_screen();
		if ( $screen && $screen->is_block_editor() ) {
			return;
		}

		// Checks the language code as it is typed. add_meta_boxes runs before the admin header, so the style still prints.
		wp_enqueue_script( $this->register_lang_code_assets() );

		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			add_meta_box(
				'nakedcatplugins_page_language',
				__( 'Page Language', 'lang-attribute-blocks' ),
				array( $this, 'render_classic_editor_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the classic editor metabox HTML.
	 *
	 * @since 3.0
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_classic_editor_metabox( \WP_Post $post ) {
		$lang = trim( get_post_meta( $post->ID, '_nakedcatplugins_page_lang', true ) );
		$dir  = trim( get_post_meta( $post->ID, '_nakedcatplugins_page_dir', true ) );
		if ( empty( $dir ) ) {
			$dir = 'ltr';
		}

		wp_nonce_field( 'nakedcatplugins_page_language_metabox', 'nakedcatplugins_page_language_nonce' );
		$placeholder = sprintf(
			/* translators: %s: The website's default language code */
			__( '%s (default website language)', 'lang-attribute-blocks' ),
			$this->get_website_language()
		);
		?>
		<p>
			<label for="nakedcatplugins_page_lang">
				<?php esc_html_e( 'Language Code', 'lang-attribute-blocks' ); ?>
			</label>
			<input type="text" id="nakedcatplugins_page_lang" name="nakedcatplugins_page_lang" value="<?php echo esc_attr( $lang ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="widefat nakedcatplugins-lang-code-input"/>
			<span class="nakedcatplugins-lang-code-error" hidden><?php echo esc_html( $this->get_lang_code_error_message() ); ?></span>
			<span class="description">
				<?php esc_html_e( "Valid language code for this page/post, like “fr” or “pt-PT”, if different from the website's main language (shown as a placeholder) - This overrides the HTML language attribute", 'lang-attribute-blocks' ); ?>
			</span>
		</p>
		<p>
			<label for="nakedcatplugins_page_dir">
				<?php esc_html_e( 'Text Direction', 'lang-attribute-blocks' ); ?>
			</label>
			<select id="nakedcatplugins_page_dir" name="nakedcatplugins_page_dir" class="widefat">
				<option value="ltr" <?php selected( $dir, 'ltr' ); ?>>
					<?php esc_html_e( 'Left to right', 'lang-attribute-blocks' ); ?>
				</option>
				<option value="rtl" <?php selected( $dir, 'rtl' ); ?>>
					<?php esc_html_e( 'Right to left', 'lang-attribute-blocks' ); ?>
				</option>
			</select>
		</p>
		<?php
	}

	/**
	 * Save the classic editor metabox values.
	 *
	 * Validates the nonce to confirm the metabox was present in the form
	 * (guards against quick edit, bulk actions, REST and programmatic saves
	 * that would otherwise wipe the meta), checks capabilities, then saves
	 * or deletes the page language meta fields.
	 *
	 * @since 3.0
	 * @hook save_post
	 * @param int      $post_id The post ID being saved.
	 * @param \WP_Post $post    The post object being saved.
	 * @return void
	 */
	public function save_classic_editor_metabox( int $post_id, \WP_Post $post ) {
		// If our metabox was not present in the request, bail out to avoid
		// accidentally wiping the meta (e.g. quick edit, REST, wp_update_post()).
		if ( ! isset( $_POST['nakedcatplugins_page_language_nonce'] ) ) {
			return;
		}

		// WordPress has already verified the post nonce, but we verify our own
		// as extra confirmation that our metabox submitted these values.
		if ( ! wp_verify_nonce( sanitize_key( $_POST['nakedcatplugins_page_language_nonce'] ), 'nakedcatplugins_page_language_metabox' ) ) {
			return;
		}

		// Bail on autosave and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check the user has permission to edit this specific post.
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
			return;
		}

		// Save lang — trim and sanitize; delete if empty so the DB stays clean.
		if ( isset( $_POST['nakedcatplugins_page_lang'] ) ) {
			$lang = trim( sanitize_text_field( wp_unslash( $_POST['nakedcatplugins_page_lang'] ) ) );
			if ( ! empty( $lang ) ) {
				update_post_meta( $post_id, '_nakedcatplugins_page_lang', $lang );
			} else {
				delete_post_meta( $post_id, '_nakedcatplugins_page_lang' );
				delete_post_meta( $post_id, '_nakedcatplugins_page_dir' );
				return; // If lang is empty, we also delete dir and skip saving it since it doesn't make sense to have a dir without a lang.
			}
		}

		// Save dir — whitelist to known values only.
		if ( isset( $_POST['nakedcatplugins_page_dir'] ) ) {
			$dir = sanitize_text_field( wp_unslash( $_POST['nakedcatplugins_page_dir'] ) );
			$dir = in_array( $dir, array( 'ltr', 'rtl' ), true ) ? $dir : 'ltr';
			update_post_meta( $post_id, '_nakedcatplugins_page_dir', $dir );
		}
	}

	/**
	 * Get post types whose list tables get the Language column and Quick Edit / Bulk Edit fields.
	 *
	 * Attachments are left out: the media list table has no Quick Edit or Bulk Edit.
	 *
	 * @since 3.3
	 * @return array Post type names.
	 */
	private function get_list_table_post_types() {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		);
		unset( $post_types['attachment'] );
		return array_values( $post_types );
	}

	/**
	 * Register the list table column and Quick Edit / Bulk Edit hooks.
	 *
	 * Runs on admin_init (not current_screen) so the column is also present when
	 * a row is re-rendered by the Quick Edit AJAX request.
	 *
	 * @since 3.3
	 * @hook admin_init
	 * @return void
	 */
	public function add_list_table_hooks() {
		foreach ( $this->get_list_table_post_types() as $post_type ) {
			add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_list_table_column' ) );
			add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'render_list_table_column' ), 10, 2 );
		}
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit_fields' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( $this, 'render_bulk_edit_fields' ), 10, 2 );
	}

	/**
	 * Add the Language column to the posts list table, before the Date column.
	 *
	 * @since 3.3
	 * @param array $columns List table columns.
	 * @return array Modified list table columns.
	 */
	public function add_list_table_column( $columns ) {
		$column   = array( self::LIST_TABLE_COLUMN => __( 'Language', 'lang-attribute-blocks' ) );
		$position = array_search( 'date', array_keys( $columns ), true );
		if ( false === $position ) {
			return array_merge( $columns, $column );
		}
		return array_slice( $columns, 0, $position, true ) + $column + array_slice( $columns, $position, null, true );
	}

	/**
	 * Render the Language column cell.
	 *
	 * Also prints the raw values in a hidden element, read by the list table
	 * script to pre-fill the Quick Edit fields.
	 *
	 * @since 3.3
	 * @param string $column_name The column being rendered.
	 * @param int    $post_id     The post ID.
	 * @return void
	 */
	public function render_list_table_column( $column_name, $post_id ) {
		if ( self::LIST_TABLE_COLUMN !== $column_name ) {
			return;
		}
		$lang = trim( get_post_meta( $post_id, '_nakedcatplugins_page_lang', true ) );
		$dir  = 'rtl' === trim( get_post_meta( $post_id, '_nakedcatplugins_page_dir', true ) ) ? 'rtl' : 'ltr';
		printf(
			'<span class="nakedcatplugins-lang-data" hidden data-lang="%s" data-dir="%s"></span>',
			esc_attr( $lang ),
			esc_attr( $dir )
		);
		if ( empty( $lang ) ) {
			return;
		}
		echo esc_html( $lang );
		if ( 'rtl' === $dir ) {
			echo '<br/><span class="description">' . esc_html__( 'Right to left', 'lang-attribute-blocks' ) . '</span>';
		}
	}

	/**
	 * Render the Quick Edit fields.
	 *
	 * @since 3.3
	 * @hook quick_edit_custom_box
	 * @param string $column_name The column the fields are attached to.
	 * @param string $post_type   The post type being listed.
	 * @return void
	 */
	public function render_quick_edit_fields( $column_name, $post_type ) {
		if ( self::LIST_TABLE_COLUMN !== $column_name || ! in_array( $post_type, $this->get_list_table_post_types(), true ) ) {
			return;
		}
		$placeholder = sprintf(
			/* translators: %s: The website's default language code */
			__( '%s (default website language)', 'lang-attribute-blocks' ),
			$this->get_website_language()
		);
		?>
		<fieldset class="inline-edit-col-right nakedcatplugins-lang-quick-edit">
			<div class="inline-edit-col">
				<?php wp_nonce_field( 'nakedcatplugins_page_language_quick_edit', 'nakedcatplugins_page_language_quick_edit_nonce', false ); ?>
				<label>
					<span class="title"><?php esc_html_e( 'Language', 'lang-attribute-blocks' ); ?></span>
					<span class="input-text-wrap">
						<input type="text" name="nakedcatplugins_quick_edit_lang" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>" size="<?php echo absint( mb_strlen( $placeholder ) + 2 ); ?>" class="nakedcatplugins-lang-code-input"/>
						<span class="nakedcatplugins-lang-code-error" hidden><?php echo esc_html( $this->get_lang_code_error_message() ); ?></span>
					</span>
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'Direction', 'lang-attribute-blocks' ); ?></span>
					<select name="nakedcatplugins_quick_edit_dir">
						<option value="ltr"><?php esc_html_e( 'Left to right', 'lang-attribute-blocks' ); ?></option>
						<option value="rtl"><?php esc_html_e( 'Right to left', 'lang-attribute-blocks' ); ?></option>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the Bulk Edit fields.
	 *
	 * An empty language and "No Change" direction leave the selected items untouched.
	 * The direction can be changed on its own, but only on items that have a language.
	 *
	 * @since 3.3
	 * @hook bulk_edit_custom_box
	 * @param string $column_name The column the fields are attached to.
	 * @param string $post_type   The post type being listed.
	 * @return void
	 */
	public function render_bulk_edit_fields( $column_name, $post_type ) {
		if ( self::LIST_TABLE_COLUMN !== $column_name || ! in_array( $post_type, $this->get_list_table_post_types(), true ) ) {
			return;
		}
		// WordPress core string, matches the other Bulk Edit fields.
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		$no_change = wp_specialchars_decode( __( '&mdash; No Change &mdash;', 'default' ) );
		?>
		<fieldset class="inline-edit-col-right nakedcatplugins-lang-bulk-edit">
			<div class="inline-edit-col">
				<?php wp_nonce_field( 'nakedcatplugins_page_language_bulk_edit', 'nakedcatplugins_page_language_bulk_edit_nonce', false ); ?>
				<label>
					<span class="title"><?php esc_html_e( 'Language', 'lang-attribute-blocks' ); ?></span>
					<span class="input-text-wrap">
						<input type="text" name="nakedcatplugins_bulk_edit_lang" value="" placeholder="<?php echo esc_attr( $no_change ); ?>" size="<?php echo absint( mb_strlen( $no_change ) + 2 ); ?>" class="nakedcatplugins-lang-code-input"/>
						<span class="nakedcatplugins-lang-code-error" hidden><?php echo esc_html( $this->get_lang_code_error_message() ); ?></span>
					</span>
				</label>
				<p class="description nakedcatplugins-lang-bulk-edit-note">
					<?php
					printf(
						/* translators: %s: The website's default language code */
						esc_html__( 'Leave empty to keep each item’s current language. Items without a language use their template’s or the website’s default (%s).', 'lang-attribute-blocks' ),
						esc_html( $this->get_website_language() )
					);
					?>
				</p>
				<label>
					<span class="title"><?php esc_html_e( 'Direction', 'lang-attribute-blocks' ); ?></span>
					<select name="nakedcatplugins_bulk_edit_dir">
						<option value=""><?php echo esc_html( $no_change ); ?></option>
						<option value="ltr"><?php esc_html_e( 'Left to right', 'lang-attribute-blocks' ); ?></option>
						<option value="rtl"><?php esc_html_e( 'Right to left', 'lang-attribute-blocks' ); ?></option>
					</select>
				</label>
				<label class="alignleft">
					<input type="checkbox" name="nakedcatplugins_bulk_edit_remove" value="1"/>
					<span class="checkbox-title">
						<?php
						printf(
							/* translators: %s: The website's default language code */
							esc_html__( 'Remove the language and direction, and use the template or website default (%s)', 'lang-attribute-blocks' ),
							esc_html( $this->get_website_language() )
						);
						?>
					</span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save the Quick Edit and Bulk Edit values.
	 *
	 * Each form has its own nonce, so this only acts when one of them was submitted
	 * and never touches the meta on any other kind of save.
	 *
	 * @since 3.3
	 * @hook save_post
	 * @param int      $post_id The post ID being saved.
	 * @param \WP_Post $post    The post object being saved.
	 * @return void
	 */
	public function save_quick_and_bulk_edit( int $post_id, \WP_Post $post ) {
		// Bulk Edit submits the list table form with GET, Quick Edit posts over AJAX.
		$is_quick_edit = isset( $_POST['nakedcatplugins_page_language_quick_edit_nonce'] )
			&& wp_verify_nonce( sanitize_key( $_POST['nakedcatplugins_page_language_quick_edit_nonce'] ), 'nakedcatplugins_page_language_quick_edit' );
		$is_bulk_edit  = isset( $_REQUEST['nakedcatplugins_page_language_bulk_edit_nonce'] )
			&& wp_verify_nonce( sanitize_key( $_REQUEST['nakedcatplugins_page_language_bulk_edit_nonce'] ), 'nakedcatplugins_page_language_bulk_edit' );
		if ( ! $is_quick_edit && ! $is_bulk_edit ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, $this->get_list_table_post_types(), true ) ) {
			return;
		}
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
			return;
		}

		if ( $is_quick_edit ) {
			$lang = isset( $_POST['nakedcatplugins_quick_edit_lang'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['nakedcatplugins_quick_edit_lang'] ) ) ) : '';
			$dir  = isset( $_POST['nakedcatplugins_quick_edit_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['nakedcatplugins_quick_edit_dir'] ) ) : 'ltr';
			$this->save_page_lang_meta( $post_id, $lang, $dir );
			return;
		}

		if ( ! empty( $_REQUEST['nakedcatplugins_bulk_edit_remove'] ) ) {
			$this->save_page_lang_meta( $post_id, '', '' );
			return;
		}
		$lang = isset( $_REQUEST['nakedcatplugins_bulk_edit_lang'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['nakedcatplugins_bulk_edit_lang'] ) ) ) : '';
		$dir  = isset( $_REQUEST['nakedcatplugins_bulk_edit_dir'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nakedcatplugins_bulk_edit_dir'] ) ) : '';
		if ( '' === $lang && '' === $dir ) {
			return;
		}
		// Keep whatever was not changed.
		if ( '' === $lang ) {
			$lang = trim( get_post_meta( $post_id, '_nakedcatplugins_page_lang', true ) );
			if ( '' === $lang ) {
				return; // A direction without a language is never stored.
			}
		}
		if ( '' === $dir ) {
			$dir = trim( get_post_meta( $post_id, '_nakedcatplugins_page_dir', true ) );
		}
		$this->save_page_lang_meta( $post_id, $lang, $dir );
	}

	/**
	 * Save or delete the page language meta.
	 *
	 * An empty language deletes both fields, since a direction without a language is never stored.
	 *
	 * @since 3.3
	 * @param int    $post_id The post ID.
	 * @param string $lang    Sanitized language code, or empty to delete.
	 * @param string $dir     'ltr' or 'rtl', anything else is saved as 'ltr'.
	 * @return void
	 */
	private function save_page_lang_meta( int $post_id, string $lang, string $dir ) {
		if ( '' === $lang ) {
			delete_post_meta( $post_id, '_nakedcatplugins_page_lang' );
			delete_post_meta( $post_id, '_nakedcatplugins_page_dir' );
			return;
		}
		update_post_meta( $post_id, '_nakedcatplugins_page_lang', $lang );
		update_post_meta( $post_id, '_nakedcatplugins_page_dir', 'rtl' === $dir ? 'rtl' : 'ltr' );
	}

	/**
	 * Enqueue the script that pre-fills Quick Edit and the column styles on the posts list tables.
	 *
	 * @since 3.3
	 * @hook admin_enqueue_scripts
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_list_table_assets( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->get_list_table_post_types(), true ) ) {
			return;
		}
		wp_enqueue_script(
			'nakedcatplugins-lang-attribute-blocks-list-table',
			plugins_url( 'assets/js/list-table.js', NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ),
			array( 'jquery', 'inline-edit-post', $this->register_lang_code_assets() ),
			filemtime( plugin_dir_path( NAKEDCATPLUGINS_LANG_ATTRIBUTE_BLOCKS_FILE ) . 'assets/js/list-table.js' ),
			true
		);
		wp_add_inline_style( 'wp-admin', '.fixed .column-' . self::LIST_TABLE_COLUMN . ' { width: 10%; } .inline-edit-row .nakedcatplugins-lang-quick-edit input[type="text"], .inline-edit-row .nakedcatplugins-lang-bulk-edit input[type="text"] { width: auto; max-width: 100%; } .inline-edit-row .nakedcatplugins-lang-bulk-edit-note { margin: 0 0 .5em 6em; }' );
	}

	/**
	 * Add settings link to the plugin action links.
	 *
	 * This function adds a "Settings" link to the plugin's row on the Plugins page
	 * that points to the Language Attribute for Container Blocks and Pages/Posts settings section
	 * on the Settings > Writing page.
	 *
	 * @since 1.2
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links with settings link added.
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-writing.php' ) ),
			esc_html__( 'Settings', 'lang-attribute-blocks' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}

/* If you're reading this you must know what you're doing ;-) Greetings from sunny Portugal! */
