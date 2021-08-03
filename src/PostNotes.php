<?php
/**
 * Main Post Notes class.
 *
 * Many methods have been borrowed or started from corresponding methods and functions from WooCommerce.
 *
 * @package Pixelgrade\WPPostNotes
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\WPPostNotes;

class PostNotes {
	protected ?string $post_type = null;

	/**
	 * @param string|null $post_type The post type to attach post notes to.
	 *
	 * @throws \Exception
	 */
	public function __construct( string $post_type ) {
		$this->post_type = $post_type;

		$this->init();
	}

	/**
	 * @throws \Exception
	 */
	public function init() {
		$this->boot();
		$this->register_hooks();
	}

	protected function boot() {
		if ( ! defined( 'ABSPATH' ) ) {
			throw new \Exception( 'WP Post Notes cannot be booted outside of a WordPress environment.' );
		}

		if ( did_action( 'init' ) ) {
			throw new \Exception( 'WP Post Notes must be booted before the "init" WordPress action has fired.' );
		}

		# Define version constant
		if ( ! defined( __NAMESPACE__ . '\VERSION' ) ) {
			define( __NAMESPACE__ . '\VERSION', '0.1.0' );
		}

		# Define root directory
		if ( ! defined( __NAMESPACE__ . '\DIR' ) ) {
			define( __NAMESPACE__ . '\DIR', dirname( __DIR__ ) );
		}

		# Define root URL
		if ( ! defined( __NAMESPACE__ . '\URL' ) ) {
			define( __NAMESPACE__ . '\URL', $this->directory_to_url( DIR ) );
		}

		Ajax::init();
	}

	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ], 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 10 );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 30 );

		// Secure post notes.
		add_filter( 'comments_clauses', [ __CLASS__, 'exclude_post_note_comments' ], 10, 1 );
		add_filter( 'comment_feed_where', [ __CLASS__, 'exclude_post_note_comments_from_feed_where' ] );

		// Count comments.
		add_filter( 'wp_count_comments', [ __CLASS__, 'wp_count_comments' ], 10, 2 );

		// Delete comments count cache whenever there is a new comment or a comment status changes.
		add_action( 'wp_insert_comment', [ __CLASS__, 'delete_comments_count_cache' ] );
		add_action( 'wp_set_comment_status', [ __CLASS__, 'delete_comments_count_cache' ] );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 0.1.0
	 */
	public function register_assets() {
		global $post;

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_register_script( 'jquery-blockui', \path_join( URL, 'assets/js/jquery-blockui/jquery.blockUI' . $suffix . '.js' ), [ 'jquery' ], '2.70', true );

		wp_register_script(
			'pixelgrade_wppostnotes-metabox',
			\path_join( URL, 'assets/js/notes-metabox.js' ),
			[ 'jquery', 'jquery-blockui' ],
			'20210805',
			true
		);

		$params = array(
			'ajax_url'                      => admin_url( 'admin-ajax.php' ),
			'post_id'                       => $post->ID ?? '',
			'name_label'                    => __( 'Name', 'woocommerce' ),
			'remove_label'                  => __( 'Remove', 'woocommerce' ),
			'add_post_note_nonce'          => wp_create_nonce( 'add-post-note' ),
			'delete_post_note_nonce'       => wp_create_nonce( 'delete-post-note' ),
			'i18n_delete_note'              => __( 'Are you sure you wish to delete this note? This action cannot be undone.', 'woocommerce' ),
		);
		wp_localize_script( 'pixelgrade_wppostnotes-metabox', 'pixelgrade_wppostnotes_metabox', $params );

		wp_set_script_translations(
			'pixelgrade_wppostnotes-metabox',
			'pixelgrade-wppostnotes',
			\path_join( DIR, 'languages' ),
		);

		wp_register_style(
			'pixelgrade_wppostnotes-metabox',
			\path_join( URL, 'assets/css/notes-metabox.css' ),
			[],
			'20210805'
		);
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_assets() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		if ( ! in_array( $screen_id, [ $this->post_type, 'edit-' . $this->post_type ] ) ) {
			return;
		}

		wp_enqueue_script( 'pixelgrade_wppostnotes-metabox' );
		wp_enqueue_style( 'pixelgrade_wppostnotes-metabox' );
	}

	public function add_meta_boxes() {
		$post_type_object = get_post_type_object( $this->post_type );
		if ( empty( $post_type_object ) ) {
			return;
		}

		add_meta_box( 'pixelgrade_wppostnotes-metabox', sprintf( __( '%s notes', 'woocommerce' ), $post_type_object->labels->singular_name ), 'Pixelgrade\WPPostNotes\MetaBox::output', $this->post_type, 'side', 'default' );
	}

	/**
	 * Exclude order comments from queries and RSS.
	 *
	 * This code should exclude post_note comments from queries. Some queries (like the recent comments widget on the dashboard) are hardcoded
	 * and are not filtered, however, the code current_user_can( 'read_post', $comment->comment_post_ID ) should keep them safe.
	 *
	 * @param  array $clauses A compacted array of comment query clauses.
	 * @return array
	 */
	public static function exclude_post_note_comments( $clauses ) {
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type != 'post_note' ";
		return $clauses;
	}

	/**
	 * Exclude post_note comments from queries and RSS.
	 *
	 * @param  string $where The WHERE clause of the query.
	 * @return string
	 */
	public static function exclude_post_note_comments_from_feed_where( $where ) {
		return $where . ( $where ? ' AND ' : '' ) . " comment_type != 'post_note' ";
	}

	/**
	 * Delete comments count cache whenever there is
	 * new comment or the status of a comment changes. Cache
	 * will be regenerated next time WC_Comments::wp_count_comments()
	 * is called.
	 */
	public static function delete_comments_count_cache() {
		delete_transient( 'pixelgrade_wppostnotes_count_comments' );
	}

	/**
	 * Remove post notes from wp_count_comments().
	 *
	 * @since  0.1.0
	 * @param  object $stats   Comment stats.
	 * @param  int    $post_id Post ID.
	 * @return object
	 */
	public static function wp_count_comments( $stats, $post_id ) {
		global $wpdb;

		if ( 0 === $post_id ) {
			$stats = get_transient( 'pixelgrade_wppostnotes_count_comments' );

			if ( ! $stats ) {
				$stats = array(
					'total_comments' => 0,
					'all'            => 0,
				);

				$count = $wpdb->get_results(
					"
					SELECT comment_approved, COUNT(*) AS num_comments
					FROM {$wpdb->comments}
					WHERE comment_type NOT IN ('post_note')
					GROUP BY comment_approved
					",
					ARRAY_A
				);

				$approved = array(
					'0'            => 'moderated',
					'1'            => 'approved',
					'spam'         => 'spam',
					'trash'        => 'trash',
					'post-trashed' => 'post-trashed',
				);

				foreach ( (array) $count as $row ) {
					// Don't count post-trashed toward totals.
					if ( ! in_array( $row['comment_approved'], array( 'post-trashed', 'trash', 'spam' ), true ) ) {
						$stats['all']            += $row['num_comments'];
						$stats['total_comments'] += $row['num_comments'];
					} elseif ( ! in_array( $row['comment_approved'], array( 'post-trashed', 'trash' ), true ) ) {
						$stats['total_comments'] += $row['num_comments'];
					}
					if ( isset( $approved[ $row['comment_approved'] ] ) ) {
						$stats[ $approved[ $row['comment_approved'] ] ] = $row['num_comments'];
					}
				}

				foreach ( $approved as $key ) {
					if ( empty( $stats[ $key ] ) ) {
						$stats[ $key ] = 0;
					}
				}

				$stats = (object) $stats;
				set_transient( 'pixelgrade_wppostnotes_count_comments', $stats );
			}
		}

		return $stats;
	}

	/**
	 * Resolve the public url of a directory inside WordPress
	 *
	 * Borrowed from CarbonFields: https://github.com/htmlburger/carbon-fields/blob/46e9b60c30061e869fca66227e50dfc009afe194/core/Carbon_Fields.php
	 *
	 * @param  string $directory
	 * @return string
	 */
	protected function directory_to_url( $directory ) {
		$url = \trailingslashit( $directory );
		$count = 0;

		# Sanitize directory separator on Windows
		$url = str_replace( '\\' ,'/', $url );

		$possible_locations = array(
			WP_PLUGIN_DIR => \plugins_url(), # If installed as a plugin
			WP_CONTENT_DIR => \content_url(), # If anywhere in wp-content
			ABSPATH => \site_url( '/' ), # If anywhere else within the WordPress installation
		);

		foreach ( $possible_locations as $test_dir => $test_url ) {
			$test_dir_normalized = str_replace( '\\' ,'/', $test_dir );
			$url = str_replace( $test_dir_normalized, $test_url, $url, $count );

			if ( $count > 0 ) {
				return \untrailingslashit( $url );
			}
		}

		return ''; // return empty string to avoid exposing half-parsed paths
	}
}
