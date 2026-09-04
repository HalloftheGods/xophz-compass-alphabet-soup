<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://youmeos.com
 * @since      1.0.0
 *
 * @package    Xophz_Compass_Alphabet_Soup
 * @subpackage Xophz_Compass_Alphabet_Soup/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Xophz_Compass_Alphabet_Soup
 * @subpackage Xophz_Compass_Alphabet_Soup/includes
 * @author     Your Name
 */
if ( ! class_exists( 'Xophz_Compass_Plugin_Base' ) ) {
	$core_plugin_base = dirname( dirname( __DIR__ ) ) . '/xophz-compass/includes/core/class-compass-plugin-base.php';
	if ( file_exists( $core_plugin_base ) ) {
		require_once $core_plugin_base;
	}
}

class Xophz_Compass_Alphabet_Soup extends Xophz_Compass_Plugin_Base {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Xophz_Compass_Alphabet_Soup_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected string $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct( ?string $param1 = null, ?string $version = null, string $param3 = '' ) {
		if ( null === $param1 ) {
			$file = dirname( __DIR__ ) . '/xophz-compass-alphabet-soup.php';
			$ver  = defined( 'XOPHZ_COMPASS_ALPHABET_SOUP_VERSION' ) ? XOPHZ_COMPASS_ALPHABET_SOUP_VERSION : '1.0.0';
			parent::__construct( $file, $ver, 'xophz-compass-alphabet-soup' );
		} else {
			parent::__construct( $param1, $version ?? '1.0.0', $param3 );
		}
		$this->plugin_name = $this->text_domain;
		$this->loader = $this;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		$this->loader->add_action( 'init', $this, 'register_dynamic_cpts', 10 );
		$this->loader->add_action( 'add_meta_boxes', $this, 'add_dynamic_cpt_meta_boxes' );
		$this->loader->add_action( 'save_post', $this, 'save_dynamic_cpt_meta_fields', 10, 2 );
		$this->loader->add_action( 'rest_api_init', $this, 'register_dynamic_cpt_rest_fields' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Xophz_Compass_Alphabet_Soup_Loader. Orchestrates the hooks of the plugin.
	 * - Xophz_Compass_Alphabet_Soup_i18n. Defines internationalization functionality.
	 * - Xophz_Compass_Alphabet_Soup_Admin. Defines all hooks for the admin area.
	 * - Xophz_Compass_Alphabet_Soup_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */

		/**
		 * The class responsible for handling REST API CPT Management.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-xophz-compass-alphabet-soup-api.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-xophz-compass-alphabet-soup-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-xophz-compass-alphabet-soup-public.php';


	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Xophz_Compass_Alphabet_Soup_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		// Localization handled by Xophz_Compass_Plugin_Base on init priority 5
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Xophz_Compass_Alphabet_Soup_Admin( $this->get_xophz_compass_alphabet_soup(), $this->get_version() );

		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'addToMenu' );
		$this->loader->add_action( 'wp_ajax_get_posts', $plugin_admin, 'getPosts'); 
		$this->loader->add_action( 'wp_ajax_get_post', $plugin_admin, 'getPost'); 
		$this->loader->add_action( 'enqueue_block_editor_assets', $plugin_admin, 'enqueue_gutenberg_dark_mode' );
		$this->loader->add_filter( 'block_editor_settings_all', $plugin_admin, 'inject_gutenberg_iframe_styles', 10, 2 );
		
		$plugin_api = new Xophz_Compass_Alphabet_Soup_API();
		$this->loader->add_action( 'rest_api_init', $plugin_api, 'register_routes' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Xophz_Compass_Alphabet_Soup_Public( $this->get_xophz_compass_alphabet_soup(), $this->get_version() );

		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Register Spark with YouMeOS Registry
		// $this->loader->add_filter( 'xophz_register_sparks', $plugin_public, 'register_spark' );
		// $this->loader->add_filter( 'xophz_get_spark_manifest', $plugin_public, 'get_spark_manifest', 10, 2 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run(): void {
		$this->run_hooks();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_xophz_compass_alphabet_soup() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Xophz_Compass_Alphabet_Soup_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader(): self {
		return $this;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Register all dynamic Custom Post Types stored in wp_options.
	 *
	 * This array is hydrated by the Alphabet Soup REST API.
	 * By registering them on init, standard WordPress plugins (like Forminator)
	 * and YouMeOS can interact with them natively.
	 *
	 * @since     1.1.0
	 */
	public function register_dynamic_cpts() {
		$registered_cpts = get_option( 'xophz_compass_registered_cpts', array() );

		if ( ! empty( $registered_cpts ) && is_array( $registered_cpts ) ) {
			foreach ( $registered_cpts as $cpt ) {
				$slug   = sanitize_key( $cpt['slug'] );
				$single = sanitize_text_field( $cpt['singular_label'] );
				$plural = sanitize_text_field( $cpt['plural_label'] );
				$icon   = isset( $cpt['icon'] ) ? sanitize_text_field( $cpt['icon'] ) : 'dashicons-admin-post';
				
				$taxonomies = array();
				if ( ! empty( $cpt['supports_categories'] ) ) {
					$taxonomies[] = 'category';
				}
				if ( ! empty( $cpt['supports_tags'] ) ) {
					$taxonomies[] = 'post_tag';
				}

				$labels = array(
					'name'               => _x( $plural, 'post type general name', 'xophz-compass-alphabet-soup' ),
					'singular_name'      => _x( $single, 'post type singular name', 'xophz-compass-alphabet-soup' ),
					'menu_name'          => _x( $plural, 'admin menu', 'xophz-compass-alphabet-soup' ),
					'name_admin_bar'     => _x( $single, 'add new on admin bar', 'xophz-compass-alphabet-soup' ),
					'add_new'            => _x( 'Add New', $slug, 'xophz-compass-alphabet-soup' ),
					'add_new_item'       => __( 'Add New ' . $single, 'xophz-compass-alphabet-soup' ),
					'new_item'           => __( 'New ' . $single, 'xophz-compass-alphabet-soup' ),
					'edit_item'          => __( 'Edit ' . $single, 'xophz-compass-alphabet-soup' ),
					'view_item'          => __( 'View ' . $single, 'xophz-compass-alphabet-soup' ),
					'all_items'          => __( 'All ' . $plural, 'xophz-compass-alphabet-soup' ),
					'search_items'       => __( 'Search ' . $plural, 'xophz-compass-alphabet-soup' ),
					'not_found'          => __( 'No ' . strtolower( $plural ) . ' found.', 'xophz-compass-alphabet-soup' ),
					'not_found_in_trash' => __( 'No ' . strtolower( $plural ) . ' found in Trash.', 'xophz-compass-alphabet-soup' )
				);

				$args = array(
					'labels'             => $labels,
					'description'        => current( explode( '_', $slug ) ) . ' ' . $plural . '.',
					'public'             => true,
					'publicly_queryable' => true,
					'show_ui'            => true,
					'show_in_menu'       => true,
					'query_var'          => true,
					'rewrite'            => array( 'slug' => str_replace( '_', '-', $slug ) ),
					'capability_type'    => 'post',
					'has_archive'        => true,
					'hierarchical'       => false,
					'menu_position'      => null,
					'menu_icon'          => $icon,
					'show_in_rest'       => true, // Essential for Vue and external API integrations
					'supports'           => ( ! isset( $cpt['supports_custom_fields'] ) || filter_var( $cpt['supports_custom_fields'], FILTER_VALIDATE_BOOLEAN ) ) 
											? array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ) 
											: array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
					'taxonomies'         => $taxonomies,
				);

				register_post_type( $slug, $args );

				// Register custom fields as post meta for the REST API
				if ( ! empty( $cpt['fields'] ) && is_array( $cpt['fields'] ) ) {
					foreach ( $cpt['fields'] as $field ) {
						if ( ! empty( $field['key'] ) ) {
							register_post_meta( $slug, $field['key'], array(
								'show_in_rest' => true,
								'single'       => true,
								'type'         => 'string',
							) );
						}
					}
				}
			}
		}
	}

	/**
	 * Register Dynamic Meta Boxes for user-defined schema fields.
	 */
	public function add_dynamic_cpt_meta_boxes() {
		$registered_cpts = get_option( 'xophz_compass_registered_cpts', array() );
		foreach ( $registered_cpts as $cpt ) {
			if ( ! empty( $cpt['fields'] ) && is_array( $cpt['fields'] ) ) {
				add_meta_box(
					'dynamic_fields_box_' . $cpt['slug'],
					$cpt['plural_label'] . ' Data',
					array( $this, 'render_dynamic_cpt_meta_box' ),
					$cpt['slug'],
					'normal',
					'high',
					array( 'fields' => $cpt['fields'] )
				);
			}
		}
	}

	public function render_dynamic_cpt_meta_box( $post, $metabox ) {
		$fields = $metabox['args']['fields'];
		wp_nonce_field( 'dynamic_cpt_meta_box_save', 'dynamic_cpt_meta_box_nonce' );

		foreach ( $fields as $field ) {
			$value = get_post_meta( $post->ID, $field['key'], true );
			echo '<p>';
			echo '<label for="' . esc_attr( $field['key'] ) . '" style="display:block; font-weight:bold;">' . esc_html( $field['label'] ) . '</label>';
			echo '<input type="text" id="' . esc_attr( $field['key'] ) . '" name="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '" style="width:100%;">';
			echo '</p>';
		}
	}

	public function save_dynamic_cpt_meta_fields( $post_id, $post ) {
		if ( ! isset( $_POST['dynamic_cpt_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['dynamic_cpt_meta_box_nonce'], 'dynamic_cpt_meta_box_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$registered_cpts = get_option( 'xophz_compass_registered_cpts', array() );
		foreach ( $registered_cpts as $cpt ) {
			if ( $post->post_type === $cpt['slug'] && ! empty( $cpt['fields'] ) && is_array( $cpt['fields'] ) ) {
				foreach ( $cpt['fields'] as $field ) {
					if ( isset( $_POST[ $field['key'] ] ) ) {
						update_post_meta( $post_id, $field['key'], sanitize_text_field( $_POST[ $field['key'] ] ) );
					}
				}
				break;
			}
		}
	}

	/**
	 * Map these dynamically created fields into the REST API.
	 */
	public function register_dynamic_cpt_rest_fields() {
		$registered_cpts = get_option( 'xophz_compass_registered_cpts', array() );
		foreach ( $registered_cpts as $cpt ) {
			if ( ! empty( $cpt['fields'] ) && is_array( $cpt['fields'] ) ) {
				foreach ( $cpt['fields'] as $field ) {
					register_rest_field(
						$cpt['slug'],
						$field['key'],
						array(
							'get_callback'    => function( $object ) use ( $field ) {
								return get_post_meta( $object['id'], $field['key'], true );
							},
							'update_callback' => function( $value, $object ) use ( $field ) {
								return update_post_meta( $object->ID, $field['key'], sanitize_text_field( $value ) );
							},
							'schema'          => null,
						)
					);
				}
			}
		}
	}
}
