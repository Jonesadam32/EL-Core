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
                $patterns     = array_filter( array_map( 'trim', explode( "\n", $c->bank_patterns ) ) );
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
                        data-bank-patterns="<?php echo esc_attr( $c->bank_patterns ); ?>"
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
