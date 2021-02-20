<?php
/**
 * (Post) Content functions
 *
 * @package LifterLMS/Functions
 *
 * @since 3.25.1
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'llms_get_post_content' ) ) {

	/**
	 * Post Template Include
	 *
	 * Adds LifterLMS template content before and after the post's default content.
	 *
	 * @since 1.0.0
	 * @since 3.25.2 Unknown.
	 * @since [version] TODO.
	 *
	 * @param string $content WP_Post post_content.
	 * @return string
	 */
	function llms_get_post_content( $content ) {

		global $post;
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'course', 'llms_membership', 'lesson', 'llms_quiz' ), true ) ) {
			return $content;
		}

		$restrictions    = llms_page_restricted( $post->ID );
		$post_type       = str_replace( 'llms_', '', $post->post_type );
		$template_before = 'single-' . $post_type . '-before';
		$template_after  = 'single-' . $post_type . '-after';

		if ( $restrictions['is_restricted'] ) {
			$content = llms_get_post_sales_page_content( $post, $content );
			if ( in_array( $post->post_type, array( 'lesson', 'llms_quiz' ), true ) ) {
				$content         = '';
				$template_before = 'no-access-before';
				$template_after  = 'no-access-after';
			}
		}

		ob_start();
		load_template( llms_get_template_part_contents( 'content', $template_before ), false );
		$before = ob_get_clean();

		ob_start();
		load_template( llms_get_template_part_contents( 'content', $template_after ), false );
		$after = ob_get_clean();

		/**
		 * Filter the post_content of a LifterLMS post type.
		 *
		 * @since Unknown
		 *
		 * @param string  $content      Post content.
		 * @param WP_Post $post         Post object.
		 * @param array   $restrictions Result from `llms_page_restricted()` for the current post.
		 */
		return apply_filters( 'llms_get_post_content', do_shortcode( $before . $content . $after ), $post, $restrictions );

	}
}

/**
 * Retrieve the sales page content for a course or membership
 *
 * By default only courses and memberships support sales pages, the meta property
 * must be set to `content` or an empty string, and the post must have a `post_excerpt`
 * property value.
 *
 * @since [version]
 *
 * @param WP_Post $post    The post object.
 * @param string  $default Optional. Default content to use when no override content can be found.
 * @return string
 */
function llms_get_post_sales_page_content( $post, $default = '' ) {

	$content = $default;

	if ( post_type_supports( $post->post_type, 'llms-sales-page' ) ) {
		$sales_page = get_post_meta( $post->ID, '_llms_sales_page_content_type', true );
		if ( $post->post_excerpt && ( '' === $sales_page || 'content' === $sales_page ) ) {
			add_filter( 'the_excerpt', array( $GLOBALS['wp_embed'], 'autoembed' ), 9 );
			$content = llms_get_excerpt( $post->ID );
		}
	}

	/**
	 * Filters the HTML content of a LifterLMS post type's sales page content
	 *
	 * @since [version]
	 *
	 * @param string  $content HTML content of the sales page.
	 * @param WP_Post $content Post object.
	 * @param string  $default Default content used when no override content can be found.
	 */
	return apply_filters( 'llms_post_sales_page_content', $content, $post, $default );

}

/**
 * Initialize LifterLMS post type content filters
 *
 * This method is used to determine whether or `llms_get_post_content()` should automatically
 * be added as a filter callback for the WP core `the_content` filter.
 *
 * When working with posts on the admin panel (during course building, importing) we don't want
 * other plugins that may desire running `apply_filters( 'the_content', $content )` to apply our
 * plugin's filters.
 *
 * Additionally, we don't want to return template information when processing REST requests.
 *
 * @since [version]
 *
 * @return boolean Returns `true` if content filters are added and `false` if not.
 */
function llms_post_content_init() {

	// Don't filter any requests on the admin panel or when processing REST requests.
	$should_filter = ( ! is_admin() && ! llms_is_rest() );

	/**
	 * Filters whether or not LifterLMS content filters should be applied.
	 *
	 * @since [version]
	 *
	 * @param boolean $should_filter Whether or not to filter the content.
	 */
	if ( apply_filters( 'llms_should_filter_post_content', $should_filter ) ) {
		return add_filter( 'the_content', 'llms_get_post_content' );
	}

	return false;

}
add_action( 'init', 'llms_post_content_init' );
