<?php
/**
 * Post note HTML for the meta box list.
 *
 * @package Pixelgrade\WPPostNotes
 */

/**
 * @global stdClass $note
 * @global \WP_Post $post
 * @global array    $args
 */

use function Pixelgrade\WPPostNotes\get_date_format;
use function Pixelgrade\WPPostNotes\get_time_format;

defined( 'ABSPATH' ) || exit;

$css_class   = [ 'note', $note->note_type . '-note' ];
$css_class[] = 'system' === $note->added_by ? 'system-note' : '';
$css_class   = apply_filters( 'pixelgrade_wppostnotes/note_class', array_filter( $css_class ), $note, $post, $args );
?>
<li rel="<?php echo absint( $note->id ); ?>" class="<?php echo esc_attr( implode( ' ', $css_class ) ); ?>">
	<div class="note_content">
		<?php echo wpautop( wptexturize( wp_kses_post( $note->content ) ) ); // @codingStandardsIgnoreLine ?>
	</div>
	<p class="meta">
		<abbr class="exact-date" title="<?php echo esc_attr( $note->date_created->format( 'Y-m-d H:i:s' ) ); ?>">
			<?php
			/* translators: %1$s: note date %2$s: note time */
			echo esc_html(
					sprintf( __( 'added on %1$s at %2$s', 'pixelgrade-wppostnotes' ),
							wp_date( get_date_format(), $note->date_created->format( 'U' ) ),
							wp_date( get_time_format(), $note->date_created->format( 'U' ) )
					)
			);
			?>
		</abbr>
		<?php
		if ( 'system' !== $note->added_by ) :
			/* translators: %s: note author */
			echo esc_html( sprintf( ' ' . __( 'by %s', 'pixelgrade-wppostnotes' ), $note->added_by ) );
		endif;
		?>
		<a href="#" class="delete_note" role="button"><?php echo $args['i18n']['delete_note_button'] ?? ''; ?></a>
	</p>
</li>
