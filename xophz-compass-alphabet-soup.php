<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Xophz_Compass_Alphabet_Soup
 *
 * @wordpress-plugin
 * Category:          Command Deck
 * Group:             CMS
 * Plugin Name:       Xophz Alphabet Soup
 * Plugin URI:        https://github.com/HalloftheGods/xophz-compass-alphabet-soup
 * Description:       Quickly add, edit, delete posts in this modern-day post manager.
 * Version:           26.8.16.12
 * Author:            Hall of the Gods, Inc.
 * Author URI:        http://www.midknightknerd.com/xp
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       xophz-compass-alphabet-soup
 * Domain Path:       /languages
 * Update URI:        https://github.com/HalloftheGods/xophz-compass-alphabet-soup
 */
 // If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
  die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'XOPHZ_COMPASS_ALPHABET_SOUP_VERSION', '26.8.16.12' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-xophz-compass-alphabet-soup-activator.php
 */
function activate_xophz_compass_alphabet_soup() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-xophz-compass-alphabet-soup-activator.php';
  Xophz_Compass_Alphabet_Soup_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-xophz-compass-alphabet-soup-deactivator.php
 */
function deactivate_xophz_compass_alphabet_soup() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-xophz-compass-alphabet-soup-deactivator.php';
  Xophz_Compass_Alphabet_Soup_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_xophz_compass_alphabet_soup' );
register_deactivation_hook( __FILE__, 'deactivate_xophz_compass_alphabet_soup' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-xophz-compass-alphabet-soup.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_xophz_compass_alphabet_soup() {
  if ( ! class_exists( 'Xophz_Compass' ) ) {
    add_action( 'admin_init', 'shutoff_xophz_compass_alphabet_soup' );
    add_action( 'admin_notices', 'admin_notice_xophz_compass_alphabet_soup' );

    function shutoff_xophz_compass_alphabet_soup() {
      if ( ! function_exists( 'deactivate_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
      }
      deactivate_plugins( plugin_basename( __FILE__ ) );
    }

    function admin_notice_xophz_compass_alphabet_soup() {
      echo '<div class="error"><h2><strong>Xophz Alphabet Soup</strong> requires Compass to run. It has self <strong>deactivated</strong>.</h2></div>';
      if ( isset( $_GET['activate'] ) )
        unset( $_GET['activate'] );
    }
  } else {
    $plugin = new Xophz_Compass_Alphabet_Soup();
    $plugin->run();
  }
}
add_action( 'plugins_loaded', 'run_xophz_compass_alphabet_soup' );

add_action( 'init', 'xophz_register_cafeteria_core_types', 10 );

function xophz_register_cafeteria_core_types() {
	// Register the Hierarchical Taxonomy (Boards / Categories)
	$labels_taxonomy = array(
		'name'              => _x( 'Cafeteria Boards', 'taxonomy general name', 'xophz-compass-alphabet-soup' ),
		'singular_name'     => _x( 'Cafeteria Board', 'taxonomy singular name', 'xophz-compass-alphabet-soup' ),
		'search_items'      => __( 'Search Boards', 'xophz-compass-alphabet-soup' ),
		'all_items'         => __( 'All Boards', 'xophz-compass-alphabet-soup' ),
		'parent_item'       => __( 'Parent Category', 'xophz-compass-alphabet-soup' ),
		'parent_item_colon' => __( 'Parent Category:', 'xophz-compass-alphabet-soup' ),
		'edit_item'         => __( 'Edit Board', 'xophz-compass-alphabet-soup' ),
		'update_item'       => __( 'Update Board', 'xophz-compass-alphabet-soup' ),
		'add_new_item'      => __( 'Add New Board', 'xophz-compass-alphabet-soup' ),
		'new_item_name'     => __( 'New Board Name', 'xophz-compass-alphabet-soup' ),
		'menu_name'         => __( 'Cafeteria Boards', 'xophz-compass-alphabet-soup' ),
	);

	$args_taxonomy = array(
		'hierarchical'      => true,
		'labels'            => $labels_taxonomy,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'cafeteria-board' ),
		'show_in_rest'      => true,
		'rest_base'         => 'cafeteria_board',
	);

	register_taxonomy( 'cafeteria_board', array( 'cafeteria_topic' ), $args_taxonomy );

	// Register the Custom Post Type (Topics / Threads)
	$labels_cpt = array(
		'name'                  => _x( 'Cafeteria Topics', 'Post type general name', 'xophz-compass-alphabet-soup' ),
		'singular_name'         => _x( 'Cafeteria Topic', 'Post type singular name', 'xophz-compass-alphabet-soup' ),
		'menu_name'             => _x( 'Cafeteria Topics', 'Admin Menu text', 'xophz-compass-alphabet-soup' ),
		'name_admin_bar'        => _x( 'Cafeteria Topic', 'Add New on Toolbar', 'xophz-compass-alphabet-soup' ),
		'add_new'               => __( 'Add New', 'xophz-compass-alphabet-soup' ),
		'add_new_item'          => __( 'Add New Topic', 'xophz-compass-alphabet-soup' ),
		'new_item'              => __( 'New Topic', 'xophz-compass-alphabet-soup' ),
		'edit_item'             => __( 'Edit Topic', 'xophz-compass-alphabet-soup' ),
		'view_item'             => __( 'View Topic', 'xophz-compass-alphabet-soup' ),
		'all_items'             => __( 'All Topics', 'xophz-compass-alphabet-soup' ),
		'search_items'          => __( 'Search Topics', 'xophz-compass-alphabet-soup' ),
		'parent_item_colon'     => __( 'Parent Topics:', 'xophz-compass-alphabet-soup' ),
		'not_found'             => __( 'No topics found.', 'xophz-compass-alphabet-soup' ),
		'not_found_in_trash'    => __( 'No topics found in Trash.', 'xophz-compass-alphabet-soup' ),
	);

	$args_cpt = array(
		'labels'             => $labels_cpt,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'cafeteria-topic' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'menu_icon'          => 'dashicons-format-chat',
		'supports'           => array( 'title', 'editor', 'author', 'comments' ),
		'show_in_rest'       => true,
		'rest_base'          => 'cafeteria_topic',
		'taxonomies'         => array( 'cafeteria_board' ),
	);

	register_post_type( 'cafeteria_topic', $args_cpt );
}

/**
 * Register custom REST fields & Term Meta for Cafeteria
 */
add_action( 'init', 'xophz_register_cafeteria_board_meta' );
function xophz_register_cafeteria_board_meta() {
    register_term_meta( 'cafeteria_board', 'board_icon', array(
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true,
    ));
    register_term_meta( 'cafeteria_board', 'board_order', array(
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true,
    ));
}

add_action( 'rest_api_init', 'xophz_register_cafeteria_board_stats' );
function xophz_register_cafeteria_board_stats() {
    register_rest_field( 'cafeteria_board', 'stats', array(
        'get_callback' => 'xophz_get_cafeteria_board_stats',
        'schema'       => null,
    ));
    // Also register stats for individual topics to track replies and last activity
    register_rest_field( 'cafeteria_topic', 'stats', array(
        'get_callback' => 'xophz_get_cafeteria_topic_stats',
        'schema'       => null,
    ));
}

function xophz_get_cafeteria_board_stats( $term, $field_name, $request ) {
    $term_id = $term['id'];
    
    // Default stats
    $stats = array(
        'topics'        => $term['count'],
        'replies'       => 0,
        'last_activity' => null
    );

    // Aggregate replies across all topics (all statuses)
    $topics = get_posts( array(
        'post_type'      => 'cafeteria_topic',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => array(
            array(
                'taxonomy' => 'cafeteria_board',
                'field'    => 'term_id',
                'terms'    => $term_id,
            ),
        ),
    ));

    $total_replies = 0;
    foreach($topics as $topic_id) {
        $comments_count = wp_count_comments($topic_id);
        // Include all comments: approved & awaiting moderation
        $total_replies += $comments_count->total_comments;
    }
    $stats['replies'] = $total_replies;

    // Resolve latest activity
    $last_activity = null;
    $latest_ts = 0;

    // 1. Check most recent topic
    $latest_topic = get_posts( array(
        'post_type'      => 'cafeteria_topic',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => array(
            array(
                'taxonomy' => 'cafeteria_board',
                'field'    => 'term_id',
                'terms'    => $term_id,
            ),
        ),
    ));
    
    if ( ! empty( $latest_topic ) ) {
        $t = $latest_topic[0];
        $latest_ts = strtotime($t->post_date);
        $author_id = $t->post_author;
        $last_activity = array(
            'title'     => $t->post_title,
            'timestamp' => $t->post_date,
            'author'    => get_the_author_meta('display_name', $author_id) ?: 'Unknown',
            'avatar'    => get_avatar_url($author_id, array('size' => 48))
        );
    }

    // 2. Check most recent comment in these topics
    if ( ! empty( $topics ) ) {
        $latest_comment = get_comments( array(
            'post_id__in' => $topics,
            'number'      => 1,
            'orderby'     => 'comment_date',
            'order'       => 'DESC',
            'status'      => 'all'
        ));

        if ( ! empty($latest_comment) ) {
            $c = $latest_comment[0];
            $c_ts = strtotime($c->comment_date);
            if ( $c_ts > $latest_ts ) {
                $p = get_post($c->comment_post_ID);
                $last_activity = array(
                    'title'     => 'Re: ' . $p->post_title,
                    'timestamp' => $c->comment_date,
                    'author'    => $c->comment_author,
                    'avatar'    => get_avatar_url($c->comment_author_email, array('size' => 48))
                );
            }
        }
    }

    $stats['last_activity'] = $last_activity;
    
    // Fallback topic true count if count is out-of-sync or missing
    if (!empty($topics) && $stats['topics'] == 0) {
        $stats['topics'] = count($topics);
    }

    return $stats;
}

function xophz_get_cafeteria_topic_stats( $post, $field_name, $request ) {
    $topic_id = $post['id'];
    $stats = array(
        'replies'       => 0,
        'views'         => 0, 
        'last_activity' => null,
    );

    $comments_count = wp_count_comments($topic_id);
    $stats['replies'] = $comments_count->total_comments;

    $latest_comment = get_comments( array(
        'post_id' => $topic_id,
        'number'  => 1,
        'orderby' => 'comment_date',
        'order'   => 'DESC',
        'status'  => 'all'
    ));

    if ( ! empty($latest_comment) ) {
        $c = $latest_comment[0];
        $stats['last_activity'] = array(
            'timestamp' => $c->comment_date,
            'author'    => $c->comment_author,
            'avatar'    => get_avatar_url($c->comment_author_email, array('size' => 48))
        );
    }

    return $stats;
}

add_action( 'init', 'xophz_seed_suggestion_box_boards', 20 );
function xophz_seed_suggestion_box_boards() {
    $taxonomy = 'cafeteria_board';
    $parent_slug = 'suggestion-box';

    $existing = term_exists( $parent_slug, $taxonomy );
    if ( $existing ) return;

    $parent = wp_insert_term( 'Suggestion Box', $taxonomy, array(
        'slug'        => $parent_slug,
        'description' => 'A place for ideas, feedback, and feature requests.',
    ));

    if ( is_wp_error( $parent ) ) return;

    $parent_id = $parent['term_id'];
    update_term_meta( $parent_id, 'board_icon', 'fal fa-box-ballot' );

    $children = array(
        array( 'name' => 'Ideas, Suggestions, Nuances',    'slug' => 'ideas-suggestions',  'icon' => 'fal fa-lightbulb-on',      'desc' => 'Share your ideas and suggestions for the platform.' ),
        array( 'name' => 'Comments, Feedback, Shouts',     'slug' => 'comments-feedback',  'icon' => 'fal fa-comment-alt-dots',   'desc' => 'General comments and feedback about the experience.' ),
        array( 'name' => 'Feature Requests',               'slug' => 'feature-requests',   'icon' => 'fal fa-flask-potion',       'desc' => 'Request new features and capabilities.' ),
    );

    foreach ( $children as $child ) {
        $result = wp_insert_term( $child['name'], $taxonomy, array(
            'slug'        => $child['slug'],
            'description' => $child['desc'],
            'parent'      => $parent_id,
        ));
        if ( ! is_wp_error( $result ) ) {
            update_term_meta( $result['term_id'], 'board_icon', $child['icon'] );
        }
    }
}
