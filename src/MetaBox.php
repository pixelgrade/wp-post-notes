<?php
/**
 * Post Notes Metabox.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pixelgrade\WPPostNotes
 */

declare ( strict_types=1 );

namespace Pixelgrade\WPPostNotes;

/**
 * MetaBox Class.
 */
class MetaBox {

	/**
	 * Output the metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $box  The box array with details about the metabox like id, title, callback, and optional `args`.
	 */
	public static function output( \WP_Post $post, array $box = [] ) {
		global $post;

		$args = $box['args'] ?? [];

		$query_args = array(
				'post_id' => $post->ID,
		);

		$notes = get_notes( $query_args );

		do_action( 'pixelgrade_wppostnotes/before_notes_list', $post, $notes, $args );

		include __DIR__ . '/views/html-post-notes-list.php';

		do_action( 'pixelgrade_wppostnotes/after_notes_list', $post, $notes, $args );

		do_action( 'pixelgrade_wppostnotes/before_add_note_form', $post, $args );
		?>
		<div class="add_note">
			<p>
				<label for="post_note_content"><?php echo $args['i18n']['add_post_note_label'] ?? ''; ?><?php echo self::help_tip( $args['i18n']['add_post_note_tip'] ?? '' ); ?></label>
				<textarea type="text" name="post_note_content" id="post_note_content" class="input-text" cols="20" rows="5"></textarea>
			</p>
			<p>
				<label for="post_note_type"
				       class="screen-reader-text"><?php echo $args['i18n']['post_note_type_label'] ?? ''; ?></label>
				<select name="post_note_type" id="post_note_type">
					<?php foreach ( $args['note_types'] as $type_config ) { ?>
						<option value="<?php echo esc_attr( $type_config['value'] ); ?>"><?php echo $type_config['label']; ?></option>
					<?php } ?>
				</select>
				<button type="button" class="add_note button"><?php echo $args['i18n']['add_post_note_button'] ?? ''; ?></button>
			</p>
		</div>
		<?php
		do_action( 'pixelgrade_wppostnotes/after_add_note_form', $post, $args );
	}

	/**
	 * Display a WooCommerce help tip.
	 *
	 * @since  0.1.0
	 *
	 * @param string $tip        Help tip text.
	 * @param bool   $allow_html Allow sanitized HTML if true or escape.
	 *
	 * @return string
	 */
	public static function help_tip( $tip, $allow_html = false ) {
		if ( $allow_html ) {
			$tip = wc_sanitize_tooltip( $tip );
		} else {
			$tip = esc_attr( $tip );
		}

		return '<span class="pixelgrade_wppostnotes-help-tip" data-tip="' . $tip . '"></span>';
	}
}
