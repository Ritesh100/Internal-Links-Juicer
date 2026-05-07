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
				return absint( $item['insert_count'] ) . ' uses';
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

		$this->items = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT $per_page OFFSET $offset", ARRAY_A );
		
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
			if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'oilm_edit_rule_' . $rule_id ) ) {
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
			$wpdb->delete( $table_name, array( 'id' => $rule_id ) );
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

		if ( ! function_exists( 'wp_link_query' ) ) {
			require_once ABSPATH . 'wp-admin/includes/internal-linking.php';
		}

		$results = wp_link_query(
			array(
				's'         => $query,
				'post_type' => $this->get_searchable_post_types(),
			)
		);

		$links = array();

		foreach ( $results as $result ) {
			if ( empty( $result['permalink'] ) ) {
				continue;
			}

			$links[] = array(
				'id'    => esc_url_raw( $result['permalink'] ),
				'text'  => html_entity_decode( $result['title'], ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'url'   => esc_url_raw( $result['permalink'] ),
				'info'  => isset( $result['info'] ) ? sanitize_text_field( $result['info'] ) : '',
			);
		}

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
