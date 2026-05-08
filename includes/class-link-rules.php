<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class OILM_Rules_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'rule',
			'plural'   => 'rules',
			'ajax'     => false
		) );
	}

	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'keywords'  => 'Keywords',
			'url'       => 'Target URL',
			'is_active' => 'Active',
			'priority'  => 'Priority',
			'stats'     => 'Insertions'
		);
	}

	public function get_sortable_columns() {
		return array(
			'keywords' => array( 'keywords', false ),
			'priority' => array( 'priority', false ),
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'url':
				return esc_html( $item['url'] );
			case 'is_active':
				return $item['is_active'] ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>';
			case 'priority':
				return absint( $item['priority'] );
			case 'stats':
				$count = absint( $item['insert_count'] );
				$label = sprintf(
					_n( '%s use', '%s uses', $count, 'op-internal-link-manager' ),
					number_format_i18n( $count )
				);

				if ( ! empty( $item['location_count'] ) ) {
					$url = add_query_arg(
						array(
							'page'     => 'op-internal-link-manager',
							'action'   => 'edit',
							'rule'     => absint( $item['id'] ),
							'_wpnonce' => wp_create_nonce( 'oilm_edit_rule_' . $item['id'] ),
						),
						admin_url( 'admin.php' )
					);

					return sprintf( '<a href="%s#oilm-insertion-locations">%s</a>', esc_url( $url ), esc_html( $label ) );
				}

				return esc_html( $label );
			default:
				return print_r( $item, true );
		}
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="rule[]" value="%s" />', $item['id']
		);
	}

	protected function column_keywords( $item ) {
		$edit_nonce = wp_create_nonce( 'oilm_edit_rule_' . $item['id'] );
		$delete_nonce = wp_create_nonce( 'oilm_delete_rule_' . $item['id'] );
		
		$page = $_REQUEST['page'];
		
		$actions = array(
			'edit'   => sprintf( '<a href="?page=%s&action=%s&rule=%s&_wpnonce=%s">Edit</a>', esc_attr( $page ), 'edit', absint( $item['id'] ), $edit_nonce ),
			'delete' => sprintf( '<a href="?page=%s&action=%s&rule=%s&_wpnonce=%s" onclick="return confirm(\'Are you sure?\')">Delete</a>', esc_attr( $page ), 'delete', absint( $item['id'] ), $delete_nonce ),
		);

		return sprintf( '%1$s %2$s', esc_html( $item['keywords'] ), $this->row_actions( $actions ) );
	}

	public function prepare_items() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'oilm_rules';
		
		$per_page = 20;
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		
		$this->_column_headers = array( $columns, $hidden, $sortable );
		
		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'priority';
		$order = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'ASC';
		
		$current_page = $this->get_pagenum();
		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
		
		$offset = ( $current_page - 1 ) * $per_page;
		
		// Safe orderby logic
		$valid_columns = array('keywords', 'priority');
		if ( ! in_array( $orderby, $valid_columns ) ) {
			$orderby = 'priority';
		}
		$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

		$locations_table_name = $wpdb->prefix . 'oilm_insertion_locations';

		$this->items = $wpdb->get_results(
			"SELECT rules.*, COALESCE(location_totals.location_count, 0) AS location_count
			FROM $table_name rules
			LEFT JOIN (
				SELECT rule_id, COUNT(id) AS location_count
				FROM $locations_table_name
				GROUP BY rule_id
			) location_totals ON location_totals.rule_id = rules.id
			ORDER BY rules.$orderby $order
			LIMIT $per_page OFFSET $offset",
			ARRAY_A
		);
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page
		) );
	}
}

class OILM_Link_Rules {

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		
		if ( $action === 'edit' || $action === 'new' ) {
			$this->render_form();
		} else {
			$this->render_list();
		}
	}

	private function render_list() {
		$list_table = new OILM_Rules_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap oilm-modern-wrap">
			<h1 class="wp-heading-inline">Internal Link Rules</h1>
			<a href="?page=op-internal-link-manager&action=new" class="page-title-action">Add New</a>
			<hr class="wp-header-end">
			<form id="rules-filter" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	private function render_insertion_locations( $rule ) {
		global $wpdb;

		$rule_id = absint( $rule['id'] );
		$table_name = $wpdb->prefix . 'oilm_insertion_locations';
		$per_page = 20;
		$current_page = isset( $_GET['location_page'] ) ? max( 1, absint( $_GET['location_page'] ) ) : 1;
		$offset = ( $current_page - 1 ) * $per_page;
		$total_items = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE rule_id = %d", $rule_id ) );
		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT locations.*, posts.post_title, posts.post_type, posts.post_status
				FROM $table_name locations
				LEFT JOIN {$wpdb->posts} posts ON posts.ID = locations.post_id
				WHERE locations.rule_id = %d
				ORDER BY locations.last_inserted_at DESC
				LIMIT %d OFFSET %d",
				$rule_id,
				$per_page,
				$offset
			)
		);

		$total_pages = $total_items > 0 ? (int) ceil( $total_items / $per_page ) : 1;
		$base_url = add_query_arg(
			array(
				'page'     => 'op-internal-link-manager',
				'action'   => 'edit',
				'rule'     => $rule_id,
				'_wpnonce' => wp_create_nonce( 'oilm_edit_rule_' . $rule_id ),
			),
			admin_url( 'admin.php' )
		);
		?>
		<div id="oilm-insertion-locations" class="oilm-settings-card oilm-locations-card">
			<h2><?php esc_html_e( 'Insertion Locations', 'op-internal-link-manager' ); ?></h2>
			<p><?php esc_html_e( 'These are posts and pages where this rule inserted links during frontend rendering.', 'op-internal-link-manager' ); ?></p>

			<?php if ( empty( $locations ) ) : ?>
				<p class="oilm-empty-state">
					<?php
					if ( ! empty( $rule['insert_count'] ) ) {
						esc_html_e( 'Locations will appear as pages are viewed after tracking was enabled. Existing total counts cannot be mapped to posts retroactively.', 'op-internal-link-manager' );
					} else {
						esc_html_e( 'No insertion locations have been logged for this rule yet.', 'op-internal-link-manager' );
					}
					?>
				</p>
			<?php else : ?>
				<table class="wp-list-table widefat striped oilm-locations-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'op-internal-link-manager' ); ?></th>
							<th><?php esc_html_e( 'Type / Status', 'op-internal-link-manager' ); ?></th>
							<th><?php esc_html_e( 'Source', 'op-internal-link-manager' ); ?></th>
							<th><?php esc_html_e( 'Insertions', 'op-internal-link-manager' ); ?></th>
							<th><?php esc_html_e( 'Last Keyword', 'op-internal-link-manager' ); ?></th>
							<th><?php esc_html_e( 'Last Inserted', 'op-internal-link-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'op-internal-link-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $locations as $location ) : ?>
							<?php
							$post_title = $location->post_title ? $location->post_title : sprintf( __( '(Post #%d)', 'op-internal-link-manager' ), absint( $location->post_id ) );
							$view_link = get_permalink( $location->post_id );
							$edit_link = get_edit_post_link( $location->post_id );
							?>
							<tr>
								<td><?php echo esc_html( $post_title ); ?></td>
								<td><?php echo esc_html( $location->post_type && $location->post_status ? $location->post_type . ' / ' . $location->post_status : __( 'Unknown', 'op-internal-link-manager' ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $location->source_type ) ); ?></td>
								<td><?php echo absint( $location->insert_count ); ?></td>
								<td><?php echo esc_html( $location->last_keyword ); ?></td>
								<td><?php echo esc_html( $location->last_inserted_at ); ?></td>
								<td>
									<?php if ( $view_link ) : ?>
										<a href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'op-internal-link-manager' ); ?></a>
									<?php endif; ?>
									<?php if ( $view_link && $edit_link ) : ?>
										<span aria-hidden="true"> | </span>
									<?php endif; ?>
									<?php if ( $edit_link ) : ?>
										<a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit', 'op-internal-link-manager' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								printf(
									esc_html( _n( '%s item', '%s items', $total_items, 'op-internal-link-manager' ) ),
									number_format_i18n( $total_items )
								);
								?>
							</span>
							<?php if ( $current_page > 1 ) : ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( 'location_page', $current_page - 1, $base_url ) . '#oilm-insertion-locations' ); ?>"><?php esc_html_e( 'Previous', 'op-internal-link-manager' ); ?></a>
							<?php endif; ?>
							<span class="paging-input">
								<?php
								printf(
									esc_html__( '%1$s of %2$s', 'op-internal-link-manager' ),
									number_format_i18n( $current_page ),
									number_format_i18n( $total_pages )
								);
								?>
							</span>
							<?php if ( $current_page < $total_pages ) : ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( 'location_page', $current_page + 1, $base_url ) . '#oilm-insertion-locations' ); ?>"><?php esc_html_e( 'Next', 'op-internal-link-manager' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_form() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'oilm_rules';
		
		$rule = array(
			'id' => 0,
			'keywords' => '',
			'url' => '',
			'title_attr' => '',
			'is_exact_match' => 0,
			'open_new_tab' => 0,
			'is_nofollow' => 0,
			'is_sponsored' => 0,
			'max_links_per_page' => 0,
			'max_uses_per_keyword' => 0,
			'is_active' => 1,
			'priority' => 10,
		);

		if ( isset( $_GET['rule'] ) ) {
			$rule_id = absint( $_GET['rule'] );
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'oilm_edit_rule_' . $rule_id ) ) {
				die( 'Security check failed' );
			}
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $rule_id ), ARRAY_A );
			if ( $existing ) {
				$rule = $existing;
			}
		}

		?>
		<div class="wrap oilm-modern-wrap">
			<h1><?php echo esc_html( $rule['id'] ? __( 'Edit Rule', 'op-internal-link-manager' ) : __( 'Add New Rule', 'op-internal-link-manager' ) ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="oilm-settings-card oilm-rule-form">
				<input type="hidden" name="action" value="oilm_save_rule">
				<input type="hidden" name="rule_id" value="<?php echo absint( $rule['id'] ); ?>">
				<?php wp_nonce_field( 'oilm_save_rule_nonce' ); ?>
				<h2><?php esc_html_e( 'Rule Details', 'op-internal-link-manager' ); ?></h2>
				<p><?php esc_html_e( 'Choose the keywords to detect and the internal page they should point to.', 'op-internal-link-manager' ); ?></p>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="keywords"><?php esc_html_e( 'Keywords', 'op-internal-link-manager' ); ?></label></th>
						<td>
							<textarea name="keywords" id="keywords" rows="3" class="regular-text" required><?php echo esc_textarea( $rule['keywords'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Comma-separated list of keywords to link.', 'op-internal-link-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oilm-link-search"><?php esc_html_e( 'Internal Link Search', 'op-internal-link-manager' ); ?></label></th>
						<td>
							<div class="oilm-url-field-wrapper">
								<select id="oilm-link-search" class="regular-text">
									<option></option>
								</select>
								<p class="description"><?php esc_html_e( 'Start typing a post, page, or public content title to fill the target URL.', 'op-internal-link-manager' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="url"><?php esc_html_e( 'Target URL', 'op-internal-link-manager' ); ?></label></th>
						<td>
							<input name="url" id="url" type="url" value="<?php echo esc_attr( $rule['url'] ); ?>" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'You can select internal content above or paste a URL manually.', 'op-internal-link-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="title_attr"><?php esc_html_e( 'Link Title Attribute', 'op-internal-link-manager' ); ?></label></th>
						<td>
							<input name="title_attr" id="title_attr" type="text" value="<?php echo esc_attr( $rule['title_attr'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Settings', 'op-internal-link-manager' ); ?></th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="open_new_tab" value="1" <?php checked( 1, $rule['open_new_tab'] ); ?>> <?php esc_html_e( 'Open in new tab (target="_blank")', 'op-internal-link-manager' ); ?></label><br>
								<label><input type="checkbox" name="is_nofollow" value="1" <?php checked( 1, $rule['is_nofollow'] ); ?>> <?php esc_html_e( 'Add nofollow (rel="nofollow")', 'op-internal-link-manager' ); ?></label><br>
								<label><input type="checkbox" name="is_sponsored" value="1" <?php checked( 1, $rule['is_sponsored'] ); ?>> <?php esc_html_e( 'Add sponsored (rel="sponsored")', 'op-internal-link-manager' ); ?></label><br>
								<label><input type="checkbox" name="is_exact_match" value="1" <?php checked( 1, $rule['is_exact_match'] ); ?>> <?php esc_html_e( 'Exact match only (case sensitive, whole word)', 'op-internal-link-manager' ); ?></label><br>
								<label><input type="checkbox" name="is_active" value="1" <?php checked( 1, $rule['is_active'] ); ?>> <?php esc_html_e( 'Enable this rule', 'op-internal-link-manager' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="max_links_per_page"><?php esc_html_e( 'Max links per page', 'op-internal-link-manager' ); ?></label></th>
						<td>
							<input name="max_links_per_page" id="max_links_per_page" type="number" value="<?php echo esc_attr( $rule['max_links_per_page'] ); ?>" min="0" />
							<p class="description"><?php esc_html_e( '0 for unlimited.', 'op-internal-link-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="max_uses_per_keyword"><?php esc_html_e( 'Max uses per keyword', 'op-internal-link-manager' ); ?></label></th>
						<td>
							<input name="max_uses_per_keyword" id="max_uses_per_keyword" type="number" value="<?php echo esc_attr( $rule['max_uses_per_keyword'] ); ?>" min="0" />
							<p class="description"><?php esc_html_e( '0 for unlimited.', 'op-internal-link-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="priority"><?php esc_html_e( 'Priority', 'op-internal-link-manager' ); ?></label></th>
						<td>
							<input name="priority" id="priority" type="number" value="<?php echo esc_attr( $rule['priority'] ); ?>" min="0" />
							<p class="description"><?php esc_html_e( 'Lower number runs first.', 'op-internal-link-manager' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Rule', 'op-internal-link-manager' ) ); ?>
			</form>
			<?php
			if ( ! empty( $rule['id'] ) ) {
				$this->render_insertion_locations( $rule );
			}
			?>
		</div>
		<?php
	}

	public function save_rule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'oilm_save_rule_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'oilm_rules';

		$data = array(
			'keywords' => sanitize_textarea_field( $_POST['keywords'] ),
			'url' => esc_url_raw( $_POST['url'] ),
			'title_attr' => sanitize_text_field( $_POST['title_attr'] ),
			'is_exact_match' => isset( $_POST['is_exact_match'] ) ? 1 : 0,
			'open_new_tab' => isset( $_POST['open_new_tab'] ) ? 1 : 0,
			'is_nofollow' => isset( $_POST['is_nofollow'] ) ? 1 : 0,
			'is_sponsored' => isset( $_POST['is_sponsored'] ) ? 1 : 0,
			'max_links_per_page' => absint( $_POST['max_links_per_page'] ),
			'max_uses_per_keyword' => absint( $_POST['max_uses_per_keyword'] ),
			'is_active' => isset( $_POST['is_active'] ) ? 1 : 0,
			'priority' => absint( $_POST['priority'] ),
		);

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		if ( $rule_id > 0 ) {
			$wpdb->update( $table_name, $data, array( 'id' => $rule_id ) );
		} else {
			$wpdb->insert( $table_name, $data );
		}

		delete_transient( 'oilm_active_rules' );
		
		wp_redirect( admin_url( 'admin.php?page=op-internal-link-manager&message=saved' ) );
		exit;
	}

	public function delete_rule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$rule_id = isset( $_GET['rule'] ) ? absint( $_GET['rule'] ) : 0;
		if ( $rule_id && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'oilm_delete_rule_' . $rule_id ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'oilm_rules';
			$locations_table_name = $wpdb->prefix . 'oilm_insertion_locations';
			$wpdb->delete( $table_name, array( 'id' => $rule_id ) );
			$wpdb->delete( $locations_table_name, array( 'rule_id' => $rule_id ) );
			delete_transient( 'oilm_active_rules' );
		}
		
		wp_redirect( admin_url( 'admin.php?page=op-internal-link-manager&message=deleted' ) );
		exit;
	}

	public function search_links() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(), 403 );
		}

		check_ajax_referer( 'oilm_admin_nonce', 'nonce' );

		$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		if ( strlen( $query ) < 2 ) {
			wp_send_json_success( array() );
		}

		$links = array();
		$search_query = new WP_Query(
			array(
				's'                   => $query,
				'post_type'           => $this->get_searchable_post_types(),
				'post_status'         => array( 'publish', 'private' ),
				'posts_per_page'      => 20,
				'orderby'             => 'relevance',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		foreach ( $search_query->posts as $post ) {
			$permalink = get_permalink( $post );

			if ( ! $permalink ) {
				continue;
			}

			$post_type = get_post_type_object( $post->post_type );
			$post_type_label = $post_type ? $post_type->labels->singular_name : $post->post_type;

			$links[] = array(
				'id'   => esc_url_raw( $permalink ),
				'text' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'url'  => esc_url_raw( $permalink ),
				'info' => sprintf(
					'%1$s - %2$s',
					$post_type_label,
					get_the_date( '', $post )
				),
			);
		}

		wp_reset_postdata();

		wp_send_json_success( $links );
	}

	private function get_searchable_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		unset( $post_types['attachment'] );

		return array_values( $post_types );
	}
}
