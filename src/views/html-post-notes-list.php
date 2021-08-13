<?php
/**
 * Post notes HTML for meta box.
 *
 * @package Pixelgrade\WPPostNotes
 */

/**
 * @global \WP_Post $post
 * @global array $notes
 * @global array $args
 */

defined( 'ABSPATH' ) || exit;

?>
<ul class="post_notes">
	<?php
	if ( $notes ) {
		foreach ( $notes as $note ) {
			include __DIR__ . '/html-post-note.php';
		}
	} else {
		?>
		<li class="no-items"><?php echo $args['i18n']['notes_list_no_items'] ?? ''; ?></li>
		<?php
	}
	?>
</ul>
