<?php
/**
 * Shortcode: [el_invoice_view id="123"]
 * Single invoice detail (printable/PDF-ready). Same view as preview URL.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_invoice_view( $atts = [] ): string {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'el_invoice_view' );
    $id   = absint( $atts['id'] );
    if ( ! $id ) {
        return '<p class="el-inv-invoice-view">' . esc_html__( 'Invoice ID required.', 'el-core' ) . '</p>';
    }
    if ( ! is_user_logged_in() || ( ! current_user_can( 'view_invoices' ) && ! current_user_can( 'create_invoices' ) ) ) {
        return '<p class="el-inv-invoice-view">' . esc_html__( 'You do not have permission to view this invoice.', 'el-core' ) . '</p>';
    }
    $module = EL_Invoicing_Module::instance();
    $css    = '<style>.el-inv-view{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:800px;margin:0 auto;padding:2rem;color:#1a1a1a}.el-inv-view h1{font-size:1.5rem;margin-bottom:.5rem}.el-inv-view .el-inv-meta{color:#555;margin-bottom:1.5rem}.el-inv-view table{width:100%;border-collapse:collapse;margin:1.5rem 0}.el-inv-view th,.el-inv-view td{padding:.5rem .75rem;text-align:left;border-bottom:1px solid #ddd}.el-inv-view th{font-weight:600}.el-inv-view .el-inv-totals{margin-top:1rem;text-align:right}.el-inv-view .el-inv-total-row{padding:.25rem 0}.el-inv-view .el-inv-grand{font-size:1.25rem;font-weight:700}.el-inv-view .el-inv-notes{margin-top:1.5rem;padding-top:1rem;border-top:1px solid #ddd;font-size:.9rem;color:#555}@media print{.el-inv-view .el-inv-print{display:none}}</style>';
    return $css . $module->get_invoice_view_fragment( $id );
}
