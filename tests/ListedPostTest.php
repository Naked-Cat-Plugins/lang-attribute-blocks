<?php
/**
 * The language of a post listed outside its own page: blog, archives, search.
 *
 * @package NakedCatPlugins\LangAttr
 */

namespace NakedCatPlugins\LangAttr\Tests;

/**
 * A Portuguese post in an English list gets lang="pt-PT" around its title and excerpt.
 */
final class ListedPostTest extends TestCase {

	/**
	 * Make a list (not singular) of the given posts the main query, inside the loop on the first.
	 *
	 * @param int[] $post_ids Post IDs.
	 */
	private function go_to_list( array $post_ids ): void {
		$GLOBALS['wp_query']     = new \WP_Query(
			array(
				'post_type' => 'page',
				'post__in'  => $post_ids,
				'orderby'   => 'post__in',
			)
		);
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		$GLOBALS['wp_query']->the_post();
	}

	/**
	 * Undo the loop state.
	 */
	protected function tearDown(): void {
		if ( isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof \WP_Query ) {
			$GLOBALS['wp_query']->in_the_loop = false;
		}
		wp_reset_postdata();
		parent::tearDown();
	}

	/**
	 * A post in another language gets its language around the title and excerpt.
	 */
	public function test_title_and_excerpt_in_list(): void {
		$post_id = $this->create_page( 'pt-PT', 'ltr' );
		$this->go_to_list( array( $post_id ) );
		$this->assertSame( '<span lang="pt-PT">Título</span>', $this->plugin->apply_post_lang_to_listed_title( 'Título', $post_id ) );
		$this->assertSame( '<div lang="pt-PT"><p>Resumo</p></div>', $this->plugin->apply_post_lang_to_listed_excerpt( '<p>Resumo</p>' ) );
	}

	/**
	 * Posts without a language, or in the document's language, are left alone.
	 */
	public function test_no_wrap_when_same_or_no_language(): void {
		$none = $this->create_page( '', '' );
		$same = $this->create_page( get_bloginfo( 'language' ), 'ltr' );
		$this->go_to_list( array( $none, $same ) );
		$this->assertSame( 'Title', $this->plugin->apply_post_lang_to_listed_title( 'Title', $none ) );
		$this->assertSame( 'Title', $this->plugin->apply_post_lang_to_listed_title( 'Title', $same ) );
	}

	/**
	 * On the post's own page the whole document has its language: nothing to wrap.
	 */
	public function test_no_wrap_on_its_own_page(): void {
		$post_id = $this->create_page( 'pt-PT', 'ltr' );
		$this->go_to_page( $post_id );
		$GLOBALS['wp_query']->the_post();
		$this->assertSame( 'Título', $this->plugin->apply_post_lang_to_listed_title( 'Título', $post_id ) );
	}

	/**
	 * Outside the loop (browser tab title, menus, widgets), the title stays plain text.
	 */
	public function test_no_wrap_outside_the_loop(): void {
		$post_id = $this->create_page( 'pt-PT', 'ltr' );
		$this->go_to_list( array( $post_id ) );
		$GLOBALS['wp_query']->in_the_loop = false;
		$this->assertSame( 'Título', $this->plugin->apply_post_lang_to_listed_title( 'Título', $post_id ) );
	}

	/**
	 * The filter can switch it off.
	 */
	public function test_filter_switches_it_off(): void {
		$post_id = $this->create_page( 'pt-PT', 'ltr' );
		$this->go_to_list( array( $post_id ) );
		add_filter( 'nakedcatplugins_lang_attribute_blocks_mark_listed_post', '__return_false' );
		$title = $this->plugin->apply_post_lang_to_listed_title( 'Título', $post_id );
		remove_filter( 'nakedcatplugins_lang_attribute_blocks_mark_listed_post', '__return_false' );
		$this->assertSame( 'Título', $title );
	}

	/**
	 * In a block theme's Query Loop, the Post Title block gets the attribute on its own element.
	 */
	public function test_post_title_block_in_query_loop(): void {
		$post_id  = $this->create_page( 'pt-PT', 'ltr' );
		$this->go_to_list( array( $post_id ) );
		$block    = array(
			'blockName'    => 'core/post-title',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
		$instance = new \WP_Block( $block, array( 'postId' => $post_id ) );
		$html     = $this->plugin->apply_post_lang_to_listed_block( '<h2 class="wp-block-post-title">Título</h2>', $block, $instance );
		$this->assertSame( '<h2 lang="pt-PT" class="wp-block-post-title">Título</h2>', $html );
	}
}
