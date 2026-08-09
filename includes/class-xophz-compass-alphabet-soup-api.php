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
			update_post_meta( $post_id, '_wds_focus_keyword', $sanitized_kw );
			update_post_meta( $post_id, 'rank_math_focus_keyword', $sanitized_kw );
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $sanitized_kw );
		}

		$seo_html = '';
		$readability_html = '';

		// Check for Smartcrawl or Yoast or Rank Math first
		if ( class_exists( '\Smartcrawl\Core\Controllers\Analysis' ) ) {
			try {
				$analyzer = new \Smartcrawl\Core\Controllers\Analysis();
				if ( method_exists( $analyzer, 'maybe_analyze_post' ) ) {
					$analyzer->maybe_analyze_post( $post_id );
				}
				if ( method_exists( $analyzer, 'retrieve_post_seo_analysis' ) ) {
					$seo_data = $analyzer->retrieve_post_seo_analysis( $post );
					if ( ! empty( $seo_data['primary_keyword'] ) ) {
						$seo_html .= '<div class="mb-2"><strong>Focus Keyword:</strong> ' . esc_html( $seo_data['primary_keyword'] ) . '</div>';
						$seo_html .= '<div class="mb-2"><strong>Errors:</strong> ' . esc_html( $seo_data['primary_error_count'] ) . '</div>';
						if ( ! empty( $seo_data['primary_checks'] ) && is_array( $seo_data['primary_checks'] ) ) {
							$seo_html .= '<ul class="pl-4 mt-2" style="list-style: none;">';
							foreach ( $seo_data['primary_checks'] as $check ) {
								$status_color = $check['status'] === 'good' ? '#4caf50' : ( $check['status'] === 'warning' ? '#ffeb3b' : '#f44336' );
								$icon = $check['status'] === 'good' ? '✓' : ( $check['status'] === 'warning' ? '⚠' : '✕' );
								$seo_html .= '<li style="color: ' . $status_color . '; margin-bottom: 6px;">';
								$seo_html .= '<span style="margin-right: 6px;">' . $icon . '</span>' . esc_html( $check['message'] );
								$seo_html .= '</li>';
							}
							$seo_html .= '</ul>';
						}
					}
				}
				if ( method_exists( $analyzer, 'retrieve_post_readability_analysis' ) ) {
					$read_data = $analyzer->retrieve_post_readability_analysis( $post );
					if ( ! empty( $read_data ) && isset( $read_data['score'] ) ) {
						$status_color = $read_data['state'] === 'good' ? '#4caf50' : ( $read_data['state'] === 'warning' ? '#ffeb3b' : '#f44336' );
						$readability_html .= '<div class="mb-2" style="color: ' . $status_color . '"><strong>Score:</strong> ' . esc_html( $read_data['score'] ) . '/100</div>';
						$readability_html .= '<div class="mb-2"><strong>Level:</strong> ' . esc_html( $read_data['level'] ) . '</div>';
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
			$focus_keyword = get_post_meta( $post_id, '_wds_focus_keyword', true );
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

			// 2. Word Count Check
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

			// 3. Focus Keyword Analysis
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
				$checks[] = array( 'status' => 'warning', 'msg' => 'No focus keyword set. Set one in post meta for targeted feedback.' );
			}

			// 4. Headings Check
			preg_match_all( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $headings );
			$heading_count = count( $headings[0] );
			if ( $heading_count > 0 ) {
				$checks[] = array( 'status' => 'good', 'msg' => "Subheadings structure found ($heading_count headings)." );
				$passed_count++;
			} else {
				$checks[] = array( 'status' => 'warning', 'msg' => 'No subheadings found in content. Use H2/H3 tags.' );
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
				$seo_html .= '<div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">';
				$seo_html .= '<div><strong>Focus Keyword:</strong> ' . ( ! empty( $focus_keyword ) ? '<span style="color:#62c9ff">' . esc_html( $focus_keyword ) . '</span>' : '<span style="opacity:0.6">Not set</span>' ) . '</div>';
				$seo_html .= '<div style="padding: 2px 8px; border-radius: 12px; background: rgba(98, 201, 255, 0.15); color: #62c9ff; font-weight: bold; font-size: 0.75rem;">Score: ' . $passed_count . '/' . count( $checks ) . '</div>';
				$seo_html .= '</div>';

				$seo_html .= '<ul style="padding: 0; margin: 0; list-style: none;">';
				foreach ( $checks as $chk ) {
					$color = $chk['status'] === 'good' ? '#22c55e' : ( $chk['status'] === 'warning' ? '#eab308' : '#ef4444' );
					$icon = $chk['status'] === 'good' ? '✓' : ( $chk['status'] === 'warning' ? '⚡' : '✕' );
					$seo_html .= '<li style="color: ' . $color . '; margin-bottom: 8px; font-size: 0.825rem; display: flex; align-items: flex-start;">';
					$seo_html .= '<span style="margin-right: 8px; font-weight: bold;">' . $icon . '</span>';
					$seo_html .= '<span>' . esc_html( $chk['msg'] ) . '</span>';
					$seo_html .= '</li>';
				}
				$seo_html .= '</ul>';
			}

			// Build Native Readability Analysis
			if ( empty( $readability_html ) ) {
				if ( $word_count < 15 ) {
					$readability_html = '<div style="opacity: 0.7; font-style: italic;">Not enough content to analyze readability (minimum 15 words needed).</div>';
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

		return rest_ensure_response( array(
			'focus_keyword' => isset( $focus_keyword ) ? $focus_keyword : '',
			'seo'           => $seo_html,
			'readability'   => $readability_html
		) );
	}


	/**
	 * Permissions check. Only admins/editors should define architectural elements.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET: Retrieve all dynamic CPT definitions
	 */
	public function get_cpts() {
		$registered_cpts = get_option( 'xophz_compass_registered_cpts', array() );
		return rest_ensure_response( $registered_cpts );
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
