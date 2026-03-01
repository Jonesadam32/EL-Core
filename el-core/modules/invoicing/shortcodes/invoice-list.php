<?php
/**
 * Shortcode: [el_invoice_list]
 * Admin/staff: all invoices with filters and stats (built in Step 3).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_invoice_list( $atts = [] ): string {
    return '<div class="el-inv el-inv-invoice-list"><p class="el-inv-placeholder">Invoice list (admin) will be built in Step 3.</p></div>';
}
