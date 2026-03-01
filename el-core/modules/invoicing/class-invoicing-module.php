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

    // ═══════════════════════════════════════════
    // AJAX STUBS (implemented in later steps)
    // ═══════════════════════════════════════════

    public function handle_create_invoice(): void   { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_update_invoice(): void   { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_delete_invoice(): void   { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_get_invoice(): void      { $this->ajax_not_implemented( 'view_invoices' ); }
    public function handle_duplicate_invoice(): void { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_send_invoice(): void     { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_record_payment(): void  { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_delete_payment(): void   { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_create_product(): void   { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_update_product(): void   { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_delete_product(): void   { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_get_products(): void    { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_seed_products(): void    { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_get_revenue_data(): void { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_export_csv(): void       { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_get_client_invoices(): void { $this->ajax_not_implemented( 'view_invoices' ); }

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
