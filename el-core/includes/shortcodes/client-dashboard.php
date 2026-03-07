<?php
/**
 * Shortcode: [el_client_dashboard]
 *
 * Universal client home base — shows all projects and invoices for the logged-in
 * user's organization(s). Module-agnostic: project cards adapt to whichever
 * modules are active (Expand Site, future Training, future Expand Partners).
 *
 * Usage: Place on any WordPress page with [el_client_dashboard]
 *
 * @version 1.27.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the shortcode. Called from el-core.php boot sequence.
 */
function el_register_client_dashboard_shortcode(): void {
	add_shortcode( 'el_client_dashboard', 'el_shortcode_client_dashboard' );
}

/**
 * Main shortcode handler.
 */
function el_shortcode_client_dashboard( $atts = [] ): string {
	ob_start();

	// ── Auth check ────────────────────────────────────────────────────────────
	if ( ! is_user_logged_in() ) {
		?>
		<div class="el-cd el-cd-notice el-cd-notice--warning">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
			<p><?php esc_html_e( 'Please log in to view your dashboard.', 'el-core' ); ?> <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in', 'el-core' ); ?></a></p>
		</div>
		<?php
		el_cd_styles();
		return ob_get_clean();
	}

	global $wpdb;
	$user_id = get_current_user_id();

	// ── Look up org IDs the current user belongs to via el_contacts ───────────
	$contacts_table = $wpdb->prefix . 'el_contacts';
	$org_ids = [];

	$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$contacts_table}'" );
	if ( $table_exists ) {
		$org_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT organization_id FROM {$contacts_table} WHERE user_id = %d AND organization_id IS NOT NULL",
			$user_id
		) );
		$org_ids = array_map( 'intval', $org_ids );
	}

	if ( empty( $org_ids ) ) {
		?>
		<div class="el-cd el-cd-notice el-cd-notice--info">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
			<p><?php esc_html_e( 'No client account is linked to your user. Please contact your project manager.', 'el-core' ); ?></p>
		</div>
		<?php
		el_cd_styles();
		return ob_get_clean();
	}

	// ── Load org info ─────────────────────────────────────────────────────────
	$orgs_table = $wpdb->prefix . 'el_organizations';
	$org_id_placeholders = implode( ',', array_fill( 0, count( $org_ids ), '%d' ) );
	$orgs = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, name FROM {$orgs_table} WHERE id IN ({$org_id_placeholders})",
		...$org_ids
	) );
	$org_names = [];
	foreach ( $orgs as $org ) {
		$org_names[ $org->id ] = $org->name;
	}

	// ── Load projects user is a stakeholder on ────────────────────────────────
	$projects      = [];
	$es_active     = el_core_module_active( 'expand-site' );
	$inv_active    = el_core_module_active( 'invoicing' );

	if ( $es_active ) {
		$projects_table     = $wpdb->prefix . 'el_es_projects';
		$stakeholders_table = $wpdb->prefix . 'el_es_stakeholders';

		$projects_tbl_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$projects_table}'" );
		if ( $projects_tbl_exists ) {
			// Projects where user is an explicit stakeholder
			$stakeholder_project_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT project_id FROM {$stakeholders_table} WHERE user_id = %d",
				$user_id
			) );
			$stakeholder_project_ids = array_map( 'intval', $stakeholder_project_ids );

			// Projects where user is the legacy client_user_id
			$legacy_project_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT id FROM {$projects_table} WHERE client_user_id = %d",
				$user_id
			) );
			$legacy_project_ids = array_map( 'intval', $legacy_project_ids );

			$all_project_ids = array_unique( array_merge( $stakeholder_project_ids, $legacy_project_ids ) );

			if ( ! empty( $all_project_ids ) ) {
				$id_placeholders = implode( ',', array_fill( 0, count( $all_project_ids ), '%d' ) );
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT p.*, s.role AS stakeholder_role
					 FROM {$projects_table} p
					 LEFT JOIN {$stakeholders_table} s ON s.project_id = p.id AND s.user_id = %d
					 WHERE p.id IN ({$id_placeholders})
					 ORDER BY p.current_stage DESC, p.created_at DESC",
					$user_id,
					...$all_project_ids
				) );
				foreach ( $rows as $row ) {
					$projects[] = (object) [
						'id'               => (int) $row->id,
						'name'             => $row->name,
						'module'           => 'expand-site',
						'current_stage'    => (int) $row->current_stage,
						'status'           => $row->status,
						'organization_id'  => (int) $row->organization_id,
						'stakeholder_role' => $row->stakeholder_role ?: ( (int) $row->decision_maker_id === $user_id ? 'decision_maker' : 'contributor' ),
					];
				}
			}
		}
	}

	// ── Load invoices grouped by project_id ───────────────────────────────────
	$invoices_by_project = [];
	$total_outstanding   = 0.0;
	$all_invoices        = [];

	if ( $inv_active ) {
		$inv_table = $wpdb->prefix . 'el_inv_invoices';
		$inv_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$inv_table}'" );
		if ( $inv_exists ) {
			$inv_id_placeholders = implode( ',', array_fill( 0, count( $org_ids ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, invoice_number, issue_date, due_date, total, balance_due, status, project_id
				 FROM {$inv_table}
				 WHERE organization_id IN ({$inv_id_placeholders}) AND status != 'draft'
				 ORDER BY issue_date DESC",
				...$org_ids
			) );
			foreach ( $rows as $row ) {
				$pid = (int) $row->project_id;
				if ( ! isset( $invoices_by_project[ $pid ] ) ) {
					$invoices_by_project[ $pid ] = [ 'balance' => 0.0, 'rows' => [] ];
				}
				$invoices_by_project[ $pid ]['balance']   += (float) $row->balance_due;
				$invoices_by_project[ $pid ]['rows'][]     = $row;
				$total_outstanding                         += (float) $row->balance_due;
				$all_invoices[]                             = $row;
			}
		}
	}

	// ── Detect attention items ────────────────────────────────────────────────
	$attention_items = [];

	if ( $es_active && ! empty( $projects ) ) {
		$def_table      = $wpdb->prefix . 'el_es_project_definition';
		$proposal_table = $wpdb->prefix . 'el_es_proposals';
		$def_exists     = $wpdb->get_var( "SHOW TABLES LIKE '{$def_table}'" );
		$prop_exists    = $wpdb->get_var( "SHOW TABLES LIKE '{$proposal_table}'" );

		foreach ( $projects as $project ) {
			if ( $def_exists ) {
				$review_status = $wpdb->get_var( $wpdb->prepare(
					"SELECT review_status FROM {$def_table} WHERE project_id = %d",
					$project->id
				) );
				if ( $review_status === 'pending_review' ) {
					$attention_items[] = [
						'type'       => 'definition',
						'label'      => sprintf( __( 'Review Definition for "%s"', 'el-core' ), esc_html( $project->name ) ),
						'project_id' => $project->id,
					];
				}
			}
			if ( $prop_exists ) {
				$sent_proposal = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$proposal_table} WHERE project_id = %d AND status = 'sent' LIMIT 1",
					$project->id
				) );
				if ( $sent_proposal ) {
					$attention_items[] = [
						'type'       => 'proposal',
						'label'      => sprintf( __( 'Review Proposal for "%s"', 'el-core' ), esc_html( $project->name ) ),
						'project_id' => $project->id,
					];
				}
			}
		}
	}

	if ( $inv_active ) {
		foreach ( $all_invoices as $inv ) {
			if ( $inv->status === 'overdue' ) {
				$attention_items[] = [
					'type'  => 'invoice',
					'label' => sprintf( __( 'Overdue invoice %s', 'el-core' ), esc_html( $inv->invoice_number ) ),
				];
			}
		}
	}

	// ── Find portal page URL (for project CTA links) ──────────────────────────
	$portal_page_url = el_cd_find_shortcode_page( 'el_expand_site_portal' );
	$invoices_page_url = el_cd_find_shortcode_page( 'el_client_invoices' );

	// ── Expand Site stage names ───────────────────────────────────────────────
	$stage_names = [
		1 => 'Discovery',
		2 => 'Site Definition',
		3 => 'Scope Lock',
		4 => 'Style Direction',
		5 => 'Site Architecture',
		6 => 'Content & Copy',
		7 => 'Build & QA',
		8 => 'Launch',
	];

	// ── Output ─────────────────────────────────────────────────────────────────
	el_cd_styles();
	?>
	<div class="el-cd">

		<?php // ── Attention Banner ──────────────────────────────────────────── ?>
		<?php if ( ! empty( $attention_items ) ) : ?>
		<div class="el-cd-banner el-cd-banner--attention">
			<div class="el-cd-banner__icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
			</div>
			<div class="el-cd-banner__body">
				<strong><?php printf( esc_html( _n( 'You have %d item that needs your attention:', 'You have %d items that need your attention:', count( $attention_items ), 'el-core' ) ), count( $attention_items ) ); ?></strong>
				<ul>
					<?php foreach ( $attention_items as $item ) : ?>
						<li><?php echo esc_html( $item['label'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php endif; ?>

		<?php // ── Projects Section ──────────────────────────────────────────── ?>
		<section class="el-cd-section">
			<h2 class="el-cd-section__heading">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
				<?php esc_html_e( 'Your Projects', 'el-core' ); ?>
			</h2>

			<?php if ( empty( $projects ) ) : ?>
				<div class="el-cd-empty">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
					<p><?php esc_html_e( 'No projects yet. Your project manager will add your first project soon.', 'el-core' ); ?></p>
				</div>
			<?php else : ?>
				<div class="el-cd-cards">
					<?php foreach ( $projects as $project ) :
						$is_dm        = ( $project->stakeholder_role === 'decision_maker' );
						$stage_num    = $project->current_stage;
						$stage_label  = $stage_names[ $stage_num ] ?? 'Stage ' . $stage_num;
						$proj_balance = $invoices_by_project[ $project->id ]['balance'] ?? 0.0;

						// Determine CTA
						$cta_label = __( 'View Project', 'el-core' );
						$cta_class = 'el-cd-btn el-cd-btn--primary';

						// Check if there's a pending review or proposal for this project
						foreach ( $attention_items as $item ) {
							if ( isset( $item['project_id'] ) && $item['project_id'] === $project->id ) {
								if ( $item['type'] === 'definition' ) {
									$cta_label = __( 'Review Definition', 'el-core' );
									$cta_class = 'el-cd-btn el-cd-btn--attention';
								} elseif ( $item['type'] === 'proposal' ) {
									$cta_label = __( 'Review Proposal', 'el-core' );
									$cta_class = 'el-cd-btn el-cd-btn--attention';
								}
								break;
							}
						}

						$cta_url = $portal_page_url
							? add_query_arg( 'project_id', $project->id, $portal_page_url )
							: '#';

						$status_map = [
							'active'    => [ 'label' => __( 'Active', 'el-core' ),    'mod' => 'active' ],
							'on_hold'   => [ 'label' => __( 'On Hold', 'el-core' ),   'mod' => 'hold' ],
							'complete'  => [ 'label' => __( 'Complete', 'el-core' ),  'mod' => 'complete' ],
							'cancelled' => [ 'label' => __( 'Cancelled', 'el-core' ), 'mod' => 'cancelled' ],
						];
						$status_info = $status_map[ $project->status ] ?? [ 'label' => ucfirst( $project->status ), 'mod' => 'active' ];
					?>
					<div class="el-cd-card">
						<div class="el-cd-card__header">
							<span class="el-cd-badge el-cd-badge--module">
								<?php if ( $project->module === 'expand-site' ) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
									<?php esc_html_e( 'Website Development', 'el-core' ); ?>
								<?php else : ?>
									<?php echo esc_html( ucwords( str_replace( '-', ' ', $project->module ) ) ); ?>
								<?php endif; ?>
							</span>
							<span class="el-cd-badge el-cd-badge--status el-cd-badge--status-<?php echo esc_attr( $status_info['mod'] ); ?>">
								<?php echo esc_html( $status_info['label'] ); ?>
							</span>
						</div>

						<h3 class="el-cd-card__title"><?php echo esc_html( $project->name ); ?></h3>

						<div class="el-cd-card__meta">
							<div class="el-cd-card__stage">
								<div class="el-cd-stage-pip">
									<?php for ( $i = 1; $i <= 8; $i++ ) :
										$pip_class = 'el-cd-stage-pip__dot';
										if ( $i < $stage_num )       $pip_class .= ' el-cd-stage-pip__dot--done';
										elseif ( $i === $stage_num ) $pip_class .= ' el-cd-stage-pip__dot--current';
										else                         $pip_class .= ' el-cd-stage-pip__dot--upcoming';
									?>
									<span class="<?php echo esc_attr( $pip_class ); ?>" title="<?php echo esc_attr( $stage_names[ $i ] ?? '' ); ?>"></span>
									<?php endfor; ?>
								</div>
								<span class="el-cd-card__stage-label">
									<?php printf( esc_html__( 'Stage %d: %s', 'el-core' ), $stage_num, esc_html( $stage_label ) ); ?>
								</span>
							</div>
							<div class="el-cd-card__right-meta">
								<?php if ( $proj_balance > 0 ) : ?>
									<span class="el-cd-card__balance">
										<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
										<?php echo esc_html( '$' . number_format( $proj_balance, 2 ) . ' due' ); ?>
									</span>
								<?php endif; ?>
								<span class="el-cd-badge el-cd-badge--role el-cd-badge--role-<?php echo $is_dm ? 'dm' : 'contributor'; ?>">
									<?php echo $is_dm ? esc_html__( 'Decision Maker', 'el-core' ) : esc_html__( 'Contributor', 'el-core' ); ?>
								</span>
							</div>
						</div>

						<div class="el-cd-card__footer">
							<a href="<?php echo esc_url( $cta_url ); ?>" class="<?php echo esc_attr( $cta_class ); ?>">
								<?php echo esc_html( $cta_label ); ?>
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
							</a>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<?php // ── Invoices Section ─────────────────────────────────────────── ?>
		<?php if ( $inv_active && ! empty( $all_invoices ) ) : ?>
		<section class="el-cd-section">
			<h2 class="el-cd-section__heading">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
				<?php esc_html_e( 'Invoices', 'el-core' ); ?>
			</h2>

			<?php if ( $total_outstanding > 0 ) : ?>
			<div class="el-cd-balance-callout">
				<span class="el-cd-balance-callout__label"><?php esc_html_e( 'Total Outstanding Balance', 'el-core' ); ?></span>
				<span class="el-cd-balance-callout__amount"><?php echo esc_html( '$' . number_format( $total_outstanding, 2 ) ); ?></span>
			</div>
			<?php endif; ?>

			<div class="el-cd-table-wrap">
				<table class="el-cd-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Invoice #', 'el-core' ); ?></th>
							<th><?php esc_html_e( 'Project', 'el-core' ); ?></th>
							<th><?php esc_html_e( 'Date', 'el-core' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
							<th><?php esc_html_e( 'Status', 'el-core' ); ?></th>
							<th><?php esc_html_e( 'Balance Due', 'el-core' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_invoices as $inv ) :
							$inv_project_name = '';
							foreach ( $projects as $p ) {
								if ( $p->id === (int) $inv->project_id ) {
									$inv_project_name = $p->name;
									break;
								}
							}

							$status_labels = [
								'sent'    => [ 'label' => __( 'Sent', 'el-core' ),    'mod' => 'sent' ],
								'viewed'  => [ 'label' => __( 'Viewed', 'el-core' ),  'mod' => 'viewed' ],
								'partial' => [ 'label' => __( 'Partial', 'el-core' ), 'mod' => 'partial' ],
								'paid'    => [ 'label' => __( 'Paid', 'el-core' ),    'mod' => 'paid' ],
								'overdue' => [ 'label' => __( 'Overdue', 'el-core' ), 'mod' => 'overdue' ],
							];
							$inv_status = $status_labels[ $inv->status ] ?? [ 'label' => ucfirst( $inv->status ), 'mod' => 'sent' ];

							$view_url = $invoices_page_url
								? add_query_arg( [ 'el_invoice_view' => 1, 'id' => $inv->id ], $invoices_page_url )
								: add_query_arg( [ 'el_invoice_view' => 1, 'id' => $inv->id ], get_permalink() );
						?>
						<tr>
							<td><strong><?php echo esc_html( $inv->invoice_number ); ?></strong></td>
							<td><?php echo esc_html( $inv_project_name ?: '—' ); ?></td>
							<td><?php echo esc_html( $inv->issue_date ? date_i18n( get_option( 'date_format' ), strtotime( $inv->issue_date ) ) : '—' ); ?></td>
							<td><?php echo esc_html( '$' . number_format( (float) $inv->total, 2 ) ); ?></td>
							<td><span class="el-cd-inv-status el-cd-inv-status--<?php echo esc_attr( $inv_status['mod'] ); ?>"><?php echo esc_html( $inv_status['label'] ); ?></span></td>
							<td><?php echo ( (float) $inv->balance_due > 0 ) ? '<strong>' . esc_html( '$' . number_format( (float) $inv->balance_due, 2 ) ) . '</strong>' : '<span class="el-cd-paid">—</span>'; ?></td>
							<td><a href="<?php echo esc_url( $view_url ); ?>" class="el-cd-btn el-cd-btn--sm"><?php esc_html_e( 'View', 'el-core' ); ?></a></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php endif; ?>

		<?php // ── Contributor note ──────────────────────────────────────────── ?>
		<?php
		$is_contributor_only = ! empty( $projects ) && ! in_array( 'decision_maker', array_column( (array) $projects, 'stakeholder_role' ), true );
		if ( $is_contributor_only ) :
		?>
		<p class="el-cd-contributor-note">
			<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
			<?php esc_html_e( 'Some actions (like approving proposals and making final decisions) are only available to the Decision Maker on your team.', 'el-core' ); ?>
		</p>
		<?php endif; ?>

	</div><!-- .el-cd -->
	<?php
	return ob_get_clean();
}

/**
 * Find the URL of a page that contains a given shortcode.
 * Searches up to 200 pages. Returns null if not found.
 */
function el_cd_find_shortcode_page( string $shortcode_tag ): ?string {
	global $wpdb;
	$like = '%[' . $shortcode_tag . '%';
	$page = $wpdb->get_row( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s LIMIT 1",
		$like
	) );
	return $page ? get_permalink( $page->ID ) : null;
}

/**
 * Output inline CSS for the dashboard. Called once per page load.
 */
function el_cd_styles(): void {
	static $printed = false;
	if ( $printed ) return;
	$printed = true;
	?>
	<style>
	/* ── EL Client Dashboard ── */
	.el-cd { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; max-width: 1100px; margin: 0 auto; padding: 0 16px 48px; }

	/* Notices */
	.el-cd-notice { display: flex; align-items: flex-start; gap: 12px; padding: 16px 20px; border-radius: 10px; margin-bottom: 24px; }
	.el-cd-notice--warning { background: #fffbeb; border: 1px solid #f59e0b; color: #92400e; }
	.el-cd-notice--info    { background: #eff6ff; border: 1px solid #3b82f6; color: #1e40af; }
	.el-cd-notice svg      { flex-shrink: 0; margin-top: 2px; }
	.el-cd-notice p        { margin: 0; }

	/* Attention Banner */
	.el-cd-banner { display: flex; gap: 14px; align-items: flex-start; padding: 16px 20px; border-radius: 10px; margin-bottom: 28px; background: #fffbeb; border: 1px solid #f59e0b; }
	.el-cd-banner--attention .el-cd-banner__icon { color: #d97706; flex-shrink: 0; margin-top: 2px; }
	.el-cd-banner__body strong { display: block; color: #92400e; margin-bottom: 6px; font-size: 0.9375rem; }
	.el-cd-banner__body ul { margin: 0; padding-left: 18px; }
	.el-cd-banner__body li { font-size: 0.875rem; color: #78350f; margin-bottom: 3px; }

	/* Sections */
	.el-cd-section { margin-bottom: 40px; }
	.el-cd-section__heading { display: flex; align-items: center; gap: 8px; font-size: 1.125rem; font-weight: 700; color: #0f172a; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
	.el-cd-section__heading svg { color: #6366f1; }

	/* Project cards grid */
	.el-cd-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }

	/* Individual card */
	.el-cd-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.06); transition: box-shadow .15s, border-color .15s; }
	.el-cd-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); border-color: #c7d2fe; }
	.el-cd-card__header { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
	.el-cd-card__title { margin: 0; font-size: 1.0625rem; font-weight: 700; color: #0f172a; line-height: 1.3; }
	.el-cd-card__meta { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
	.el-cd-card__stage { display: flex; flex-direction: column; gap: 5px; }
	.el-cd-card__stage-label { font-size: 0.8125rem; color: #475569; font-weight: 600; }
	.el-cd-card__right-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
	.el-cd-card__balance { font-size: 0.8125rem; color: #ef4444; font-weight: 600; display: flex; align-items: center; gap: 4px; }
	.el-cd-card__footer { margin-top: auto; }

	/* Stage pip (8 dots) */
	.el-cd-stage-pip { display: flex; gap: 5px; align-items: center; }
	.el-cd-stage-pip__dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
	.el-cd-stage-pip__dot--done     { background: #6366f1; }
	.el-cd-stage-pip__dot--current  { background: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.25); }
	.el-cd-stage-pip__dot--upcoming { background: #e2e8f0; }

	/* Badges */
	.el-cd-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
	.el-cd-badge--module { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
	.el-cd-badge--status-active    { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
	.el-cd-badge--status-hold      { background: #fef9c3; color: #a16207; border: 1px solid #fde68a; }
	.el-cd-badge--status-complete  { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
	.el-cd-badge--status-cancelled { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
	.el-cd-badge--role-dm          { background: #fdf4ff; color: #7e22ce; border: 1px solid #e9d5ff; }
	.el-cd-badge--role-contributor { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }

	/* Buttons */
	.el-cd-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: all .15s; cursor: pointer; border: none; }
	.el-cd-btn--primary   { background: #6366f1; color: #fff; }
	.el-cd-btn--primary:hover   { background: #4f46e5; color: #fff; }
	.el-cd-btn--attention { background: #f59e0b; color: #fff; }
	.el-cd-btn--attention:hover { background: #d97706; color: #fff; }
	.el-cd-btn--sm        { background: #f1f5f9; color: #334155; padding: 6px 12px; font-size: 0.8125rem; }
	.el-cd-btn--sm:hover  { background: #e2e8f0; color: #0f172a; }

	/* Empty state */
	.el-cd-empty { text-align: center; padding: 48px 24px; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; color: #94a3b8; }
	.el-cd-empty svg { margin-bottom: 12px; opacity: .5; }
	.el-cd-empty p { margin: 0; font-size: 0.9375rem; }

	/* Outstanding balance callout */
	.el-cd-balance-callout { display: flex; align-items: center; justify-content: space-between; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px; padding: 14px 20px; margin-bottom: 16px; }
	.el-cd-balance-callout__label { font-size: 0.9375rem; color: #7c2d12; font-weight: 600; }
	.el-cd-balance-callout__amount { font-size: 1.25rem; font-weight: 800; color: #c2410c; }

	/* Invoice table */
	.el-cd-table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid #e2e8f0; }
	.el-cd-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
	.el-cd-table th { background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; padding: 11px 14px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
	.el-cd-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
	.el-cd-table tr:last-child td { border-bottom: none; }
	.el-cd-table tr:hover td { background: #f8fafc; }
	.el-cd-inv-status { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
	.el-cd-inv-status--sent    { background: #dbeafe; color: #1e40af; }
	.el-cd-inv-status--viewed  { background: #e0e7ff; color: #3730a3; }
	.el-cd-inv-status--partial { background: #fef9c3; color: #a16207; }
	.el-cd-inv-status--paid    { background: #dcfce7; color: #15803d; }
	.el-cd-inv-status--overdue { background: #fee2e2; color: #991b1b; }
	.el-cd-paid { color: #94a3b8; }

	/* Contributor note */
	.el-cd-contributor-note { display: flex; align-items: center; gap: 8px; font-size: 0.8125rem; color: #64748b; margin-top: 8px; }
	.el-cd-contributor-note svg { flex-shrink: 0; color: #94a3b8; }

	/* Responsive */
	@media (max-width: 640px) {
		.el-cd-cards { grid-template-columns: 1fr; }
		.el-cd-table th, .el-cd-table td { padding: 9px 10px; }
		.el-cd-balance-callout { flex-direction: column; gap: 4px; }
	}
	</style>
	<?php
}
