<?php
/**
 * Template loader.
 *
 * @since 1.0.0
 * @version 3.37.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template loader class.
 *
 * @since 1.0.0
 * @since 3.20.0 Unknown.
 * @since 3.37.2 Made sure notices are printed on sales pages too.
 */
class LLMS_Template_Loader {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @since 3.20.0 Unknown.
	 */
	public function __construct() {

		// do template loading.
		add_filter( 'template_include', array( $this, 'template_loader' ) );

		// restriction actions for each kind of restriction.
		$reasons = apply_filters(
			'llms_restriction_reasons',
			array(
				'course_prerequisite',
				'course_track_prerequisite',
				'course_time_period',
				'enrollment_lesson',
				'lesson_drip',
				'lesson_prerequisite',
				'membership',
				'sitewide_membership',
				'quiz',
			)
		);

		foreach ( $reasons as $reason ) {
			add_action( 'llms_content_restricted_by_' . $reason, array( $this, 'restricted_by_' . $reason ), 10, 1 );
		}

		add_action( 'wp', array( $this, 'maybe_redirect_to_sales_page' ) );

	}

	/**
	 * Add a notice and/or redirect during restriction actions
	 *
	 * @since 3.0.0
	 *
	 * @param string $msg      Notice message to display.
	 * @param string $redirect Optional. Url to redirect to after setting a notice. Default empty string.
	 * @param string $msg_type Optional. Type of message to display [notice|success|error|debug]. Default 'notice'.
	 * @return void
	 */
	private function handle_restriction( $msg = '', $redirect = '', $msg_type = 'notice' ) {

		if ( $msg ) {
			llms_add_notice( do_shortcode( $msg ), $msg_type );
		}

		if ( $redirect ) {
			wp_redirect( $redirect );
			exit;
		}

	}

	/**
	 * Handle sales page redirects for courses & memberships
	 *
	 * @since 3.20.0
	 * @since 3.37.2 Flag to print notices, if there are, when landing on the redirected sales page.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_sales_page() {

		// only proceed for courses and memberships.
		if ( ! in_array( get_post_type(), array( 'course', 'llms_membership' ), true ) ) {
			return;
		}

		$page_restricted = llms_page_restricted( get_the_id() );

		// only proceed if the page isn't restricted.
		if ( ! $page_restricted['is_restricted'] ) {
			return;
		}

		$post = llms_get_post( get_the_ID() );

		if ( ! $post->has_sales_page_redirect() ) {
			return;
		}

		llms_redirect_and_exit(
			llms_notice_count() ?
				add_query_arg(
					array(
						'llms_print_notices' => 1,
					),
					$post->get_sales_page_url()
				) : $post->get_sales_page_url(),
			array(
				'safe' => false,
			)
		);

	}

	/**
	 * Handle redirects and messages when a user attempts to access an item
	 * restricted by a course track prerequisite.
	 *
	 * Redirect to parent course and display message.
	 * If course do nothing.
	 *
	 * @since 3.7.3
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_course_track_prerequisite( $info ) {

		if ( 'course' === get_post_type( $info['content_id'] ) ) {
			return;
		}

		$msg      = llms_get_restriction_message( $info );
		$course   = llms_get_post_parent_course( $info['content_id'] );
		$redirect = get_permalink( $course->get( 'id' ) );
		$this->handle_restriction(
			apply_filters( 'llms_restricted_by_course_track_prerequisite_message', $msg, $info ),
			apply_filters( 'llms_restricted_by_course_track_prerequisite_redirect', $redirect, $info ),
			'error'
		);

	}

	/**
	 * Handle redirects and messages when a user attempts to access an item
	 * restricted by a course prerequisite.
	 *
	 * Redirect to parent course and display message.
	 * If course do nothing.
	 *
	 * @since 3.7.3
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_course_prerequisite( $info ) {

		if ( 'course' === get_post_type( $info['content_id'] ) ) {
			return;
		}

		$msg      = llms_get_restriction_message( $info );
		$course   = llms_get_post_parent_course( $info['content_id'] );
		$redirect = get_permalink( $course->get( 'id' ) );
		$this->handle_restriction(
			apply_filters( 'llms_restricted_by_course_prerequisite_message', $msg, $info ),
			apply_filters( 'llms_restricted_by_course_prerequisite_redirect', $redirect, $info ),
			'error'
		);

	}

	/**
	 * Handle redirects and messages when a course or associated quiz or lesson has time period
	 * date restrictions placed upon it.
	 *
	 * Quizzes & Lessons redirect to the parent course.
	 * Courses display a notice until the course opens and an error once the course closes.
	 *
	 * @since 3.0.0
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_course_time_period( $info ) {

		$post_type = get_post_type( $info['content_id'] );

		// if this restriction occurs when attempting to view a lesson,
		// redirect the user to the course, course restriction will handle display of the
		// message once we get there.
		// This prevents duplicate messages from being displayed.
		if ( 'lesson' === $post_type || 'llms_quiz' === $post_type ) {
			$msg      = '';
			$redirect = get_permalink( $info['restriction_id'] );
		}

		if ( ! $msg && ! $redirect ) {
			return;
		}

		// handle the restriction action & allow developers to filter the results.
		$this->handle_restriction(
			apply_filters( 'llms_restricted_by_course_time_period_message', $msg, $info ),
			apply_filters( 'llms_restricted_by_course_time_period_redirect', $redirect, $info ),
			'notice'
		);

	}

	/**
	 * Handle redirects and messages when a user attempts to access a lesson
	 * for a course they're not enrolled in.
	 *
	 * Redirect to parent course and display message.
	 *
	 * @since 3.0.0
	 * @since 3.2.4 Moved message generation to `llms_get_restriction_message()`
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_enrollment_lesson( $info ) {

		$msg      = llms_get_restriction_message( $info );
		$redirect = get_permalink( $info['restriction_id'] );

		$this->handle_restriction(
			apply_filters( 'llms_restricted_by_enrollment_lesson_message', $msg, $info ),
			apply_filters( 'llms_restricted_by_enrollment_lesson_redirect', $redirect, $info ),
			'error'
		);

	}

	/**
	 * Handle redirects and messages when a user attempts to access a lesson
	 * for that is restricted by lesson drip settings.
	 *
	 * Redirect to parent course and display message.
	 *
	 * @since 3.0.0
	 * @since 3.2.4 Moved message generation to `llms_get_restriction_message()`
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_lesson_drip( $info ) {

		$lesson = new LLMS_Lesson( $info['restriction_id'] );

		$msg      = llms_get_restriction_message( $info );
		$redirect = get_permalink( $lesson->get_parent_course() );

		$this->handle_restriction(
			apply_filters( 'llms_restricted_by_lesson_drip_message', $msg, $info ),
			apply_filters( 'llms_restricted_by_lesson_drip_redirect', $redirect, $info ),
			'error'
		);

	}

	/**
	 * Handle redirects and messages when a user attempts to access a lesson
	 * for that is restricted by prerequisite lesson.
	 *
	 * Redirect to parent course and display message.
	 *
	 * @since 3.0.0
	 * @since 3.2.4 Moved message generation to `llms_get_restriction_message()`
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_lesson_prerequisite( $info ) {

		$msg      = llms_get_restriction_message( $info );
		$redirect = get_permalink( $info['restriction_id'] );
		$this->handle_restriction(
			apply_filters( 'llms_restricted_by_lesson_prerequisite_message', $msg, $info ),
			apply_filters( 'llms_restricted_by_lesson_prerequisite_redirect', $redirect, $info ),
			'error'
		);

	}

	/**
	 * Handle content restricted to a membership.
	 *
	 * Parses and obeys Membership "Restriction Behavior" settings.
	 *
	 * @since 3.0.0
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_membership( $info ) {

		$membership_id = $info['restriction_id'];

		// do nothing if we don't have a membership id.
		if ( ! empty( $membership_id ) && is_numeric( $membership_id ) ) {

			// instantiate the membership.
			$membership = new LLMS_Membership( $membership_id );

			$msg      = '';
			$redirect = '';

			// get the redirect based on the redirect type (if set).
			switch ( $membership->get( 'restriction_redirect_type' ) ) {

				case 'custom':
					$redirect = $membership->get( 'redirect_custom_url' );
					break;

				case 'membership':
					$redirect = get_permalink( $membership->get( 'id' ) );
					break;

				case 'page':
					$redirect = get_permalink( $membership->get( 'redirect_page_id' ) );
					break;

			}

			if ( 'yes' === $membership->get( 'restriction_add_notice' ) ) {

				$msg = $membership->get( 'restriction_notice' );

			}

			// handle the restriction action & allow developers to filter the results.
			$this->handle_restriction(
				apply_filters( 'llms_restricted_by_membership_message', $msg, $info ),
				apply_filters( 'llms_restricted_by_membership_redirect', $redirect, $info )
			);

		}

	}

	/**
	 * Handle attempts to access quizzes.
	 *
	 * @since 3.1.6
	 * @since 3.16.1 Unknown.
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_quiz( $info ) {

		$msg      = '';
		$redirect = '';

		if ( get_current_user_id() ) {

			$msg  = __( 'You must be enrolled in the course to access this quiz.', 'lifterlms' );
			$quiz = llms_get_post( $info['restriction_id'] );
			if ( $quiz ) {
				$course = $quiz->get_course();
				if ( $course ) {
					$redirect = get_permalink( $course->get( 'id' ) );
				}
			}
		} else {

			$msg      = __( 'You must be logged in to take quizzes.', 'lifterlms' );
			$redirect = llms_person_my_courses_url();

		}

		$this->handle_restriction(
			apply_filters( 'llms_restricted_by_membership_message', $msg, $info ),
			apply_filters( 'llms_restricted_by_membership_redirect', $redirect, $info ),
			'error'
		);

	}

	/**
	 * Handle content restricted to a membership
	 *
	 * Parses and obeys Membership "Restriction Behavior" settings.
	 *
	 * @since 3.0.0
	 *
	 * @param array $info Array of restriction info from `llms_page_restricted()`.
	 * @return void
	 */
	public function restricted_by_sitewide_membership( $info ) {
		$this->restricted_by_membership( $info );
	}

	/**
	 * Check if content should be restricted and include overrides where appropriate
	 * triggers various actions based on content restrictions.
	 *
	 * @since 1.0.0
	 * @since 3.16.11 Unknown.
	 * @since 3.37.2 Make sure to print notices on sales page redirect.
	 *
	 * @param string $template The template to load.
	 * @return string
	 */
	public function template_loader( $template ) {

		$page_restricted = llms_page_restricted( get_the_ID() );
		$post_type       = get_post_type();

		$this->maybe_print_notices_on_sales_page_redirect();

		// blog should bypass checks, except when sitewide restrictions are enabled.
		if ( is_home() && 'sitewide_membership' === $page_restricted['reason'] && $page_restricted['is_restricted'] ) {

			// generic content restricted action.
			do_action( 'lifterlms_content_restricted', $page_restricted );

			// specific content restriction action.
			do_action( 'llms_content_restricted_by_' . $page_restricted['reason'], $page_restricted );

			// prints notices on the blog page when there's not redirects setup.
			add_action( 'loop_start', 'llms_print_notices', 5 );

			return $template;

		} elseif ( $page_restricted['is_restricted'] ) {

			// generic content restricted action.
			do_action( 'lifterlms_content_restricted', $page_restricted );

			// specific content restriction action.
			do_action( 'llms_content_restricted_by_' . $page_restricted['reason'], $page_restricted );

			// the actual content of membership and courses is handled via separate wysiwyg areas,
			// so for these post types we'll return the regular template.
			if ( 'course' === $post_type || 'llms_membership' === $post_type ) {
				return $template;
			} else {
				$template = 'single-no-access.php';
			}
		} elseif ( is_post_type_archive( 'course' ) || is_page( llms_get_page_id( 'llms_shop' ) ) ) {

			$template = 'archive-course.php';

		} elseif ( is_tax( array( 'course_cat', 'course_tag', 'course_difficulty', 'course_track', 'membership_tag', 'membership_cat' ) ) ) {

			global $wp_query;
			$obj      = $wp_query->get_queried_object();
			$template = 'taxonomy-' . $obj->taxonomy . '.php';

		} elseif ( is_post_type_archive( 'llms_membership' ) || is_page( llms_get_page_id( 'memberships' ) ) ) {

			$template = 'archive-llms_membership.php';

		} elseif ( is_single() && ( get_post_type() === 'llms_certificate' || get_post_type() === 'llms_my_certificate' ) ) {

			$template = 'single-certificate.php';

		} else {

			return $template;

		}

		// check for an override file.
		$override      = llms_get_template_override( $template );
		$template_path = $override ? $override : LLMS()->plugin_path() . '/templates/';
		return $template_path . $template;

	}

	/**
	 * Maybe print notices after redirection.
	 *
	 * @since 3.37.2
	 *
	 * @return void
	 */
	private function maybe_print_notices_on_sales_page_redirect() {

		if ( llms_filter_input( INPUT_GET, 'llms_print_notices' ) ) {
			// prints notices on the page at loop start.
			add_action( 'loop_start', 'llms_print_notices', 5 );
		}

	}

}

new LLMS_Template_Loader();
