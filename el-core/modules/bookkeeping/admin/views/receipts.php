<?php
/**
 * Bookkeeping — Receipts Tab (AI Receipt Scanner)
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$unmatched = $module->get_receipts( 'unmatched' );
$all       = $module->get_receipts();
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php esc_html_e( 'Receipts', 'el-core' ); ?></h2>
        <p class="el-bk-tab-desc"><?php esc_html_e( 'Upload receipt photos. AI extracts merchant, date, amount, and category automatically.', 'el-core' ); ?></p>
    </div>
</div>

<?php echo EL_Admin_UI::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    __( 'AI receipt scanning and file upload will be available in Phase 6.', 'el-core' ),
    'info'
); ?>

<!-- Upload Drop Zone -->
<div class="el-bk-card el-bk-upload-zone" id="el-bk-receipt-upload-zone">
    <div class="el-bk-upload-icon">📷</div>
    <p><?php esc_html_e( 'Drag and drop receipts here, or click to browse', 'el-core' ); ?></p>
    <p class="el-bk-hint"><?php esc_html_e( 'Accepts: JPG, PNG, PDF — max 10MB each', 'el-core' ); ?></p>
    <input type="file" id="el-bk-receipt-file-input" accept=".jpg,.jpeg,.png,.pdf" multiple style="display:none;">
    <button class="el-btn el-btn-primary" id="el-bk-receipt-browse-btn">
        <?php esc_html_e( 'Browse Files', 'el-core' ); ?>
    </button>
</div>

<!-- Review Queue (populated by JS after upload) -->
<div id="el-bk-receipt-review-queue" class="el-bk-receipt-review-queue"></div>

<!-- Unmatched Receipts Panel -->
<?php if ( ! empty( $unmatched ) ) : ?>
<div class="el-bk-card">
    <h3><?php echo esc_html( sprintf( __( 'Unmatched Receipts (%d)', 'el-core' ), count( $unmatched ) ) ); ?></h3>
    <div class="el-bk-receipt-grid">
        <?php foreach ( $unmatched as $r ) : ?>
        <div class="el-bk-receipt-thumb" data-receipt-id="<?php echo esc_attr( $r->id ); ?>">
            <?php if ( $r->file_url ) : ?>
                <img src="<?php echo esc_url( $r->file_url ); ?>" alt="">
            <?php else : ?>
                <div class="el-bk-receipt-thumb-placeholder">📄</div>
            <?php endif; ?>
            <div class="el-bk-receipt-meta">
                <span><?php echo esc_html( $r->ai_extracted_merchant ?: __( 'Unknown merchant', 'el-core' ) ); ?></span>
                <span><?php echo esc_html( $r->ai_extracted_date ?: '—' ); ?></span>
                <?php if ( $r->ai_extracted_amount ) : ?>
                    <span>$<?php echo esc_html( number_format( (float) $r->ai_extracted_amount, 2 ) ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- All Receipts Table -->
<?php if ( empty( $all ) ) : ?>
    <?php echo EL_Admin_UI::notice( __( 'No receipts uploaded yet.', 'el-core' ), 'info' ); // phpcs:ignore ?>
<?php else : ?>
<div class="el-bk-card">
    <h3><?php esc_html_e( 'All Receipts', 'el-core' ); ?></h3>
    <table class="el-bk-receipts-table widefat">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Thumbnail', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Merchant', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Date', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Category', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Attached To', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $all as $r ) : ?>
            <tr>
                <td>
                    <?php if ( $r->file_url ) : ?>
                        <img src="<?php echo esc_url( $r->file_url ); ?>" class="el-bk-receipt-mini" alt="">
                    <?php else : ?>
                        <span>📄</span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( $r->ai_extracted_merchant ?: '—' ); ?></td>
                <td><?php echo esc_html( $r->ai_extracted_date ?: '—' ); ?></td>
                <td><?php echo $r->ai_extracted_amount ? esc_html( '$' . number_format( (float) $r->ai_extracted_amount, 2 ) ) : '—'; // phpcs:ignore ?></td>
                <td><?php echo esc_html( $r->ai_extracted_category ?: '—' ); ?></td>
                <td>
                    <?php if ( $r->transaction_id ) : ?>
                        <a href="#" class="el-bk-view-transaction-link" data-id="<?php echo esc_attr( $r->transaction_id ); ?>">
                            <?php echo esc_html( sprintf( __( 'Transaction #%d', 'el-core' ), $r->transaction_id ) ); ?>
                        </a>
                    <?php else : ?>
                        <em><?php esc_html_e( 'Unattached', 'el-core' ); ?></em>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $r->transaction_id ) : ?>
                        <button class="el-btn el-btn-outline el-bk-detach-receipt-btn" data-receipt-id="<?php echo esc_attr( $r->id ); ?>">
                            <?php esc_html_e( 'Detach', 'el-core' ); ?>
                        </button>
                    <?php endif; ?>
                    <button class="el-btn el-btn-outline el-bk-delete-receipt-btn" data-receipt-id="<?php echo esc_attr( $r->id ); ?>">
                        <?php esc_html_e( 'Delete', 'el-core' ); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
