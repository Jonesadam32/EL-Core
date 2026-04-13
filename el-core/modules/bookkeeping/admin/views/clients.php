<?php
/**
 * Bookkeeping — Clients / 1099-NEC Tab
 *
 * Tracks clients who PAY Fred (1099-NEC issuers).
 * Completely separate from el_bk_contractors (people Fred PAYS).
 *
 * @var EL_Bookkeeping_Module $module
 * @var int                   $tax_year
 * @var array                 $prefetch_clients
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$clients = $prefetch_clients;

$contract_types = [
    'Consulting',
    'Training',
    'Curriculum Development',
    'Coaching',
    'Facilitation',
    'Web Design/License',
    'Other',
];

$status_labels = [
    'active'    => __( 'Active',    'el-core' ),
    'inactive'  => __( 'Inactive',  'el-core' ),
    'completed' => __( 'Completed', 'el-core' ),
];
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php esc_html_e( 'Clients / 1099-NEC', 'el-core' ); ?></h2>
        <p class="description" style="margin:4px 0 0;">
            <?php esc_html_e( 'Organizations that pay Fred — 1099-NEC issuers. (Separate from Contractors, who Fred pays.)', 'el-core' ); ?>
        </p>
    </div>
</div>

<!-- ── Annual Income Summary ─────────────────────────────────────── -->
<div class="el-bk-annual-summary-section">
    <div class="el-bk-section-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <h3 style="margin:0;"><?php echo esc_html( sprintf( __( '%d Income Summary', 'el-core' ), $tax_year ) ); ?></h3>
        <button class="el-btn el-btn-outline" id="el-bk-refresh-summary-btn">
            <?php esc_html_e( 'Refresh', 'el-core' ); ?>
        </button>
    </div>
    <div id="el-bk-annual-summary-table-wrap">
        <p class="el-bk-loading"><?php esc_html_e( 'Loading summary…', 'el-core' ); ?></p>
    </div>
</div>

<!-- Action row -->
<div class="el-bk-action-row" style="margin-bottom:20px;">
    <button class="el-btn el-btn-primary" id="el-bk-add-client-btn">
        <?php esc_html_e( '+ Add Client', 'el-core' ); ?>
    </button>
    <input type="text" id="el-bk-client-search" class="el-input"
        placeholder="<?php esc_attr_e( 'Search clients…', 'el-core' ); ?>"
        style="max-width:260px;">
    <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
        <?php esc_html_e( 'Status:', 'el-core' ); ?>
        <select id="el-bk-client-status-filter" class="el-select" style="min-width:120px;">
            <option value=""><?php esc_html_e( 'All', 'el-core' ); ?></option>
            <option value="active"><?php esc_html_e( 'Active', 'el-core' ); ?></option>
            <option value="inactive"><?php esc_html_e( 'Inactive', 'el-core' ); ?></option>
            <option value="completed"><?php esc_html_e( 'Completed', 'el-core' ); ?></option>
        </select>
    </label>
</div>

<!-- ── Section B: Client Entry / Edit Form ─────────────────────────────── -->
<div id="el-bk-client-form" class="el-bk-card el-bk-client-form-card" style="display:none;">
    <h3 id="el-bk-client-form-title"><?php esc_html_e( 'Add Client', 'el-core' ); ?></h3>
    <input type="hidden" id="el-bk-client-id" value="">

    <div class="el-bk-client-form-grid">

        <div class="el-bk-client-form-col el-bk-client-form-col--wide">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Client Name (full legal name as on 1099-NEC)', 'el-core' ); ?>
                <input type="text" id="el-bk-client-name" class="el-input el-bk-voice-input"
                    placeholder="<?php esc_attr_e( 'e.g. Sacramento County Office of Education', 'el-core' ); ?>">
            </label>
        </div>

        <div class="el-bk-client-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Short Name', 'el-core' ); ?>
                <input type="text" id="el-bk-client-short-name" class="el-input el-bk-voice-input"
                    placeholder="<?php esc_attr_e( 'e.g. SCOE', 'el-core' ); ?>">
            </label>
        </div>

        <div class="el-bk-client-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'EIN (Employer Identification Number)', 'el-core' ); ?>
                <input type="text" id="el-bk-client-ein" class="el-input el-bk-voice-input"
                    placeholder="<?php esc_attr_e( 'XX-XXXXXXX', 'el-core' ); ?>">
            </label>
        </div>

        <div class="el-bk-client-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Contract Type', 'el-core' ); ?>
                <select id="el-bk-client-contract-type" class="el-select">
                    <option value=""><?php esc_html_e( '— Select —', 'el-core' ); ?></option>
                    <?php foreach ( $contract_types as $ct ) : ?>
                        <option value="<?php echo esc_attr( $ct ); ?>"><?php echo esc_html( $ct ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="el-bk-client-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Status', 'el-core' ); ?>
                <select id="el-bk-client-status" class="el-select">
                    <?php foreach ( $status_labels as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="el-bk-client-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Contact Name', 'el-core' ); ?>
                <input type="text" id="el-bk-client-contact-name" class="el-input el-bk-voice-input"
                    placeholder="<?php esc_attr_e( 'Primary contact person', 'el-core' ); ?>">
            </label>
        </div>

        <div class="el-bk-client-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Contact Email', 'el-core' ); ?>
                <input type="email" id="el-bk-client-contact-email" class="el-input el-bk-voice-input">
            </label>
        </div>

        <div class="el-bk-client-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Contact Phone', 'el-core' ); ?>
                <input type="text" id="el-bk-client-contact-phone" class="el-input el-bk-voice-input">
            </label>
        </div>

        <div class="el-bk-client-form-col el-bk-client-form-col--wide">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Address (as shown on 1099-NEC)', 'el-core' ); ?>
                <textarea id="el-bk-client-address" class="el-textarea el-bk-voice-input" rows="3"
                    placeholder="<?php esc_attr_e( 'Street, City, State ZIP', 'el-core' ); ?>"></textarea>
            </label>
        </div>

        <div class="el-bk-client-form-col el-bk-client-form-col--wide">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Bank Deposit Patterns', 'el-core' ); ?>
                <span class="description" style="font-weight:normal;font-size:12px;display:block;margin-bottom:6px;">
                    <?php esc_html_e( 'Known text strings that appear in bank deposit descriptions for this client. Used to auto-match deposits.', 'el-core' ); ?>
                </span>
            </label>
            <div class="el-bk-pattern-input-row">
                <input type="text" id="el-bk-client-pattern-input" class="el-input el-bk-voice-input"
                    placeholder="<?php esc_attr_e( 'e.g. SACRAMENTO CO OFFICE', 'el-core' ); ?>"
                    style="flex:1;">
                <button type="button" class="el-btn el-btn-outline" id="el-bk-client-add-pattern-btn">
                    <?php esc_html_e( 'Add', 'el-core' ); ?>
                </button>
            </div>
            <div id="el-bk-client-pattern-tags" class="el-bk-pattern-tags"></div>
            <input type="hidden" id="el-bk-client-bank-patterns" value="">
        </div>

        <div class="el-bk-client-form-col el-bk-client-form-col--wide">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Notes', 'el-core' ); ?>
                <textarea id="el-bk-client-notes" class="el-textarea el-bk-voice-input" rows="3"
                    placeholder="<?php esc_attr_e( 'Contract details, payment terms, or anything else worth noting…', 'el-core' ); ?>"></textarea>
            </label>
        </div>

    </div><!-- .el-bk-client-form-grid -->

    <div class="el-bk-form-actions">
        <button class="el-btn el-btn-primary" id="el-bk-save-client-btn">
            <?php esc_html_e( 'Save Client', 'el-core' ); ?>
        </button>
        <button class="el-btn el-btn-outline" id="el-bk-cancel-client-btn">
            <?php esc_html_e( 'Cancel', 'el-core' ); ?>
        </button>
    </div>
</div><!-- #el-bk-client-form -->

<!-- ── Section C: 1099-NEC Entry Form ──────────────────────────────────── -->
<div id="el-bk-nec-form" class="el-bk-card el-bk-nec-form-card" style="display:none;">
    <h3 id="el-bk-nec-form-title"><?php esc_html_e( 'Add 1099-NEC Record', 'el-core' ); ?></h3>
    <input type="hidden" id="el-bk-nec-id" value="">
    <input type="hidden" id="el-bk-nec-doc-attachment-id" value="">
    <input type="hidden" id="el-bk-nec-form4852-attachment-id" value="">

    <div class="el-bk-nec-form-grid">

        <div class="el-bk-nec-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Client', 'el-core' ); ?>
                <select id="el-bk-nec-client-id" class="el-select">
                    <option value=""><?php esc_html_e( '— Select Client —', 'el-core' ); ?></option>
                    <?php foreach ( $clients as $c ) : ?>
                        <option value="<?php echo esc_attr( $c->id ); ?>">
                            <?php echo esc_html( $c->client_name . ( $c->short_name ? ' (' . $c->short_name . ')' : '' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="el-bk-nec-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Tax Year', 'el-core' ); ?>
                <input type="number" id="el-bk-nec-tax-year" class="el-input el-bk-voice-input"
                    value="<?php echo esc_attr( $tax_year ); ?>"
                    placeholder="<?php esc_attr_e( 'e.g. 2025', 'el-core' ); ?>"
                    min="2000" max="2099" step="1">
            </label>
        </div>

        <div class="el-bk-nec-form-col el-bk-nec-form-col--wide">
            <div class="el-bk-form-label"><?php esc_html_e( 'Document Status', 'el-core' ); ?></div>
            <div class="el-bk-nec-radio-group">
                <label class="el-bk-radio-label">
                    <input type="radio" name="el-bk-nec-doc-status" value="received" checked>
                    <?php esc_html_e( 'Received — 1099-NEC in hand', 'el-core' ); ?>
                </label>
                <label class="el-bk-radio-label">
                    <input type="radio" name="el-bk-nec-doc-status" value="missing">
                    <?php esc_html_e( 'Missing — never received', 'el-core' ); ?>
                </label>
                <label class="el-bk-radio-label">
                    <input type="radio" name="el-bk-nec-doc-status" value="substitute">
                    <?php esc_html_e( 'Substitute — using bank deposits instead', 'el-core' ); ?>
                </label>
            </div>
        </div>

        <div class="el-bk-nec-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Box 1 Amount ($)', 'el-core' ); ?>
                <input type="number" id="el-bk-nec-box1-amount" class="el-input el-bk-voice-input"
                    placeholder="0.00" min="0" step="0.01">
            </label>
        </div>

        <div class="el-bk-nec-form-col" id="el-bk-nec-date-row">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Date Received', 'el-core' ); ?>
                <input type="date" id="el-bk-nec-date-received" class="el-input el-bk-voice-input">
            </label>
        </div>

        <div class="el-bk-nec-form-col" id="el-bk-nec-doc-row">
            <label class="el-bk-form-label">
                <?php esc_html_e( '1099-NEC Document (PDF, JPG, PNG)', 'el-core' ); ?>
                <input type="file" id="el-bk-nec-doc-file" class="el-input"
                    accept=".pdf,.jpg,.jpeg,.png">
            </label>
            <div id="el-bk-nec-doc-current" class="el-bk-nec-doc-current" style="display:none;"></div>
        </div>

        <div class="el-bk-nec-form-col el-bk-nec-form-col--wide" id="el-bk-nec-substitute-row" style="display:none;">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Substitute Documents / Notes', 'el-core' ); ?>
                <span class="description" style="font-weight:normal;font-size:12px;display:block;margin-bottom:6px;">
                    <?php esc_html_e( 'Describe the bank statements or other records being used in place of a 1099-NEC.', 'el-core' ); ?>
                </span>
                <textarea id="el-bk-nec-substitute-docs" class="el-textarea el-bk-voice-input" rows="3"
                    placeholder="<?php esc_attr_e( 'e.g. Chase Bank deposits Jan–Dec 2025', 'el-core' ); ?>"></textarea>
            </label>
            <div style="margin-top:12px;display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
                <button type="button" class="el-btn el-btn-outline" id="el-bk-nec-calculate-btn">
                    <?php esc_html_e( 'Calculate from Deposits', 'el-core' ); ?>
                </button>
                <span id="el-bk-nec-calculate-result" class="el-bk-muted" style="font-size:13px;"></span>
            </div>
            <div style="margin-top:16px;">
                <label class="el-bk-form-label">
                    <?php esc_html_e( 'IRS Form 4852 (PDF, JPG, PNG)', 'el-core' ); ?>
                    <span class="description" style="font-weight:normal;font-size:12px;display:block;margin-bottom:6px;">
                        <?php esc_html_e( 'Upload your completed Form 4852 (Substitute for Form 1099-NEC) if prepared.', 'el-core' ); ?>
                    </span>
                    <input type="file" id="el-bk-nec-form4852-file" class="el-input"
                        accept=".pdf,.jpg,.jpeg,.png">
                </label>
                <div id="el-bk-nec-form4852-current" class="el-bk-nec-doc-current" style="display:none;"></div>
            </div>

            <div style="margin-top:16px;">
                <label class="el-bk-form-label">
                    <?php esc_html_e( 'Supporting Document Title', 'el-core' ); ?>
                    <span class="description" style="font-weight:normal;font-size:12px;display:block;margin-bottom:6px;">
                        <?php esc_html_e( 'Describe what you are uploading (e.g. "Chase Bank Deposits Jan–Dec", "Check Stubs Q1–Q4").', 'el-core' ); ?>
                    </span>
                    <input type="text" id="el-bk-nec-supporting-doc-title" class="el-input el-bk-voice-input"
                        placeholder="<?php esc_attr_e( 'e.g. Chase Bank Deposits Jan–Dec 2024', 'el-core' ); ?>">
                </label>
            </div>

            <div style="margin-top:12px;">
                <label class="el-bk-form-label">
                    <?php esc_html_e( 'Supporting Document Upload (PDF, JPG, PNG)', 'el-core' ); ?>
                    <span class="description" style="font-weight:normal;font-size:12px;display:block;margin-bottom:6px;">
                        <?php esc_html_e( 'Upload bank deposits, check stubs, or any other supporting documentation.', 'el-core' ); ?>
                    </span>
                    <input type="file" id="el-bk-nec-supporting-doc-file" class="el-input"
                        accept=".pdf,.jpg,.jpeg,.png">
                </label>
                <input type="hidden" id="el-bk-nec-supporting-doc-attachment-id" value="">
                <div id="el-bk-nec-supporting-doc-current" class="el-bk-nec-doc-current" style="display:none;"></div>
            </div>
        </div>

        <div class="el-bk-nec-form-col">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Reconciliation Status', 'el-core' ); ?>
                <select id="el-bk-nec-reconciliation-status" class="el-select">
                    <option value="pending"><?php esc_html_e( 'Pending', 'el-core' ); ?></option>
                    <option value="reconciled"><?php esc_html_e( 'Reconciled', 'el-core' ); ?></option>
                    <option value="discrepancy"><?php esc_html_e( 'Discrepancy', 'el-core' ); ?></option>
                </select>
            </label>
        </div>

        <div class="el-bk-nec-form-col el-bk-nec-form-col--wide">
            <label class="el-bk-form-label">
                <?php esc_html_e( 'Notes', 'el-core' ); ?>
                <textarea id="el-bk-nec-notes" class="el-textarea el-bk-voice-input" rows="3"
                    placeholder="<?php esc_attr_e( 'Additional notes about this 1099-NEC record…', 'el-core' ); ?>"></textarea>
            </label>
        </div>

    </div><!-- .el-bk-nec-form-grid -->

    <div class="el-bk-form-actions">
        <button class="el-btn el-btn-primary" id="el-bk-save-nec-btn">
            <?php esc_html_e( 'Save 1099-NEC Record', 'el-core' ); ?>
        </button>
        <button class="el-btn el-btn-outline" id="el-bk-cancel-nec-btn">
            <?php esc_html_e( 'Cancel', 'el-core' ); ?>
        </button>
    </div>
</div><!-- #el-bk-nec-form -->

<!-- ── Section A: Client List ──────────────────────────────────────────── -->
<?php if ( empty( $clients ) ) : ?>
    <?php echo EL_Admin_UI::notice( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        'message' => __( 'No clients yet. Click "Add Client" to create your first 1099-NEC client record.', 'el-core' ),
        'type'    => 'info',
    ] ); ?>
<?php else : ?>

<div class="el-bk-table-wrap" id="el-bk-clients-table-wrap">
    <table class="el-bk-clients-table widefat" id="el-bk-clients-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Client Name', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Short Name', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'EIN', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Contract Type', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Status', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Bank Patterns', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $clients as $c ) :
                $patterns     = array_filter( array_map( 'trim', explode( "|", $c->bank_patterns ) ) );
                $patterns     = array_values( $patterns );
                $status_class = 'el-bk-client-status--' . esc_attr( $c->status );
            ?>
            <tr class="el-bk-client-row"
                data-id="<?php echo esc_attr( $c->id ); ?>"
                data-status="<?php echo esc_attr( $c->status ); ?>"
                data-name="<?php echo esc_attr( $c->client_name ); ?>">
                <td class="el-bk-client-name">
                    <strong><?php echo esc_html( $c->client_name ); ?></strong>
                </td>
                <td><?php echo esc_html( $c->short_name ); ?></td>
                <td><?php echo esc_html( $c->ein ); ?></td>
                <td><?php echo esc_html( $c->contract_type ); ?></td>
                <td>
                    <span class="el-bk-status-badge <?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( $status_labels[ $c->status ] ?? $c->status ); ?>
                    </span>
                </td>
                <td>
                    <?php if ( $patterns ) : ?>
                        <div class="el-bk-pattern-tags el-bk-pattern-tags--display">
                            <?php foreach ( $patterns as $p ) : ?>
                                <span class="el-bk-pattern-tag"><?php echo esc_html( $p ); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <span class="el-bk-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="el-bk-actions">
                    <button class="el-btn el-btn-outline el-bk-add-nec-btn"
                        data-client-id="<?php echo esc_attr( $c->id ); ?>"
                        data-client-name="<?php echo esc_attr( $c->client_name ); ?>">
                        <?php esc_html_e( '+ 1099', 'el-core' ); ?>
                    </button>
                    <button class="el-btn el-btn-outline el-bk-edit-client-btn"
                        data-id="<?php echo esc_attr( $c->id ); ?>"
                        data-client-name="<?php echo esc_attr( $c->client_name ); ?>"
                        data-short-name="<?php echo esc_attr( $c->short_name ); ?>"
                        data-ein="<?php echo esc_attr( $c->ein ); ?>"
                        data-contact-name="<?php echo esc_attr( $c->contact_name ); ?>"
                        data-contact-email="<?php echo esc_attr( $c->contact_email ); ?>"
                        data-contact-phone="<?php echo esc_attr( $c->contact_phone ); ?>"
                        data-address="<?php echo esc_attr( $c->address ); ?>"
                        data-contract-type="<?php echo esc_attr( $c->contract_type ); ?>"
                        data-status="<?php echo esc_attr( $c->status ); ?>"
                        data-bank-patterns="<?php echo esc_attr( wp_json_encode( $patterns ) ); ?>"
                        data-notes="<?php echo esc_attr( $c->notes ); ?>">
                        <?php esc_html_e( 'Edit', 'el-core' ); ?>
                    </button>
                    <button class="el-btn el-btn-outline el-btn-danger el-bk-delete-client-btn"
                        data-id="<?php echo esc_attr( $c->id ); ?>"
                        data-name="<?php echo esc_attr( $c->client_name ); ?>">
                        <?php esc_html_e( 'Delete', 'el-core' ); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div><!-- .el-bk-table-wrap -->

<?php endif; ?>

<!-- ── Section D: 1099-NEC Records ─────────────────────────────────────── -->
<div class="el-bk-section-header" style="margin-top:36px;margin-bottom:12px;display:flex;align-items:center;gap:16px;">
    <h3 style="margin:0;"><?php esc_html_e( '1099-NEC Records', 'el-core' ); ?></h3>
</div>

<?php
$doc_status_labels = [
    'received'   => __( 'Received',   'el-core' ),
    'missing'    => __( 'Missing',    'el-core' ),
    'substitute' => __( 'Substitute', 'el-core' ),
];
$rec_status_labels = [
    'pending'     => __( 'Pending',     'el-core' ),
    'reconciled'  => __( 'Reconciled',  'el-core' ),
    'discrepancy' => __( 'Discrepancy', 'el-core' ),
];
?>

<?php if ( empty( $prefetch_1099s ) ) : ?>
    <?php echo EL_Admin_UI::notice( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        'message' => __( 'No 1099-NEC records yet. Click "+ 1099" on a client row above to create one.', 'el-core' ),
        'type'    => 'info',
    ] ); ?>
<?php else : ?>

<div class="el-bk-table-wrap" id="el-bk-nec-table-wrap">
    <table class="el-bk-nec-table widefat" id="el-bk-nec-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Client', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Tax Year', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Box 1 Amount', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Document Status', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Date Received', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Reconciliation', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Document', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Form 4852', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Supporting Doc', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $prefetch_1099s as $n ) :
                $doc_url          = $n->document_attachment_id         ? wp_get_attachment_url( (int) $n->document_attachment_id )         : '';
                $form4852_url     = $n->form_4852_attachment_id        ? wp_get_attachment_url( (int) $n->form_4852_attachment_id )        : '';
                $supporting_url   = $n->supporting_doc_attachment_id   ? wp_get_attachment_url( (int) $n->supporting_doc_attachment_id )   : '';
            ?>
            <tr class="el-bk-nec-row"
                data-id="<?php echo esc_attr( $n->id ); ?>"
                data-client-id="<?php echo esc_attr( $n->client_id ); ?>"
                data-tax-year="<?php echo esc_attr( $n->tax_year ); ?>">
                <td>
                    <strong><?php echo esc_html( $n->client_name ); ?></strong>
                    <?php if ( $n->short_name ) : ?>
                        <br><span class="el-bk-muted"><?php echo esc_html( $n->short_name ); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( $n->tax_year ); ?></td>
                <td>$<?php echo esc_html( number_format( (float) $n->box1_amount, 2 ) ); ?></td>
                <td>
                    <span class="el-bk-status-badge el-bk-nec-status--<?php echo esc_attr( $n->document_status ); ?>">
                        <?php echo esc_html( $doc_status_labels[ $n->document_status ] ?? $n->document_status ); ?>
                    </span>
                </td>
                <td>
                    <?php echo $n->date_received
                        ? esc_html( gmdate( 'M j, Y', strtotime( $n->date_received ) ) )
                        : '<span class="el-bk-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </td>
                <td>
                    <span class="el-bk-status-badge el-bk-rec-status--<?php echo esc_attr( $n->reconciliation_status ); ?>">
                        <?php echo esc_html( $rec_status_labels[ $n->reconciliation_status ] ?? $n->reconciliation_status ); ?>
                    </span>
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
                <td>
                    <?php if ( $form4852_url ) : ?>
                        <a href="<?php echo esc_url( $form4852_url ); ?>" target="_blank" class="el-bk-doc-link">
                            <?php esc_html_e( 'View', 'el-core' ); ?>
                        </a>
                    <?php else : ?>
                        <span class="el-bk-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $supporting_url ) : ?>
                        <a href="<?php echo esc_url( $supporting_url ); ?>" target="_blank" class="el-bk-doc-link">
                            <?php echo esc_html( $n->supporting_doc_title ?: 'View' ); ?>
                        </a>
                    <?php else : ?>
                        <span class="el-bk-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="el-bk-actions">
                    <button class="el-btn el-btn-outline el-bk-edit-nec-btn"
                        data-id="<?php echo esc_attr( $n->id ); ?>"
                        data-client-id="<?php echo esc_attr( $n->client_id ); ?>"
                        data-tax-year="<?php echo esc_attr( $n->tax_year ); ?>"
                        data-document-status="<?php echo esc_attr( $n->document_status ); ?>"
                        data-box1-amount="<?php echo esc_attr( $n->box1_amount ); ?>"
                        data-date-received="<?php echo esc_attr( $n->date_received ?? '' ); ?>"
                        data-document-attachment-id="<?php echo esc_attr( $n->document_attachment_id ); ?>"
                        data-doc-url="<?php echo esc_attr( $doc_url ); ?>"
                        data-form-4852-attachment-id="<?php echo esc_attr( $n->form_4852_attachment_id ); ?>"
                        data-form4852-url="<?php echo esc_attr( $form4852_url ); ?>"
                        data-supporting-doc-attachment-id="<?php echo esc_attr( $n->supporting_doc_attachment_id ); ?>"
                        data-supporting-doc-title="<?php echo esc_attr( $n->supporting_doc_title ); ?>"
                        data-supporting-url="<?php echo esc_attr( $supporting_url ); ?>"
                        data-substitute-docs="<?php echo esc_attr( $n->substitute_docs ); ?>"
                        data-reconciliation-status="<?php echo esc_attr( $n->reconciliation_status ); ?>"
                        data-notes="<?php echo esc_attr( $n->notes ); ?>">
                        <?php esc_html_e( 'Edit', 'el-core' ); ?>
                    </button>
                    <button class="el-btn el-btn-outline el-bk-view-reconciliation-btn"
                        data-id="<?php echo esc_attr( $n->id ); ?>"
                        data-client-id="<?php echo esc_attr( $n->client_id ); ?>"
                        data-tax-year="<?php echo esc_attr( $n->tax_year ); ?>"
                        data-box1-amount="<?php echo esc_attr( $n->box1_amount ); ?>">
                        <?php esc_html_e( 'Details', 'el-core' ); ?>
                    </button>
                    <button class="el-btn el-btn-outline el-btn-danger el-bk-delete-nec-btn"
                        data-id="<?php echo esc_attr( $n->id ); ?>"
                        data-client="<?php echo esc_attr( $n->client_name ); ?>"
                        data-year="<?php echo esc_attr( $n->tax_year ); ?>">
                        <?php esc_html_e( 'Delete', 'el-core' ); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div><!-- .el-bk-table-wrap #el-bk-nec-table-wrap -->

<?php endif; ?>
