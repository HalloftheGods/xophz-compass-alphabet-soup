<?php

/**
 * The Alphabet Soup API (Alphabet Soup)
 *
 * Handles the REST API endpoints for configuring and spawning Custom Post Types.
 *
 * @since      1.1.0
 * @package    Xophz_Compass_Alphabet_Soup
 * @subpackage Xophz_Compass_Alphabet_Soup/includes
 */

class Xophz_Compass_Alphabet_Soup_API {

	public function register_routes() {
		register_rest_route( 'compass/v1', '/alphabet-soup/cpts', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cpts' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_cpt' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_string( $param ) && ! empty( $param );
						}
					),
					'singular_label' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_string( $param ) && ! empty( $param );
						}
					),
					'plural_label' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_string( $param ) && ! empty( $param );
						}
					),
					'icon' => array(
						'required'          => false,
						'validate_callback' => function( $param ) {
							return is_string( $param );
						}
					),
					'supports_categories' => array(
						'required'          => false,
						'validate_callback' => function( $param ) {
							return is_bool( $param );
						}
					),
					'supports_tags' => array(
						'required'          => false,
						'validate_callback' => function( $param ) {
							return is_bool( $param );
						}
					),
					'supports_custom_fields' => array(
						'required'          => false,
						'validate_callback' => function( $param ) {
							return is_bool( $param );
						}
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_cpt' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_string( $param ) && ! empty( $param );
						}
					),
				),
			),
		) );
		register_rest_route( 'compass/v1', '/alphabet-soup/seo-stats', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_seo_stats' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						}
					),
				),
			)
		) );

		// Register page_views REST field for all public post types
		$types = array_values( get_post_types( array( 'public' => true ) ) );
		register_rest_field( $types, 'page_views', array(
			'get_callback' => function( $post_arr ) {
				$id = isset( $post_arr['id'] ) ? (int) $post_arr['id'] : 0;
				if ( ! $id ) return 0;
				$views = get_post_meta( $id, 'post_views_count', true );
				if ( '' === $views || false === $views ) {
					$views = get_post_meta( $id, '_compass_page_views', true );
					if ( '' === $views || false === $views ) {
						$views = 0;
					}
				}
				return (int) $views;
			},
			'update_callback' => null,
			'schema'          => null,
		) );



		// Render glassmorphic password protection form if post_password_required
		add_filter( 'the_password_form', array( $this, 'get_glass_password_form' ), 10, 2 );

		// Register AI Assistant generation endpoint
		register_rest_route( 'xophz-compass/v1', '/ai/generate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'generate_ai_content' ),
			'permission_callback' => array( $this, 'check_editor_permissions' ),
		) );

		// Register Duplicate endpoint
		register_rest_route( 'xophz-compass/v1', '/alphabet-soup/duplicate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'duplicate_post' ),
			'permission_callback' => array( $this, 'check_editor_permissions' ),
		) );
	}

	/**
	 * Permissions check for post editors and administrators.
	 */
	public function check_editor_permissions() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}

	/**
	 * POST: Duplicate a post
	 */
	public function duplicate_post( $request ) {
		$params = $request->get_json_params();
		$post_id = isset( $params['id'] ) ? (int) $params['id'] : 0;

		if ( ! $post_id ) {
			return new WP_Error( 'invalid_id', 'Invalid Post ID.', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		// Create duplicate post as draft
		$new_post_args = array(
			'post_title'   => $post->post_title . ' (Copy)',
			'post_content' => $post->post_content,
			'post_status'  => 'draft',
			'post_type'    => $post->post_type,
			'post_author'  => get_current_user_id(),
			'post_parent'  => $post->post_parent,
			'post_excerpt' => $post->post_excerpt,
			'post_password'=> $post->post_password,
		);

		$new_post_id = wp_insert_post( $new_post_args );

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		// Copy taxonomies
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
		}

		// Copy post meta
		$post_meta_infos = get_post_meta( $post_id );
		if ( count( $post_meta_infos ) != 0 ) {
			foreach ( $post_meta_infos as $meta_key => $meta_values ) {
				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $new_post_id, $meta_key, maybe_unserialize( $meta_value ) );
				}
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'id'      => $new_post_id,
			'message' => 'Post duplicated successfully.',
		) );
	}

	private function get_openai_key() {
		if ( function_exists( 'wp_get_connectors' ) ) {
			$connectors = wp_get_connectors();
			if ( ! empty( $connectors['openai']['authentication']['setting_name'] ) ) {
				$api_key = get_option( $connectors['openai']['authentication']['setting_name'], '' );
				if ( ! empty( $api_key ) ) {
					return $api_key;
				}
			}
		}
		$key = get_option( 'xophz_compass_ai_api_key', '' );
		if ( empty($key) ) {
			$key = getenv( 'OPENAI_API_KEY' );
		}
		return $key;
	}

	private function get_openai_model() {
		if ( function_exists( 'wp_get_connectors' ) ) {
			$connectors = wp_get_connectors();
			if ( ! empty( $connectors['openai']['options']['model']['setting_name'] ) ) {
				$model = get_option( $connectors['openai']['options']['model']['setting_name'], '' );
				if ( ! empty( $model ) ) {
					return $model;
				}
			}
		}
		return 'gpt-4o';
	}

	/**
	 * POST: Generate AI Assistant Content
	 */
	public function generate_ai_content( $request ) {
		$params = $request->get_json_params();
		$action  = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : 'polish';
		$title   = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$content = isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '';

		$api_key = $this->get_openai_key();
		$model   = $this->get_openai_model();

		if ( ! empty( $api_key ) ) {
			$prompt = "You are an expert editor. Action: {$action}. Title: {$title}. Content: {$content}";
			$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( array(
					'model' => $model,
					'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
				) ),
				'timeout' => 15,
			) );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				$gen  = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
				if ( ! empty( $gen ) ) {
					return rest_ensure_response( array(
						'success'        => true,
						'generated_text' => $gen,
					) );
				}
			}
		}

		$generated_text = '';
		if ( 'polish' === $action ) {
			$generated_text = "<p><em>" . esc_html( $content ?: "Polished document draft." ) . "</em></p>";
		} elseif ( 'expand' === $action ) {
			$generated_text = "\n\n<h3>Key Analysis & Insights</h3>\n<ul>\n<li>Deep dive into <strong>" . esc_html( $title ?: "this topic" ) . "</strong> and explore strategic opportunities.</li>\n<li>Actionable execution framework designed for maximum performance.</li>\n</ul>\n";
		} elseif ( 'summarize' === $action ) {
			$clean_title = sanitize_text_field( $title );
			$words = array_values( array_filter( explode( ' ', preg_replace( '/[^a-zA-Z0-9\s]/', '', strtolower( $clean_title ) ) ), function( $w ) {
				return strlen( $w ) > 3;
			} ) );
			$keyword = ! empty( $words ) ? implode( ' ', array_slice( $words, 0, 2 ) ) : 'halloween special';

			return rest_ensure_response( array(
				'success'            => true,
				'focus_keyword'      => $keyword,
				'seo_title'          => $clean_title ? "{$clean_title} | Official Announcement" : "Featured Article",
				'generated_text'     => "Comprehensive breakdown of " . esc_html( $clean_title ?: "this topic" ) . " covering strategic objectives, core findings, and execution steps.",
				'social_title'       => $clean_title ? "{$clean_title} - Limited Offer" : "Featured Story",
				'social_description' => "Discover essential insights, exclusive offers, and strategic takeaways regarding " . esc_html( $clean_title ?: "this release" ) . ".",
			) );
		} elseif ( 'outline' === $action ) {
			$generated_text = "\n\n<h2>1. Introduction & Background</h2>\n<p>Overview of core concepts and objectives.</p>\n<h2>2. Key Analysis & Implementation</h2>\n<p>Detailed breakdown of findings and action steps.</p>\n<h2>3. Summary & Next Steps</h2>\n<p>Key takeaways and future recommendations.</p>\n";
		} elseif ( 'seo_optimize' === $action ) {
			$clean_title = sanitize_text_field( $title );
			$kw    = isset( $params['focus_keyword'] ) ? sanitize_text_field( $params['focus_keyword'] ) : '';

			if ( empty( $kw ) ) {
				$words = array_values( array_filter( explode( ' ', preg_replace( '/[^a-zA-Z0-9\s]/', '', strtolower( $clean_title ) ) ), function( $w ) {
					return strlen( $w ) > 3;
				} ) );
				$kw = ! empty( $words ) ? implode( ' ', array_slice( $words, 0, 2 ) ) : 'featured guide';
			}

			$has_headings = preg_match( '/<h[1-6]/i', $content );
			$content_append = '';
			if ( ! $has_headings ) {
				$content_append = "\n\n<h2>" . esc_html( ucfirst( $clean_title ?: $kw ) ) . " Insights</h2>\n<p>Explore comprehensive features, operational guidelines, and strategic insights regarding <strong>" . esc_html( $kw ) . "</strong>.</p>\n";
			}

			return rest_ensure_response( array(
				'success'            => true,
				'focus_keyword'      => $kw,
				'seo_title'          => $clean_title ? "{$clean_title} - " . ucfirst( $kw ) . " Guide" : "Optimized SEO Post",
				'generated_text'     => "In-depth guide covering " . esc_html( $clean_title ) . " optimized for search engine rankings on key phrase " . esc_html( $kw ) . ".",
				'social_title'       => $clean_title ? "{$clean_title} | " . ucfirst( $kw ) : "Featured Story",
				'social_description' => "Explore actionable insights and core takeaways regarding " . esc_html( $kw ) . " in " . esc_html( $clean_title ) . ".",
				'content_append'     => $content_append,
			) );
		}

		return rest_ensure_response( array(
			'success'        => true,
			'generated_text' => "Expanded analysis and strategic breakdown for " . esc_html( $title ?: "this section" ) . "."
		) );
	}

	/**
	 * Render glassmorphic password protection unlock form on live site
	 */
	public function get_glass_password_form( $output = '', $post = null ) {
		$post_obj = get_post( $post );
		$label    = 'pwbox-' . ( empty( $post_obj->ID ) ? rand() : $post_obj->ID );
		ob_start();
		?>
		<div class="compass-password-protection-card pa-8 text-center rounded-2xl mx-auto my-8" style="background: rgba(10, 22, 44, 0.85); backdrop-filter: blur(30px); border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 20px 50px rgba(0,0,0,0.5); color: #fff; max-width: 480px; padding: 2.5rem; border-radius: 1.5rem; text-align: center; margin: 3rem auto;">
			<div style="font-size: 42px; margin-bottom: 12px; line-height: 1;">🔒</div>
			<h3 style="font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #ffffff; letter-spacing: -0.02em;">Protected Content</h3>
			<p style="font-size: 13px; color: rgba(255,255,255,0.7); margin-bottom: 24px; line-height: 1.5;">
				This content is password protected. To view it, please enter your secret password below:
			</p>
			<form action="<?php echo esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ); ?>" method="post" style="display: flex; flex-direction: column; gap: 14px;">
				<input name="post_password" id="<?php echo esc_attr( $label ); ?>" type="password" placeholder="Enter password to unlock..." style="padding: 12px 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.08); color: #fff; font-size: 14px; outline: none; transition: all 0.2s;" required />
				<button type="submit" name="Submit" style="padding: 12px 24px; border-radius: 10px; border: none; background: #62c9ff; color: #081224; font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 15px rgba(98, 201, 255, 0.4);">
					Unlock Content
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * GET: Retrieve SEO Stats
	 */
	public function get_seo_stats( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$post = get_post( $post_id );

		if ( ! $post ) {
			return rest_ensure_response( array(
				'seo' => '<p class="text-warning">Post not found.</p>',
				'readability' => '<p class="text-warning">Post not found.</p>'
			) );
		}

		$focus_kw_param = $request->get_param( 'focus_keyword' );
		if ( $focus_kw_param !== null ) {
			$sanitized_kw = sanitize_text_field( $focus_kw_param );
			update_post_meta( $post_id, '_wds_focus-keywords', $sanitized_kw );
			update_post_meta( $post_id, '_wds_focus_keyword', $sanitized_kw );
			update_post_meta( $post_id, 'rank_math_focus_keyword', $sanitized_kw );
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $sanitized_kw );
		}

		$seo_html = '';
		$readability_html = '';

		// Check for SmartCrawl or Yoast or Rank Math first
		$smartcrawl_class = '';
		if ( class_exists( '\SmartCrawl\Controllers\Analysis' ) ) {
			$smartcrawl_class = '\SmartCrawl\Controllers\Analysis';
		} elseif ( class_exists( '\Smartcrawl\Controllers\Analysis' ) ) {
			$smartcrawl_class = '\Smartcrawl\Controllers\Analysis';
		} elseif ( class_exists( '\Smartcrawl\Core\Controllers\Analysis' ) ) {
			$smartcrawl_class = '\Smartcrawl\Core\Controllers\Analysis';
		}

		if ( ! empty( $smartcrawl_class ) ) {
			try {
				$analyzer = method_exists( $smartcrawl_class, 'get' ) ? $smartcrawl_class::get() : new $smartcrawl_class();
				if ( method_exists( $analyzer, 'maybe_analyze_post' ) ) {
					$analyzer->maybe_analyze_post( $post_id );
				}
				if ( method_exists( $analyzer, 'retrieve_post_seo_analysis' ) ) {
					$seo_data = $analyzer->retrieve_post_seo_analysis( $post );
					if ( ! empty( $seo_data['primary_checks'] ) && is_array( $seo_data['primary_checks'] ) ) {
						$sc_checks = count( $seo_data['primary_checks'] );
						$sc_good = 0;
						foreach ( $seo_data['primary_checks'] as $check ) {
							if ( isset( $check['status'] ) && $check['status'] === 'good' ) {
								$sc_good++;
							}
						}
						$smartcrawl_seo_score = $sc_checks > 0 ? round( ( $sc_good / $sc_checks ) * 100 ) : 0;
					}
					if ( ! empty( $seo_data['primary_keyword'] ) ) {
						$seo_html .= '<div class="mb-2"><strong>Focus Keyword:</strong> ' . esc_html( $seo_data['primary_keyword'] ) . '</div>';
						$seo_html .= '<div class="mb-2"><strong>Errors:</strong> ' . esc_html( $seo_data['primary_error_count'] ) . '</div>';
						if ( ! empty( $seo_data['primary_checks'] ) && is_array( $seo_data['primary_checks'] ) ) {
							$seo_html .= '<ul class="pl-4 mt-2" style="list-style: none;">';
							$rendered_count = 0;
							foreach ( $seo_data['primary_checks'] as $check ) {
								$chk_text = '';
								if ( ! empty( $check['label'] ) ) {
									$chk_text = $check['label'];
								} elseif ( ! empty( $check['title'] ) ) {
									$chk_text = $check['title'];
								} elseif ( ! empty( $check['message'] ) ) {
									$chk_text = $check['message'];
								} elseif ( ! empty( $check['description'] ) ) {
									$chk_text = $check['description'];
								} elseif ( ! empty( $check['text'] ) ) {
									$chk_text = $check['text'];
								}
								if ( empty( trim( wp_strip_all_tags( $chk_text ) ) ) ) {
									continue;
								}
								$rendered_count++;
								$status_color = ( isset($check['status']) && $check['status'] === 'good' ) ? '#22c55e' : ( ( isset($check['status']) && $check['status'] === 'warning' ) ? '#eab308' : '#ef4444' );
								$icon_class = ( isset($check['status']) && $check['status'] === 'good' ) ? 'fal fa-check-circle' : ( ( isset($check['status']) && $check['status'] === 'warning' ) ? 'fal fa-exclamation-triangle' : 'fal fa-times-circle' );
								$seo_html .= '<li style="color: #e2e8f0; margin-bottom: 8px; font-size: 0.825rem; display: flex; align-items: flex-start;">';
								$seo_html .= '<i class="' . $icon_class . '" style="color: ' . $status_color . '; font-size: 14px; margin-right: 8px; flex-shrink: 0; margin-top: 2px;"></i>';
								$seo_html .= '<span>' . esc_html( $chk_text ) . '</span>';
								$seo_html .= '</li>';
							}
							$seo_html .= '</ul>';
							if ( 0 === $rendered_count ) {
								$seo_html = ''; // Force native engine if SmartCrawl check messages were empty
								unset( $smartcrawl_seo_score );
							}
						}
					}
				}
				if ( method_exists( $analyzer, 'retrieve_post_readability_analysis' ) ) {
					$read_data = $analyzer->retrieve_post_readability_analysis( $post );
					if ( ! empty( $read_data ) && isset( $read_data['score'] ) ) {
						$smartcrawl_readability_score = round( (float) $read_data['score'] );
						$status_color = ( isset($read_data['state']) && $read_data['state'] === 'good' ) ? '#4caf50' : ( ( isset($read_data['state']) && $read_data['state'] === 'warning' ) ? '#ffeb3b' : '#f44336' );
						$readability_html .= '<div class="mb-2" style="color: ' . $status_color . '"><strong>Score:</strong> ' . esc_html( $read_data['score'] ) . '/100</div>';
						if ( ! empty( $read_data['level'] ) ) {
							$smartcrawl_readability_grade = $read_data['level'];
							$readability_html .= '<div class="mb-2"><strong>Level:</strong> ' . esc_html( $read_data['level'] ) . '</div>';
						}
					}
				}
			} catch ( Exception $e ) {
				// Fallback to built-in engine below
			}
		}

		// Built-in Native Compass SEO Engine (when external plugin analysis is absent)
		if ( empty( $seo_html ) || empty( $readability_html ) ) {
			$title = get_the_title( $post_id );
			$content = $post->post_content;
			$clean_content = wp_strip_all_tags( $content );

			// Focus Keyword check from common meta fields
			$focus_keyword = get_post_meta( $post_id, '_wds_focus-keywords', true );
			if ( empty( $focus_keyword ) ) {
				$focus_keyword = get_post_meta( $post_id, '_wds_focus_keyword', true );
			}
			if ( empty( $focus_keyword ) ) {
				$focus_keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			}
			if ( empty( $focus_keyword ) ) {
				$focus_keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			}

			// Generate SEO Checks
			$checks = array();
			$passed_count = 0;

			// 1. Title Length Check
			$title_len = mb_strlen( $title );
			if ( $title_len >= 30 && $title_len <= 60 ) {
				$checks[] = array( 'status' => 'good', 'msg' => "Title length is optimal ($title_len chars)." );
				$passed_count++;
			} elseif ( $title_len > 0 ) {
				$checks[] = array( 'status' => 'warning', 'msg' => "Title length is $title_len chars (30-60 recommended)." );
			} else {
				$checks[] = array( 'status' => 'error', 'msg' => 'Title is missing.' );
			}

			// 2. Headings Check (H2/H3 Structure)
			preg_match_all( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $headings );
			$heading_count = count( $headings[0] );
			if ( $heading_count > 0 ) {
				$checks[] = array( 'status' => 'good', 'msg' => "Subheadings structure found ($heading_count headings)." );
				$passed_count++;
			} else {
				$checks[] = array( 'status' => 'warning', 'msg' => 'No subheadings found in content. Use H2/H3 tags.' );
			}

			// 3. Word Count Check
			$words = preg_split( '/\s+/', trim( $clean_content ) );
			$words = array_filter( $words );
			$word_count = count( $words );
			if ( $word_count >= 600 ) {
				$checks[] = array( 'status' => 'good', 'msg' => "Comprehensive content length ($word_count words)." );
				$passed_count++;
			} elseif ( $word_count >= 300 ) {
				$checks[] = array( 'status' => 'good', 'msg' => "Sufficient content length ($word_count words)." );
				$passed_count++;
			} else {
				$checks[] = array( 'status' => 'warning', 'msg' => "Word count is $word_count words (min 300 recommended)." );
			}

			// 4. Focus Keyword Analysis
			if ( ! empty( $focus_keyword ) ) {
				$fk_lower = mb_strtolower( $focus_keyword );
				
				if ( strpos( mb_strtolower( $title ), $fk_lower ) !== false ) {
					$checks[] = array( 'status' => 'good', 'msg' => "Focus keyword '$focus_keyword' found in title." );
					$passed_count++;
				} else {
					$checks[] = array( 'status' => 'warning', 'msg' => "Focus keyword '$focus_keyword' not in title." );
				}

				if ( $word_count > 0 ) {
					$occurrences = substr_count( mb_strtolower( $clean_content ), $fk_lower );
					$density = round( ( $occurrences / $word_count ) * 100, 2 );
					if ( $density >= 0.5 && $density <= 2.5 ) {
						$checks[] = array( 'status' => 'good', 'msg' => "Keyword density is $density% ($occurrences times)." );
						$passed_count++;
					} elseif ( $density > 2.5 ) {
						$checks[] = array( 'status' => 'warning', 'msg' => "Keyword density is high ($density%). Avoid over-optimization." );
					} else {
						$checks[] = array( 'status' => 'warning', 'msg' => "Keyword density is low ($density%). Add keyword to content." );
					}
				}
			} else {
				$checks[] = array( 'status' => 'warning', 'msg' => 'No focus keyword set. Enter one below.' );
			}

			// 5. Images Alt Check
			preg_match_all( '/<img[^>]+>/i', $content, $images );
			$image_count = count( $images[0] );
			if ( $image_count > 0 ) {
				$missing_alt = 0;
				foreach ( $images[0] as $img ) {
					if ( ! preg_match( '/alt=["\'](?!["\'])[^"\']+["\']/i', $img ) ) {
						$missing_alt++;
					}
				}
				if ( $missing_alt === 0 ) {
					$checks[] = array( 'status' => 'good', 'msg' => "All $image_count image(s) have alt text." );
					$passed_count++;
				} else {
					$checks[] = array( 'status' => 'warning', 'msg' => "$missing_alt of $image_count image(s) missing alt text." );
				}
			}

			// Build Native SEO HTML output
			if ( empty( $seo_html ) ) {
				$seo_html .= '<div style="margin-bottom: 12px; font-size: 0.95rem; font-weight: bold; color: #62c9ff; display: flex; align-items: center; justify-content: space-between;">';
				$seo_html .= '<div style="display: flex; align-items: center;"><i class="fal fa-list-check" style="margin-right: 8px; font-size: 16px; color: #62c9ff;"></i>SEO Audit Checklist</div>';
				$seo_html .= '<div style="padding: 2px 8px; border-radius: 12px; background: rgba(98, 201, 255, 0.15); color: #62c9ff; font-weight: bold; font-size: 0.75rem;">Score: ' . $passed_count . '/' . count( $checks ) . '</div>';
				$seo_html .= '</div>';

				$seo_html .= '<div style="margin-bottom: 12px; font-size: 0.825rem;"><strong>Focus Keyword:</strong> ' . ( ! empty( $focus_keyword ) ? '<span style="color:#62c9ff">' . esc_html( $focus_keyword ) . '</span>' : '<span style="opacity:0.6">Not set</span>' ) . '</div>';

				$seo_html .= '<ul style="padding: 0; margin: 0; list-style: none;">';
				foreach ( $checks as $chk ) {
					$color = $chk['status'] === 'good' ? '#22c55e' : ( $chk['status'] === 'warning' ? '#eab308' : '#ef4444' );
					$icon_class = $chk['status'] === 'good' ? 'fal fa-check-circle' : ( $chk['status'] === 'warning' ? 'fal fa-exclamation-triangle' : 'fal fa-times-circle' );
					$seo_html .= '<li style="color: #e2e8f0; margin-bottom: 8px; font-size: 0.825rem; display: flex; align-items: flex-start;">';
					$seo_html .= '<i class="' . $icon_class . '" style="color: ' . $color . '; font-size: 14px; margin-right: 8px; flex-shrink: 0; margin-top: 2px;"></i>';
					$seo_html .= '<span>' . esc_html( $chk['msg'] ) . '</span>';
					$seo_html .= '</li>';
				}
				$seo_html .= '</ul>';
			}

			// Build Native Readability Analysis
			if ( empty( $readability_html ) ) {
				if ( $word_count < 15 ) {
					$readability_html = '<div style="opacity: 0.7; font-style: italic;">Not enough content to analyze readability (minimum 15 words needed).</div>';
					$flesch = 0;
					$grade = 'Needs Content';
				} else {
					$sentences = preg_split( '/[.!?]+/', $clean_content, -1, PREG_SPLIT_NO_EMPTY );
					$sentence_count = max( 1, count( $sentences ) );
					$avg_sentence_len = round( $word_count / $sentence_count, 1 );

					$syllables = 0;
					foreach ( $words as $w ) {
						$w_clean = preg_replace( '/[^a-z]/i', '', mb_strtolower( $w ) );
						if ( empty( $w_clean ) ) continue;
						$s_count = preg_match_all( '/[aeiouy]{1,2}/i', $w_clean );
						if ( preg_match( '/e$/i', $w_clean ) && ! preg_match( '/le$/i', $w_clean ) ) {
							$s_count--;
						}
						$syllables += max( 1, $s_count );
					}
					
					$flesch = 206.835 - ( 1.015 * ( $word_count / $sentence_count ) ) - ( 84.6 * ( $syllables / max( 1, $word_count ) ) );
					$flesch = max( 0, min( 100, round( $flesch ) ) );

					if ( $flesch >= 70 ) {
						$grade = 'Easy to Read';
						$color = '#22c55e';
					} elseif ( $flesch >= 50 ) {
						$grade = 'Standard / Good';
						$color = '#62c9ff';
					} else {
						$grade = 'Difficult / Complex';
						$color = '#eab308';
					}

					$readability_html .= '<div style="margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">';
					$readability_html .= '<div><strong>Flesch Ease Score:</strong></div>';
					$readability_html .= '<div style="font-weight: bold; color: ' . $color . '; font-size: 1.1rem;">' . $flesch . ' / 100</div>';
					$readability_html .= '</div>';
					$readability_html .= '<div style="margin-bottom: 8px; font-size: 0.825rem; color: ' . $color . '"><strong>Level:</strong> ' . $grade . '</div>';
					$readability_html .= '<div style="font-size: 0.8rem; opacity: 0.8; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 6px; margin-top: 6px;">';
					$readability_html .= '<div>• Total Words: <strong>' . $word_count . '</strong></div>';
					$readability_html .= '<div>• Total Sentences: <strong>' . $sentence_count . '</strong></div>';
					$readability_html .= '<div>• Avg. Sentence Length: <strong>' . $avg_sentence_len . ' words</strong></div>';
					$readability_html .= '</div>';
				}
			}
		}

		$seo_score = isset( $smartcrawl_seo_score ) ? $smartcrawl_seo_score : ( ( isset( $checks ) && count( $checks ) > 0 ) ? round( ( $passed_count / count( $checks ) ) * 100 ) : 0 );
		$readability_score = isset( $smartcrawl_readability_score ) ? $smartcrawl_readability_score : ( isset( $flesch ) ? round( $flesch ) : 0 );
		$readability_grade = isset( $smartcrawl_readability_grade ) ? $smartcrawl_readability_grade : ( isset( $grade ) ? $grade : 'Unknown' );

		return rest_ensure_response( array(
			'focus_keyword'     => isset( $focus_keyword ) ? $focus_keyword : '',
			'seo_score'         => $seo_score,
			'readability_score' => $readability_score,
			'readability_grade' => $readability_grade,
			'seo'               => $seo_html,
			'readability'       => $readability_html
		) );
	}

	/**
	 * Permissions check. Only admins/editors should define architectural elements.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET: Retrieve all registered CPTs (both native WP public CPTs and COMPASS custom CPTs)
	 */
	public function get_cpts() {
		$all_cpts = array();
		$seen_slugs = array();

		// 1. Get custom CPTs registered via COMPASS options database
		$custom_registered = get_option( 'xophz_compass_registered_cpts', array() );
		if ( is_array( $custom_registered ) ) {
			foreach ( $custom_registered as $cpt ) {
				if ( isset( $cpt['slug'] ) ) {
					$all_cpts[] = $cpt;
					$seen_slugs[ $cpt['slug'] ] = true;
				}
			}
		}

		// 2. Fetch all public WordPress post types registered via register_post_type()
		$wp_post_types = get_post_types( array( 'public' => true ), 'objects' );
		
		// Excluded internal non-content WP system post types
		$excluded_slugs = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'compass_dead_letter' );

		foreach ( $wp_post_types as $slug => $pt_obj ) {
			if ( in_array( $slug, $excluded_slugs, true ) ) {
				continue;
			}
			if ( ! isset( $seen_slugs[ $slug ] ) ) {
				$icon = 'dashicons-admin-post';
				if ( ! empty( $pt_obj->menu_icon ) && is_string( $pt_obj->menu_icon ) ) {
					$icon = $pt_obj->menu_icon;
				} elseif ( $slug === 'post' ) {
					$icon = 'fal fa-newspaper';
				} elseif ( $slug === 'page' ) {
					$icon = 'fal fa-file';
				} elseif ( $slug === 'product' ) {
					$icon = 'fal fa-shopping-cart';
				}

				$plural_label = ! empty( $pt_obj->labels->name ) ? $pt_obj->labels->name : ( ! empty( $pt_obj->label ) ? $pt_obj->label : ucfirst( $slug ) );
				$singular_label = ! empty( $pt_obj->labels->singular_name ) ? $pt_obj->labels->singular_name : $plural_label;

				$all_cpts[] = array(
					'slug'                => $slug,
					'name'                => $plural_label,
					'singular_label'      => $singular_label,
					'plural_label'        => $plural_label,
					'icon'                => $icon,
					'fields'              => array(),
					'supports_categories' => taxonomy_exists( 'category' ) && is_object_in_taxonomy( $slug, 'category' ),
					'supports_tags'       => taxonomy_exists( 'post_tag' ) && is_object_in_taxonomy( $slug, 'post_tag' ),
					'supports_custom_fields' => post_type_supports( $slug, 'custom-fields' ),
					'is_builtin'          => isset( $pt_obj->_builtin ) ? $pt_obj->_builtin : false
				);
				$seen_slugs[ $slug ] = true;
			}
		}

		return rest_ensure_response( $all_cpts );
	}



	/**
	 * POST: Append a new CPT schema to the core WP options database
	 */
	public function create_cpt( $request ) {
		$slug     = sanitize_key( $request->get_param( 'slug' ) );
		$singular = sanitize_text_field( $request->get_param( 'singular_label' ) );
		$plural   = sanitize_text_field( $request->get_param( 'plural_label' ) );
		$icon     = sanitize_text_field( $request->get_param( 'icon' ) );
		$fields   = $request->get_param( 'fields' );
		$supports_categories = filter_var( $request->get_param( 'supports_categories' ), FILTER_VALIDATE_BOOLEAN );
		$supports_tags       = filter_var( $request->get_param( 'supports_tags' ), FILTER_VALIDATE_BOOLEAN );
		
		$supports_custom_fields = $request->get_param( 'supports_custom_fields' );
		if ( $supports_custom_fields === null ) {
			$supports_custom_fields = true;
		} else {
			$supports_custom_fields = filter_var( $supports_custom_fields, FILTER_VALIDATE_BOOLEAN );
		}
		
		if ( empty( $icon ) ) {
			$icon = 'dashicons-admin-post';
		}
		
		if ( ! is_array( $fields ) ) {
			$fields = array();
		} else {
			// Sanitize array inside loop
			$sanitized_fields = array();
			foreach ( $fields as $field ) {
				if ( isset( $field['label'] ) && isset( $field['key'] ) ) {
					$sanitized_fields[] = array(
						'label' => sanitize_text_field( $field['label'] ),
						'key'   => sanitize_key( $field['key'] )
					);
				}
			}
			$fields = $sanitized_fields;
		}

		$registered_cpts = get_option( 'xophz_compass_registered_cpts', array() );

		// Check for duplicate slugs
		foreach ( $registered_cpts as $index => $cpt ) {
			if ( $cpt['slug'] === $slug ) {
				// Update existing
				$registered_cpts[ $index ] = array(
					'slug'                => $slug,
					'singular_label'      => $singular,
					'plural_label'        => $plural,
					'icon'                => $icon,
					'fields'              => $fields,
					'supports_categories' => $supports_categories,
					'supports_tags'       => $supports_tags,
					'supports_custom_fields' => $supports_custom_fields
				);
				update_option( 'xophz_compass_registered_cpts', $registered_cpts );
				flush_rewrite_rules(); // Ensure the newly updated slugs map properly
				
				return rest_ensure_response( array(
					'status'  => 'success',
					'message' => "CPT '{$slug}' updated successfully.",
					'data'    => $registered_cpts[ $index ]
				) );
			}
		}

		// Insert new
		$new_cpt = array(
			'slug'                => $slug,
			'singular_label'      => $singular,
			'plural_label'        => $plural,
			'icon'                => $icon,
			'fields'              => $fields,
			'supports_categories' => $supports_categories,
			'supports_tags'       => $supports_tags,
			'supports_custom_fields' => $supports_custom_fields
		);
		$registered_cpts[] = $new_cpt;

		update_option( 'xophz_compass_registered_cpts', $registered_cpts );
		
		// Flush rewrite rules dynamically after option updates.
		flush_rewrite_rules();

		return rest_ensure_response( array(
			'status'  => 'success',
			'message' => "CPT '{$slug}' appended successfully.",
			'data'    => $new_cpt
		) );
	}

	/**
	 * DELETE: Remove a CPT definition from WP_Options
	 */
	public function delete_cpt( $request ) {
		$slug = sanitize_key( $request->get_param( 'slug' ) );
		$registered_cpts = get_option( 'xophz_compass_registered_cpts', array() );
		
		$found = false;
		foreach ( $registered_cpts as $index => $cpt ) {
			if ( $cpt['slug'] === $slug ) {
				unset( $registered_cpts[ $index ] );
				$found = true;
				break;
			}
		}

		if ( $found ) {
			// Re-index array
			$registered_cpts = array_values( $registered_cpts );
			update_option( 'xophz_compass_registered_cpts', $registered_cpts );
			flush_rewrite_rules();

			return rest_ensure_response( array(
				'status'  => 'success',
				'message' => "CPT '{$slug}' deleted successfully."
			) );
		}

		return new WP_Error( 'not_found', 'No Custom Post Type found with that slug.', array( 'status' => 404 ) );
	}
}
