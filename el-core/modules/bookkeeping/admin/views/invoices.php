<?php
/**
 * Bookkeeping — Invoices Tab
 *
 * AI-powered invoice upload (primary) + manual entry form (secondary).
 *
 * @var EL_Bookkeeping_Module $module
 * @var int                   $tax_year
 * @var array                 $prefetch_invoices
 * @var array                 $prefetch_clients_for_invoices
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$invoices = $prefetch_invoices;
$clients  = $prefetch_clients_for_invoices;

$status_labels = [
	'unpaid'  => __( 'Unpaid',  'el-core' ),
	'paid'    => __( 'Paid',    'el-core' ),
	'partial' => __( 'Partial', 'el-core' ),
	'void'    => __( 'Void',    'el-core' ),
];

$withholding_types = [
	''               => __( '— None —', 'el-core' ),
	'CA Withholding' => __( 'CA Withholding (7%)', 'el-core' ),
	'Federal'        => __( 'Federal', 'el-core' ),
	'Other'          => __( 'Other', 'el-core' ),
];

// Calculate totals
$total_invoiced = array_sum( array_map( fn( $inv ) => (float) $inv->amount, $invoices ) );
$total_paid     = array_sum( array_map( fn( $inv ) => $inv->status === 'paid' ? (float) $inv->amount : 0, $invoices ) );
$total_unpaid   = array_sum( array_map( fn( $inv ) => $inv->status === 'unpaid' ? (float) $inv->amount : 0, $invoices ) );

// Build client lookup for JS
$client_options = [];
foreach ( $clients as $c ) {
	$client_options[] = [
		'id'    => (int) $c->id,
		'name'  => $c->client_name,
		'short' => $c->short_name ?: '',
	];
}
?>

<div class="el-bk-tab-header">
	<div class="el-bk-tab-header-left">
		<h2><?php echo esc_html( sprintf( __( 'Invoices — %d', 'el-core' ), $tax_year ) ); ?></h2>
		<p class="description" style="margin:4px 0 0;">
			<?php esc_html_e( 'Upload invoice images/PDFs for AI extraction, or add manually.', 'el-core' ); ?>
		</p>
	</div>
</div>

<!-- Summary Cards -->
<div class="el-bk-invoice-summary-cards">
	<div class="el-bk-invoice-summary-card">
		<div class="el-bk-invoice-summary-label"><?php esc_html_e( 'Total Invoiced', 'el-core' ); ?></div>
		<div class="el-bk-invoice-summary-amount">$<?php echo esc_html( number_format( $total_invoiced, 2 ) ); ?></div>
	</div>
	<div class="el-bk-invoice-summary-card el-bk-invoice-summary-card--paid">
		<div class="el-bk-invoice-summary-label"><?php esc_html_e( 'Paid', 'el-core' ); ?></div>
		<div class="el-bk-invoice-summary-amount">$<?php echo esc_html( number_format( $total_paid, 2 ) ); ?></div>
	</div>
	<div class="el-bk-invoice-summary-card el-bk-invoice-summary-card--unpaid">
		<div class="el-bk-invoice-summary-label"><?php esc_html_e( 'Unpaid', 'el-core' ); ?></div>
		<div class="el-bk-invoice-summary-amount">$<?php echo esc_html( number_format( $total_unpaid, 2 ) ); ?></div>
	</div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     UPLOAD ZONE (Primary Entry Method)
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="el-bk-card el-bk-upload-zone" id="el-bk-invoice-upload-zone">
	<div class="el-bk-upload-icon">📄</div>
	<p><?php esc_html_e( 'Drag and drop invoices here, or click to browse', 'el-core' ); ?></p>
	<p class="el-bk-hint"><?php esc_html_e( 'Accepts: JPG, PNG, PDF — max 10 MB. AI extracts invoice number, date, amount, and client.', 'el-core' ); ?></p>
	<input type="file" id="el-bk-invoice-file-input" accept=".jpg,.jpeg,.png,.pdf" multiple style="display:none;">
	<button class="el-btn el-btn-primary" id="el-bk-invoice-browse-btn">
		<?php esc_html_e( 'Browse Files', 'el-core' ); ?>
	</button>
	<button class="el-btn el-btn-outline" id="el-bk-invoice-manual-btn" style="margin-left:8px;">
		<?php esc_html_e( 'Add Manually', 'el-core' ); ?>
	</button>
	<div id="el-bk-invoice-upload-status" style="margin-top:10px;font-size:13px;min-height:18px;"></div>
</div>

<!-- Review Queue — populated by JS after each successful upload -->
<div id="el-bk-invoice-review-queue" class="el-bk-invoice-review-queue"></div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MANUAL ENTRY FORM (Secondary — also used for editing)
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="el-bk-invoice-form" class="el-bk-card el-bk-invoice-form-card" style="display:none;">
	<h3 id="el-bk-invoice-form-title"><?php esc_html_e( 'Add Invoice', 'el-core' ); ?></h3>
	<input type="hidden" id="el-bk-invoice-id" value="">
	<input type="hidden" id="el-bk-invoice-doc-attachment-id" value="">
	<input type="hidden" id="el-bk-invoice-ai-extracted-data" value="">

	<div class="el-bk-invoice-form-grid">

		<!-- Row 1: Client + Invoice Number + Date -->
		<div class="el-bk-invoice-form-col">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Client', 'el-core' ); ?> <span class="required">*</span>
				<select id="el-bk-invoice-client-id" class="el-select">
					<option value=""><?php esc_html_e( '— Select Client —', 'el-core' ); ?></option>
					<?php foreach ( $clients as $c ) : ?>
						<option value="<?php echo esc_attr( $c->id ); ?>">
							<?php echo esc_html( $c->client_name . ( $c->short_name ? ' (' . $c->short_name . ')' : '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<div class="el-bk-invoice-form-col">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Invoice Number / PO', 'el-core' ); ?>
				<input type="text" id="el-bk-invoice-number" class="el-input el-bk-voice-input"
					placeholder="<?php esc_attr_e( 'e.g. ELS-2024-001 or PO#12345', 'el-core' ); ?>">
			</label>
		</div>

		<div class="el-bk-invoice-form-col">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Invoice Date', 'el-core' ); ?> <span class="required">*</span>
				<input type="date" id="el-bk-invoice-date" class="el-input el-bk-voice-input"
					value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
			</label>
		</div>

		<!-- Row 2: Amount + Status -->
		<div class="el-bk-invoice-form-col">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Invoice Amount ($)', 'el-core' ); ?> <span class="required">*</span>
				<input type="text" id="el-bk-invoice-amount" class="el-input el-bk-voice-input"
					placeholder="<?php esc_attr_e( '0.00', 'el-core' ); ?>"
					inputmode="decimal">
			</label>
		</div>

		<div class="el-bk-invoice-form-col">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Status', 'el-core' ); ?>
				<select id="el-bk-invoice-status" class="el-select">
					<?php foreach ( $status_labels as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<!-- Row 3: Withholding -->
		<div class="el-bk-invoice-form-col" id="el-bk-invoice-withholding-row">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Withholding Type', 'el-core' ); ?>
				<select id="el-bk-invoice-withholding-type" class="el-select">
					<?php foreach ( $withholding_types as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<div class="el-bk-invoice-form-col" id="el-bk-invoice-withholding-amount-row" style="display:none;">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Withholding Amount ($)', 'el-core' ); ?>
				<input type="text" id="el-bk-invoice-withholding-amount" class="el-input el-bk-voice-input"
					placeholder="<?php esc_attr_e( '0.00', 'el-core' ); ?>"
					inputmode="decimal">
			</label>
		</div>

		<!-- Row 4: Description (full width) -->
		<div class="el-bk-invoice-form-col el-bk-invoice-form-col--wide">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Description / Work Performed', 'el-core' ); ?>
				<textarea id="el-bk-invoice-description" class="el-textarea el-bk-voice-input" rows="3"
					placeholder="<?php esc_attr_e( 'Professional development training, coaching sessions, curriculum development…', 'el-core' ); ?>"></textarea>
			</label>
		</div>

		<!-- Row 5: Document Upload -->
		<div class="el-bk-invoice-form-col" id="el-bk-invoice-doc-upload-row">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Invoice Document (PDF, JPG, PNG)', 'el-core' ); ?>
				<input type="file" id="el-bk-invoice-doc-file" class="el-input"
					accept=".pdf,.jpg,.jpeg,.png">
			</label>
			<div id="el-bk-invoice-doc-current" class="el-bk-invoice-doc-current" style="display:none;"></div>
		</div>

		<!-- Row 6: Notes (full width) -->
		<div class="el-bk-invoice-form-col el-bk-invoice-form-col--wide">
			<label class="el-bk-form-label">
				<?php esc_html_e( 'Notes', 'el-core' ); ?>
				<textarea id="el-bk-invoice-notes" class="el-textarea el-bk-voice-input" rows="2"
					placeholder="<?php esc_attr_e( 'Payment terms, follow-up notes…', 'el-core' ); ?>"></textarea>
			</label>
		</div>

	</div><!-- .el-bk-invoice-form-grid -->

	<div class="el-bk-form-actions">
		<button class="el-btn el-btn-primary" id="el-bk-save-invoice-btn">
			<?php esc_html_e( 'Save Invoice', 'el-core' ); ?>
		</button>
		<button class="el-btn el-btn-outline" id="el-bk-save-invoice-add-btn">
			<?php esc_html_e( 'Save & Add Another', 'el-core' ); ?>
		</button>
		<button class="el-btn el-btn-outline" id="el-bk-cancel-invoice-btn">
			<?php esc_html_e( 'Cancel', 'el-core' ); ?>
		</button>
	</div>
</div><!-- #el-bk-invoice-form -->

<!-- Action Row (filters) -->
<div class="el-bk-action-row" style="margin-bottom:20px; margin-top:24px;">
	<input type="text" id="el-bk-invoice-search" class="el-input"
		placeholder="<?php esc_attr_e( 'Search invoices…', 'el-core' ); ?>"
		style="max-width:260px;">
	<label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
		<?php esc_html_e( 'Client:', 'el-core' ); ?>
		<select id="el-bk-invoice-client-filter" class="el-select" style="min-width:160px;">
			<option value=""><?php esc_html_e( 'All Clients', 'el-core' ); ?></option>
			<?php foreach ( $clients as $c ) : ?>
				<option value="<?php echo esc_attr( $c->id ); ?>">
					<?php echo esc_html( $c->short_name ?: $c->client_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>
	<label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
		<?php esc_html_e( 'Status:', 'el-core' ); ?>
		<select id="el-bk-invoice-status-filter" class="el-select" style="min-width:120px;">
			<option value=""><?php esc_html_e( 'All', 'el-core' ); ?></option>
			<?php foreach ( $status_labels as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
</div>

<!-- Invoice List Table -->
<?php if ( empty( $invoices ) ) : ?>
	<?php echo EL_Admin_UI::notice( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		'message' => __( 'No invoices yet. Upload an invoice image above or click "Add Manually".', 'el-core' ),
		'type'    => 'info',
	] ); ?>
<?php else : ?>

<div class="el-bk-table-wrap" id="el-bk-invoice-table-wrap">
	<table class="el-bk-invoice-table widefat" id="el-bk-invoice-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Invoice #', 'el-core' ); ?></th>
				<th><?php esc_html_e( 'Client', 'el-core' ); ?></th>
				<th><?php esc_html_e( 'Date', 'el-core' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
				<th><?php esc_html_e( 'Status', 'el-core' ); ?></th>
				<th><?php esc_html_e( 'Withholding', 'el-core' ); ?></th>
				<th><?php esc_html_e( 'Description', 'el-core' ); ?></th>
				<th><?php esc_html_e( 'Document', 'el-core' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $invoices as $inv ) :
				$doc_url      = $inv->document_attachment_id ? wp_get_attachment_url( (int) $inv->document_attachment_id ) : '';
				$status_class = 'el-bk-invoice-status--' . esc_attr( $inv->status );
			?>
			<tr class="el-bk-invoice-row"
				data-id="<?php echo esc_attr( $inv->id ); ?>"
				data-client-id="<?php echo esc_attr( $inv->client_id ); ?>"
				data-status="<?php echo esc_attr( $inv->status ); ?>"
				data-search="<?php echo esc_attr( strtolower( $inv->invoice_number . ' ' . $inv->client_name . ' ' . $inv->description ) ); ?>">
				<td>
					<strong><?php echo esc_html( $inv->invoice_number ?: '—' ); ?></strong>
				</td>
				<td>
					<?php echo esc_html( $inv->client_name ); ?>
					<?php if ( $inv->short_name ) : ?>
						<br><span class="el-bk-muted"><?php echo esc_html( $inv->short_name ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $inv->invoice_date ) ) ); ?></td>
				<td class="el-bk-amount">$<?php echo esc_html( number_format( (float) $inv->amount, 2 ) ); ?></td>
				<td>
					<span class="el-bk-status-badge <?php echo esc_attr( $status_class ); ?>">
						<?php echo esc_html( $status_labels[ $inv->status ] ?? $inv->status ); ?>
					</span>
				</td>
				<td>
					<?php if ( (float) $inv->withholding_amount > 0 ) : ?>
						$<?php echo esc_html( number_format( (float) $inv->withholding_amount, 2 ) ); ?>
						<br><span class="el-bk-muted"><?php echo esc_html( $inv->withholding_type ); ?></span>
					<?php else : ?>
						<span class="el-bk-muted">—</span>
					<?php endif; ?>
				</td>
				<td class="el-bk-description-cell">
					<?php echo esc_html( wp_trim_words( $inv->description, 10, '…' ) ); ?>
				</td>
				<td>
					<?php if ( $doc_url ) : ?>
						<a href="<?php echo esc_url( $doc_url ); ?>" target="_blank" class="el-bk-doc-link">
							<?php esc_html_e( 'View', 'el-core' ); ?>
						</a>
					<?php else : ?>
						<span class="el-bk-muted">—</span>
					<?php endif; ?>
				</td>
				<td class="el-bk-actions">
					<button class="el-btn el-btn-outline el-bk-edit-invoice-btn"
						data-id="<?php echo esc_attr( $inv->id ); ?>"
						data-client-id="<?php echo esc_attr( $inv->client_id ); ?>"
						data-invoice-number="<?php echo esc_attr( $inv->invoice_number ); ?>"
						data-invoice-date="<?php echo esc_attr( $inv->invoice_date ); ?>"
						data-amount="<?php echo esc_attr( $inv->amount ); ?>"
						data-status="<?php echo esc_attr( $inv->status ); ?>"
						data-withholding-amount="<?php echo esc_attr( $inv->withholding_amount ); ?>"
						data-withholding-type="<?php echo esc_attr( $inv->withholding_type ); ?>"
						data-description="<?php echo esc_attr( $inv->description ); ?>"
						data-document-attachment-id="<?php echo esc_attr( $inv->document_attachment_id ); ?>"
						data-doc-url="<?php echo esc_attr( $doc_url ); ?>"
						data-notes="<?php echo esc_attr( $inv->notes ); ?>">
						<?php esc_html_e( 'Edit', 'el-core' ); ?>
					</button>
					<button class="el-btn el-btn-outline el-btn-danger el-bk-delete-invoice-btn"
						data-id="<?php echo esc_attr( $inv->id ); ?>"
						data-number="<?php echo esc_attr( $inv->invoice_number ); ?>">
						<?php esc_html_e( 'Delete', 'el-core' ); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr class="el-bk-total-row">
				<td colspan="3"><strong><?php esc_html_e( 'Total', 'el-core' ); ?></strong></td>
				<td class="el-bk-amount"><strong>$<?php echo esc_html( number_format( $total_invoiced, 2 ) ); ?></strong></td>
				<td colspan="5"></td>
			</tr>
		</tfoot>
	</table>
</div><!-- .el-bk-table-wrap -->

<?php endif; ?>

<!-- Client options for JS -->
<script>
var elBkInvoiceClients = <?php echo wp_json_encode( $client_options ); ?>;
</script>
