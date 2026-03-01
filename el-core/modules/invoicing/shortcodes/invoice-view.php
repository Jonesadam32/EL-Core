<?php
/**
 * Shortcode: [el_invoice_view]
 * Single invoice detail (printable/PDF-ready) — built in Step 5.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_invoice_view( $atts = [] ): string {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'el_invoice_view' );
    $id  = absint( $atts['id'] );
    return '<div class="el-inv el-inv-invoice-view" data-invoice-id="' . esc_attr( $id ) . '"><p class="el-inv-placeholder">Invoice detail view will be built in Step 5.</p></div>';
}
