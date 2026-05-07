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
			<h1><?php echo $rule['id'] ? 'Edit Rule' : 'Add New Rule'; ?></h1>
			<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
				<input type="hidden" name="action" value="oilm_save_rule">
				<input type="hidden" name="rule_id" value="<?php echo absint( $rule['id'] ); ?>">
				<?php wp_nonce_field( 'oilm_save_rule_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="keywords">Keywords</label></th>
						<td>
							<textarea name="keywords" id="keywords" rows="3" class="regular-text" required><?php echo esc_textarea( $rule['keywords'] ); ?></textarea>
							<p class="description">Comma-separated list of keywords to link.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="url">Target URL</label></th>
						<td>
							<input name="url" id="url" type="url" value="<?php echo esc_attr( $rule['url'] ); ?>" class="regular-text" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="title_attr">Link Title Attribute</label></th>
						<td>
							<input name="title_attr" id="title_attr" type="text" value="<?php echo esc_attr( $rule['title_attr'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">Settings</th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="open_new_tab" value="1" <?php checked( 1, $rule['open_new_tab'] ); ?>> Open in new tab (target="_blank")</label><br>
								<label><input type="checkbox" name="is_nofollow" value="1" <?php checked( 1, $rule['is_nofollow'] ); ?>> Add nofollow (rel="nofollow")</label><br>
								<label><input type="checkbox" name="is_sponsored" value="1" <?php checked( 1, $rule['is_sponsored'] ); ?>> Add sponsored (rel="sponsored")</label><br>
								<label><input type="checkbox" name="is_exact_match" value="1" <?php checked( 1, $rule['is_exact_match'] ); ?>> Exact match only (case sensitive, whole word)</label><br>
								<label><input type="checkbox" name="is_active" value="1" <?php checked( 1, $rule['is_active'] ); ?>> Enable this rule</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="max_links_per_page">Max links per page</label></th>
						<td>
							<input name="max_links_per_page" id="max_links_per_page" type="number" value="<?php echo esc_attr( $rule['max_links_per_page'] ); ?>" min="0" />
							<p class="description">0 for unlimited.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="max_uses_per_keyword">Max uses per keyword</label></th>
						<td>
							<input name="max_uses_per_keyword" id="max_uses_per_keyword" type="number" value="<?php echo esc_attr( $rule['max_uses_per_keyword'] ); ?>" min="0" />
							<p class="description">0 for unlimited.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="priority">Priority</label></th>
						<td>
							<input name="priority" id="priority" type="number" value="<?php echo esc_attr( $rule['priority'] ); ?>" min="0" />
							<p class="description">Lower number runs first.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save Rule' ); ?>
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
}
