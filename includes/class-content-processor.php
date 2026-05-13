<?php

class OILM_Content_Processor {

    private $rules = array();
    private $settings = array();
    private $page_links_count = 0;
    private $url_links_count = array();
    private $keyword_links_count = array();
    private $processed_posts = array();
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
            $suppress = $wpdb->suppress_errors();
            $this->rules = $wpdb->get_results( "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY priority ASC", ARRAY_A );
            $wpdb->suppress_errors( $suppress );
            
            if ( ! is_wp_error( $this->rules ) && $this->rules ) {
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
            return $content; 
        }

        $this->set_current_context();

        if ( in_the_loop() && is_main_query() ) {
            $post_type = get_post_type();
            $enabled_types = isset( $this->settings['enabled_post_types'] ) ? $this->settings['enabled_post_types'] : array();
            if ( ! in_array( $post_type, $enabled_types ) && $this->current_source_type !== 'acf' ) {
                return $content;
            }
            
            global $post;
            if ( $post ) {
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
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        
        if ( function_exists( 'mb_convert_encoding' ) ) {
            $html = mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );
        } else {
            $html = $content;
        }

        $html = '<?xml encoding="utf-8" ?><div>' . $html . '</div>';
        $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $xpath = new DOMXPath( $dom );
        
        // 1. Structural exclusions (Tags and standard areas to ignore)
        $tag_exclusions = array(
            'a', 'script', 'style', 'code', 'pre', 'textarea', 'button', 
            'iframe', 'header', 'nav', 'footer', 'aside', 'noscript', 'img'
        );
        
        if ( isset( $this->settings['exclude_headings'] ) && $this->settings['exclude_headings'] ) {
            $tag_exclusions = array_merge( $tag_exclusions, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') );
        }

        // 2. Class/ID exclusions for headers and navbars
        $extra_exclusions = array(
            '.navbar', '.site-header', '.main-navigation', '.navigation', 
            '.menu-container', '#header', '#nav', '.elementor-location-header'
        );

        if ( isset( $this->settings['exclude_elements'] ) && is_array( $this->settings['exclude_elements'] ) ) {
            $extra_exclusions = array_merge( $extra_exclusions, $this->settings['exclude_elements'] );
        }

        // Build XPath: Only select text nodes that are NOT children of the excluded tags or classes
        $query = $this->build_exclusion_xpath( $tag_exclusions, $extra_exclusions );
        $text_nodes = $xpath->query( $query );

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
        $link_css_class = $this->get_link_css_class();

        $updates_made = false;
        $rules_hit = array(); 
        $location_hits = array();

        foreach ( $text_nodes as $node ) {
            if ( ( $first_occurrence_only && $this->page_links_count >= 1 ) || ( $global_max > 0 && $this->page_links_count >= $global_max ) ) {
                break;
            }

            $text = $node->nodeValue;
            if ( strlen(trim( $text )) < 2 ) continue;

            foreach ( $this->rules as $rule ) {
                if ( in_array( rtrim( $rule['url'], '/' ), $existing_hrefs ) ) continue;

                $url_count = $this->url_links_count[$rule['url']] ?? 0;
                if ( $url_count >= $global_url_max ) continue;
                
                $rule_max = absint($rule['max_links_per_page']);
                if ( $rule_max > 0 && ($this->keyword_links_count[$rule['id']] ?? 0) >= $rule_max ) continue;

                if ( $this->current_post_url && rtrim($this->current_post_url, '/') === rtrim($rule['url'], '/') ) continue;

                foreach ( $rule['keywords_arr'] as $keyword ) {
                    if ( empty( $keyword ) ) continue;

                    $kw_max = absint($rule['max_uses_per_keyword']);
                    $kw_key = $rule['id'] . '_' . $keyword;
                    if ( $kw_max > 0 && ($this->keyword_links_count[$kw_key] ?? 0) >= $kw_max ) continue;

                    $escaped_kw = preg_quote( $keyword, '/' );
                    $plural_suffix = $enable_pluralization ? '(?:s|es)?' : '';
                    $pattern = $rule['is_exact_match'] ? '/\b(' . $escaped_kw . $plural_suffix . ')\b/u' : '/\b(' . $escaped_kw . $plural_suffix . ')\b/iu';

                    if ( preg_match( $pattern, $text, $matches ) ) {
                        $matched_text = $matches[1];
                        
                        $open_new_tab = $global_override_rule_attributes ? !empty($this->settings['default_new_tab']) : ( ! empty( $rule['open_new_tab'] ) );
                        $add_nofollow = $global_override_rule_attributes ? !empty($this->settings['default_nofollow']) : ( ! empty( $rule['is_nofollow'] ) );
                        
                        $target = $open_new_tab ? ' target="_blank"' : '';
                        $rel_parts = [];
                        if ($add_nofollow) $rel_parts[] = 'nofollow';
                        if ($rule['is_sponsored']) $rel_parts[] = 'sponsored';
                        if ($open_new_tab) $rel_parts[] = 'noopener';
                        $rel = !empty($rel_parts) ? ' rel="' . implode(' ', $rel_parts) . '"' : '';
                        
                        $title = !empty($rule['title_attr']) ? ' title="' . esc_attr($rule['title_attr']) . '"' : '';
                        $class = $link_css_class ? ' class="' . esc_attr( $link_css_class ) . '"' : '';
                        
                        $link_html = '<a href="' . esc_url($rule['url']) . '"' . $class . $target . $rel . $title . '>' . htmlspecialchars($matched_text, ENT_QUOTES, 'UTF-8') . '</a>';

                        // Replacement
                        $new_text = preg_replace( $pattern, $link_html, $text, 1 );

                        if ( $new_text !== $text ) {
                            $fragment = $dom->createDocumentFragment();
                            if ( @$fragment->appendXML( $new_text ) ) {
                                $node->parentNode->replaceChild( $fragment, $node );
                                
                                $this->page_links_count++;
                                $this->url_links_count[$rule['url']] = ($this->url_links_count[$rule['url']] ?? 0) + 1;
                                $this->keyword_links_count[$kw_key] = ($this->keyword_links_count[$kw_key] ?? 0) + 1;
                                $this->keyword_links_count[$rule['id']] = ($this->keyword_links_count[$rule['id']] ?? 0) + 1;

                                $this->add_rule_hit( $rules_hit, $rule['id'] );
                                $this->add_location_hit( $location_hits, $rule['id'], $keyword );
                                $updates_made = true;
                                break 2; 
                            }
                        }
                    }
                }
            }
        }

        if ( $updates_made ) {
            $this->update_stats( $rules_hit );
            $this->update_location_stats( $location_hits );
            $body = $dom->getElementsByTagName('div')->item(0);
            if ( $body ) {
                $output = '';
                foreach ( $body->childNodes as $child ) {
                    $output .= $dom->saveHTML( $child );
                }
                return str_replace( '<?xml encoding="utf-8" ?>', '', $output );
            }
        }

        return $content;
    }

    private function build_exclusion_xpath( $tag_exclusions, $extra_exclusions ) {
        $conditions = array();
        foreach ( $tag_exclusions as $tag ) {
            $conditions[] = "not(ancestor::$tag)";
        }
        foreach ( $extra_exclusions as $excl ) {
            $excl = trim( $excl );
            if ( '' === $excl ) continue;
            if ( $excl[0] === '.' ) {
                $class = substr( $excl, 1 );
                $conditions[] = "not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')])";
            } elseif ( $excl[0] === '#' ) {
                $id = substr( $excl, 1 );
                $conditions[] = "not(ancestor::*[@id='$id'])";
            } else {
                $conditions[] = "not(ancestor::$excl)";
            }
        }
        // CRITICAL: only select text nodes that are actually inside elements, not attributes
        return "//text()[" . implode( ' and ', $conditions ) . "]";
    }

    private function get_link_css_class() {
        $class_value = $this->settings['link_css_class'] ?? 'op-internal-link';
        $classes = preg_split( '/\s+/', trim( (string)$class_value ) );
        return implode( ' ', array_filter( array_map( 'sanitize_html_class', $classes ) ) );
    }

    private function add_rule_hit( &$rules_hit, $rule_id ) {
        $rule_id = absint( $rule_id );
        if ( ! isset( $rules_hit[ $rule_id ] ) ) $rules_hit[ $rule_id ] = array('count' => 0);
        $rules_hit[ $rule_id ]['count']++;
    }

    private function set_current_context() {
        global $post;
        $this->current_post_id = $post ? absint( $post->ID ) : 0;
        $this->current_post_url = $this->current_post_id ? get_permalink( $this->current_post_id ) : '';
        $this->current_source_type = 'content';
        $filter = current_filter();
        $map = ['get_the_excerpt' => 'excerpt', 'comment_text' => 'comment', 'acf' => 'acf', 'woocommerce' => 'woocommerce'];
        foreach($map as $key => $val) { if (strpos((string)$filter, $key) !== false) { $this->current_source_type = $val; break; } }
    }

    private function add_location_hit( &$location_hits, $rule_id, $keyword ) {
        if ( ! $this->current_post_id ) return;
        $key = absint($rule_id) . ':' . $this->current_post_id . ':' . $this->current_source_type;
        if ( ! isset( $location_hits[ $key ] ) ) {
            $location_hits[ $key ] = array('rule_id' => $rule_id, 'post_id' => $this->current_post_id, 'source_type' => $this->current_source_type, 'count' => 0, 'keyword' => $keyword);
        }
        $location_hits[ $key ]['count']++;
    }

    private function update_stats( $rules_hit ) {
        if ( empty( $rules_hit ) ) return;
        global $wpdb;
        $table = $wpdb->prefix . 'oilm_rules';
        $loc_table = $wpdb->prefix . 'oilm_insertion_locations';
        foreach ( $rules_hit as $rule_id => $hit ) {
            $other_count = (int) $wpdb->get_var( $wpdb->prepare("SELECT COALESCE(SUM(insert_count), 0) FROM $loc_table WHERE rule_id = %d AND NOT (post_id = %d AND source_type = %s)", $rule_id, $this->current_post_id, $this->current_source_type) );
            $wpdb->query( $wpdb->prepare("UPDATE $table SET insert_count = %d, last_inserted_at = CURRENT_TIMESTAMP WHERE id = %d", $other_count + $hit['count'], $rule_id) );
        }
    }

    private function update_location_stats( $location_hits ) {
        if ( empty( $location_hits ) ) return;
        global $wpdb;
        $table = $wpdb->prefix . 'oilm_insertion_locations';
        foreach ( $location_hits as $hit ) {
            $wpdb->query( $wpdb->prepare("INSERT INTO $table (rule_id, post_id, source_type, insert_count, last_keyword, first_inserted_at, last_inserted_at) VALUES (%d, %d, %s, %d, %s, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE insert_count = VALUES(insert_count), last_keyword = VALUES(last_keyword), last_inserted_at = CURRENT_TIMESTAMP", $hit['rule_id'], $hit['post_id'], $hit['source_type'], $hit['count'], $hit['keyword']) );
        }
    }
}