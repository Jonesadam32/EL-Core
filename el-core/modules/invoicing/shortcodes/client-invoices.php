<?php
/**
 * Shortcode: [el_client_invoices]
 * Client portal: their invoices and payment status (Step 5).
 * Admins see a "View As" bar to preview as a specific client.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_client_invoices( $atts = [] ): string {
    if ( ! is_user_logged_in() ) {
        return '<div class="el-inv el-inv-client-invoices"><p class="el-inv-client-login-required">' . esc_html__( 'Please log in to view your invoices.', 'el-core' ) . '</p></div>';
    }
    if ( ! current_user_can( 'view_invoices' ) ) {
        return '<div class="el-inv el-inv-client-invoices"><p class="el-inv-client-login-required">' . esc_html__( 'You do not have permission to view invoices.', 'el-core' ) . '</p></div>';
    }

    $module = EL_Invoicing_Module::instance();
    $org_ids = $module->get_organization_ids_for_current_user();
    if ( empty( $org_ids ) ) {
        return '<div class="el-inv el-inv-client-invoices"><p class="el-inv-client-empty">' . esc_html__( 'No client account is linked to your user. Contact support if you expect to see invoices here.', 'el-core' ) . '</p></div>';
    }

    $core = el_core();
    global $wpdb;
    $inv_table = $core->database->get_table_name( 'el_inv_invoices' );
    $placeholders = implode( ',', array_fill( 0, count( $org_ids ), '%d' ) );
    $invoices = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$inv_table} WHERE organization_id IN ({$placeholders}) AND status != 'cancelled' ORDER BY created_at DESC",
        ...$org_ids
    ) );
    $invoices = is_array( $invoices ) ? $invoices : [];
    $outstanding = 0;
    foreach ( $invoices as $inv ) {
        $outstanding += (float) $inv->balance_due;
    }
    $outstanding = round( $outstanding, 2 );

    $view_as_param = '';
    $view_as_user_id = 0;
    $is_viewing_as = false;
    if ( current_user_can( 'manage_invoices' ) && ! empty( $_GET['el_view_as'] ) ) {
        $view_as_user_id = absint( $_GET['el_view_as'] );
        if ( $view_as_user_id && $view_as_user_id !== get_current_user_id() ) {
            $view_as_param = '&el_view_as=' . $view_as_user_id;
            $is_viewing_as = ( $module->get_effective_client_user_id() === $view_as_user_id );
        }
    }
    $view_base = home_url( '/?el_invoice_view=1&id=' );
    $status_labels = [
        'draft'     => __( 'Draft', 'el-core' ),
        'sent'      => __( 'Sent', 'el-core' ),
        'viewed'    => __( 'Viewed', 'el-core' ),
        'partial'   => __( 'Partial', 'el-core' ),
        'paid'      => __( 'Paid', 'el-core' ),
        'overdue'   => __( 'Overdue', 'el-core' ),
    ];

    $view_as_bar = '';
    if ( current_user_can( 'manage_invoices' ) ) {
        $current_url = remove_query_arg( 'el_view_as' );
        if ( $is_viewing_as && $view_as_user_id ) {
            $viewing_user = get_user_by( 'id', $view_as_user_id );
            $view_as_bar = '<div class="el-inv-view-as-bar el-inv-view-as-active">' .
                '<span class="el-inv-view-as-label">' . esc_html__( 'Viewing as:', 'el-core' ) . '</span> ' .
                '<span class="el-inv-view-as-name">' . esc_html( $viewing_user ? $viewing_user->display_name : (string) $view_as_user_id ) . '</span> ' .
                '<a href="' . esc_url( $current_url ) . '" class="el-inv-view-as-exit">' . esc_html__( 'Exit view', 'el-core' ) . '</a>' .
                '</div>';
        } else {
            $clients = $module->get_view_as_client_list();
            $view_as_bar = '<div class="el-inv-view-as-bar">' .
                '<label for="el-inv-view-as-select" class="el-inv-view-as-label">' . esc_html__( 'View as:', 'el-core' ) . '</label> ' .
                '<select id="el-inv-view-as-select" class="el-inv-view-as-select">' .
                '<option value="">— ' . esc_html__( 'Select client', 'el-core' ) . ' —</option>';
            foreach ( $clients as $c ) {
                $opt_label = $c['display_name'];
                if ( ! empty( $c['org_name'] ) ) {
                    $opt_label .= ' (' . $c['org_name'] . ')';
                }
                $view_as_bar .= '<option value="' . esc_attr( (string) $c['user_id'] ) . '">' . esc_html( $opt_label ) . '</option>';
            }
            $view_as_bar .= '</select></div>';
            $view_as_bar .= '<script>(function(){var s=document.getElementById("el-inv-view-as-select");if(s){s.addEventListener("change",function(){var v=this.value;if(v){var u=new URL(window.location.href);u.searchParams.set("el_view_as",v);window.location.href=u.toString();}});}})();</script>';
        }
    }

    ob_start();
    ?>
<div class="el-inv el-inv-client-invoices">
    <?php if ( $view_as_bar ) { echo $view_as_bar; } ?>
    <h2 class="el-inv-client-title"><?php echo esc_html__( 'Your Invoices', 'el-core' ); ?></h2>
    <?php if ( $outstanding > 0 ) : ?>
    <div class="el-inv-client-outstanding">
        <span class="el-inv-client-outstanding-label"><?php echo esc_html__( 'Total balance due:', 'el-core' ); ?></span>
        <span class="el-inv-client-outstanding-amount">$<?php echo esc_html( number_format( $outstanding, 2 ) ); ?></span>
    </div>
    <?php endif; ?>
    <?php if ( empty( $invoices ) ) : ?>
    <p class="el-inv-client-empty"><?php echo esc_html__( 'You have no invoices yet.', 'el-core' ); ?></p>
    <?php else : ?>
    <table class="el-inv-client-table">
        <thead>
            <tr>
                <th><?php echo esc_html__( 'Invoice #', 'el-core' ); ?></th>
                <th><?php echo esc_html__( 'Date', 'el-core' ); ?></th>
                <th class="el-inv-col-right"><?php echo esc_html__( 'Amount', 'el-core' ); ?></th>
                <th><?php echo esc_html__( 'Status', 'el-core' ); ?></th>
                <th class="el-inv-col-right"><?php echo esc_html__( 'Balance due', 'el-core' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $invoices as $inv ) :
                $status = $inv->status ?? 'draft';
                $status_class = 'el-inv-status-' . $status;
                $label = $status_labels[ $status ] ?? ucfirst( $status );
                $issue_date = $inv->issue_date ? date_i18n( get_option( 'date_format' ), strtotime( $inv->issue_date ) ) : '—';
            ?>
            <tr>
                <td><a href="<?php echo esc_url( $view_base . (int) $inv->id . $view_as_param ); ?>" class="el-inv-client-link"><?php echo esc_html( $inv->invoice_number ); ?></a></td>
                <td><?php echo esc_html( $issue_date ); ?></td>
                <td class="el-inv-col-right">$<?php echo esc_html( number_format( (float) $inv->total, 2 ) ); ?></td>
                <td><span class="el-inv-client-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $label ); ?></span></td>
                <td class="el-inv-col-right">$<?php echo esc_html( number_format( (float) $inv->balance_due, 2 ) ); ?></td>
                <td><a href="<?php echo esc_url( $view_base . (int) $inv->id . $view_as_param ); ?>" class="el-inv-client-view-btn"><?php echo esc_html__( 'View', 'el-core' ); ?></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
    <?php
    return ob_get_clean();
}
