<?php
/**
 * ELS Bookkeeping Module
 *
 * Internal bookkeeping tool for Expanded Learning Solutions LLC.
 * Handles expense categorization, income tracking, contractor management,
 * receipt scanning (AI), and Schedule C P&L reporting.
 *
 * Admin-only module. No client-facing shortcodes.
 * CSS prefix: el-bk-
 * Table prefix: el_bk_
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Bookkeeping_Module {

    private static ?EL_Bookkeeping_Module $instance = null;

    /** @var EL_Core|null Core reference; public so admin views can access settings/database. */
    public ?EL_Core $core = null;

    // ─────────────────────────────────────────────────────────────
    // SINGLETON
    // ─────────────────────────────────────────────────────────────

    public static function instance( ?EL_Core $core = null ): self {
        if ( null === self::$instance ) {
            self::$instance = new self( $core );
        } elseif ( $core !== null && self::$instance->core === null ) {
            self::$instance->core = $core;
        }
        return self::$instance;
    }

    private function __construct( ?EL_Core $core = null ) {
        $this->core = $core;
        $this->init_hooks();
    }

    // ─────────────────────────────────────────────────────────────
    // HOOKS
    // ─────────────────────────────────────────────────────────────

    private function init_hooks(): void {
        // Admin menu + assets
        add_action( 'admin_menu',            [ $this, 'register_admin_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // ── Expenses ──────────────────────────────────────────────
        add_action( 'el_core_ajax_bk_import_csv',          [ $this, 'handle_csv_import' ] );
        add_action( 'el_core_ajax_bk_import_ledger',       [ $this, 'handle_import_ledger' ] );
        add_action( 'el_core_ajax_bk_update_transaction',  [ $this, 'handle_update_transaction' ] );
        add_action( 'el_core_ajax_bk_bulk_confirm',        [ $this, 'handle_bulk_confirm' ] );
        add_action( 'el_core_ajax_bk_reclassify',          [ $this, 'handle_reclassify' ] );
        add_action( 'el_core_ajax_bk_export_csv',          [ $this, 'handle_export_csv' ] );
        add_action( 'el_core_ajax_bk_export_pl',           [ $this, 'handle_export_pl' ] );

        // ── Known Expense Rules ───────────────────────────────────
        add_action( 'el_core_ajax_bk_process_rules',       [ $this, 'handle_process_rules' ] );
        add_action( 'el_core_ajax_bk_save_rule',           [ $this, 'handle_save_rule' ] );
        add_action( 'el_core_ajax_bk_delete_rule',         [ $this, 'handle_delete_rule' ] );
        add_action( 'el_core_ajax_bk_bulk_delete_rules',   [ $this, 'handle_bulk_delete_rules' ] );
        add_action( 'el_core_ajax_bk_reorder_rules',       [ $this, 'handle_reorder_rules' ] );
        add_action( 'el_core_ajax_bk_import_rules_csv',    [ $this, 'handle_import_rules_csv' ] );
        add_action( 'el_core_ajax_bk_import_ledger',       [ $this, 'handle_import_ledger' ] );

        // ── Travel Dates ──────────────────────────────────────────
        add_action( 'el_core_ajax_bk_save_travel_period',   [ $this, 'handle_save_travel_period' ] );
        add_action( 'el_core_ajax_bk_delete_travel_period', [ $this, 'handle_delete_travel_period' ] );
        add_action( 'el_core_ajax_bk_reapply_travel_rules', [ $this, 'handle_reapply_travel_rules' ] );

        // ── Receipts ──────────────────────────────────────────────
        add_action( 'el_core_ajax_bk_upload_receipt',      [ $this, 'handle_upload_receipt' ] );
        add_action( 'el_core_ajax_bk_attach_receipt',      [ $this, 'handle_attach_receipt' ] );
        add_action( 'el_core_ajax_bk_detach_receipt',      [ $this, 'handle_detach_receipt' ] );
        add_action( 'el_core_ajax_bk_delete_receipt',      [ $this, 'handle_delete_receipt' ] );

        // ── Contractors ───────────────────────────────────────────
        add_action( 'el_core_ajax_bk_save_contractor',     [ $this, 'handle_save_contractor' ] );
        add_action( 'el_core_ajax_bk_delete_contractor',   [ $this, 'handle_delete_contractor' ] );
        add_action( 'el_core_ajax_bk_assign_contractor',   [ $this, 'handle_assign_contractor' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // ADMIN MENU
    // ─────────────────────────────────────────────────────────────

    public function register_admin_menu(): void {
        add_submenu_page(
            'el-core',
            __( 'ELS Bookkeeping', 'el-core' ),
            __( 'Bookkeeping', 'el-core' ),
            'view_bookkeeping',
            'els-bookkeeping',
            [ $this, 'render_admin_page' ]
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        $our_pages = [ 'el-core_page_els-bookkeeping' ];
        if ( ! in_array( $hook, $our_pages, true ) ) {
            return;
        }

        $base = plugins_url( 'assets/', __FILE__ );
        $ver  = defined( 'EL_CORE_VERSION' ) ? EL_CORE_VERSION : '1.0.0';

        wp_enqueue_style(
            'el-bookkeeping',
            $base . 'css/bookkeeping.css',
            [ 'el-core-admin' ],
            $ver
        );

        wp_enqueue_script(
            'el-bookkeeping',
            $base . 'js/bookkeeping.js',
            [ 'jquery' ],
            $ver,
            true
        );

        wp_localize_script( 'el-bookkeeping', 'elBookkeeping', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'el_core_nonce' ),
            'taxYear' => $this->get_setting( 'tax_year', (int) gmdate( 'Y' ) ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // ADMIN PAGE ROUTER — 8-TAB NAVIGATION
    // ─────────────────────────────────────────────────────────────

    public function render_admin_page(): void {
        if ( ! el_core_can( 'view_bookkeeping' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'el-core' ) );
        }

        $active_tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );

        // Tax year from URL param, falling back to the stored default setting.
        $current_year     = (int) gmdate( 'Y' );
        $default_tax_year = $this->get_setting( 'tax_year', $current_year );
        $selected_year    = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) $default_tax_year;

        // Clamp to a sensible range.
        if ( $selected_year < 2000 || $selected_year > $current_year + 1 ) {
            $selected_year = (int) $default_tax_year;
        }

        // Validate tab; gate settings.
        $valid_tabs = [
            'dashboard', 'expenses', 'income', 'profit-loss', 'contractors',
            'known-expenses', 'travel-dates', 'receipts', 'settings',
        ];
        if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
            $active_tab = 'dashboard';
        }
        if ( $active_tab === 'settings' && ! el_core_can( 'manage_bookkeeping_settings' ) ) {
            $active_tab = 'dashboard';
        }

        $tax_year = $selected_year;

        // ── Pre-fetch DB data BEFORE ob_start so fatals surface cleanly ──────
        $prefetch_expenses    = in_array( $active_tab, [ 'dashboard', 'expenses' ], true )
                                    ? $this->get_transactions( [ 'type' => 'expense', 'tax_year' => $tax_year ] )
                                    : [];
        $prefetch_income      = in_array( $active_tab, [ 'dashboard', 'income' ], true )
                                    ? $this->get_transactions( [ 'type' => 'income', 'tax_year' => $tax_year ] )
                                    : [];
        $prefetch_contractors = in_array( $active_tab, [ 'dashboard', 'contractors' ], true )
                                    ? $this->get_contractors()
                                    : [];
        $prefetch_receipts    = in_array( $active_tab, [ 'dashboard', 'receipts' ], true )
                                    ? $this->get_receipts( 'unreviewed' )
                                    : [];
        $prefetch_contract_labor = ( $active_tab === 'contractors' )
                                    ? $this->get_transactions( [ 'type' => 'expense', 'tax_year' => $tax_year, 'category' => 'Contract Labor' ] )
                                    : [];

        $base_url = admin_url( 'admin.php?page=els-bookkeeping&year=' . $selected_year );

        // ── Year selector ─────────────────────────────────────────────────────
        $year_selector_url = admin_url( 'admin.php?page=els-bookkeeping&tab=' . $active_tab );
        $year_selector  = '<div class="el-bk-year-selector">';
        $year_selector .= '<label for="el-bk-year-select">' . esc_html__( 'Tax Year:', 'el-core' ) . '</label>';
        $year_selector .= '<select id="el-bk-year-select" onchange="window.location=\'' . esc_js( $year_selector_url ) . '&year=\'+this.value">';
        for ( $y = $current_year + 1; $y >= 2020; $y-- ) {
            $year_selector .= '<option value="' . esc_attr( $y ) . '"' . selected( $y, $selected_year, false ) . '>' . esc_html( $y ) . '</option>';
        }
        $year_selector .= '</select></div>';

        // ── Tab navigation ────────────────────────────────────────────────────
        $tabs = [
            'dashboard'      => __( 'Dashboard', 'el-core' ),
            'expenses'       => __( 'Expenses', 'el-core' ),
            'income'         => __( 'Income & Deposits', 'el-core' ),
            'profit-loss'    => __( 'Profit & Loss', 'el-core' ),
            'contractors'    => __( 'Contractors', 'el-core' ),
            'known-expenses' => __( 'Known Expenses', 'el-core' ),
            'travel-dates'   => __( 'Travel Dates', 'el-core' ),
            'receipts'       => __( 'Receipts', 'el-core' ),
            'settings'       => __( 'Settings', 'el-core' ),
        ];

        $tab_html = '<nav class="el-bk-tab-nav" role="navigation">';
        foreach ( $tabs as $slug => $label ) {
            if ( $slug === 'settings' && ! el_core_can( 'manage_bookkeeping_settings' ) ) {
                continue;
            }
            $is_active = ( $slug === $active_tab );
            $classes   = 'el-bk-tab-btn' . ( $is_active ? ' el-bk-tab-btn--active' : '' );
            $url       = esc_url( $base_url . '&tab=' . $slug );
            $tab_html .= '<a href="' . $url . '" class="' . $classes . '"' . ( $is_active ? ' aria-current="page"' : '' ) . '>'
                       . esc_html( $label ) . '</a>';
        }
        $tab_html .= '</nav>';

        // ── Render content ────────────────────────────────────────────────────
        $view_file = __DIR__ . '/admin/views/' . $active_tab . '.php';

        ob_start();
        echo $year_selector; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $tab_html;      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="el-bk-tab-content">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( file_exists( $view_file ) ) {
            $module = $this;
            include $view_file;
        } else {
            echo '<p>' . esc_html__( 'View not found.', 'el-core' ) . '</p>';
        }
        echo '</div>';

        // ── Shared Bank Statement Upload Modal ──────────────────────────────────
        echo '<div id="el-bk-csv-upload-modal" class="el-bk-modal" style="display:none;">'; // phpcs:ignore
        echo '<div class="el-bk-modal-backdrop"></div>'; // phpcs:ignore
        echo '<div class="el-bk-modal-content el-bk-card">'; // phpcs:ignore
        echo '<h3 id="el-bk-csv-modal-title">' . esc_html__( 'Upload Bank Statement', 'el-core' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Upload one or more monthly CSV files from your bank. Deposits become income, charges become expenses (auto-classified by your rules and travel dates).', 'el-core' ) . '</p>';

        echo '<div id="el-bk-csv-step1">';
        echo '<div class="el-bk-form-row">';
        echo '<label>' . esc_html__( 'Bank Account:', 'el-core' ) . '<br>';
        echo '<input type="text" id="el-bk-csv-bank-input" class="el-input" list="el-bk-csv-bank-list" placeholder="' . esc_attr__( 'e.g. Chase Business, Wells Fargo Personal', 'el-core' ) . '" style="width:100%;max-width:320px;">';
        echo '<datalist id="el-bk-csv-bank-list"></datalist>';
        echo '</label></div>';
        echo '<div class="el-bk-form-row" style="margin-top:8px;">';
        echo '<label>' . esc_html__( 'CSV Files (select one or more):', 'el-core' ) . ' <input type="file" id="el-bk-csv-txn-file" accept=".csv" multiple></label>';
        echo '</div>';
        echo '<div class="el-bk-form-actions" style="margin-top:12px;">';
        echo '<button class="el-btn el-btn-primary" id="el-bk-csv-txn-upload-btn" disabled>' . esc_html__( 'Upload & Map Columns', 'el-core' ) . '</button>';
        echo '<button class="el-btn el-btn-outline el-bk-csv-modal-close">' . esc_html__( 'Cancel', 'el-core' ) . '</button>';
        echo '</div></div>';

        echo '<div id="el-bk-csv-step2" style="display:none;">';
        echo '<p><strong>' . esc_html__( 'Map your CSV columns:', 'el-core' ) . '</strong></p>';
        echo '<p class="description">' . esc_html__( 'These mappings will be used for all selected files.', 'el-core' ) . '</p>';
        echo '<div class="el-bk-form-row">';
        echo '<label>' . esc_html__( 'Date column:', 'el-core' ) . ' <select id="el-bk-csv-date-col" class="el-select"></select></label>';
        echo '<label>' . esc_html__( 'Amount column:', 'el-core' ) . ' <select id="el-bk-csv-amount-col" class="el-select"></select></label>';
        echo '<label>' . esc_html__( 'Merchant / Description:', 'el-core' ) . ' <select id="el-bk-csv-merchant-txn-col" class="el-select"></select></label>';
        echo '</div>';
        echo '<div class="el-bk-form-actions">';
        echo '<button class="el-btn el-btn-primary" id="el-bk-csv-txn-import-btn">' . esc_html__( 'Import All Files', 'el-core' ) . '</button>';
        echo '<button class="el-btn el-btn-outline el-bk-csv-modal-close">' . esc_html__( 'Cancel', 'el-core' ) . '</button>';
        echo '</div></div>';

        echo '<div id="el-bk-csv-progress" style="display:none; margin-top:10px;"></div>';
        echo '<div id="el-bk-csv-result" style="display:none;"></div>';
        echo '</div></div>';

        $content = ob_get_clean();

        echo EL_Admin_UI::wrap( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            EL_Admin_UI::page_header( [
                'title'    => __( 'ELS Bookkeeping', 'el-core' ),
                'subtitle' => sprintf( __( '%d — Expense tracking, income, contractors, and Schedule C reporting.', 'el-core' ), $selected_year ),
            ] )
            . $content
        );
    }

    // ─────────────────────────────────────────────────────────────
    // SETTINGS HELPERS
    // ─────────────────────────────────────────────────────────────

    public function get_setting( string $key, mixed $default = null ): mixed {
        if ( ! $this->core ) return $default;
        return $this->core->settings->get( 'mod_bookkeeping', $key, $default );
    }

    public function get_tax_year(): int {
        return (int) $this->get_setting( 'tax_year', (int) gmdate( 'Y' ) );
    }

    public function get_business_name(): string {
        return (string) $this->get_setting( 'business_name', 'Expanded Learning Solutions LLC' );
    }

    // ─────────────────────────────────────────────────────────────
    // IRS SCHEDULE C CATEGORIES
    // ─────────────────────────────────────────────────────────────

    public static function get_expense_categories(): array {
        return [
            'Accounting Fees',
            'Advertising & Promotion',
            'Bank Service Charges',
            'Computer - Hardware',
            'Computer - Hosting',
            'Computer - Software',
            'Dues & Subscriptions',
            'Education & Training',
            'Health Care Insurance',
            'Insurance-General Liability',
            'Vehicles Insurance',
            'Interest Expense',
            'Meals & Entertainment',
            'Merchant Account Fees',
            'Office Supplies',
            'Out of pocket Medical Expenses',
            'Parking & tolls',
            'Professional Fees',
            'Rent Expense',
            'Telephone - Wireless',
            'Travel Expense',
            'Vehicle - Fuel',
            'Vehicle - Repairs and Maintenance',
            'Contract Labor',
            'Owner Draw',
        ];
    }

    public static function get_income_categories(): array {
        return [
            'Income - Expanded Learning Solutions',
            'Retreats',
            'LMS Licensing',
            'Professional Development',
            'NYC SMV Tool',
            'Other',
            'Bank Transfer',
            'Ignore',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // DATABASE HELPERS
    // ─────────────────────────────────────────────────────────────

    private function table( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    public function get_transactions( array $args = [] ): array {
        global $wpdb;
        $table = $this->table( 'el_bk_transactions' );

        $where    = [ '1=1' ];
        $values   = [];
        $type     = sanitize_text_field( $args['type']        ?? 'expense' );
        $tax_year = absint(              $args['tax_year']    ?? $this->get_tax_year() );
        $status   = sanitize_text_field( $args['status']      ?? '' );
        $category = sanitize_text_field( $args['category']    ?? '' );
        $search   = sanitize_text_field( $args['search']      ?? '' );
        $limit    = absint(              $args['limit']        ?? 500 );
        $offset   = absint(              $args['offset']       ?? 0 );

        $where[]  = 'type = %s';
        $values[] = $type;

        $where[]  = 'tax_year = %d';
        $values[] = $tax_year;

        if ( $status ) {
            $where[]  = 'status = %s';
            $values[] = $status;
        }
        if ( $category ) {
            $where[]  = 'category = %s';
            $values[] = $category;
        }
        if ( $search ) {
            $where[]  = 'merchant LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $where_sql = implode( ' AND ', $where );
        $all_values = array_merge( $values, [ $limit, $offset ] );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY date DESC LIMIT %d OFFSET %d",
            ...$all_values
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $query ) ?: [];
    }

    public function get_transaction( int $id ): ?object {
        global $wpdb;
        $table = $this->table( 'el_bk_transactions' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null;
    }

    public function get_rules(): array {
        global $wpdb;
        $table = $this->table( 'el_bk_rules' );
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY priority ASC" ) ?: [];
    }

    public function get_travel_periods(): array {
        global $wpdb;
        $table = $this->table( 'el_bk_travel_periods' );
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY start_date ASC" ) ?: [];
    }

    public function get_receipts( string $status = '' ): array {
        global $wpdb;
        $table = $this->table( 'el_bk_receipts' );
        if ( $status ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC",
                $status
            ) ) ?: [];
        }
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" ) ?: [];
    }

    public function get_contractors(): array {
        global $wpdb;
        $table = $this->table( 'el_bk_contractors' );
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ) ?: [];
    }

    // ─────────────────────────────────────────────────────────────
    // MERCHANT NAME CLEANER
    // ─────────────────────────────────────────────────────────────

    /**
     * Clean a raw bank-statement merchant description into a human-readable name.
     *
     * Strips CHECKCARD/PURCHASE/DEBIT prefixes, date codes, masked card numbers,
     * CKCD codes, RECURRING flags, phone numbers, long numeric sequences, and
     * URLs — then title-cases the result.
     *
     * State codes are PRESERVED (e.g. "GA", "NY") so rules can distinguish
     * location-based categories (hometown meals = owner draw vs. travel meals).
     *
     * @param string $raw  Raw description from a bank CSV.
     * @return string      Cleaned merchant name, or empty string for fee rows.
     */
    public static function clean_merchant_name( string $raw ): string {
        $s = trim( $raw );
        if ( $s === '' ) {
            return '';
        }

        if ( stripos( $s, 'INTERNATIONAL TRANSACTION FEE' ) !== false ) {
            return '';
        }

        $s = preg_replace( '/^(?:CHECKCARD|PURCHASE|DEBIT)\s+\d{4}\s+/i', '', $s );
        $s = preg_replace( '/^(?:CHECKCARD|PURCHASE|DEBIT)\s+/i', '', $s );
        $s = preg_replace( '/\bRECURRING\b/i', '', $s );
        $s = preg_replace( '/\bCKCD\s+\d{4}\b/i', '', $s );
        $s = preg_replace( '/X{5,}\d*/', '', $s );
        $s = preg_replace( '/[X\d]{3}[-.]?[X\d]{2,4}[-.]?[X\d]{4,7}/', '', $s );
        $s = preg_replace( '/\b\d{10,}\b/', '', $s );

        // State codes are intentionally NOT stripped — they carry location info
        // needed for rules like "Chick-fil-A GA = Owner Draw" vs "Chick-fil-A NY = Meals"

        $s = preg_replace( '/HTTPS?(?:WWW\.)?/i', '', $s );
        $s = preg_replace( '/^WEB\*/i', '', $s );
        $s = preg_replace( '/^DRI\*/i', '', $s );
        $s = preg_replace( '/\s*\*\s*/', ' ', $s );
        $s = preg_replace( '#/(?:CHARGE|PAY|BILL|PAYMENT)\b#i', '', $s );
        $s = preg_replace( '/\bMSBILL\.INFO\b/i', '', $s );

        // Strip mixed alphanumeric reference codes (4+ chars with both letters and digits)
        $s = preg_replace( '/\b(?=[A-Za-z]*\d)(?=\d*[A-Za-z])[A-Za-z\d]{4,}\b/', '', $s );

        // Strip standalone short numeric codes (1-5 digits)
        $s = preg_replace( '/(?<![.\w])\b\d{1,5}\b(?![.\w])/', '', $s );

        // Strip "Inc.", "LLC", "Corp." suffixes
        $s = preg_replace( '/\b(?:Inc\.?|LLC|Corp\.?)\b/i', '', $s );

        // Strip standalone punctuation left behind
        $s = preg_replace( '/(?<!\w)[#.]+(?!\w)/', '', $s );

        // Strip duplicate domain: if "Merchant" and "Merchant.com" both appear, drop the domain
        if ( preg_match( '/\b(\w+)\.(?:com|net|org|io|co)\b/i', $s, $dm ) ) {
            $domain_full = $dm[0];
            $domain_name = $dm[1];
            if ( preg_match( '/\b' . preg_quote( $domain_name, '/' ) . '\b/i', str_replace( $domain_full, '', $s ) ) ) {
                $s = str_ireplace( $domain_full, '', $s );
            }
        }

        // Known URL-mangled merchant names — run all patterns (no early exit)
        $url_merchants = [
            'ACUITYSCHEDULING\.COM' => 'Acuity Scheduling',
            'ACUITYSC\w*'           => 'Acuity Scheduling',
            'LOOMCA\b'              => 'Loom',
            'FRANCISCOCA\b'         => 'Francisco Ca',
        ];
        foreach ( $url_merchants as $regex => $name ) {
            $s = preg_replace( '/' . $regex . '/i', $name, $s );
        }

        $s = preg_replace( '/\s+/', ' ', $s );
        $s = trim( $s );

        // Deduplicate repeated tokens (case-insensitive)
        $tokens = explode( ' ', $s );
        $seen   = [];
        $unique = [];
        foreach ( $tokens as $t ) {
            $key = strtolower( $t );
            if ( ! isset( $seen[ $key ] ) ) {
                $seen[ $key ] = true;
                $unique[]     = $t;
            }
        }
        $s = implode( ' ', $unique );

        if ( $s === '' ) {
            return '';
        }

        // Domain-like result (e.g. Hostgator.com) — title-case name, lowercase extension
        if ( preg_match( '/\.\w{2,3}$/', $s ) ) {
            $parts = explode( '.', $s );
            $ext   = array_pop( $parts );
            $name  = implode( '.', $parts );
            $name  = mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' );
            return $name . '.' . strtolower( $ext );
        }

        return mb_convert_case( strtolower( $s ), MB_CASE_TITLE, 'UTF-8' );
    }

    // ─────────────────────────────────────────────────────────────
    // AUTO-CLASSIFICATION
    // ─────────────────────────────────────────────────────────────

    /**
     * Attempt to auto-classify a transaction.
     * Returns [ 'category' => string, 'source' => 'travel'|'rule'|'', 'travel_period_id' => int ]
     */
    public function auto_classify( string $merchant, string $date ): array {
        // Step 1 — Travel Date Rules
        $travel = $this->match_travel_period( $date );
        if ( $travel ) {
            $category = $this->map_travel_category( $merchant );
            return [
                'category'         => $category,
                'source'           => 'travel',
                'travel_period_id' => (int) $travel->id,
            ];
        }

        // Step 2 — Known Expense Rules
        // Clean the merchant so raw bank descriptions match cleaned rule keywords
        $cleaned   = self::clean_merchant_name( $merchant );
        $haystack  = strtolower( $cleaned ?: $merchant );
        $raw_lower = strtolower( $merchant );

        $rules = $this->get_rules();
        foreach ( $rules as $rule ) {
            $keyword = strtolower( $rule->keyword );

            // Bidirectional contains — works for simple keywords
            $contains_match = str_contains( $haystack, $keyword )
                           || str_contains( $keyword, $haystack )
                           || str_contains( $raw_lower, $keyword )
                           || str_contains( $keyword, $raw_lower );

            $matched = match ( $rule->match_type ) {
                'exact'      => $haystack === $keyword,
                'all_words'  => $contains_match
                             || self::match_all_words( $keyword, $haystack )
                             || self::match_all_words( $keyword, $raw_lower ),
                default      => $contains_match,
            };
            if ( $matched ) {
                return [
                    'category'         => $rule->category,
                    'source'           => 'rule',
                    'travel_period_id' => 0,
                ];
            }
        }

        return [ 'category' => '', 'source' => '', 'travel_period_id' => 0 ];
    }

    /**
     * Check if ALL words in $keyword appear somewhere in $haystack.
     * Both strings should already be lowercased.
     */
    private static function match_all_words( string $keyword, string $haystack ): bool {
        $words = preg_split( '/\s+/', trim( $keyword ) );
        if ( empty( $words ) ) {
            return false;
        }
        foreach ( $words as $word ) {
            if ( $word === '' ) {
                continue;
            }
            if ( ! str_contains( $haystack, $word ) ) {
                return false;
            }
        }
        return true;
    }

    private function match_travel_period( string $date ): ?object {
        global $wpdb;
        $table = $this->table( 'el_bk_travel_periods' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE start_date <= %s AND end_date >= %s LIMIT 1",
            $date, $date
        ) ) ?: null;
    }

    private function map_travel_category( string $merchant ): string {
        $merchant_upper = strtoupper( $merchant );

        $airlines = [ 'AIRLINE', 'DELTA', 'UNITED', 'AMERICAN', 'SOUTHWEST', 'SPIRIT', 'JETBLUE', 'FRONTIER' ];
        $hotels   = [ 'HOTEL', 'MARRIOTT', 'HILTON', 'HYATT', 'IHG', 'WESTIN', 'AIRBNB', 'VRBO' ];
        $ground   = [ 'UBER', 'LYFT', 'TAXI', 'CAB', 'PARKING', 'GARAGE' ];
        $meals    = [ 'RESTAURANT', 'CAFE', 'COFFEE', 'MCDONALD', 'CHICK-FIL', 'SUBWAY', 'STARBUCKS', 'DUNKIN', 'DOORDASH', 'GRUBHUB', 'UBEREATS' ];
        $gas      = [ 'GAS', 'SHELL', 'EXXON', 'CHEVRON', 'BP', 'SUNOCO' ];

        foreach ( $airlines as $kw ) { if ( str_contains( $merchant_upper, $kw ) ) return 'Travel Expense'; }
        foreach ( $hotels   as $kw ) { if ( str_contains( $merchant_upper, $kw ) ) return 'Travel Expense'; }
        foreach ( $ground   as $kw ) { if ( str_contains( $merchant_upper, $kw ) ) return 'Travel Expense'; }
        foreach ( $meals    as $kw ) { if ( str_contains( $merchant_upper, $kw ) ) return 'Meals & Entertainment'; }
        foreach ( $gas      as $kw ) { if ( str_contains( $merchant_upper, $kw ) ) return 'Vehicle - Fuel'; }

        return 'Travel Expense';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS — EXPENSES
    // ─────────────────────────────────────────────────────────────

    public function handle_csv_import( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        if ( empty( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            EL_AJAX_Handler::error( __( 'No file uploaded or upload error.', 'el-core' ) );
            return;
        }

        $file = $_FILES['csv_file'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'csv' ) {
            EL_AJAX_Handler::error( __( 'Only CSV files are accepted.', 'el-core' ) );
            return;
        }

        $bank_account = sanitize_text_field( $data['bank_account'] ?? '' );
        $date_col     = sanitize_text_field( $data['date_col']     ?? '' );
        $amount_col   = sanitize_text_field( $data['amount_col']   ?? '' );
        $merchant_col = sanitize_text_field( $data['merchant_col'] ?? '' );

        $handle = fopen( $file['tmp_name'], 'r' );
        if ( ! $handle ) {
            EL_AJAX_Handler::error( __( 'Could not read the uploaded file.', 'el-core' ) );
            return;
        }

        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'CSV file appears to be empty.', 'el-core' ) );
            return;
        }
        $header = array_map( 'trim', $header );

        // Step 1: If columns not yet mapped, return header for mapping UI
        if ( empty( $date_col ) || empty( $amount_col ) || empty( $merchant_col ) ) {
            fclose( $handle );

            global $wpdb;
            $accounts = $wpdb->get_col(
                "SELECT DISTINCT bank_account FROM {$this->table('el_bk_transactions')} WHERE bank_account != '' ORDER BY bank_account ASC"
            );

            EL_AJAX_Handler::success( [
                'step'     => 'map_columns',
                'columns'  => $header,
                'accounts' => $accounts ?: [],
                'filename' => basename( $file['name'] ),
            ], __( 'Please map the columns and select a bank account.', 'el-core' ) );
            return;
        }

        // Step 2: Import with mapped columns
        if ( empty( $bank_account ) ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'Bank account name is required.', 'el-core' ) );
            return;
        }

        $date_idx     = array_search( $date_col, $header, true );
        $amount_idx   = array_search( $amount_col, $header, true );
        $merchant_idx = array_search( $merchant_col, $header, true );

        if ( $date_idx === false || $amount_idx === false || $merchant_idx === false ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'Could not find the specified columns in the CSV header.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table       = $this->table( 'el_bk_transactions' );
        $source_file = sanitize_file_name( $file['name'] );

        $income_imported  = 0;
        $expense_imported = 0;
        $classified       = 0;
        $skipped          = 0;

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $raw_date   = trim( $row[ $date_idx ]     ?? '' );
            $raw_amount = trim( $row[ $amount_idx ]    ?? '' );
            $merchant   = trim( $row[ $merchant_idx ]  ?? '' );

            if ( empty( $raw_date ) || empty( $raw_amount ) || empty( $merchant ) ) {
                $skipped++;
                continue;
            }

            $date = $this->parse_csv_date( $raw_date );
            if ( ! $date ) {
                $skipped++;
                continue;
            }

            $amount = $this->parse_csv_amount( $raw_amount );
            if ( $amount === null || $amount == 0 ) {
                $skipped++;
                continue;
            }

            // Auto-sort: positive = income, negative = expense
            if ( $amount >= 0 ) {
                $type       = 'income';
                $store_amt  = $amount;
            } else {
                $type       = 'expense';
                $store_amt  = abs( $amount );
            }

            $txn_year = (int) substr( $date, 0, 4 );

            // Duplicate detection
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE date = %s AND amount = %f AND merchant = %s AND bank_account = %s AND type = %s",
                $date, $store_amt, $merchant, $bank_account, $type
            ) );

            if ( (int) $exists > 0 ) {
                $skipped++;
                continue;
            }

            // Auto-classify expenses using rules + travel dates
            $category         = '';
            $status           = 'unclassified';
            $travel_period_id = 0;

            if ( $type === 'expense' ) {
                $classification   = $this->auto_classify( $merchant, $date );
                $category         = $classification['category'];
                $status           = $category ? 'suggested' : 'unclassified';
                $travel_period_id = $classification['travel_period_id'];
                if ( $category ) {
                    $classified++;
                }
            }

            $wpdb->insert( $table, [
                'type'             => $type,
                'date'             => $date,
                'merchant'         => $merchant,
                'amount'           => $store_amt,
                'category'         => $category,
                'bank_account'     => $bank_account,
                'business'         => $this->get_business_name(),
                'status'           => $status,
                'comments'         => '',
                'source_file'      => $source_file,
                'tax_year'         => $txn_year,
                'travel_period_id' => $travel_period_id,
                'receipt_id'       => 0,
            ] );

            if ( $type === 'income' ) {
                $income_imported++;
            } else {
                $expense_imported++;
            }
        }

        fclose( $handle );

        $total_imported = $income_imported + $expense_imported;
        $message = sprintf(
            __( 'Import complete: %1$d income, %2$d expenses (%3$d auto-classified), %4$d skipped.', 'el-core' ),
            $income_imported,
            $expense_imported,
            $classified,
            $skipped
        );

        EL_AJAX_Handler::success( [
            'imported'         => $total_imported,
            'income_imported'  => $income_imported,
            'expense_imported' => $expense_imported,
            'classified'       => $classified,
            'skipped'          => $skipped,
        ], $message );
    }

    /**
     * Parse a date string from CSV into Y-m-d format.
     * Handles: M/D/YYYY, MM/DD/YYYY, YYYY-MM-DD, M-D-YYYY, etc.
     */
    private function parse_csv_date( string $raw ): string {
        $raw = trim( $raw );

        // Already ISO format
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            return $raw;
        }

        $ts = strtotime( $raw );
        if ( $ts && $ts > 0 ) {
            return date( 'Y-m-d', $ts );
        }

        return '';
    }

    /**
     * Parse an amount string from CSV into a float.
     * Handles: $1,234.56  (1,234.56)  -1234.56  1234.56  etc.
     */
    private function parse_csv_amount( string $raw ): ?float {
        $raw = trim( $raw );
        if ( $raw === '' ) {
            return null;
        }

        $negative = false;
        if ( str_starts_with( $raw, '(' ) && str_ends_with( $raw, ')' ) ) {
            $negative = true;
            $raw = trim( $raw, '()' );
        }
        if ( str_starts_with( $raw, '-' ) ) {
            $negative = true;
            $raw = ltrim( $raw, '-' );
        }

        $raw = str_replace( [ '$', ',', ' ' ], '', $raw );

        if ( ! is_numeric( $raw ) ) {
            return null;
        }

        $val = (float) $raw;
        return $negative ? -$val : $val;
    }

    /**
     * Import a single-category ledger tab CSV.
     * Each file = one category. Columns: Date, Description, Amount.
     * Creates transactions AND rules from unique merchants.
     */
    public function handle_import_ledger( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        if ( empty( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            EL_AJAX_Handler::error( __( 'No file uploaded or upload error.', 'el-core' ) );
            return;
        }

        $file = $_FILES['csv_file'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'csv' ) {
            EL_AJAX_Handler::error( __( 'Only CSV files are accepted.', 'el-core' ) );
            return;
        }

        $category     = sanitize_text_field( $data['category']     ?? '' );
        $date_col     = sanitize_text_field( $data['date_col']     ?? '' );
        $amount_col   = sanitize_text_field( $data['amount_col']   ?? '' );
        $merchant_col = sanitize_text_field( $data['merchant_col'] ?? '' );
        $bank_account = sanitize_text_field( $data['bank_account'] ?? '' );

        $handle = fopen( $file['tmp_name'], 'r' );
        if ( ! $handle ) {
            EL_AJAX_Handler::error( __( 'Could not read the uploaded file.', 'el-core' ) );
            return;
        }

        // Skip non-data header rows (lines that don't look like CSV column headers)
        $header = null;
        $max_skip = 20;
        while ( $max_skip-- > 0 ) {
            $row = fgetcsv( $handle );
            if ( $row === false ) {
                break;
            }
            $row = array_map( 'trim', $row );
            // A valid header has at least 2 non-empty cells
            $non_empty = count( array_filter( $row, fn( $c ) => $c !== '' ) );
            if ( $non_empty >= 2 ) {
                // Check if this looks like a data header (contains common keywords)
                $joined = strtolower( implode( ' ', $row ) );
                if ( preg_match( '/date|description|amount|debit|credit|merchant|memo/i', $joined ) ) {
                    $header = $row;
                    break;
                }
            }
        }

        if ( ! $header ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'Could not find a valid column header row in the CSV.', 'el-core' ) );
            return;
        }

        // Step 1: If columns not yet mapped, return header for mapping UI
        if ( empty( $date_col ) || empty( $amount_col ) || empty( $merchant_col ) || empty( $category ) ) {
            fclose( $handle );

            EL_AJAX_Handler::success( [
                'step'       => 'map_columns',
                'columns'    => $header,
                'categories' => self::get_expense_categories(),
            ], __( 'Map the columns and select a category.', 'el-core' ) );
            return;
        }

        // Validate category
        if ( ! in_array( $category, self::get_expense_categories(), true ) ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'Invalid category selected.', 'el-core' ) );
            return;
        }

        $date_idx     = array_search( $date_col, $header, true );
        $amount_idx   = array_search( $amount_col, $header, true );
        $merchant_idx = array_search( $merchant_col, $header, true );

        if ( $date_idx === false || $amount_idx === false || $merchant_idx === false ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'Could not find the specified columns in the CSV header.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table       = $this->table( 'el_bk_transactions' );
        $source_file = sanitize_file_name( $file['name'] );

        $imported    = 0;
        $skipped     = 0;
        $merchants   = [];

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $raw_date   = trim( $row[ $date_idx ]     ?? '' );
            $raw_amount = trim( $row[ $amount_idx ]    ?? '' );
            $merchant   = trim( $row[ $merchant_idx ]  ?? '' );

            if ( empty( $raw_date ) || empty( $merchant ) ) {
                $skipped++;
                continue;
            }

            // Skip summary/total rows
            $merchant_lower = strtolower( $merchant );
            if ( str_contains( $merchant_lower, 'total' ) || str_contains( $merchant_lower, 'balance' ) || str_contains( $merchant_lower, 'starting' ) ) {
                $skipped++;
                continue;
            }

            $date = $this->parse_csv_date( $raw_date );
            if ( ! $date ) {
                $skipped++;
                continue;
            }

            $amount = $this->parse_csv_amount( $raw_amount );
            if ( $amount === null || $amount == 0 ) {
                $skipped++;
                continue;
            }

            $amount   = abs( $amount );
            $txn_year = (int) substr( $date, 0, 4 );

            // Duplicate detection
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE date = %s AND amount = %f AND merchant = %s AND category = %s",
                $date, $amount, $merchant, $category
            ) );

            if ( (int) $exists > 0 ) {
                $skipped++;
                continue;
            }

            $wpdb->insert( $table, [
                'type'             => 'expense',
                'date'             => $date,
                'merchant'         => $merchant,
                'amount'           => $amount,
                'category'         => $category,
                'bank_account'     => $bank_account,
                'business'         => $this->get_business_name(),
                'status'           => 'classified',
                'comments'         => '',
                'source_file'      => $source_file,
                'tax_year'         => $txn_year,
                'travel_period_id' => 0,
                'receipt_id'       => 0,
            ] );

            $imported++;

            // Collect unique cleaned merchants for rule creation
            $cleaned = self::clean_merchant_name( $merchant );
            if ( ! empty( $cleaned ) ) {
                $merch_key = strtolower( $cleaned );
                if ( ! isset( $merchants[ $merch_key ] ) ) {
                    $merchants[ $merch_key ] = $cleaned;
                }
            }
        }

        fclose( $handle );

        // Bulk-create rules from unique cleaned merchants
        $rules_data = [];
        foreach ( $merchants as $m ) {
            $rules_data[] = [ 'keyword' => $m, 'category' => $category, 'match_type' => 'all_words' ];
        }
        $rules_saved = $this->bulk_save_rules( $rules_data );

        $message = sprintf(
            __( 'Import complete: %1$d transactions imported as "%2$s", %3$d skipped. %4$d new rules created from unique merchants.', 'el-core' ),
            $imported,
            $category,
            $skipped,
            $rules_saved
        );

        EL_AJAX_Handler::success( [
            'imported'    => $imported,
            'skipped'     => $skipped,
            'rules_saved' => $rules_saved,
        ], $message );
    }

    public function handle_update_transaction( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id       = absint( $data['id'] ?? 0 );
        $field    = sanitize_key( $data['field'] ?? '' );
        $value    = sanitize_text_field( $data['value'] ?? '' );

        if ( ! $id || ! $field ) {
            EL_AJAX_Handler::error( __( 'Invalid request.', 'el-core' ) );
            return;
        }

        $allowed_fields = [ 'category', 'status', 'comments', 'bank_account', 'business', 'merchant', 'amount', 'date' ];
        if ( ! in_array( $field, $allowed_fields, true ) ) {
            EL_AJAX_Handler::error( __( 'Field not allowed.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $this->table( 'el_bk_transactions' );
        $wpdb->update( $table, [ $field => $value, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $id ] );

        EL_AJAX_Handler::success( null, __( 'Transaction updated.', 'el-core' ) );
    }

    public function handle_bulk_confirm( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $scope    = sanitize_key( $data['scope'] ?? 'all' ); // 'all' | 'travel'
        $tax_year = absint( $data['tax_year'] ?? $this->get_tax_year() );

        global $wpdb;
        $table = $this->table( 'el_bk_transactions' );

        if ( $scope === 'travel' ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET status = 'classified', updated_at = %s WHERE status = 'suggested' AND travel_period_id > 0 AND tax_year = %d",
                current_time( 'mysql' ), $tax_year
            ) );
        } else {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET status = 'classified', updated_at = %s WHERE status = 'suggested' AND tax_year = %d",
                current_time( 'mysql' ), $tax_year
            ) );
        }

        EL_AJAX_Handler::success( null, __( 'Suggestions confirmed.', 'el-core' ) );
    }

    public function handle_reclassify( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $tax_year = absint( $data['tax_year'] ?? $this->get_tax_year() );

        global $wpdb;
        $table = $this->table( 'el_bk_transactions' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, merchant, date, category, status FROM {$table} WHERE type = 'expense' AND tax_year = %d",
            $tax_year
        ) );

        $reclassified = 0;
        foreach ( $rows as $row ) {
            $classification = $this->auto_classify( $row->merchant, $row->date );
            if ( ! empty( $classification['category'] ) && $classification['category'] !== $row->category ) {
                $wpdb->update( $table, [
                    'category'         => $classification['category'],
                    'status'           => 'suggested',
                    'travel_period_id' => $classification['travel_period_id'],
                ], [ 'id' => $row->id ] );
                $reclassified++;
            }
        }

        EL_AJAX_Handler::success( [
            'reclassified' => $reclassified,
            'total'        => count( $rows ),
        ], sprintf(
            __( 'Re-classified %1$d of %2$d expense transactions.', 'el-core' ),
            $reclassified,
            count( $rows )
        ) );
    }

    public function handle_export_csv( array $data ): void {
        if ( ! el_core_can( 'view_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        // Phase 2
        EL_AJAX_Handler::error( __( 'Export not yet implemented.', 'el-core' ) );
    }

    public function handle_export_pl( array $data ): void {
        if ( ! el_core_can( 'view_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        // Phase 7
        EL_AJAX_Handler::error( __( 'P&L export not yet implemented.', 'el-core' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS — KNOWN EXPENSE RULES
    // ─────────────────────────────────────────────────────────────

    public function handle_process_rules( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $message = sanitize_textarea_field( wp_unslash( $data['message'] ?? '' ) );
        if ( empty( $message ) ) {
            EL_AJAX_Handler::error( __( 'Please enter some text to process.', 'el-core' ) );
            return;
        }

        if ( ! $this->core || ! $this->core->ai || ! $this->core->ai->is_configured() ) {
            EL_AJAX_Handler::error( __( 'AI is not configured. Go to EL Core → Brand → AI Configuration to add your API key.', 'el-core' ) );
            return;
        }

        $categories_list = implode( ', ', self::get_expense_categories() );

        $result = $this->core->ai->complete( [
            'system' => "You are a bookkeeping assistant that extracts merchant-to-category classification rules from natural language.\n\n"
                      . "VALID CATEGORIES (use EXACTLY these names):\n{$categories_list}\n\n"
                      . "The user will describe merchants and what category they belong to. Extract each merchant/keyword and its category.\n\n"
                      . "Respond ONLY with a JSON array. Each element must have:\n"
                      . "- \"keyword\": the merchant name or keyword (e.g. \"Adobe\", \"Chick-fil-A\")\n"
                      . "- \"category\": one of the valid categories above (EXACT match required)\n"
                      . "- \"match_type\": \"contains\" (default) or \"exact\"\n\n"
                      . "If a category the user mentions doesn't match any valid category, pick the closest valid one.\n"
                      . "If you cannot determine any rules, return an empty array: []\n\n"
                      . "Example input: \"Adobe is Software and Application Fees, Chick-fil-A is Meals\"\n"
                      . "Example output: [{\"keyword\":\"Adobe\",\"category\":\"Software and Application Fees\",\"match_type\":\"contains\"},{\"keyword\":\"Chick-fil-A\",\"category\":\"Meals\",\"match_type\":\"contains\"}]",
            'prompt'     => $message,
            'max_tokens' => 2048,
        ] );

        if ( ! $result['success'] ) {
            EL_AJAX_Handler::error( sprintf( __( 'AI error: %s', 'el-core' ), $result['error'] ) );
            return;
        }

        $parsed = $this->parse_rules_from_ai_response( $result['content'] );

        if ( empty( $parsed ) ) {
            EL_AJAX_Handler::success( [
                'reply'       => __( 'I couldn\'t extract any rules from that. Try something like: "Adobe is Software and Application Fees, Starbucks is Meals"', 'el-core' ),
                'rules_saved' => 0,
            ] );
            return;
        }

        $saved = $this->bulk_save_rules( $parsed );

        $reply = sprintf(
            _n( 'Created %d rule:', 'Created %d rules:', $saved, 'el-core' ),
            $saved
        ) . "\n";
        foreach ( $parsed as $r ) {
            $reply .= "• {$r['keyword']} → {$r['category']}\n";
        }

        EL_AJAX_Handler::success( [
            'reply'       => $reply,
            'rules_saved' => $saved,
            'rules'       => $parsed,
        ] );
    }

    /**
     * Parse JSON rules array from AI response text.
     * Handles cases where the AI wraps JSON in markdown code fences.
     */
    private function parse_rules_from_ai_response( string $content ): array {
        $content = trim( $content );

        if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/', $content, $m ) ) {
            $content = trim( $m[1] );
        }

        $decoded = json_decode( $content, true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        $valid_categories = self::get_expense_categories();
        $rules = [];

        foreach ( $decoded as $item ) {
            $keyword  = sanitize_text_field( $item['keyword']    ?? '' );
            $category = sanitize_text_field( $item['category']   ?? '' );
            $type     = sanitize_key( $item['match_type']        ?? 'contains' );

            if ( empty( $keyword ) || empty( $category ) ) {
                continue;
            }
            if ( ! in_array( $category, $valid_categories, true ) ) {
                continue;
            }
            if ( ! in_array( $type, [ 'contains', 'all_words', 'exact' ], true ) ) {
                $type = 'contains';
            }

            $rules[] = [ 'keyword' => $keyword, 'category' => $category, 'match_type' => $type ];
        }

        return $rules;
    }

    /**
     * Save multiple rules at once, skipping duplicates (same keyword+category).
     * Returns count of newly inserted rules.
     */
    private function bulk_save_rules( array $rules ): int {
        global $wpdb;
        $table    = $this->table( 'el_bk_rules' );
        $existing = $wpdb->get_results( "SELECT keyword, category FROM {$table}", ARRAY_A );

        $existing_set = [];
        foreach ( $existing as $e ) {
            $existing_set[ strtolower( $e['keyword'] ) . '|' . $e['category'] ] = true;
        }

        $max_priority = (int) $wpdb->get_var( "SELECT MAX(priority) FROM {$table}" );
        $saved = 0;

        foreach ( $rules as $rule ) {
            $key = strtolower( $rule['keyword'] ) . '|' . $rule['category'];
            if ( isset( $existing_set[ $key ] ) ) {
                continue;
            }

            $max_priority++;
            $wpdb->insert( $table, [
                'keyword'    => $rule['keyword'],
                'match_type' => $rule['match_type'],
                'category'   => $rule['category'],
                'priority'   => $max_priority,
            ] );
            $existing_set[ $key ] = true;
            $saved++;
        }

        return $saved;
    }

    public function handle_save_rule( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id       = absint( $data['id'] ?? 0 );
        $keyword  = sanitize_text_field( $data['keyword'] ?? '' );
        $type     = sanitize_key( $data['match_type'] ?? 'contains' );
        $category = sanitize_text_field( $data['category'] ?? '' );
        $priority = absint( $data['priority'] ?? 0 );

        if ( ! in_array( $type, [ 'contains', 'all_words', 'exact' ], true ) ) {
            $type = 'contains';
        }

        if ( ! $keyword || ! $category ) {
            EL_AJAX_Handler::error( __( 'Keyword and category are required.', 'el-core' ) );
            return;
        }

        if ( ! in_array( $category, self::get_expense_categories(), true ) ) {
            EL_AJAX_Handler::error( __( 'Invalid category.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $this->table( 'el_bk_rules' );
        $row   = [ 'keyword' => $keyword, 'match_type' => $type, 'category' => $category, 'priority' => $priority ];

        if ( $id ) {
            $wpdb->update( $table, $row, [ 'id' => $id ] );
        } else {
            $wpdb->insert( $table, $row );
            $id = $wpdb->insert_id;
        }

        EL_AJAX_Handler::success( [ 'id' => $id ], __( 'Rule saved.', 'el-core' ) );
    }

    public function handle_delete_rule( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid rule ID.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $wpdb->delete( $this->table( 'el_bk_rules' ), [ 'id' => $id ] );
        EL_AJAX_Handler::success( null, __( 'Rule deleted.', 'el-core' ) );
    }

    public function handle_bulk_delete_rules( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $raw = sanitize_text_field( $data['ids'] ?? '' );
        if ( empty( $raw ) ) {
            EL_AJAX_Handler::error( __( 'No rule IDs provided.', 'el-core' ) );
            return;
        }

        $ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
        if ( empty( $ids ) ) {
            EL_AJAX_Handler::error( __( 'No valid rule IDs provided.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table        = $this->table( 'el_bk_rules' );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $deleted      = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE id IN ({$placeholders})",
            ...$ids
        ) );

        EL_AJAX_Handler::success( [
            'deleted' => (int) $deleted,
        ], sprintf(
            __( 'Deleted %d rule(s).', 'el-core' ),
            (int) $deleted
        ) );
    }

    public function handle_reorder_rules( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $order = array_map( 'absint', $data['order'] ?? [] );
        if ( empty( $order ) ) {
            EL_AJAX_Handler::error( __( 'No order provided.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $this->table( 'el_bk_rules' );
        foreach ( $order as $priority => $rule_id ) {
            $wpdb->update( $table, [ 'priority' => $priority ], [ 'id' => $rule_id ] );
        }

        EL_AJAX_Handler::success( null, __( 'Rules reordered.', 'el-core' ) );
    }

    /**
     * Import rules from a prior-year categorized expense CSV.
     * Expects columns for merchant/description and category.
     */
    public function handle_import_rules_csv( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        if ( empty( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            EL_AJAX_Handler::error( __( 'No file uploaded or upload error.', 'el-core' ) );
            return;
        }

        $file = $_FILES['csv_file'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'csv' ) {
            EL_AJAX_Handler::error( __( 'Only CSV files are accepted.', 'el-core' ) );
            return;
        }

        $merchant_col    = sanitize_text_field( $data['merchant_col']    ?? '' );
        $single_category = sanitize_text_field( $data['single_category'] ?? '' );

        $handle = fopen( $file['tmp_name'], 'r' );
        if ( ! $handle ) {
            EL_AJAX_Handler::error( __( 'Could not read the uploaded file.', 'el-core' ) );
            return;
        }

        // Skip non-data header rows
        $header   = null;
        $max_skip = 20;
        while ( $max_skip-- > 0 ) {
            $row = fgetcsv( $handle );
            if ( $row === false ) {
                break;
            }
            $row       = array_map( 'trim', $row );
            $non_empty = count( array_filter( $row, fn( $c ) => $c !== '' ) );
            if ( $non_empty >= 2 ) {
                $joined = strtolower( implode( ' ', $row ) );
                if ( preg_match( '/date|description|amount|debit|credit|merchant|memo/i', $joined ) ) {
                    $header = $row;
                    break;
                }
            }
        }

        if ( ! $header ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'Could not find a valid column header row in the CSV.', 'el-core' ) );
            return;
        }

        // Step 1: No merchant column selected yet — return headers for mapping
        if ( empty( $merchant_col ) ) {
            fclose( $handle );
            EL_AJAX_Handler::success( [
                'step'    => 'map_columns',
                'columns' => $header,
            ], __( 'Please map the description column.', 'el-core' ) );
            return;
        }

        // Validate the chosen category
        if ( empty( $single_category ) || ! in_array( $single_category, self::get_expense_categories(), true ) ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'Invalid or missing category.', 'el-core' ) );
            return;
        }

        $merchant_idx = array_search( $merchant_col, $header, true );
        if ( $merchant_idx === false ) {
            fclose( $handle );
            EL_AJAX_Handler::error( __( 'Could not find the specified column in the CSV header.', 'el-core' ) );
            return;
        }

        // Read all rows, clean merchant names, and collect unique ones
        $merchants = [];
        $skipped_fees = 0;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $raw_merchant = trim( $row[ $merchant_idx ] ?? '' );
            if ( empty( $raw_merchant ) ) {
                continue;
            }
            $merchant_lower = strtolower( $raw_merchant );
            if ( str_contains( $merchant_lower, 'total' ) || str_contains( $merchant_lower, 'balance' ) || str_contains( $merchant_lower, 'starting' ) ) {
                continue;
            }

            $merchant = self::clean_merchant_name( $raw_merchant );
            if ( empty( $merchant ) ) {
                $skipped_fees++;
                continue;
            }

            $key = strtolower( $merchant );
            if ( ! isset( $merchants[ $key ] ) ) {
                $merchants[ $key ] = $merchant;
            }
        }
        fclose( $handle );

        if ( empty( $merchants ) ) {
            EL_AJAX_Handler::error( __( 'No valid descriptions found in the CSV.', 'el-core' ) );
            return;
        }

        // Build rules from unique cleaned merchants, all with the single category
        $rules_data = [];
        foreach ( $merchants as $m ) {
            $rules_data[] = [ 'keyword' => $m, 'category' => $single_category, 'match_type' => 'all_words' ];
        }

        $saved = $this->bulk_save_rules( $rules_data );

        EL_AJAX_Handler::success( [
            'rules_saved' => $saved,
            'total_found' => count( $merchants ),
        ], sprintf(
            __( 'Found %1$d unique descriptions. Created %2$d new rules for "%3$s" (%4$d already existed).', 'el-core' ),
            count( $merchants ),
            $saved,
            $single_category,
            count( $merchants ) - $saved
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS — TRAVEL DATES
    // ─────────────────────────────────────────────────────────────

    public function handle_save_travel_period( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id         = absint( $data['id'] ?? 0 );
        $label      = sanitize_text_field( $data['label']      ?? '' );
        $start_date = sanitize_text_field( $data['start_date'] ?? '' );
        $end_date   = sanitize_text_field( $data['end_date']   ?? '' );
        $purpose    = sanitize_textarea_field( $data['purpose'] ?? '' );

        if ( ! $start_date || ! $end_date ) {
            EL_AJAX_Handler::error( __( 'Start and end dates are required.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $this->table( 'el_bk_travel_periods' );
        $row   = [ 'label' => $label, 'start_date' => $start_date, 'end_date' => $end_date, 'purpose' => $purpose ];

        if ( $id ) {
            $wpdb->update( $table, $row, [ 'id' => $id ] );
        } else {
            $wpdb->insert( $table, $row );
            $id = $wpdb->insert_id;
        }

        EL_AJAX_Handler::success( [ 'id' => $id ], __( 'Travel period saved.', 'el-core' ) );
    }

    public function handle_delete_travel_period( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid period ID.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $wpdb->delete( $this->table( 'el_bk_travel_periods' ), [ 'id' => $id ] );

        // Detach from transactions
        $wpdb->update(
            $this->table( 'el_bk_transactions' ),
            [ 'travel_period_id' => 0 ],
            [ 'travel_period_id' => $id ]
        );

        EL_AJAX_Handler::success( null, __( 'Travel period deleted.', 'el-core' ) );
    }

    public function handle_reapply_travel_rules( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        global $wpdb;
        $table    = $this->table( 'el_bk_transactions' );
        $tax_year = absint( $data['tax_year'] ?? $this->get_tax_year() );

        // Only re-apply to unclassified transactions
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, merchant, date FROM {$table} WHERE status = 'unclassified' AND tax_year = %d",
            $tax_year
        ) );

        $updated = 0;
        foreach ( $rows as $row ) {
            $result = $this->auto_classify( $row->merchant, $row->date );
            if ( $result['source'] === 'travel' ) {
                $wpdb->update( $table, [
                    'category'         => $result['category'],
                    'status'           => 'suggested',
                    'travel_period_id' => $result['travel_period_id'],
                    'updated_at'       => current_time( 'mysql' ),
                ], [ 'id' => $row->id ] );
                $updated++;
            }
        }

        EL_AJAX_Handler::success(
            [ 'updated' => $updated ],
            sprintf( _n( '%d transaction tagged.', '%d transactions tagged.', $updated, 'el-core' ), $updated )
        );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS — RECEIPTS
    // ─────────────────────────────────────────────────────────────

    public function handle_upload_receipt( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        // Phase 6
        EL_AJAX_Handler::error( __( 'Receipt upload not yet implemented.', 'el-core' ) );
    }

    public function handle_attach_receipt( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $receipt_id     = absint( $data['receipt_id']     ?? 0 );
        $transaction_id = absint( $data['transaction_id'] ?? 0 );

        if ( ! $receipt_id || ! $transaction_id ) {
            EL_AJAX_Handler::error( __( 'Invalid IDs.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $wpdb->update(
            $this->table( 'el_bk_receipts' ),
            [ 'transaction_id' => $transaction_id, 'status' => 'matched' ],
            [ 'id' => $receipt_id ]
        );
        $wpdb->update(
            $this->table( 'el_bk_transactions' ),
            [ 'receipt_id' => $receipt_id, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $transaction_id ]
        );

        EL_AJAX_Handler::success( null, __( 'Receipt attached.', 'el-core' ) );
    }

    public function handle_detach_receipt( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $receipt_id = absint( $data['receipt_id'] ?? 0 );
        if ( ! $receipt_id ) {
            EL_AJAX_Handler::error( __( 'Invalid receipt ID.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $receipt = $wpdb->get_row( $wpdb->prepare(
            "SELECT transaction_id FROM {$this->table('el_bk_receipts')} WHERE id = %d",
            $receipt_id
        ) );

        $wpdb->update( $this->table( 'el_bk_receipts' ), [ 'transaction_id' => 0, 'status' => 'unmatched' ], [ 'id' => $receipt_id ] );

        if ( $receipt && $receipt->transaction_id ) {
            $wpdb->update(
                $this->table( 'el_bk_transactions' ),
                [ 'receipt_id' => 0, 'updated_at' => current_time( 'mysql' ) ],
                [ 'id' => $receipt->transaction_id ]
            );
        }

        EL_AJAX_Handler::success( null, __( 'Receipt detached.', 'el-core' ) );
    }

    public function handle_delete_receipt( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $receipt_id = absint( $data['receipt_id'] ?? 0 );
        if ( ! $receipt_id ) {
            EL_AJAX_Handler::error( __( 'Invalid receipt ID.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $receipt = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table('el_bk_receipts')} WHERE id = %d",
            $receipt_id
        ) );

        if ( $receipt && $receipt->file_path && file_exists( $receipt->file_path ) ) {
            wp_delete_file( $receipt->file_path );
        }

        $wpdb->delete( $this->table( 'el_bk_receipts' ), [ 'id' => $receipt_id ] );

        if ( $receipt && $receipt->transaction_id ) {
            $wpdb->update(
                $this->table( 'el_bk_transactions' ),
                [ 'receipt_id' => 0, 'updated_at' => current_time( 'mysql' ) ],
                [ 'id' => $receipt->transaction_id ]
            );
        }

        EL_AJAX_Handler::success( null, __( 'Receipt deleted.', 'el-core' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS — CONTRACTORS
    // ─────────────────────────────────────────────────────────────

    public function handle_save_contractor( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id      = absint( $data['id'] ?? 0 );
        $name    = sanitize_text_field( $data['name']    ?? '' );
        $email   = sanitize_email(      $data['email']   ?? '' );
        $address = sanitize_textarea_field( wp_unslash( $data['address'] ?? '' ) );

        if ( ! $name ) {
            EL_AJAX_Handler::error( __( 'Contractor name is required.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $this->table( 'el_bk_contractors' );
        $row   = [ 'name' => $name, 'email' => $email, 'address' => $address ];

        if ( $id ) {
            $wpdb->update( $table, $row, [ 'id' => $id ] );
        } else {
            $wpdb->insert( $table, $row );
            $id = $wpdb->insert_id;
        }

        EL_AJAX_Handler::success( [ 'id' => $id ], __( 'Contractor saved.', 'el-core' ) );
    }

    public function handle_delete_contractor( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid contractor ID.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $wpdb->delete( $this->table( 'el_bk_contractors' ),            [ 'contractor_id' => $id ] );
        $wpdb->delete( $this->table( 'el_bk_contractor_assignments' ), [ 'contractor_id' => $id ] );

        EL_AJAX_Handler::success( null, __( 'Contractor deleted.', 'el-core' ) );
    }

    public function handle_assign_contractor( array $data ): void {
        if ( ! el_core_can( 'manage_bookkeeping' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $transaction_id = absint( $data['transaction_id'] ?? 0 );
        $contractor_id  = absint( $data['contractor_id']  ?? 0 );

        if ( ! $transaction_id || ! $contractor_id ) {
            EL_AJAX_Handler::error( __( 'Invalid IDs.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $this->table( 'el_bk_contractor_assignments' );

        // Remove existing assignment for this transaction first
        $wpdb->delete( $table, [ 'transaction_id' => $transaction_id ] );

        $wpdb->insert( $table, [
            'transaction_id' => $transaction_id,
            'contractor_id'  => $contractor_id,
        ] );

        EL_AJAX_Handler::success( null, __( 'Contractor assigned.', 'el-core' ) );
    }
}
