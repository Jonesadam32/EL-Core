<?php
/**
 * Shortcode: [el_client_invoices]
 * Client portal: their invoices and payment status (built in Step 5).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_client_invoices( $atts = [] ): string {
    return '<div class="el-inv el-inv-client-invoices"><p class="el-inv-placeholder">Client invoices view will be built in Step 5.</p></div>';
}
