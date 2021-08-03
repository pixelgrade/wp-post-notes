<?php
/**
 * Helper functions
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace Pixelgrade\WPPostNotes;

/**
 * Get an order note.
 *
 * @since  0.1.0
 * @param  int|\WP_Comment $data Note ID (or WP_Comment instance for internal use only).
 * @return \stdClass|null        Object with order note details or null when does not exists.
 */
function get_note( $data ) {
	if ( is_numeric( $data ) ) {
		$data = get_comment( $data );
	}

	if ( ! is_a( $data, 'WP_Comment' ) ) {
		return null;
	}

	return (object) apply_filters(
		'pixelgrade_wppostnotes/get_note',
		array(
			'id'            => (int) $data->comment_ID,
			'date_created'  => string_to_datetime( $data->comment_date ),
			'content'       => $data->comment_content,
			'customer_note' => (bool) get_comment_meta( $data->comment_ID, 'is_customer_note', true ),
			'added_by'      => __( 'WordPress', 'woocommerce' ) === $data->comment_author ? 'system' : $data->comment_author,
		),
		$data
	);
}

/**
 * Get order notes.
 *
 * @since  0.1.0
 *
 * @param array     $args          Query arguments {
 *     Array of query parameters.
 *
 *     @type string $limit         Maximum number of notes to retrieve.
 *                                 Default empty (no limit).
 *     @type int    $post_id      Limit results to those affiliated with a given post ID.
 *                                 Default 0.
 *     @type array  $post__in     Array of post IDs to include affiliated notes for.
 *                                 Default empty.
 *     @type array  $post__not_in Array of post IDs to exclude affiliated notes for.
 *                                 Default empty.
 *     @type string $orderby       Define how should sort notes.
 *                                 Accepts 'date_created', 'date_created_gmt' or 'id'.
 *                                 Default: 'id'.
 *     @type string $order         How to order retrieved notes.
 *                                 Accepts 'ASC' or 'DESC'.
 *                                 Default: 'DESC'.
 *     @type string $type          Define what type of note should retrieve.
 *                                 Accepts 'customer', 'internal' or empty for both.
 *                                 Default empty.
 * }
 * @return \stdClass[]              Array of stdClass objects with order notes details.
 */
function get_notes( array $args ) {
	$key_mapping = array(
		'limit'         => 'number',
	);

	foreach ( $key_mapping as $query_key => $db_key ) {
		if ( isset( $args[ $query_key ] ) ) {
			$args[ $db_key ] = $args[ $query_key ];
			unset( $args[ $query_key ] );
		}
	}

	// Define orderby.
	$orderby_mapping = array(
		'date_created'     => 'comment_date',
		'date_created_gmt' => 'comment_date_gmt',
		'id'               => 'comment_ID',
	);

	$args['orderby'] = ! empty( $args['orderby'] ) && in_array( $args['orderby'], array( 'date_created', 'date_created_gmt', 'id' ), true ) ? $orderby_mapping[ $args['orderby'] ] : 'comment_ID';

	// Set WooCommerce order type.
	if ( isset( $args['type'] ) && 'customer' === $args['type'] ) {
		$args['meta_query'] = array( // WPCS: slow query ok.
			array(
				'key'     => 'is_customer_note',
				'value'   => 1,
				'compare' => '=',
			),
		);
	} elseif ( isset( $args['type'] ) && 'internal' === $args['type'] ) {
		$args['meta_query'] = array( // WPCS: slow query ok.
			array(
				'key'     => 'is_customer_note',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	// Set correct comment type.
	$args['type'] = 'post_note';

	// Always approved.
	$args['status'] = 'approve';

	// Does not support 'count' or 'fields'.
	unset( $args['count'], $args['fields'] );

	remove_filter( 'comments_clauses', [ 'Pixelgrade\WPPostNotes\PostNotes', 'exclude_post_note_comments' ], 10, 1 );

	$notes = get_comments( $args );

	add_filter( 'comments_clauses', [ 'Pixelgrade\WPPostNotes\PostNotes', 'exclude_post_note_comments' ], 10, 1 );

	return array_filter( array_map( 'Pixelgrade\WPPostNotes\get_note', $notes ) );
}

///**
// * List order notes (public) for the customer.
// *
// * @return array
// */
//function get_customer_post_notes() {
//	$notes = array();
//	$args  = array(
//		'post_id' => $this->get_id(),
//		'approve' => 'approve',
//		'type'    => '',
//	);
//
//	remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
//
//	$comments = get_comments( $args );
//
//	foreach ( $comments as $comment ) {
//		if ( ! get_comment_meta( $comment->comment_ID, 'is_customer_note', true ) ) {
//			continue;
//		}
//		$comment->comment_content = make_clickable( $comment->comment_content );
//		$notes[]                  = $comment;
//	}
//
//	add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
//
//	return $notes;
//}

/**
 * Create a post note.
 *
 * @since  0.1.0
 *
 * @param int    $post_id          Post ID.
 * @param string $note             Note to add.
 * @param bool   $is_customer_note If this is a costumer note.
 * @param bool   $added_by_user    If note is create by a user.
 *
 * @return int|\WP_Error             Integer when created or WP_Error when found an error.
 */
function create_note( int $post_id, string $note, bool $is_customer_note, bool $added_by_user ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return new \WP_Error( 'invalid_post_id', __( 'Invalid post ID.', 'woocommerce' ), array( 'status' => 400 ) );
	}

	return add_note( $post, $note, (int) $is_customer_note, $added_by_user );
}

/**
 * Adds a note (comment) to a post. Post must exist.
 *
 * @param \WP_Post $post          Post to add note to.
 * @param string   $note          Note content to add.
 * @param int      $is_customer_note  Is this a note for the user?.
 * @param bool     $added_by_user Was the note added by a user?.
 *
 * @return int                       Comment/Note ID.
 */
function add_note( \WP_Post $post , string $note, int $is_customer_note = 0, bool $added_by_user = false ): int {
	if ( ! $post->ID ) {
		return 0;
	}

	if ( is_user_logged_in() && current_user_can( 'edit_post', $post->ID ) && $added_by_user ) {
		$user                 = get_user_by( 'id', get_current_user_id() );
		$comment_author       = $user->display_name;
		$comment_author_email = $user->user_email;
	} else {
		$comment_author        = __( 'WordPress', 'woocommerce' );
		$comment_author_email  = strtolower( __( 'WordPress', 'woocommerce' ) ) . '@';
		$comment_author_email .= isset( $_SERVER['HTTP_HOST'] ) ? str_replace( 'www.', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : 'noreply.com'; // WPCS: input var ok.
		$comment_author_email  = sanitize_email( $comment_author_email );
	}
	$commentdata = apply_filters(
		'pixelgrade_wppostnotes/new_post_note_data',
		array(
			'comment_post_ID'      => $post->ID,
			'comment_author'       => $comment_author,
			'comment_author_email' => $comment_author_email,
			'comment_author_url'   => '',
			'comment_content'      => $note,
			'comment_agent'        => 'WordPress',
			'comment_type'         => 'post_note',
			'comment_parent'       => 0,
			'comment_approved'     => 1,
		),
		array(
			'order_id'         => $post->ID,
			'is_customer_note' => $is_customer_note,
		)
	);

	$comment_id = wp_insert_comment( $commentdata );

	if ( $is_customer_note ) {
		add_comment_meta( $comment_id, 'is_customer_note', 1 );

		do_action(
			'pixelgrade_wppostnotes/new_customer_note',
			array(
				'order_id'      => $post->ID,
				'customer_note' => $commentdata['comment_content'],
			)
		);
	}

	/**
	 * Action hook fired after a post note is added.
	 *
	 * @param int      $post_note_id Post note ID.
	 * @param \WC_Order $post         Post data.
	 *
	 * @since 0.1.0
	 */
	do_action( 'pixelgrade_wppostnotes/post_note_added', $comment_id, $post );

	return $comment_id;
}

/**
 * Delete an order note.
 *
 * @since  0.1.0
 *
 * @param int $note_id Order note.
 *
 * @return bool         True on success, false on failure.
 */
function delete_note( int $note_id ): bool {
	return wp_delete_comment( $note_id, true );
}

/**
 * @param       $time
 * @param false $gmt
 *
 * @return \DateTimeImmutable|false
 */
function string_to_datetime( $time, $gmt = false ) {
	$wp_timezone = wp_timezone();

	if ( $gmt ) {
		$timezone = new \DateTimeZone( 'UTC' );
	} else {
		$timezone = $wp_timezone;
	}

	if ( empty( $time ) || '0000-00-00 00:00:00' === $time ) {
		return false;
	}

	$datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', $time, $timezone );

	if ( false === $datetime ) {
		return false;
	}

	return $datetime->setTimezone( $wp_timezone );
}

/**
 *
 * @return string
 */
function get_date_format() {
	$date_format = get_option( 'date_format' );
	if ( empty( $date_format ) ) {
		// Return default date format if the option is empty.
		$date_format = 'F j, Y';
	}
	return apply_filters( 'pixelgrade_wppostnotes/date_format', $date_format );
}

/**
 *
 * @return string
 */
function get_time_format() {
	$time_format = get_option( 'time_format' );
	if ( empty( $time_format ) ) {
		// Return default time format if the option is empty.
		$time_format = 'g:i a';
	}
	return apply_filters( 'pixelgrade_wppostnotes/time_format', $time_format );
}

/**
 * Whether debug mode is enabled.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function is_debug_mode(): bool {
	return \defined( 'WP_DEBUG' ) && true === WP_DEBUG;
}

function doing_it_wrong( $function, $message, $version ) {
	// @codingStandardsIgnoreStart
	$message .= ' Backtrace: ' . wp_debug_backtrace_summary();

	if ( wp_doing_ajax() || is_rest_request() ) {
		do_action( 'doing_it_wrong_run', $function, $message, $version );
		error_log( "{$function} was called incorrectly. {$message}. This message was added in version {$version}." );
	} else {
		_doing_it_wrong( $function, $message, $version );
	}
}

function is_rest_request() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}

	$rest_prefix         = trailingslashit( rest_get_url_prefix() );
	$is_rest_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ) ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	return apply_filters( 'pixelgrade_wppostnotes/is_rest_api_request', $is_rest_api_request );
}

/**
 * Whether we are running unit tests.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function is_running_unit_tests(): bool {
	return \defined( 'Pixelgrade\WPPostNotes\RUNNING_UNIT_TESTS' ) && true === RUNNING_UNIT_TESTS;
}

/**
 * Test if a given URL is one that we identify as a local/development site.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function is_dev_url( string $url ): bool {
	// Local/development url parts to match for
	$devsite_needles = array(
		'localhost',
		':8888',
		'.local',
		'pixelgrade.dev',
		'.dev',
		':8082',
		'staging.',
	);

	foreach ( $devsite_needles as $needle ) {
		if ( false !== strpos( $url, $needle ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Preload REST API data.
 *
 * @since 0.1.0
 *
 * @param array $paths Array of REST paths.
 */
function preload_rest_data( $paths ) {
	$preload_data = array_reduce(
		$paths,
		'rest_preload_api_request',
		[]
	);

	wp_add_inline_script(
		'wp-api-fetch',
		sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', wp_json_encode( $preload_data ) ),
		'after'
	);
}
