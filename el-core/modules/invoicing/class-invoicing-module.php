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

    public function enqueue_admin_assets( string $hook ): void {
        $our_pages = [ 'el-core-invoices', 'el-core-inv-products', 'el-core-inv-revenue' ];
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
    // AJAX STUBS (implemented in later steps)
    // ═══════════════════════════════════════════

    public function handle_create_invoice( array $data ): void   { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_update_invoice( array $data ): void   { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_delete_invoice( array $data ): void   { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_get_invoice( array $data ): void     { $this->ajax_not_implemented( 'view_invoices' ); }
    public function handle_duplicate_invoice( array $data ): void { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_send_invoice( array $data ): void    { $this->ajax_not_implemented( 'create_invoices' ); }
    public function handle_record_payment( array $data ): void  { $this->ajax_not_implemented( 'manage_invoices' ); }
    public function handle_delete_payment( array $data ): void  { $this->ajax_not_implemented( 'manage_invoices' ); }
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
