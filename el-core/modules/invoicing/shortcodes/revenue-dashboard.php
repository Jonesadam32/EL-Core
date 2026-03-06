<?php
/**
 * Shortcode: [el_revenue_dashboard]
 * Revenue reporting with breakdowns (Step 6). Admin-only.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_revenue_dashboard( $atts = [] ): string {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_invoices' ) ) {
        return '<div class="el-inv el-inv-revenue-dashboard"><p class="el-inv-placeholder">' . esc_html__( 'You do not have permission to view the revenue dashboard.', 'el-core' ) . '</p></div>';
    }
    $redirect = admin_url( 'admin.php?page=el-core-inv-revenue' );
    return '<div class="el-inv el-inv-revenue-dashboard"><p>' . esc_html__( 'Revenue dashboard is available in the admin.', 'el-core' ) . ' <a href="' . esc_url( $redirect ) . '">' . esc_html__( 'Open Revenue Dashboard', 'el-core' ) . '</a></p></div>';
}
