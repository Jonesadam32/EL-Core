<?php
/**
 * Invoicing — Invoice Editor (Create / Edit)
 *
 * Organization autocomplete, contact/project dropdowns, line items, totals.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'create_invoices' ) ) {
    wp_die( __( 'Permission denied.', 'el-core' ) );
}

$core = el_core();
$module = EL_Invoicing_Module::instance();
$is_new = isset( $_GET['new'] ) && $_GET['new'] === '1';
$invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;

$invoice = null;
$line_items = [];
$organization = null;
$contact = null;
$contacts = [];
$projects = [];
$org = $core->organizations ?? null;

if ( $invoice_id ) {
    $invoices = $core->database->query( 'el_inv_invoices', [ 'id' => $invoice_id ], [ 'limit' => 1 ] );
    if ( ! empty( $invoices ) ) {
        $invoice = $invoices[0];
        $line_items = $core->database->query( 'el_inv_line_items', [ 'invoice_id' => $invoice_id ], [ 'orderby' => 'sort_order', 'order' => 'ASC' ] );
        $line_items = is_array( $line_items ) ? $line_items : [];
        if ( $org && $invoice->organization_id ) {
            $organization = $org->get_organization( (int) $invoice->organization_id );
            $contacts = $org->get_contacts( (int) $invoice->organization_id );
            $projects = $org->get_projects_for_org( (int) $invoice->organization_id );
        }
        if ( $org && $invoice->contact_id ) {
            $contact = $org->get_contact( (int) $invoice->contact_id );
        }
    }
}

$products = $core->database->query( 'el_inv_products', [ 'status' => 'active' ], [ 'orderby' => 'name', 'order' => 'ASC' ] );
if ( ! is_array( $products ) ) {
    $products = [];
}
$default_due_days = (int) $core->settings->get( 'mod_invoicing', 'default_due_days', 30 );
$list_url = admin_url( 'admin.php?page=el-core-invoices' );

$org_name = $organization ? $organization->name : '';
$org_id = $invoice ? (int) $invoice->organization_id : 0;
$contact_id = $invoice ? (int) $invoice->contact_id : 0;
$project_id = $invoice ? (int) $invoice->project_id : 0;
$issue_date = $invoice && $invoice->issue_date ? $invoice->issue_date : gmdate( 'Y-m-d' );
$due_date = $invoice && $invoice->due_date ? $invoice->due_date : gmdate( 'Y-m-d', strtotime( "+{$default_due_days} days" ) );
$tax_rate = $invoice ? (float) $invoice->tax_rate : 0;
$notes = $invoice ? ( $invoice->notes ?? '' ) : '';
$internal_notes = $invoice ? ( $invoice->internal_notes ?? '' ) : '';

$page_title = $is_new ? __( 'Create Invoice', 'el-core' ) : sprintf( __( 'Edit Invoice %s', 'el-core' ), $invoice ? $invoice->invoice_number : '' );
$product_options = [ '' => '— ' . __( 'Freeform / Other', 'el-core' ) . ' —' ];
foreach ( $products as $p ) {
    $product_options[ $p->id ] = $p->name . ( (float) $p->default_price > 0 ? ' ($' . number_format( (float) $p->default_price, 2 ) . ')' : '' );
}
$contact_options = [ '' => '— ' . __( 'Select contact', 'el-core' ) . ' —' ];
foreach ( (array) $contacts as $c ) {
    $label = trim( ( $c->first_name ?? '' ) . ' ' . ( $c->last_name ?? '' ) );
    if ( ! empty( $c->email ) ) {
        $label .= ' (' . ( $c->email ?? '' ) . ')';
    }
    $contact_options[ $c->id ?? 0 ] = $label ?: __( 'Contact', 'el-core' );
}
$project_options = [ '' => '— ' . __( 'None', 'el-core' ) . ' —' ];
foreach ( (array) $projects as $proj ) {
    $project_options[ $proj->id ?? 0 ] = $proj->name ?? '';
}

// Line items rows for initial render (edit) or one empty row (new)
$line_rows = [];
if ( ! empty( $line_items ) ) {
    foreach ( $line_items as $li ) {
        $line_rows[] = [
            'product_id'  => (int) $li->product_id,
            'description' => $li->description,
            'quantity'    => (float) $li->quantity,
            'unit_price'  => (float) $li->unit_price,
            'amount'      => (float) $li->amount,
        ];
    }
} else {
    $line_rows[] = [ 'product_id' => 0, 'description' => '', 'quantity' => 1, 'unit_price' => 0, 'amount' => 0 ];
}

ob_start();
?>
<div class="el-inv-invoice-editor" data-invoice-id="<?php echo esc_attr( $invoice_id ); ?>" data-is-new="<?php echo $is_new ? '1' : '0'; ?>">
    <input type="hidden" id="el-inv-organization-id" name="organization_id" value="<?php echo esc_attr( $org_id ); ?>">
    <?php
    echo EL_Admin_UI::page_header( [
        'title'    => $page_title,
        'back_url' => $list_url,
        'back_label' => '← ' . __( 'Back to Invoices', 'el-core' ),
        'actions'  => [
            [
                'label'   => __( 'Save Draft', 'el-core' ),
                'variant' => 'primary',
                'type'    => 'button',
                'id'      => 'el-inv-btn-save-draft',
            ],
            [
                'label'   => __( 'Preview', 'el-core' ),
                'variant' => 'secondary',
                'type'    => 'button',
                'id'      => 'el-inv-btn-preview',
            ],
        ],
    ] );
    ?>
    <form id="el-inv-form-invoice" class="el-inv-form">
        <div class="el-inv-editor-grid">
            <div class="el-inv-editor-main">
                <?php
                echo EL_Admin_UI::card( [
                    'title' => __( 'Client', 'el-core' ),
                    'content' =>
                        '<div class="el-inv-org-search-wrap">' .
                        EL_Admin_UI::form_row( [
                            'name'        => 'organization_search',
                            'id'          => 'el-inv-org-search',
                            'label'       => __( 'Organization', 'el-core' ),
                            'value'       => $org_name,
                            'placeholder' => __( 'Type to search...', 'el-core' ),
                            'helper'       => __( 'Start typing client name.', 'el-core' ),
                        ] ) .
                        '<div id="el-inv-org-results" class="el-inv-autocomplete-results" style="display:none;"></div></div>' .
                        EL_Admin_UI::form_row( [
                            'name'     => 'contact_id',
                            'id'       => 'el-inv-contact-id',
                            'label'    => __( 'Billing contact', 'el-core' ),
                            'type'     => 'select',
                            'value'    => $contact_id,
                            'options'  => $contact_options,
                        ] ) .
                        EL_Admin_UI::form_row( [
                            'name'     => 'project_id',
                            'id'       => 'el-inv-project-id',
                            'label'    => __( 'Project (optional)', 'el-core' ),
                            'type'     => 'select',
                            'value'    => $project_id,
                            'options'  => $project_options,
                        ] ),
                ] );

                echo EL_Admin_UI::card( [
                    'title' => __( 'Dates & tax', 'el-core' ),
                    'content' =>
                        EL_Admin_UI::form_row( [
                            'name'  => 'issue_date',
                            'id'    => 'el-inv-issue-date',
                            'label' => __( 'Issue date', 'el-core' ),
                            'type'  => 'date',
                            'value' => $issue_date,
                        ] ) .
                        EL_Admin_UI::form_row( [
                            'name'  => 'due_date',
                            'id'    => 'el-inv-due-date',
                            'label' => __( 'Due date', 'el-core' ),
                            'type'  => 'date',
                            'value' => $due_date,
                        ] ) .
                        EL_Admin_UI::form_row( [
                            'name'  => 'tax_rate',
                            'id'    => 'el-inv-tax-rate',
                            'label' => __( 'Tax rate (%)', 'el-core' ),
                            'type'  => 'number',
                            'value' => (string) $tax_rate,
                        ] ),
                ] );

                $line_items_html = '<table class="el-inv-line-items-table widefat"><thead><tr>' .
                    '<th class="el-inv-col-product">' . esc_html__( 'Product / Description', 'el-core' ) . '</th>' .
                    '<th class="el-inv-col-qty">' . esc_html__( 'Qty', 'el-core' ) . '</th>' .
                    '<th class="el-inv-col-price">' . esc_html__( 'Unit price', 'el-core' ) . '</th>' .
                    '<th class="el-inv-col-amount">' . esc_html__( 'Amount', 'el-core' ) . '</th>' .
                    '<th class="el-inv-col-action"></th></tr></thead><tbody id="el-inv-line-items-tbody">';
                foreach ( $line_rows as $idx => $row ) {
                    $desc_esc = esc_attr( $row['description'] ?? '' );
                    $qty_esc = esc_attr( (string) ( $row['quantity'] ?? 1 ) );
                    $price_esc = esc_attr( (string) ( $row['unit_price'] ?? 0 ) );
                    $amount_esc = esc_attr( (string) ( $row['amount'] ?? 0 ) );
                    $line_items_html .= '<tr class="el-inv-line-row" data-index="' . $idx . '">';
                    $line_items_html .= '<td class="el-inv-col-product"><select class="el-inv-line-product" data-index="' . $idx . '"><option value="">— ' . esc_html__( 'Freeform', 'el-core' ) . ' —</option>';
                    foreach ( $products as $p ) {
                        $sel = (int) ( $row['product_id'] ?? 0 ) === (int) $p->id ? ' selected' : '';
                        $line_items_html .= '<option value="' . esc_attr( (string) $p->id ) . '" data-name="' . esc_attr( $p->name ?? '' ) . '" data-price="' . esc_attr( (string) ( $p->default_price ?? 0 ) ) . '"' . $sel . '>' . esc_html( $p->name ?? '' ) . '</option>';
                    }
                    $line_items_html .= '</select><input type="text" class="el-inv-line-description" placeholder="' . esc_attr__( 'Description', 'el-core' ) . '" value="' . $desc_esc . '"></td>';
                    $line_items_html .= '<td class="el-inv-col-qty"><input type="number" class="el-inv-line-qty" min="0" step="0.01" value="' . $qty_esc . '"></td>';
                    $line_items_html .= '<td class="el-inv-col-price"><input type="number" class="el-inv-line-unit-price" min="0" step="0.01" value="' . $price_esc . '"></td>';
                    $line_items_html .= '<td class="el-inv-col-amount"><input type="text" class="el-inv-line-amount" readonly value="' . $amount_esc . '"></td>';
                    $line_items_html .= '<td class="el-inv-col-action"><button type="button" class="el-btn el-btn-ghost el-inv-remove-line" aria-label="' . esc_attr__( 'Remove line', 'el-core' ) . '">×</button></td></tr>';
                }
                $line_items_html .= '<tr id="el-inv-line-row-template" style="display:none;" class="el-inv-line-row"><td class="el-inv-col-product"><select class="el-inv-line-product"><option value="">— ' . esc_html__( 'Freeform', 'el-core' ) . ' —</option>';
                foreach ( $products as $p ) {
                    $line_items_html .= '<option value="' . esc_attr( (string) $p->id ) . '" data-name="' . esc_attr( $p->name ?? '' ) . '" data-price="' . esc_attr( (string) ( $p->default_price ?? 0 ) ) . '">' . esc_html( $p->name ?? '' ) . '</option>';
                }
                $line_items_html .= '</select><input type="text" class="el-inv-line-description" placeholder="' . esc_attr__( 'Description', 'el-core' ) . '"></td><td class="el-inv-col-qty"><input type="number" class="el-inv-line-qty" min="0" step="0.01" value="1"></td><td class="el-inv-col-price"><input type="number" class="el-inv-line-unit-price" min="0" step="0.01" value="0"></td><td class="el-inv-col-amount"><input type="text" class="el-inv-line-amount" readonly value="0.00"></td><td class="el-inv-col-action"><button type="button" class="el-btn el-btn-ghost el-inv-remove-line" aria-label="' . esc_attr__( 'Remove line', 'el-core' ) . '">×</button></td></tr>';
                $line_items_html .= '</tbody></table><p><button type="button" class="el-btn el-btn-secondary" id="el-inv-add-line">' . esc_html__( 'Add line item', 'el-core' ) . '</button></p>';
                $line_items_html .= '<div class="el-inv-totals"><div class="el-inv-total-row"><span class="el-inv-total-label">' . esc_html__( 'Subtotal', 'el-core' ) . '</span><span id="el-inv-subtotal">0.00</span></div><div class="el-inv-total-row"><span class="el-inv-total-label">' . esc_html__( 'Tax', 'el-core' ) . '</span><span id="el-inv-tax-amount">0.00</span></div><div class="el-inv-total-row el-inv-total-grand"><span class="el-inv-total-label">' . esc_html__( 'Total', 'el-core' ) . '</span><span id="el-inv-total">0.00</span></div></div>';
                echo EL_Admin_UI::card( [
                    'title'   => __( 'Line items', 'el-core' ),
                    'content' => $line_items_html,
                ] );

                echo EL_Admin_UI::card( [
                    'title' => __( 'Notes', 'el-core' ),
                    'content' =>
                        EL_Admin_UI::form_row( [
                            'name'        => 'notes',
                            'id'          => 'el-inv-notes',
                            'label'       => __( 'Notes (on invoice)', 'el-core' ),
                            'type'        => 'textarea',
                            'value'       => $notes,
                            'placeholder' => __( 'Visible to client on invoice.', 'el-core' ),
                        ] ) .
                        EL_Admin_UI::form_row( [
                            'name'        => 'internal_notes',
                            'id'          => 'el-inv-internal-notes',
                            'label'       => __( 'Internal notes', 'el-core' ),
                            'type'        => 'textarea',
                            'value'       => $internal_notes,
                            'placeholder' => __( 'Admin only.', 'el-core' ),
                        ] ),
                ] );
                ?>
            </div>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
echo EL_Admin_UI::wrap( $content );
