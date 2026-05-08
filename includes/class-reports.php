<?php

class OILM_Reports {

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'oilm_rules';

		// Get basic stats from rules table
		$rules = $wpdb->get_results( "SELECT id, keywords, url, insert_count, last_inserted_at FROM $table_name WHERE is_active = 1 ORDER BY insert_count DESC LIMIT 50" );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>Below is a lightweight overview of rule insertions across your site.</p>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Rule Keywords</th>
						<th>Target URL</th>
						<th>Total Insertions</th>
						<th>Last Inserted Date</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr><td colspan="4">No active rules or stats available yet.</td></tr>
					<?php else : ?>
						<?php foreach ( $rules as $rule ) : ?>
							<tr>
								<td><?php echo esc_html( $rule->keywords ); ?></td>
								<td><a href="<?php echo esc_url( $rule->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $rule->url ); ?></a></td>
								<td>
									<?php
									$locations_url = add_query_arg(
										array(
											'page'     => 'op-internal-link-manager',
											'action'   => 'edit',
											'rule'     => absint( $rule->id ),
											'_wpnonce' => wp_create_nonce( 'oilm_edit_rule_' . $rule->id ),
										),
										admin_url( 'admin.php' )
									);
									?>
									<a href="<?php echo esc_url( $locations_url ); ?>#oilm-insertion-locations"><?php echo absint( $rule->insert_count ); ?></a>
								</td>
								<td><?php echo $rule->last_inserted_at ? esc_html( $rule->last_inserted_at ) : 'Never'; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
