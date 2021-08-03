<?php
/**
 * AJAX Event Handlers.
 *
 * @package Pixelgrade\WPPostNotes
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\WPPostNotes;

/**
 * Ajax Events class.
 */
class Ajax {

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {

		$ajax_events = array(
			'add_post_note',
			'delete_post_note',
		);

		foreach ( $ajax_events as $ajax_event ) {
			add_action( 'wp_ajax_pixelgrade_wppostnotes_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}
	}

	/**
	 * Add order note via ajax.
	 */
	public static function add_post_note() {
		check_ajax_referer( 'add-post-note', 'security' );

		if ( ! isset( $_POST['post_id'], $_POST['note'], $_POST['note_type'] ) || ! current_user_can( 'edit_post', absint( $_POST['post_id'] ) ) ) {
			wp_die( -1 );
		}

		$post_id   = absint( $_POST['post_id'] );
		$note      = wp_kses_post( trim( wp_unslash( $_POST['note'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$note_type = sanitize_text_field( wp_unslash( $_POST['note_type'] ) );

		$is_customer_note = ( 'customer' === $note_type ) ? 1 : 0;

		if ( $post_id > 0 ) {
			$post      = get_post( $post_id );
			$comment_id = add_note($post, $note, $is_customer_note, true );
			$note       = get_note( $comment_id );

			$note_classes   = array( 'note' );
			$note_classes[] = $is_customer_note ? 'customer-note' : '';
			$note_classes   = apply_filters( 'pixelgrade_wppostnotes/note_class', array_filter( $note_classes ), $note );
			?>
			<li rel="<?php echo absint( $note->id ); ?>" class="<?php echo esc_attr( implode( ' ', $note_classes ) ); ?>">
				<div class="note_content">
					<?php echo wp_kses_post( wpautop( wptexturize( make_clickable( $note->content ) ) ) ); ?>
				</div>
				<p class="meta">
					<abbr class="exact-date" title="<?php echo esc_attr( $note->date_created->date( 'y-m-d h:i:s' ) ); ?>">
						<?php
						/* translators: $1: Date created, $2 Time created */
						printf( esc_html__( 'added on %1$s at %2$s', 'woocommerce' ), esc_html( $note->date_created->date_i18n( wc_date_format() ) ), esc_html( $note->date_created->date_i18n( wc_time_format() ) ) );
						?>
					</abbr>
					<?php
					if ( 'system' !== $note->added_by ) :
						/* translators: %s: note author */
						printf( ' ' . esc_html__( 'by %s', 'woocommerce' ), esc_html( $note->added_by ) );
					endif;
					?>
					<a href="#" class="delete_note" role="button"><?php esc_html_e( 'Delete note', 'woocommerce' ); ?></a>
				</p>
			</li>
			<?php
		}
		wp_die();
	}

	/**
	 * Delete order note via ajax.
	 */
	public static function delete_post_note() {
		check_ajax_referer( 'delete-post-note', 'security' );

		if ( ! isset( $_POST['note_id'] ) || ! current_user_can( 'edit_post', absint( $_POST['note_id'] ) ) ) {
			wp_die( -1 );
		}

		$note_id = absint( $_POST['note_id'] );

		if ( $note_id > 0 ) {
			wp_delete_comment( $note_id, true );
		}
		wp_die();
	}
}
