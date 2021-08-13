<?php
/**
 * AJAX Event Handlers.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pixelgrade\WPPostNotes
 */

declare ( strict_types=1 );

namespace Pixelgrade\WPPostNotes;

/**
 * Ajax Events class.
 */
class Ajax {

	/**
	 * The PostNotes instance to use.
	 *
	 * @since 0.2.0
	 *
	 * @var PostNotes|null
	 */
	protected ?PostNotes $post_notes = null;

	/**
	 * Hook in ajax handlers.
	 *
	 * @since 0.1.0
	 */
	public function init( PostNotes $post_notes ) {
		$this->post_notes = $post_notes;

		$this->add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 *
	 * @since 0.1.0
	 */
	public function add_ajax_events() {

		$ajax_events = [
				'add_post_note',
				'delete_post_note',
		];

		foreach ( $ajax_events as $ajax_event ) {
			add_action( 'wp_ajax_pixelgrade_wppostnotes_' . $ajax_event, [ $this, $ajax_event ] );
		}
	}

	/**
	 * Add post note via ajax.
	 *
	 * @since 0.1.0
	 */
	public function add_post_note() {
		check_ajax_referer( 'add-post-note', 'security' );

		if ( ! isset( $_POST['post_id'], $_POST['note'], $_POST['note_type'] ) || ! current_user_can( 'edit_post', absint( $_POST['post_id'] ) ) ) {
			wp_die( - 1 );
		}

		$post_id   = absint( $_POST['post_id'] );
		$note      = wp_kses_post( trim( wp_unslash( $_POST['note'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$note_type = sanitize_text_field( wp_unslash( $_POST['note_type'] ) );

		if ( $post_id > 0 ) {
			$post       = get_post( $post_id );
			$comment_id = add_note( $post, $note, $note_type, true );

			$note       = get_note( $comment_id );
			$args = $this->post_notes->get_config_callback_args();

			include __DIR__ . '/views/html-post-note.php';
		}
		wp_die();
	}

	/**
	 * Delete post note via ajax.
	 *
	 * @since 0.1.0
	 */
	public function delete_post_note() {
		check_ajax_referer( 'delete-post-note', 'security' );

		if ( ! isset( $_POST['note_id'] ) || ! current_user_can( 'edit_post', absint( $_POST['note_id'] ) ) ) {
			wp_die( - 1 );
		}

		$note_id = absint( $_POST['note_id'] );

		if ( $note_id > 0 ) {
			wp_delete_comment( $note_id, true );
		}
		wp_die();
	}
}
