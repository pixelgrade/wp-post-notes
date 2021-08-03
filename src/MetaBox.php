<?php
/**
 * Post Notes Metabox.
 *
 * @package Pixelgrade\WPPostNotes
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\WPPostNotes;

/**
 * MetaBox Class.
 */
class MetaBox {

	/**
	 * Output the metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function output( \WP_Post $post ) {
		global $post;

		$args = array(
			'post_id' => $post->ID,
		);

		$notes = get_notes( $args );

		include __DIR__ . '/views/html-post-notes.php';
		?>
		<div class="add_note">
			<p>
				<label for="add_post_note"><?php esc_html_e( 'Add note', 'woocommerce' ); ?> <?php echo self::help_tip( __( 'Add a note for your reference, or add a customer note (the user will be notified).', 'woocommerce' ) ); ?></label>
				<textarea type="text" name="order_note" id="add_post_note" class="input-text" cols="20" rows="5"></textarea>
			</p>
			<p>
				<label for="order_note_type" class="screen-reader-text"><?php esc_html_e( 'Note type', 'woocommerce' ); ?></label>
				<select name="order_note_type" id="order_note_type">
					<option value=""><?php esc_html_e( 'Private note', 'woocommerce' ); ?></option>
					<option value="customer"><?php esc_html_e( 'Note to customer', 'woocommerce' ); ?></option>
				</select>
				<button type="button" class="add_note button"><?php esc_html_e( 'Add', 'woocommerce' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Display a WooCommerce help tip.
	 *
	 * @since  0.1.0
	 *
	 * @param  string $tip        Help tip text.
	 * @param  bool   $allow_html Allow sanitized HTML if true or escape.
	 * @return string
	 */
	public static function help_tip( $tip, $allow_html = false ) {
		if ( $allow_html ) {
			$tip = wc_sanitize_tooltip( $tip );
		} else {
			$tip = esc_attr( $tip );
		}

		return '<span class="woocommerce-help-tip" data-tip="' . $tip . '"></span>';
	}
}
