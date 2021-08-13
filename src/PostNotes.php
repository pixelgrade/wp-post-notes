<?php
/**
 * Main Post Notes class.
 *
 * Many methods have been borrowed or started from corresponding methods and functions from WooCommerce.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pixelgrade\WPPostNotes
 */

declare ( strict_types=1 );

namespace Pixelgrade\WPPostNotes;

class PostNotes {

	/**
	 * The post type to attach the post notes instance.
	 *
	 * @since 0.1.0
	 *
	 * @var string|null
	 */
	protected ?string $post_type = null;

	/**
	 * The custom configuration of the post notes instance.
	 *
	 * @since 0.2.0
	 *
	 * @var array
	 */
	protected array $config = [];

	/**
	 * The AJAX logic.
	 *
	 * @since 0.2.0
	 *
	 * @var Ajax|null
	 */
	protected ?Ajax $ajax = null;

	/**
	 * @since 0.1.0
	 *
	 * @param string|null $post_type The post type to attach post notes to.
	 * @param array       $config    Custom configuration to adjust the behavior of the post notes attached to the post type.
	 *
	 * @throws \Exception
	 */
	public function __construct( string $post_type, array $config = [] ) {
		$this->post_type = $post_type;
		$this->config    = $config;

		$this->init();
	}

	/**
	 * @since 0.1.0
	 *
	 * @throws \Exception
	 */
	public function init() {
		$this->boot();
		$this->register_hooks();
	}

	/**
	 * @since 0.1.0
	 *
	 * @throws \Exception
	 */
	protected function boot() {
		if ( ! defined( 'ABSPATH' ) ) {
			throw new \Exception( esc_html__( 'WP Post Notes cannot be booted outside of a WordPress environment.', 'pixelgrade-wppostnotes' ) );
		}

		if ( did_action( 'init' ) ) {
			throw new \Exception( esc_html__( 'WP Post Notes must be booted before the "init" WordPress action has fired.', 'pixelgrade-wppostnotes' ) );
		}

		# Define version constant
		if ( ! defined( __NAMESPACE__ . '\VERSION' ) ) {
			define( __NAMESPACE__ . '\VERSION', '0.5.1' );
		}

		# Define root directory
		if ( ! defined( __NAMESPACE__ . '\DIR' ) ) {
			define( __NAMESPACE__ . '\DIR', dirname( __DIR__ ) );
		}

		# Define root URL
		if ( ! defined( __NAMESPACE__ . '\URL' ) ) {
			define( __NAMESPACE__ . '\URL', $this->directory_to_url( DIR ) );
		}

		$this->ajax = new Ajax();
		$this->ajax->init( $this );
	}

	/**
	 * @since 0.1.0
	 */
	public function register_hooks() {
		// Merge with default and allow for filtering the metabox config.
		add_action( 'after_setup_theme', [ $this, 'init_config' ], 99 );

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

		if ( did_action( 'plugins_loaded' ) ) {
			$this->load_textdomain();
		} else {
			add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		}
	}

	/**
	 * Merge with default and allow for filtering the metabox config.
	 *
	 * @since 0.2.0
	 */
	public function init_config() {
		/**
		 * Filters the Post Notes configuration.
		 *
		 * @since 0.2.0
		 *
		 * @param array  $config    The configuration.
		 * @param string $post_type The post type the Post Notes are attached to.
		 */
		$this->config = apply_filters(
			'pixelgrade_wppostnotes/config',
			wp_parse_args( $this->config, $this->default_config() ),
			$this->post_type
		);
	}

	/**
	 * Get the active Post Notes configuration.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Get the default configuration to use for this instance of the post notes.
	 *
	 * @since 0.2.0
	 *
	 * @see   \add_meta_box()
	 *
	 * @return array The Post Notes default configuration.
	 */
	protected function default_config(): array {
		// Set entries to null to fallback on default values.
		return [
			// Meta box ID
			'id'            => null,
			// The metabox title
			'title'         => null,
			// The callable to use to render the metabox.
			'callback'      => null,
			// The context within the screen where the box should display.
			'context'       => null,
			// The priority within the context where the box should show.
			'priority'      => null,
			// Data that should be set as the $args property of the box array (which is the second parameter passed to your callback).
			'callback_args' => null,
			'note_types'    => [
				// The internal note type is reserved for private, internal notes. The default note type.
				// Do not change its value through filters.
				'internal'  => [
					'value' => 'internal',
					'label' => esc_html__( 'Internal note', 'pixelgrade-wppostnotes' ),
				],
			],
			'i18n'          => [
				'add_post_note_label'  => esc_html__( 'Add note', 'pixelgrade-wppostnotes' ),
				'add_post_note_tip'    => esc_html__( 'Add a note for you or your team\'s reference.', 'pixelgrade-wppostnotes' ),
				'post_note_type_label' => esc_html__( 'Note type', 'pixelgrade-wppostnotes' ),
				/* translators: Add post note button label.  */
				'add_post_note_button' => esc_html__( 'Add', 'pixelgrade-wppostnotes' ),
				'delete_note_button' => esc_html__( 'Delete note', 'pixelgrade-wppostnotes' ),
				'delete_note_confirm'  => esc_html__( 'Are you sure you wish to delete this note? This action cannot be undone.', 'pixelgrade-wppostnotes' ),

				'notes_list_no_items' => esc_html__( 'There are no notes yet.', 'pixelgrade-wppostnotes' ),
			],
		];
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
		wp_register_script( 'jquery-tiptip', \path_join( URL, 'assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js' ), [ 'jquery' ], '1.3', true );

		wp_register_script(
			'pixelgrade_wppostnotes-metabox',
			\path_join( URL, 'assets/js/notes-metabox.js' ),
			[ 'jquery', 'jquery-blockui', 'jquery-tiptip', ],
			'20210805',
			true
		);

		$params = array(
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			'post_id'                  => $post->ID ?? '',
			'add_post_note_nonce'      => wp_create_nonce( 'add-post-note' ),
			'delete_post_note_nonce'   => wp_create_nonce( 'delete-post-note' ),
			'i18n_delete_note_confirm' => $this->config['i18n']['delete_note_confirm'] ?? '',
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

		add_meta_box(
			$this->config['id'] ?? 'pixelgrade_wppostnotes-metabox',
			/* translators: %s: The post type singular name */
			$this->config['title'] ?? sprintf( __( '%s notes', 'pixelgrade-wppostnotes' ), $post_type_object->labels->singular_name ),
			$this->config['callback'] ?? 'Pixelgrade\WPPostNotes\MetaBox::output',
			$this->post_type,
			$this->config['context'] ?? 'side',
			$this->config['priority'] ?? 'default',
			$this->get_config_callback_args()
		);
	}

	/**
	 * Get the args to use for the metabox callback_args.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public function get_config_callback_args(): array {
		// For the callback args we want to send the `i18n` also, to avoid duplication.
		$callback_args = $this->config['callback_args'] ?? [];
		if ( empty( $callback_args['i18n'] ) ) {
			$callback_args['i18n'] = [];
		}
		if ( empty( $this->config['i18n'] ) ) {
			$this->config['i18n'] = [];
		}
		$callback_args['i18n'] = wp_parse_args( $callback_args['i18n'], $this->config['i18n'] );
		// Send the note types and don't allow overwrite via callback_args.
		$callback_args['note_types'] = $this->config['note_types'];

		return $callback_args;
	}

	/**
	 * Exclude order comments from queries and RSS.
	 *
	 * This code should exclude post_note comments from queries. Some queries (like the recent comments widget on the dashboard) are hardcoded
	 * and are not filtered, however, the code current_user_can( 'read_post', $comment->comment_post_ID ) should keep them safe.
	 *
	 * @param array $clauses A compacted array of comment query clauses.
	 *
	 * @return array
	 */
	public static function exclude_post_note_comments( $clauses ) {
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type != 'post_note' ";

		return $clauses;
	}

	/**
	 * Exclude post_note comments from queries and RSS.
	 *
	 * @param string $where The WHERE clause of the query.
	 *
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
	 *
	 * @param object $stats   Comment stats.
	 * @param int    $post_id Post ID.
	 *
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
	 * @param string $directory
	 *
	 * @return string
	 */
	protected function directory_to_url( $directory ) {
		$url   = \trailingslashit( $directory );
		$count = 0;

		# Sanitize directory separator on Windows
		$url = str_replace( '\\', '/', $url );

		$possible_locations = array(
			WP_PLUGIN_DIR  => \plugins_url(),   # If installed as a plugin
			WP_CONTENT_DIR => \content_url(),   # If anywhere in wp-content
			ABSPATH        => \site_url( '/' ), # If anywhere else within the WordPress installation
		);

		foreach ( $possible_locations as $test_dir => $test_url ) {
			$test_dir_normalized = str_replace( '\\', '/', $test_dir );
			$url                 = str_replace( $test_dir_normalized, $test_url, $url, $count );

			if ( $count > 0 ) {
				return \untrailingslashit( $url );
			}
		}

		return ''; // return empty string to avoid exposing half-parsed paths
	}

	/**
	 * Load the text domain to localize the library.
	 *
	 * @since  0.1.0
	 */
	public function load_textdomain() {
		load_textdomain( 'pixelgrade-wppostnotes', \path_join( dirname( __DIR__ ), 'languages/wp-post-notes.pot' ) );
	}
}
