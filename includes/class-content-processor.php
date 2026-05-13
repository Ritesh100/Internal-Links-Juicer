<?php

class OILM_Content_Processor {

	private $rules = array();
	private $settings = array();
	private $page_links_count = 0;
	private $url_links_count = array();
	private $keyword_links_count = array();
	private $processed_posts = array(); // Prevent infinite loops
	private $current_post_id = 0;
	private $current_post_url = '';
	private $current_source_type = 'content';

	public function __construct() {
		$this->settings = get_option( 'oilm_settings' );
		$this->load_rules();
	}

	private function load_rules() {
		$this->rules = get_transient( 'oilm_active_rules' );
		if ( false === $this->rules ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'oilm_rules';
			// Suppress errors during table check just in case
			$suppress = $wpdb->suppress_errors();
			$this->rules = $wpdb->get_results( "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY priority ASC", ARRAY_A );
			$wpdb->suppress_errors( $suppress );
			
			if ( ! is_wp_error( $this->rules ) && $this->rules ) {
				// Process keywords into array for faster matching
				foreach ( $this->rules as &$rule ) {
					$keywords = explode( ',', $rule['keywords'] );
					$rule['keywords_arr'] = array_map( 'trim', $keywords );
				}
				set_transient( 'oilm_active_rules', $this->rules, DAY_IN_SECONDS );
			} else {
				$this->rules = array();
			}
		}
	}

	public function process_content( $content ) {
		if ( empty( $content ) || empty( $this->rules ) ) {
			return $content;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $content; // Skip backend editor
		}

		$this->set_current_context();

		// Check post type restrictions if in main query
		if ( in_the_loop() && is_main_query() ) {
			$post_type = get_post_type();
			$enabled_types = isset( $this->settings['enabled_post_types'] ) ? $this->settings['enabled_post_types'] : array();
			if ( ! in_array( $post_type, $enabled_types ) ) {
				return $content;
			}
			
			// Exclude current page linking to itself, and explicit post IDs
			global $post;
			if ( $post ) {
				// Check specific exclusions
				if ( ! empty( $this->settings['exclude_post_ids'] ) ) {
					$excluded_ids = array_map( 'trim', explode( ',', $this->settings['exclude_post_ids'] ) );
					if ( in_array( $post->ID, $excluded_ids ) ) {
						return $content;
					}
				}

				if ( in_array( $post->ID, $this->processed_posts ) && $this->current_source_type !== 'acf' ) {
					return $content;
				}

				if ( $this->current_source_type !== 'acf' ) {
					$this->processed_posts[] = $post->ID;
				}
			}
		}

		return $this->parse_and_replace( $content );
	}

	public function parse_and_replace( $content ) {
		// Use DOMDocument to safely parse HTML
		$dom = new DOMDocument();
		// Suppress warnings for malformed HTML
		libxml_use_internal_errors( true );
		
		// Wrap in mb_convert_encoding to ensure UTF-8 is handled properly
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$html = mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );
		} else {
			$html = $content;
		}

		// Hack to prevent DOMDocument from adding body/html tags if not present
		// We wrap it in a root node we can extract later
		$html = '<?xml encoding="utf-8" ?><div>' . $html . '</div>';

		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		
		// Find text nodes that are NOT inside exclusions
		$exclusions = array('a', 'script', 'style', 'code', 'pre', 'textarea', 'button', 'iframe');
		
		if ( isset( $this->settings['exclude_headings'] ) && $this->settings['exclude_headings'] ) {
			$exclusions = array_merge( $exclusions, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') );
		}

		if ( isset( $this->settings['exclude_elements'] ) && is_array( $this->settings['exclude_elements'] ) ) {
			$exclusions = array_merge( $exclusions, $this->settings['exclude_elements'] );
		}

		$query = "//text()[not(ancestor::" . implode(') and not(ancestor::', $exclusions) . ")]";
		$text_nodes = $xpath->query( $query );

		// Extract existing links to avoid duplicating static links
		$existing_hrefs = array();
		$anchors = $xpath->query('//a/@href');
		foreach ( $anchors as $anchor ) {
			$existing_hrefs[] = rtrim( $anchor->nodeValue, '/' );
		}

		$global_max = isset( $this->settings['global_max_links'] ) ? absint( $this->settings['global_max_links'] ) : 0;
		$global_url_max = max( 1, isset( $this->settings['global_max_url_links'] ) ? absint( $this->settings['global_max_url_links'] ) : 1 );
		$first_occurrence_only = isset( $this->settings['first_occurrence_only'] ) && $this->settings['first_occurrence_only'];
		$enable_pluralization = isset( $this->settings['enable_pluralization'] ) && $this->settings['enable_pluralization'];
		$global_override_rule_attributes = ! empty( $this->settings['global_override_rule_attributes'] );
		$default_new_tab = ! empty( $this->settings['default_new_tab'] );
		$default_nofollow = ! empty( $this->settings['default_nofollow'] );
		$link_css_class = $this->get_link_css_class();

		$updates_made = false;
		$rules_hit = array(); // Track which rules were used for stats
		$location_hits = array();

		foreach ( $text_nodes as $node ) {
			if ( $first_occurrence_only && $this->page_links_count >= 1 ) {
				break;
			}
			
			if ( $global_max > 0 && $this->page_links_count >= $global_max ) {
				break;
			}

			// Trim text node to skip empty space processing
			$text = $node->nodeValue;
			if ( trim( $text ) === '' ) {
				continue;
			}

			$replaced = false;

			foreach ( $this->rules as $rule ) {
				// Check if the URL is already linked statically in the content
				if ( in_array( rtrim( $rule['url'], '/' ), $existing_hrefs ) ) {
					continue;
				}

				// Check global url limits (minimum 1 to prevent duplicate links to same URL)
				$url_count = isset($this->url_links_count[$rule['url']]) ? $this->url_links_count[$rule['url']] : 0;
				if ( $url_count >= $global_url_max ) continue;
				
				// Check rule limits
				$rule_max = absint($rule['max_links_per_page']);
				if ( $rule_max > 0 ) {
					$rule_count = isset($this->keyword_links_count[$rule['id']]) ? $this->keyword_links_count[$rule['id']] : 0;
					if ( $rule_count >= $rule_max ) continue;
				}

				// Avoid self-linking
				if ( $this->current_post_url && rtrim($this->current_post_url, '/') === rtrim($rule['url'], '/') ) {
					continue;
				}

				foreach ( $rule['keywords_arr'] as $keyword ) {
					if ( empty( $keyword ) ) continue;

					// Check keyword specific limit
					$kw_max = absint($rule['max_uses_per_keyword']);
					if ( $kw_max > 0 ) {
						$kw_key = $rule['id'] . '_' . $keyword;
						$kw_count = isset($this->keyword_links_count[$kw_key]) ? $this->keyword_links_count[$kw_key] : 0;
						if ( $kw_count >= $kw_max ) continue;
					}

					// Prepare regex
					$escaped_kw = preg_quote( $keyword, '/' );
					
					$plural_suffix = $enable_pluralization ? '(?:s|es)?' : '';

					if ( $rule['is_exact_match'] ) {
						$pattern = '/\b(' . $escaped_kw . $plural_suffix . ')\b/u'; // Case sensitive whole word
					} else {
						$pattern = '/\b(' . $escaped_kw . $plural_suffix . ')\b/iu'; // Case insensitive whole word
					}

					if ( preg_match( $pattern, $text, $matches ) ) {
						$matched_text = $matches[1];

						// Build replacement anchor
						$open_new_tab = $global_override_rule_attributes ? $default_new_tab : ( ! empty( $rule['open_new_tab'] ) || $default_new_tab );
						$add_nofollow = $global_override_rule_attributes ? $default_nofollow : ( ! empty( $rule['is_nofollow'] ) || $default_nofollow );
						$target = $open_new_tab ? ' target="_blank"' : '';
						$rel_arr = array();
						if ( $add_nofollow ) $rel_arr[] = 'nofollow';
						if ( $rule['is_sponsored'] ) $rel_arr[] = 'sponsored';
						if ( strpos($target, '_blank') !== false ) $rel_arr[] = 'noopener';
						
						$rel = !empty($rel_arr) ? ' rel="' . implode(' ', $rel_arr) . '"' : '';
						$title = !empty($rule['title_attr']) ? ' title="' . esc_attr($rule['title_attr']) . '"' : '';
						$class = $link_css_class ? ' class="' . esc_attr( $link_css_class ) . '"' : '';
						
						$link_html = '<a href="' . esc_url($rule['url']) . '"' . $class . $target . $rel . $title . '>' . htmlspecialchars($matched_text, ENT_QUOTES, 'UTF-8') . '</a>';

						// Replace first occurrence only per node to maintain limits
						$new_text = preg_replace( $pattern, $link_html, $text, 1 );

						if ( $new_text !== $text ) {
							// Load the new fragment into DOM
							$fragment = $dom->createDocumentFragment();
							$fragment->appendXML( $new_text );
							$node->parentNode->replaceChild( $fragment, $node );
							
							// Update counters
							$this->page_links_count++;
							$this->url_links_count[$rule['url']] = isset($this->url_links_count[$rule['url']]) ? $this->url_links_count[$rule['url']] + 1 : 1;
							
							$kw_key = $rule['id'] . '_' . $keyword;
							$this->keyword_links_count[$kw_key] = isset($this->keyword_links_count[$kw_key]) ? $this->keyword_links_count[$kw_key] + 1 : 1;
							$this->keyword_links_count[$rule['id']] = isset($this->keyword_links_count[$rule['id']]) ? $this->keyword_links_count[$rule['id']] + 1 : 1;

							$this->add_rule_hit( $rules_hit, $rule['id'] );
							$this->add_location_hit( $location_hits, $rule['id'], $keyword );
							$updates_made = true;
							$replaced = true;
							break 2; // Move to next node since we modified the DOM structure for this one
						}
					}
				}
			}
		}

		if ( $updates_made ) {
			// Update stats asynchronously or in shutdown hook ideally, 
			// but for MVP doing it directly if stats changed.
			// To avoid DB calls on every load, we might only update stats occasionally or via transient.
			// For lightweight tracking, update DB here
			$this->update_stats( $rules_hit );
			$this->update_location_stats( $location_hits );

			// Extract body content without the wrapper
			$body = $dom->getElementsByTagName('div')->item(0);
			if ( $body ) {
				// We need to return inner HTML of the div
				$output = '';
				foreach ( $body->childNodes as $child ) {
					$output .= $dom->saveHTML( $child );
				}
				// Decode the utf-8 declaration and XML wrapper we added if it leaked
				$output = str_replace( '<?xml encoding="utf-8" ?>', '', $output );
				return $output;
			}
		}

		return $content;
	}

	private function get_link_css_class() {
		$class_value = isset( $this->settings['link_css_class'] ) ? $this->settings['link_css_class'] : 'op-internal-link';
		$class_value = is_string( $class_value ) ? $class_value : '';
		$classes = preg_split( '/\s+/', trim( $class_value ) );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', $classes );
	}

	private function add_rule_hit( &$rules_hit, $rule_id ) {
		$rule_id = absint( $rule_id );

		if ( ! isset( $rules_hit[ $rule_id ] ) ) {
			$rules_hit[ $rule_id ] = array(
				'count' => 0,
			);
		}

		$rules_hit[ $rule_id ]['count']++;
	}

	private function set_current_context() {
		global $post;

		$this->current_post_id = $post ? absint( $post->ID ) : 0;
		$this->current_post_url = $this->current_post_id ? get_permalink( $this->current_post_id ) : '';
		$this->current_source_type = 'content';

		$current_filter = current_filter();
		if ( $current_filter === 'get_the_excerpt' ) {
			$this->current_source_type = 'excerpt';
		} elseif ( $current_filter === 'comment_text' ) {
			$this->current_source_type = 'comment';
		} elseif ( strpos( (string) $current_filter, 'elementor' ) !== false ) {
			$this->current_source_type = 'elementor';
		} elseif ( strpos( (string) $current_filter, 'acf' ) !== false ) {
			$this->current_source_type = 'acf';
		} elseif ( strpos( (string) $current_filter, 'woocommerce' ) !== false || strpos( (string) $current_filter, 'product' ) !== false ) {
			$this->current_source_type = 'woocommerce';
		} elseif ( strpos( (string) $current_filter, 'nav_menu' ) !== false || strpos( (string) $current_filter, 'render_block_core/navigation' ) !== false ) {
			$this->current_source_type = 'nav_menu';
		} elseif ( strpos( (string) $current_filter, 'widget_text' ) !== false || strpos( (string) $current_filter, 'widget_block' ) !== false ) {
			$this->current_source_type = 'widget';
		}
	}

	private function add_location_hit( &$location_hits, $rule_id, $keyword ) {
		if ( ! $this->current_post_id ) {
			return;
		}

		$rule_id = absint( $rule_id );
		$key = $rule_id . ':' . $this->current_post_id . ':' . $this->current_source_type;

		if ( ! isset( $location_hits[ $key ] ) ) {
			$location_hits[ $key ] = array(
				'rule_id'     => $rule_id,
				'post_id'     => $this->current_post_id,
				'source_type' => $this->current_source_type,
				'count'       => 0,
				'keyword'     => $keyword,
			);
		}

		$location_hits[ $key ]['count']++;
		$location_hits[ $key ]['keyword'] = $keyword;
	}

	private function update_stats( $rules_hit ) {
		if ( empty( $rules_hit ) ) return;
		
		global $wpdb;
		$rules_table_name = $wpdb->prefix . 'oilm_rules';
		$locations_table_name = $wpdb->prefix . 'oilm_insertion_locations';

		foreach ( $rules_hit as $rule_id => $hit ) {
			$count = absint( $hit['count'] );
			$existing_other_count = 0;

			if ( $this->current_post_id ) {
				$existing_other_count = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COALESCE(SUM(insert_count), 0)
					FROM $locations_table_name
					WHERE rule_id = %d
						AND NOT (post_id = %d AND source_type = %s)",
					$rule_id,
					$this->current_post_id,
					$this->current_source_type
				) );
			}

			$wpdb->query( $wpdb->prepare( 
				"UPDATE $rules_table_name SET insert_count = %d, last_inserted_at = CURRENT_TIMESTAMP WHERE id = %d",
				$existing_other_count + $count,
				$rule_id
			) );
		}
	}

	private function update_location_stats( $location_hits ) {
		if ( empty( $location_hits ) ) return;

		global $wpdb;
		$table_name = $wpdb->prefix . 'oilm_insertion_locations';

		foreach ( $location_hits as $hit ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO $table_name
					(rule_id, post_id, source_type, insert_count, last_keyword, first_inserted_at, last_inserted_at)
				VALUES
					(%d, %d, %s, %d, %s, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
				ON DUPLICATE KEY UPDATE
					insert_count = VALUES(insert_count),
					last_keyword = VALUES(last_keyword),
					last_inserted_at = CURRENT_TIMESTAMP",
				$hit['rule_id'],
				$hit['post_id'],
				$hit['source_type'],
				$hit['count'],
				$hit['keyword']
			) );
		}
	}
}
