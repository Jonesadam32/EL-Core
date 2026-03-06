<?php
/**
 * Invoicing Module
 *
 * Invoice creation, payment tracking, product management, and revenue reporting.
 * Replaces QuickBooks as ELS's sole invoicing tool. Links to el_organizations, el_contacts, el_es_projects.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Invoicing_Module {

    private static ?EL_Invoicing_Module $instance = null;
    private ?EL_Core $core = null;

    public static function instance( ?EL_Core $core = null ): self {
        if ( null === self::$instance ) {
            self::$instance = new self( $core );
        }
        return self::$instance;
    }

    private function __construct( ?EL_Core $core = null ) {
        $this->core = $core;
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action( 'admin_menu', [ $this, 'register_admin_pages' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Invoice CRUD
        add_action( 'el_core_ajax_inv_create_invoice',   [ $this, 'handle_create_invoice' ] );
        add_action( 'el_core_ajax_inv_update_invoice',   [ $this, 'handle_update_invoice' ] );
        add_action( 'el_core_ajax_inv_delete_invoice',   [ $this, 'handle_delete_invoice' ] );
        add_action( 'el_core_ajax_inv_get_invoice',      [ $this, 'handle_get_invoice' ] );
        add_action( 'el_core_ajax_inv_duplicate_invoice', [ $this, 'handle_duplicate_invoice' ] );
        add_action( 'el_core_ajax_inv_send_invoice',     [ $this, 'handle_send_invoice' ] );

        // Payment
        add_action( 'el_core_ajax_inv_record_payment',  [ $this, 'handle_record_payment' ] );
        add_action( 'el_core_ajax_inv_delete_payment',   [ $this, 'handle_delete_payment' ] );

        // Product CRUD
        add_action( 'el_core_ajax_inv_create_product',  [ $this, 'handle_create_product' ] );
        add_action( 'el_core_ajax_inv_update_product',   [ $this, 'handle_update_product' ] );
        add_action( 'el_core_ajax_inv_delete_product',   [ $this, 'handle_delete_product' ] );
        add_action( 'el_core_ajax_inv_get_products',     [ $this, 'handle_get_products' ] );
        add_action( 'el_core_ajax_inv_seed_products',    [ $this, 'handle_seed_products' ] );

        // Reporting & Export
        add_action( 'el_core_ajax_inv_get_revenue_data', [ $this, 'handle_get_revenue_data' ] );
        add_action( 'el_core_ajax_inv_export_csv',       [ $this, 'handle_export_csv' ] );

        // Client portal
        add_action( 'el_core_ajax_inv_get_client_invoices', [ $this, 'handle_get_client_invoices' ] );

        // Invoice editor: org contacts and projects (for dropdowns)
        add_action( 'el_core_ajax_inv_get_organization_contacts', [ $this, 'handle_get_organization_contacts' ] );
        add_action( 'el_core_ajax_inv_get_org_projects', [ $this, 'handle_get_org_projects' ] );

        // Front-end: preview URL ?el_invoice_view=1&id=X (admin or logged-in with view_invoices)
        add_action( 'template_redirect', [ $this, 'maybe_render_invoice_preview' ], 5 );
    }

    /**
     * If URL has el_invoice_view=1&id=X, render invoice view and exit (preview / customer view).
     */
    public function maybe_render_invoice_preview(): void {
        if ( empty( $_GET['el_invoice_view'] ) || $_GET['el_invoice_view'] !== '1' ) {
            return;
        }
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id ) {
            return;
        }
        if ( ! is_user_logged_in() || ( ! current_user_can( 'view_invoices' ) && ! current_user_can( 'create_invoices' ) ) ) {
            wp_die( esc_html__( 'You do not have permission to view this invoice.', 'el-core' ), 403 );
        }
        $invoices = $this->core->database->query( 'el_inv_invoices', [ 'id' => $id ], [ 'limit' => 1 ] );
        if ( empty( $invoices ) ) {
            wp_die( esc_html__( 'Invoice not found.', 'el-core' ), 404 );
        }
        $invoice = $invoices[0];
        $line_items = $this->core->database->query( 'el_inv_line_items', [ 'invoice_id' => $id ], [ 'orderby' => 'sort_order', 'order' => 'ASC' ] );
        $line_items = is_array( $line_items ) ? $line_items : [];
        $org = $this->core->organizations && $invoice->organization_id
            ? $this->core->organizations->get_organization( (int) $invoice->organization_id )
            : null;
        $contact = $this->core->organizations && $invoice->contact_id
            ? $this->core->organizations->get_contact( (int) $invoice->contact_id )
            : null;
        $this->render_invoice_view_html( $invoice, $line_items, $org, $contact );
        exit;
    }

    /**
     * Output full HTML page for one invoice (preview / print / customer view).
     */
    private function render_invoice_view_html( object $invoice, array $line_items, ?object $org, ?object $contact ): void {
        $org_name   = $org ? ( $org->name ?? '' ) : '';
        $contact_name = $contact ? trim( ( $contact->first_name ?? '' ) . ' ' . ( $contact->last_name ?? '' ) ) : '';
        $contact_email = $contact ? ( $contact->email ?? '' ) : '';
        $issue_date = $invoice->issue_date ? date_i18n( get_option( 'date_format' ), strtotime( $invoice->issue_date ) ) : '';
        $due_date   = $invoice->due_date ? date_i18n( get_option( 'date_format' ), strtotime( $invoice->due_date ) ) : '';
        $notes      = ! empty( $invoice->notes ) ? wp_kses_post( $invoice->notes ) : '';
        $company_name = $this->core->settings->get( 'mod_invoicing', 'company_name', '' );
        if ( $company_name === '' && function_exists( 'el_core_get_org_name' ) ) {
            $company_name = el_core_get_org_name();
        }
        if ( $company_name === '' ) {
            $company_name = 'Expanded Learning Solutions';
        }
        header( 'Content-Type: text/html; charset=utf-8' );
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( __( 'Invoice', 'el-core' ) . ' ' . $invoice->invoice_number ); ?></title>
    <style>
        .el-inv-view { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 800px; margin: 0 auto; padding: 2rem; color: #1a1a1a; }
        .el-inv-view h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .el-inv-view .el-inv-meta { color: #555; margin-bottom: 1.5rem; }
        .el-inv-view table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
        .el-inv-view th, .el-inv-view td { padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        .el-inv-view th { font-weight: 600; }
        .el-inv-view .el-inv-totals { margin-top: 1rem; text-align: right; }
        .el-inv-view .el-inv-total-row { padding: 0.25rem 0; }
        .el-inv-view .el-inv-grand { font-size: 1.25rem; font-weight: 700; }
        .el-inv-view .el-inv-notes { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #ddd; font-size: 0.9rem; color: #555; }
        .el-inv-view .el-inv-print { margin-top: 1.5rem; }
        @media print { .el-inv-view .el-inv-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body class="el-inv-view">
    <div class="el-inv-view">
        <h1><?php echo esc_html( __( 'INVOICE', 'el-core' ) ); ?></h1>
        <p class="el-inv-meta">
            <strong><?php echo esc_html( $invoice->invoice_number ); ?></strong>
            <?php if ( $issue_date ) { echo ' · ' . esc_html( $issue_date ); } ?>
            <?php if ( $due_date ) { echo ' · ' . esc_html( __( 'Due:', 'el-core' ) . ' ' . $due_date ); } ?>
        </p>
        <table>
            <tr><th><?php echo esc_html( __( 'Bill to', 'el-core' ) ); ?></th><th><?php echo esc_html( __( 'From', 'el-core' ) ); ?></th></tr>
            <tr>
                <td>
                    <?php echo esc_html( $org_name ); ?>
                    <?php if ( $contact_name ) { echo '<br>' . esc_html( $contact_name ); } ?>
                    <?php if ( $contact_email ) { echo '<br>' . esc_html( $contact_email ); } ?>
                </td>
                <td><?php echo esc_html( $company_name ); ?></td>
            </tr>
        </table>
        <table>
            <thead><tr>
                <th><?php echo esc_html( __( 'Description', 'el-core' ) ); ?></th>
                <th style="text-align:right;"><?php echo esc_html( __( 'Qty', 'el-core' ) ); ?></th>
                <th style="text-align:right;"><?php echo esc_html( __( 'Unit price', 'el-core' ) ); ?></th>
                <th style="text-align:right;"><?php echo esc_html( __( 'Amount', 'el-core' ) ); ?></th>
            </tr></thead>
            <tbody>
                <?php
                foreach ( $line_items as $li ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( $li->description ?? '' ) . '</td>';
                    echo '<td style="text-align:right;">' . esc_html( number_format( (float) ( $li->quantity ?? 0 ), 2 ) ) . '</td>';
                    echo '<td style="text-align:right;">' . esc_html( number_format( (float) ( $li->unit_price ?? 0 ), 2 ) ) . '</td>';
                    echo '<td style="text-align:right;">' . esc_html( number_format( (float) ( $li->amount ?? 0 ), 2 ) ) . '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        <div class="el-inv-totals">
            <div class="el-inv-total-row"><?php echo esc_html( __( 'Subtotal', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->subtotal, 2 ) ); ?></div>
            <?php if ( (float) $invoice->tax_amount > 0 ) { ?>
            <div class="el-inv-total-row"><?php echo esc_html( __( 'Tax', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->tax_amount, 2 ) ); ?></div>
            <?php } ?>
            <div class="el-inv-total-row el-inv-grand"><?php echo esc_html( __( 'Total', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->total, 2 ) ); ?></div>
            <?php if ( (float) $invoice->amount_paid > 0 ) { ?>
            <div class="el-inv-total-row"><?php echo esc_html( __( 'Amount paid', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->amount_paid, 2 ) ); ?></div>
            <div class="el-inv-total-row"><?php echo esc_html( __( 'Balance due', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->balance_due, 2 ) ); ?></div>
            <?php } ?>
        </div>
        <?php if ( $notes ) { ?>
        <div class="el-inv-notes"><?php echo $notes; ?></div>
        <?php } ?>
        <p class="el-inv-print"><button type="button" onclick="window.print();"><?php echo esc_html( __( 'Print', 'el-core' ) ); ?></button></p>
    </div>
</body>
</html>
        <?php
    }

    /**
     * Return invoice view HTML fragment for use in shortcode (no full document).
     * Caller must ensure user has view_invoices or create_invoices.
     */
    public function get_invoice_view_fragment( int $invoice_id ): string {
        $invoices = $this->core->database->query( 'el_inv_invoices', [ 'id' => $invoice_id ], [ 'limit' => 1 ] );
        if ( empty( $invoices ) ) {
            return '<p class="el-inv-invoice-not-found">' . esc_html__( 'Invoice not found.', 'el-core' ) . '</p>';
        }
        $invoice    = $invoices[0];
        $line_items = $this->core->database->query( 'el_inv_line_items', [ 'invoice_id' => $invoice_id ], [ 'orderby' => 'sort_order', 'order' => 'ASC' ] );
        $line_items = is_array( $line_items ) ? $line_items : [];
        $org        = $this->core->organizations && $invoice->organization_id ? $this->core->organizations->get_organization( (int) $invoice->organization_id ) : null;
        $contact    = $this->core->organizations && $invoice->contact_id ? $this->core->organizations->get_contact( (int) $invoice->contact_id ) : null;
        ob_start();
        $this->render_invoice_view_fragment( $invoice, $line_items, $org, $contact );
        return ob_get_clean();
    }

    /**
     * Output invoice view fragment (no full document) for shortcode embedding.
     */
    private function render_invoice_view_fragment( object $invoice, array $line_items, ?object $org, ?object $contact ): void {
        $org_name     = $org ? ( $org->name ?? '' ) : '';
        $contact_name = $contact ? trim( ( $contact->first_name ?? '' ) . ' ' . ( $contact->last_name ?? '' ) ) : '';
        $contact_email = $contact ? ( $contact->email ?? '' ) : '';
        $issue_date   = $invoice->issue_date ? date_i18n( get_option( 'date_format' ), strtotime( $invoice->issue_date ) ) : '';
        $due_date     = $invoice->due_date ? date_i18n( get_option( 'date_format' ), strtotime( $invoice->due_date ) ) : '';
        $notes        = ! empty( $invoice->notes ) ? wp_kses_post( $invoice->notes ) : '';
        $company_name = $this->core->settings->get( 'mod_invoicing', 'company_name', '' );
        if ( $company_name === '' && function_exists( 'el_core_get_org_name' ) ) {
            $company_name = el_core_get_org_name();
        }
        if ( $company_name === '' ) {
            $company_name = 'Expanded Learning Solutions';
        }
        ?>
        <div class="el-inv-view el-inv-invoice-view">
            <h1><?php echo esc_html( __( 'INVOICE', 'el-core' ) ); ?></h1>
            <p class="el-inv-meta">
                <strong><?php echo esc_html( $invoice->invoice_number ); ?></strong>
                <?php if ( $issue_date ) { echo ' · ' . esc_html( $issue_date ); } ?>
                <?php if ( $due_date ) { echo ' · ' . esc_html( __( 'Due:', 'el-core' ) . ' ' . $due_date ); } ?>
            </p>
            <table>
                <tr><th><?php echo esc_html( __( 'Bill to', 'el-core' ) ); ?></th><th><?php echo esc_html( __( 'From', 'el-core' ) ); ?></th></tr>
                <tr>
                    <td>
                        <?php echo esc_html( $org_name ); ?>
                        <?php if ( $contact_name ) { echo '<br>' . esc_html( $contact_name ); } ?>
                        <?php if ( $contact_email ) { echo '<br>' . esc_html( $contact_email ); } ?>
                    </td>
                    <td><?php echo esc_html( $company_name ); ?></td>
                </tr>
            </table>
            <table>
                <thead><tr>
                    <th><?php echo esc_html( __( 'Description', 'el-core' ) ); ?></th>
                    <th style="text-align:right;"><?php echo esc_html( __( 'Qty', 'el-core' ) ); ?></th>
                    <th style="text-align:right;"><?php echo esc_html( __( 'Unit price', 'el-core' ) ); ?></th>
                    <th style="text-align:right;"><?php echo esc_html( __( 'Amount', 'el-core' ) ); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ( $line_items as $li ) : ?>
                    <tr>
                        <td><?php echo esc_html( $li->description ?? '' ); ?></td>
                        <td style="text-align:right;"><?php echo esc_html( number_format( (float) ( $li->quantity ?? 0 ), 2 ) ); ?></td>
                        <td style="text-align:right;"><?php echo esc_html( number_format( (float) ( $li->unit_price ?? 0 ), 2 ) ); ?></td>
                        <td style="text-align:right;"><?php echo esc_html( number_format( (float) ( $li->amount ?? 0 ), 2 ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="el-inv-totals">
                <div class="el-inv-total-row"><?php echo esc_html( __( 'Subtotal', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->subtotal, 2 ) ); ?></div>
                <?php if ( (float) $invoice->tax_amount > 0 ) : ?>
                <div class="el-inv-total-row"><?php echo esc_html( __( 'Tax', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->tax_amount, 2 ) ); ?></div>
                <?php endif; ?>
                <div class="el-inv-total-row el-inv-grand"><?php echo esc_html( __( 'Total', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->total, 2 ) ); ?></div>
                <?php if ( (float) $invoice->amount_paid > 0 ) : ?>
                <div class="el-inv-total-row"><?php echo esc_html( __( 'Amount paid', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->amount_paid, 2 ) ); ?></div>
                <div class="el-inv-total-row"><?php echo esc_html( __( 'Balance due', 'el-core' ) ); ?>: <?php echo esc_html( number_format( (float) $invoice->balance_due, 2 ) ); ?></div>
                <?php endif; ?>
            </div>
            <?php if ( $notes ) : ?>
            <div class="el-inv-notes"><?php echo $notes; ?></div>
            <?php endif; ?>
            <p class="el-inv-print"><button type="button" onclick="window.print();"><?php echo esc_html( __( 'Print', 'el-core' ) ); ?></button></p>
        </div>
        <?php
    }

    /**
     * Generate next invoice number: ELS-YYYY-NNN (prefix from settings, sequence per year).
     */
    private function get_next_invoice_number(): string {
        $prefix = $this->core->settings->get( 'mod_invoicing', 'invoice_prefix', 'ELS' );
        $prefix = preg_replace( '/[^A-Za-z0-9]/', '', $prefix ) ?: 'ELS';
        $year   = gmdate( 'Y' );
        global $wpdb;
        $table = $this->core->database->get_table_name( 'el_inv_invoices' );
        $like  = $wpdb->esc_like( $prefix . '-' . $year . '-' ) . '%';
        $max   = $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) FROM {$table} WHERE invoice_number LIKE %s",
            $like
        ) );
        $seq = (int) $max + 1;
        return $prefix . '-' . $year . '-' . str_pad( (string) $seq, 3, '0', STR_PAD_LEFT );
    }

    /**
     * Recalculate and update invoice totals from line items.
     */
    private function recalc_invoice_totals( int $invoice_id ): void {
        $items = $this->core->database->query( 'el_inv_line_items', [ 'invoice_id' => $invoice_id ], [ 'orderby' => 'sort_order', 'order' => 'ASC' ] );
        $subtotal = 0;
        foreach ( $items as $row ) {
            $subtotal += (float) $row->amount;
        }
        $inv = $this->core->database->query( 'el_inv_invoices', [ 'id' => $invoice_id ], [ 'limit' => 1 ] );
        $inv = ! empty( $inv ) ? $inv[0] : null;
        if ( ! $inv ) {
            return;
        }
        $tax_rate   = (float) $inv->tax_rate;
        $tax_amount = round( $subtotal * ( $tax_rate / 100 ), 2 );
        $total      = round( $subtotal + $tax_amount, 2 );
        $amount_paid = (float) $inv->amount_paid;
        $balance_due = round( $total - $amount_paid, 2 );
        $this->core->database->update( 'el_inv_invoices', [
            'subtotal'    => $subtotal,
            'tax_amount'  => $tax_amount,
            'total'       => $total,
            'balance_due' => $balance_due,
            'updated_at'  => current_time( 'mysql' ),
        ], [ 'id' => $invoice_id ] );
    }

    /**
     * Recalculate invoice amount_paid, balance_due, status and paid_date from el_inv_payments.
     */
    private function recalc_invoice_from_payments( int $invoice_id ): void {
        $inv = $this->core->database->query( 'el_inv_invoices', [ 'id' => $invoice_id ], [ 'limit' => 1 ] );
        if ( empty( $inv ) ) {
            return;
        }
        $inv = $inv[0];
        $total = (float) $inv->total;
        $payments = $this->core->database->query( 'el_inv_payments', [ 'invoice_id' => $invoice_id ], [] );
        $amount_paid = 0;
        foreach ( $payments as $p ) {
            $amount_paid += (float) $p->amount;
        }
        $amount_paid = round( $amount_paid, 2 );
        $balance_due = round( $total - $amount_paid, 2 );
        $status = $inv->status;
        $paid_date = $inv->paid_date;
        if ( $balance_due <= 0 ) {
            $status = 'paid';
            $paid_date = ! empty( $payments ) ? $payments[ count( $payments ) - 1 ]->payment_date : current_time( 'Y-m-d' );
        } elseif ( $amount_paid > 0 ) {
            $status = 'partial';
            $paid_date = null;
        }
        $this->core->database->update( 'el_inv_invoices', [
            'amount_paid' => $amount_paid,
            'balance_due' => $balance_due,
            'status'      => $status,
            'paid_date'   => $paid_date,
            'updated_at'  => current_time( 'mysql' ),
        ], [ 'id' => $invoice_id ] );
    }

    // ═══════════════════════════════════════════
    // ADMIN PAGES
    // ═══════════════════════════════════════════

    public function register_admin_pages(): void {
        add_submenu_page(
            'el-core',
            __( 'Invoices', 'el-core' ),
            __( 'Invoices', 'el-core' ),
            'create_invoices',
            'el-core-invoices',
            [ $this, 'render_invoice_list_page' ]
        );

        add_submenu_page(
            'el-core',
            __( 'Products', 'el-core' ),
            __( 'Products', 'el-core' ),
            'manage_invoices',
            'el-core-inv-products',
            [ $this, 'render_product_list_page' ]
        );

        add_submenu_page(
            'el-core',
            __( 'Revenue', 'el-core' ),
            __( 'Revenue', 'el-core' ),
            'manage_invoices',
            'el-core-inv-revenue',
            [ $this, 'render_revenue_page' ]
        );
    }

    public function render_invoice_list_page(): void {
        $is_new   = isset( $_GET['new'] ) && $_GET['new'] === '1';
        $edit_id  = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
        if ( $is_new || $edit_id > 0 ) {
            $view = __DIR__ . '/admin/views/invoice-edit.php';
            if ( file_exists( $view ) ) {
                require_once $view;
                return;
            }
        }
        $view = __DIR__ . '/admin/views/invoice-list.php';
        if ( file_exists( $view ) ) {
            require_once $view;
        } else {
            echo '<div class="wrap"><h1>Invoices</h1><p class="el-inv-placeholder">Invoice list view will be built in Step 3.</p></div>';
        }
    }

    public function render_product_list_page(): void {
        $view = __DIR__ . '/admin/views/product-list.php';
        if ( file_exists( $view ) ) {
            require_once $view;
        } else {
            echo '<div class="wrap"><h1>Products</h1><p class="el-inv-placeholder">Product management will be built in Step 2.</p></div>';
        }
    }

    public function render_revenue_page(): void {
        $view = __DIR__ . '/admin/views/revenue-dashboard.php';
        if ( file_exists( $view ) ) {
            require_once $view;
        } else {
            echo '<div class="wrap"><h1>Revenue</h1><p class="el-inv-placeholder">Revenue dashboard will be built in Step 6.</p></div>';
        }
    }

    public function enqueue_admin_assets( string $hook ): void {
        $our_pages = [ 'el-core-invoices', 'el-core-inv-products', 'el-core-inv-revenue' ];
        // Invoice edit is same page with query params; list and edit both use invoicing.js
        $on_our_page = false;
        foreach ( $our_pages as $page ) {
            if ( strpos( $hook, $page ) !== false ) {
                $on_our_page = true;
                break;
            }
        }
        if ( ! $on_our_page ) {
            return;
        }

        wp_enqueue_style(
            'el-invoicing-admin',
            EL_CORE_URL . 'modules/invoicing/assets/css/invoicing.css',
            [ 'el-core-admin' ],
            EL_CORE_VERSION
        );

        wp_enqueue_script(
            'el-invoicing-admin',
            EL_CORE_URL . 'modules/invoicing/assets/js/invoicing.js',
            [ 'jquery', 'el-core-admin' ],
            EL_CORE_VERSION,
            true
        );

        wp_localize_script( 'el-invoicing-admin', 'elInvAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'el_core_nonce' ),
        ] );
    }

    // ═══════════════════════════════════════════
    // PRODUCT AJAX HANDLERS (Step 2)
    // ═══════════════════════════════════════════

    public function handle_get_products( array $data ): void {
        if ( ! current_user_can( 'create_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $products = $this->core->database->query( 'el_inv_products', [ 'status' => 'active' ], [ 'orderby' => 'name', 'order' => 'ASC' ] );
        EL_AJAX_Handler::success( [ 'products' => $products ] );
    }

    public function handle_create_product( array $data ): void {
        if ( ! current_user_can( 'manage_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        if ( $name === '' ) {
            wp_send_json_error( [ 'message' => __( 'Name is required.', 'el-core' ) ], 400 );
        }
        $slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
        if ( $slug === '' ) {
            $slug = sanitize_title( $name );
        }
        $category     = in_array( $data['category'] ?? '', [ 'service', 'subscription', 'contract' ], true ) ? $data['category'] : 'service';
        $default_price= isset( $data['default_price'] ) ? floatval( $data['default_price'] ) : 0;
        $billing_cycle= in_array( $data['billing_cycle'] ?? '', [ 'one-time', 'monthly', 'quarterly', 'annual' ], true ) ? $data['billing_cycle'] : 'one-time';
        $status       = ( ! empty( $data['status'] ) && $data['status'] !== '0' ) ? 'active' : 'inactive';
        $description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

        $id = $this->core->database->insert( 'el_inv_products', [
            'name'          => $name,
            'slug'          => $slug,
            'category'      => $category,
            'default_price' => $default_price,
            'billing_cycle' => $billing_cycle,
            'status'        => $status,
            'description'   => $description,
        ] );
        if ( $id === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to create product.', 'el-core' ) ], 500 );
        }
        EL_AJAX_Handler::success( [ 'product_id' => $id, 'message' => __( 'Product created.', 'el-core' ) ] );
    }

    public function handle_update_product( array $data ): void {
        if ( ! current_user_can( 'manage_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $product_id = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product.', 'el-core' ) ], 400 );
        }
        $name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        if ( $name === '' ) {
            wp_send_json_error( [ 'message' => __( 'Name is required.', 'el-core' ) ], 400 );
        }
        $slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
        if ( $slug === '' ) {
            $slug = sanitize_title( $name );
        }
        $category      = in_array( $data['category'] ?? '', [ 'service', 'subscription', 'contract' ], true ) ? $data['category'] : 'service';
        $default_price = isset( $data['default_price'] ) ? floatval( $data['default_price'] ) : 0;
        $billing_cycle = in_array( $data['billing_cycle'] ?? '', [ 'one-time', 'monthly', 'quarterly', 'annual' ], true ) ? $data['billing_cycle'] : 'one-time';
        $status        = ( ! empty( $data['status'] ) && $data['status'] !== '0' ) ? 'active' : 'inactive';
        $description   = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

        $updated = $this->core->database->update( 'el_inv_products', [
            'name'          => $name,
            'slug'          => $slug,
            'category'      => $category,
            'default_price' => $default_price,
            'billing_cycle' => $billing_cycle,
            'status'        => $status,
            'description'   => $description,
        ], [ 'id' => $product_id ] );
        if ( $updated === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to update product.', 'el-core' ) ], 500 );
        }
        EL_AJAX_Handler::success( [ 'message' => __( 'Product updated.', 'el-core' ) ] );
    }

    public function handle_delete_product( array $data ): void {
        if ( ! current_user_can( 'manage_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $product_id = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product.', 'el-core' ) ], 400 );
        }
        $deleted = $this->core->database->delete( 'el_inv_products', [ 'id' => $product_id ] );
        if ( $deleted === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to delete product.', 'el-core' ) ], 500 );
        }
        EL_AJAX_Handler::success( [ 'message' => __( 'Product deleted.', 'el-core' ) ] );
    }

    public function handle_seed_products( array $data ): void {
        if ( ! current_user_can( 'manage_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }

        $default_products = [
            [ 'name' => 'LMS Platform Licensing',           'slug' => 'lms-licensing',   'category' => 'subscription', 'default_price' => 0, 'billing_cycle' => 'monthly' ],
            [ 'name' => 'Professional Development Training', 'slug' => 'pd-training',   'category' => 'service',     'default_price' => 0, 'billing_cycle' => 'one-time' ],
            [ 'name' => 'Coaching Services',                'slug' => 'coaching',       'category' => 'service',     'default_price' => 0, 'billing_cycle' => 'one-time' ],
            [ 'name' => 'Retreat Facilitation',             'slug' => 'retreat',       'category' => 'service',     'default_price' => 0, 'billing_cycle' => 'one-time' ],
            [ 'name' => 'Website / Tech Services — Expand Site', 'slug' => 'expand-site', 'category' => 'service', 'default_price' => 0, 'billing_cycle' => 'one-time' ],
            [ 'name' => 'NYC SMV Tool',                    'slug' => 'nyc-smv',       'category' => 'contract',    'default_price' => 0, 'billing_cycle' => 'one-time' ],
        ];

        $created = 0;
        foreach ( $default_products as $row ) {
            $existing = $this->core->database->query( 'el_inv_products', [ 'slug' => $row['slug'] ], [ 'limit' => 1 ] );
            if ( ! empty( $existing ) ) {
                continue;
            }
            $id = $this->core->database->insert( 'el_inv_products', [
                'name'          => $row['name'],
                'slug'          => $row['slug'],
                'category'      => $row['category'],
                'default_price' => (float) $row['default_price'],
                'billing_cycle' => $row['billing_cycle'],
                'status'        => 'active',
            ] );
            if ( $id ) {
                $created++;
            }
        }

        EL_AJAX_Handler::success( [
            'message' => sprintf(
                /* translators: %d: number of products created */
                _n( '%d default product created.', '%d default products created.', $created, 'el-core' ),
                $created
            ),
            'created' => $created,
        ] );
    }

    // ═══════════════════════════════════════════
    // INVOICE EDITOR HELPERS
    // ═══════════════════════════════════════════

    public function handle_get_organization_contacts( array $data ): void {
        if ( ! current_user_can( 'create_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $org_id = absint( $data['organization_id'] ?? 0 );
        if ( ! $org_id ) {
            EL_AJAX_Handler::success( [ 'contacts' => [] ] );
            return;
        }
        $contacts = $this->core->organizations->get_contacts( $org_id );
        EL_AJAX_Handler::success( [ 'contacts' => $contacts ] );
    }

    public function handle_get_org_projects( array $data ): void {
        if ( ! current_user_can( 'create_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $org_id = absint( $data['organization_id'] ?? 0 );
        if ( ! $org_id ) {
            EL_AJAX_Handler::success( [ 'projects' => [] ] );
            return;
        }
        $projects = $this->core->organizations->get_projects_for_org( $org_id );
        EL_AJAX_Handler::success( [ 'projects' => $projects ] );
    }

    // ═══════════════════════════════════════════
    // INVOICE CRUD (Step 3)
    // ═══════════════════════════════════════════

    public function handle_create_invoice( array $data ): void {
        if ( ! current_user_can( 'create_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $organization_id = absint( $data['organization_id'] ?? 0 );
        if ( ! $organization_id ) {
            wp_send_json_error( [ 'message' => __( 'Please select a client (organization).', 'el-core' ) ], 400 );
        }
        $contact_id = absint( $data['contact_id'] ?? 0 );
        $project_id = absint( $data['project_id'] ?? 0 );
        $issue_date = ! empty( $data['issue_date'] ) ? sanitize_text_field( $data['issue_date'] ) : null;
        $due_date   = ! empty( $data['due_date'] ) ? sanitize_text_field( $data['due_date'] ) : null;
        $tax_rate   = isset( $data['tax_rate'] ) ? (float) $data['tax_rate'] : 0;
        $notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
        $internal_notes = isset( $_POST['internal_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_notes'] ) ) : '';

        $invoice_number = $this->get_next_invoice_number();
        $user_id = get_current_user_id();
        $now = current_time( 'mysql' );
        $invoice_id = $this->core->database->insert( 'el_inv_invoices', [
            'organization_id' => $organization_id,
            'contact_id'      => $contact_id,
            'project_id'      => $project_id,
            'invoice_number'  => $invoice_number,
            'status'          => 'draft',
            'issue_date'      => $issue_date,
            'due_date'        => $due_date,
            'tax_rate'        => $tax_rate,
            'subtotal'        => 0,
            'tax_amount'      => 0,
            'total'           => 0,
            'amount_paid'     => 0,
            'balance_due'     => 0,
            'notes'           => $notes,
            'internal_notes'   => $internal_notes,
            'created_by'      => $user_id,
            'created_at'      => $now,
            'updated_at'      => $now,
        ] );
        if ( $invoice_id === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to create invoice.', 'el-core' ) ], 500 );
        }

        $line_items_raw = isset( $_POST['line_items'] ) ? wp_unslash( $_POST['line_items'] ) : '';
        if ( is_string( $line_items_raw ) ) {
            $line_items_raw = json_decode( $line_items_raw, true );
        }
        $line_items = is_array( $line_items_raw ) ? $line_items_raw : [];
        foreach ( $line_items as $i => $item ) {
            $product_id  = absint( $item['product_id'] ?? 0 );
            $description = isset( $item['description'] ) ? sanitize_text_field( $item['description'] ) : '';
            if ( $description === '' ) {
                continue;
            }
            $quantity  = isset( $item['quantity'] ) ? (float) $item['quantity'] : 1;
            $unit_price = isset( $item['unit_price'] ) ? (float) $item['unit_price'] : 0;
            $amount    = round( $quantity * $unit_price, 2 );
            $this->core->database->insert( 'el_inv_line_items', [
                'invoice_id'  => $invoice_id,
                'product_id'  => $product_id,
                'description' => $description,
                'quantity'    => $quantity,
                'unit_price'  => $unit_price,
                'amount'      => $amount,
                'sort_order'  => $i,
            ] );
        }
        $this->recalc_invoice_totals( $invoice_id );
        EL_AJAX_Handler::success( [ 'invoice_id' => $invoice_id, 'invoice_number' => $invoice_number ] );
    }

    public function handle_update_invoice( array $data ): void {
        if ( ! current_user_can( 'create_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $invoice_id = absint( $data['invoice_id'] ?? 0 );
        if ( ! $invoice_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid invoice.', 'el-core' ) ], 400 );
        }
        $existing = $this->core->database->query( 'el_inv_invoices', [ 'id' => $invoice_id ], [ 'limit' => 1 ] );
        if ( empty( $existing ) ) {
            wp_send_json_error( [ 'message' => __( 'Invoice not found.', 'el-core' ) ], 404 );
        }
        $organization_id = absint( $data['organization_id'] ?? 0 );
        if ( ! $organization_id ) {
            wp_send_json_error( [ 'message' => __( 'Please select a client (organization).', 'el-core' ) ], 400 );
        }
        $contact_id = absint( $data['contact_id'] ?? 0 );
        $project_id = absint( $data['project_id'] ?? 0 );
        $issue_date = isset( $data['issue_date'] ) ? sanitize_text_field( $data['issue_date'] ) : null;
        $due_date   = isset( $data['due_date'] ) ? sanitize_text_field( $data['due_date'] ) : null;
        $tax_rate   = isset( $data['tax_rate'] ) ? (float) $data['tax_rate'] : 0;
        $notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
        $internal_notes = isset( $_POST['internal_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_notes'] ) ) : '';

        $this->core->database->update( 'el_inv_invoices', [
            'organization_id' => $organization_id,
            'contact_id'     => $contact_id,
            'project_id'     => $project_id,
            'issue_date'     => $issue_date,
            'due_date'       => $due_date,
            'tax_rate'       => $tax_rate,
            'notes'          => $notes,
            'internal_notes' => $internal_notes,
            'updated_at'     => current_time( 'mysql' ),
        ], [ 'id' => $invoice_id ] );

        // Replace line items: delete existing, insert new
        $this->core->database->delete( 'el_inv_line_items', [ 'invoice_id' => $invoice_id ] );
        $line_items_raw = isset( $_POST['line_items'] ) ? wp_unslash( $_POST['line_items'] ) : '';
        if ( is_string( $line_items_raw ) ) {
            $line_items_raw = json_decode( $line_items_raw, true );
        }
        $line_items = is_array( $line_items_raw ) ? $line_items_raw : [];
        foreach ( $line_items as $i => $item ) {
            $product_id  = absint( $item['product_id'] ?? 0 );
            $description = isset( $item['description'] ) ? sanitize_text_field( $item['description'] ) : '';
            if ( $description === '' ) {
                continue;
            }
            $quantity   = isset( $item['quantity'] ) ? (float) $item['quantity'] : 1;
            $unit_price = isset( $item['unit_price'] ) ? (float) $item['unit_price'] : 0;
            $amount     = round( $quantity * $unit_price, 2 );
            $this->core->database->insert( 'el_inv_line_items', [
                'invoice_id'  => $invoice_id,
                'product_id'  => $product_id,
                'description' => $description,
                'quantity'    => $quantity,
                'unit_price'  => $unit_price,
                'amount'      => $amount,
                'sort_order'  => $i,
            ] );
        }
        $this->recalc_invoice_totals( $invoice_id );
        EL_AJAX_Handler::success( [ 'invoice_id' => $invoice_id ] );
    }

    public function handle_delete_invoice( array $data ): void {
        if ( ! current_user_can( 'manage_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $invoice_id = absint( $data['invoice_id'] ?? 0 );
        if ( ! $invoice_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid invoice.', 'el-core' ) ], 400 );
        }
        $result = $this->core->database->update( 'el_inv_invoices', [
            'status'     => 'cancelled',
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $invoice_id ] );
        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to cancel invoice.', 'el-core' ) ], 500 );
        }
        EL_AJAX_Handler::success( [ 'message' => __( 'Invoice cancelled.', 'el-core' ) ] );
    }

    public function handle_get_invoice( array $data ): void {
        if ( ! current_user_can( 'view_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $invoice_id = absint( $data['invoice_id'] ?? 0 );
        if ( ! $invoice_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid invoice.', 'el-core' ) ], 400 );
        }
        $invoices = $this->core->database->query( 'el_inv_invoices', [ 'id' => $invoice_id ], [ 'limit' => 1 ] );
        if ( empty( $invoices ) ) {
            wp_send_json_error( [ 'message' => __( 'Invoice not found.', 'el-core' ) ], 404 );
        }
        $invoice = $invoices[0];
        $line_items = $this->core->database->query( 'el_inv_line_items', [ 'invoice_id' => $invoice_id ], [ 'orderby' => 'sort_order', 'order' => 'ASC' ] );
        $payments = $this->core->database->query( 'el_inv_payments', [ 'invoice_id' => $invoice_id ], [ 'orderby' => 'payment_date', 'order' => 'ASC' ] );
        $org = $this->core->organizations->get_organization( (int) $invoice->organization_id );
        $contact = $invoice->contact_id ? $this->core->organizations->get_contact( (int) $invoice->contact_id ) : null;
        EL_AJAX_Handler::success( [
            'invoice'      => $invoice,
            'line_items'   => $line_items,
            'payments'     => $payments,
            'organization' => $org,
            'contact'      => $contact,
        ] );
    }

    public function handle_duplicate_invoice( array $data ): void {
        if ( ! current_user_can( 'create_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $invoice_id = absint( $data['invoice_id'] ?? 0 );
        if ( ! $invoice_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid invoice.', 'el-core' ) ], 400 );
        }
        $invoices = $this->core->database->query( 'el_inv_invoices', [ 'id' => $invoice_id ], [ 'limit' => 1 ] );
        if ( empty( $invoices ) ) {
            wp_send_json_error( [ 'message' => __( 'Invoice not found.', 'el-core' ) ], 404 );
        }
        $src = $invoices[0];
        $line_items = $this->core->database->query( 'el_inv_line_items', [ 'invoice_id' => $invoice_id ], [ 'orderby' => 'sort_order', 'order' => 'ASC' ] );
        $new_number = $this->get_next_invoice_number();
        $user_id = get_current_user_id();
        $now = current_time( 'mysql' );
        $new_id = $this->core->database->insert( 'el_inv_invoices', [
            'organization_id' => $src->organization_id,
            'contact_id'      => $src->contact_id,
            'project_id'      => $src->project_id,
            'invoice_number'  => $new_number,
            'status'          => 'draft',
            'issue_date'      => null,
            'due_date'        => null,
            'paid_date'       => null,
            'subtotal'        => $src->subtotal,
            'tax_rate'        => $src->tax_rate,
            'tax_amount'      => $src->tax_amount,
            'total'           => $src->total,
            'amount_paid'     => 0,
            'balance_due'     => $src->total,
            'notes'           => $src->notes,
            'internal_notes'  => $src->internal_notes,
            'sent_at'         => null,
            'viewed_at'       => null,
            'created_by'      => $user_id,
            'created_at'      => $now,
            'updated_at'      => $now,
        ] );
        if ( $new_id === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to duplicate invoice.', 'el-core' ) ], 500 );
        }
        foreach ( $line_items as $i => $row ) {
            $this->core->database->insert( 'el_inv_line_items', [
                'invoice_id'  => $new_id,
                'product_id'  => $row->product_id,
                'description' => $row->description,
                'quantity'    => $row->quantity,
                'unit_price'  => $row->unit_price,
                'amount'      => $row->amount,
                'sort_order'  => $i,
            ] );
        }
        EL_AJAX_Handler::success( [ 'invoice_id' => $new_id, 'invoice_number' => $new_number ] );
    }

    public function handle_send_invoice( array $data ): void    { $this->ajax_not_implemented( 'create_invoices' ); }

    public function handle_record_payment( array $data ): void {
        if ( ! current_user_can( 'manage_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $invoice_id = absint( $data['invoice_id'] ?? 0 );
        if ( ! $invoice_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid invoice.', 'el-core' ) ], 400 );
        }
        $invoices = $this->core->database->query( 'el_inv_invoices', [ 'id' => $invoice_id ], [ 'limit' => 1 ] );
        if ( empty( $invoices ) ) {
            wp_send_json_error( [ 'message' => __( 'Invoice not found.', 'el-core' ) ], 404 );
        }
        $inv = $invoices[0];
        $balance_due = (float) $inv->balance_due;
        $amount = isset( $data['amount'] ) ? round( (float) $data['amount'], 2 ) : 0;
        if ( $amount <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Payment amount must be greater than zero.', 'el-core' ) ], 400 );
        }
        if ( $amount > $balance_due ) {
            wp_send_json_error( [ 'message' => __( 'Payment amount cannot exceed balance due.', 'el-core' ) ], 400 );
        }
        $method = in_array( $data['payment_method'] ?? '', [ 'check', 'ach', 'wire', 'zelle', 'other' ], true ) ? $data['payment_method'] : 'other';
        $payment_date = ! empty( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : current_time( 'Y-m-d' );
        $reference = isset( $data['reference_number'] ) ? sanitize_text_field( $data['reference_number'] ) : '';
        $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
        $user_id = get_current_user_id();
        $payment_id = $this->core->database->insert( 'el_inv_payments', [
            'invoice_id'       => $invoice_id,
            'amount'           => $amount,
            'payment_method'   => $method,
            'payment_date'     => $payment_date,
            'reference_number' => $reference,
            'notes'            => $notes,
            'recorded_by'      => $user_id,
        ] );
        if ( $payment_id === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to record payment.', 'el-core' ) ], 500 );
        }
        $this->recalc_invoice_from_payments( $invoice_id );
        EL_AJAX_Handler::success( [ 'message' => __( 'Payment recorded.', 'el-core' ), 'payment_id' => $payment_id ] );
    }

    public function handle_delete_payment( array $data ): void {
        if ( ! current_user_can( 'manage_invoices' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $payment_id = absint( $data['payment_id'] ?? 0 );
        if ( ! $payment_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid payment.', 'el-core' ) ], 400 );
        }
        $payments = $this->core->database->query( 'el_inv_payments', [ 'id' => $payment_id ], [ 'limit' => 1 ] );
        if ( empty( $payments ) ) {
            wp_send_json_error( [ 'message' => __( 'Payment not found.', 'el-core' ) ], 404 );
        }
        $payment = $payments[0];
        $invoice_id = (int) $payment->invoice_id;
        $deleted = $this->core->database->delete( 'el_inv_payments', [ 'id' => $payment_id ] );
        if ( $deleted === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to delete payment.', 'el-core' ) ], 500 );
        }
        $this->recalc_invoice_from_payments( $invoice_id );
        EL_AJAX_Handler::success( [ 'message' => __( 'Payment removed.', 'el-core' ) ] );
    }
    public function handle_get_revenue_data( array $data ): void { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_export_csv( array $data ): void     { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_get_client_invoices( array $data ): void { $this->ajax_not_implemented( 'view_invoices' ); }

    /**
     * Stub response for AJAX actions not yet implemented.
     */
    private function ajax_not_implemented( string $capability ): void {
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        wp_send_json_error( [ 'message' => 'Not implemented yet' ], 501 );
    }
}
