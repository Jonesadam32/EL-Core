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
        <p class="el-bk-tab-desc"><?php esc_html_e( 'Drag and drop receipt photos or PDFs. AI extracts merchant, date, amount, and category automatically.', 'el-core' ); ?></p>
    </div>
</div>

<!-- Upload Drop Zone -->
<div class="el-bk-card el-bk-upload-zone" id="el-bk-receipt-upload-zone">
    <div class="el-bk-upload-icon">📷</div>
    <p><?php esc_html_e( 'Drag and drop receipts here, or click to browse', 'el-core' ); ?></p>
    <p class="el-bk-hint"><?php esc_html_e( 'Accepts: JPG, PNG, PDF — max 10 MB each. AI extracts data from images automatically.', 'el-core' ); ?></p>
    <input type="file" id="el-bk-receipt-file-input" accept=".jpg,.jpeg,.png,.pdf" multiple style="display:none;">
    <button class="el-btn el-btn-primary" id="el-bk-receipt-browse-btn">
        <?php esc_html_e( 'Browse Files', 'el-core' ); ?>
    </button>
    <div id="el-bk-receipt-upload-status" style="margin-top:10px;font-size:13px;min-height:18px;"></div>
</div>

<!-- Review Queue — populated by JS after each successful upload -->
<div id="el-bk-receipt-review-queue" class="el-bk-receipt-review-queue"></div>

<!-- Manual Receipt Entry Form -->
<div class="el-bk-card el-bk-manual-receipt-card">
    <div class="el-bk-card-header">
        <h3><?php esc_html_e( 'Manual Receipt Entry', 'el-core' ); ?></h3>
        <p class="el-bk-tab-desc"><?php esc_html_e( 'For old, faded, or handwritten receipts the AI cannot read.', 'el-core' ); ?></p>
    </div>
    <div class="el-bk-manual-receipt-form">

        <div class="el-bk-form-row-inline">
            <div class="el-bk-form-row">
                <label for="el-bk-manual-title"><?php esc_html_e( 'Title', 'el-core' ); ?></label>
                <input type="text" id="el-bk-manual-title" class="el-input" placeholder="<?php esc_attr_e( 'e.g. Home Depot receipt', 'el-core' ); ?>">
            </div>
            <div class="el-bk-form-row">
                <label for="el-bk-manual-date"><?php esc_html_e( 'Date', 'el-core' ); ?></label>
                <input type="date" id="el-bk-manual-date" class="el-input" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
            </div>
        </div>

        <div class="el-bk-form-row-inline">
            <div class="el-bk-form-row">
                <label for="el-bk-manual-vendor"><?php esc_html_e( 'Vendor', 'el-core' ); ?></label>
                <input type="text" id="el-bk-manual-vendor" class="el-input" placeholder="<?php esc_attr_e( 'Merchant or store name', 'el-core' ); ?>">
            </div>
            <div class="el-bk-form-row">
                <label for="el-bk-manual-amount"><?php esc_html_e( 'Amount', 'el-core' ); ?></label>
                <input type="text" id="el-bk-manual-amount" class="el-input" placeholder="0.00">
            </div>
        </div>

        <div class="el-bk-form-row">
            <label for="el-bk-manual-category"><?php esc_html_e( 'Category', 'el-core' ); ?></label>
            <select id="el-bk-manual-category" class="el-select">
                <option value=""><?php esc_html_e( '— Select category —', 'el-core' ); ?></option>
                <?php $grouped = EL_Bookkeeping_Module::get_expense_categories_grouped(); ?>
                <optgroup label="<?php esc_attr_e( 'Business', 'el-core' ); ?>">
                    <?php foreach ( $grouped['business'] as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="<?php esc_attr_e( 'Personal', 'el-core' ); ?>">
                    <?php foreach ( $grouped['personal'] as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>

        <div class="el-bk-form-row">
            <label for="el-bk-manual-notes"><?php esc_html_e( 'Notes', 'el-core' ); ?></label>
            <textarea id="el-bk-manual-notes" class="el-textarea" rows="2" placeholder="<?php esc_attr_e( 'Optional notes about this receipt', 'el-core' ); ?>"></textarea>
        </div>

        <div class="el-bk-form-row">
            <label for="el-bk-manual-image"><?php esc_html_e( 'Image', 'el-core' ); ?> <span class="el-bk-hint"><?php esc_html_e( '(optional — JPG, PNG, PDF, max 10 MB)', 'el-core' ); ?></span></label>
            <input type="file" id="el-bk-manual-image" class="el-input" accept=".jpg,.jpeg,.png,.pdf">
        </div>

        <div class="el-bk-form-actions">
            <button class="el-btn el-btn-primary" id="el-bk-manual-receipt-save-btn">
                <?php esc_html_e( 'Save Receipt', 'el-core' ); ?>
            </button>
            <button class="el-btn el-btn-outline" id="el-bk-manual-receipt-add-another-btn">
                <?php esc_html_e( 'Save &amp; Add Another', 'el-core' ); ?>
            </button>
        </div>

        <div id="el-bk-manual-receipt-status" class="el-bk-manual-receipt-status"></div>
    </div>
</div>

<!-- Unmatched Receipts Panel -->
<?php if ( ! empty( $unmatched ) ) : ?>
<div class="el-bk-card">
    <h3><?php echo esc_html( sprintf( __( 'Unmatched Receipts (%d)', 'el-core' ), count( $unmatched ) ) ); ?></h3>
    <div class="el-bk-receipt-grid">
        <?php foreach ( $unmatched as $r ) : ?>
        <div class="el-bk-receipt-thumb" data-receipt-id="<?php echo esc_attr( $r->id ); ?>">
            <?php if ( $r->file_url && in_array( $r->file_type, [ 'jpg', 'jpeg', 'png' ], true ) ) : ?>
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
    <?php echo EL_Admin_UI::notice( [ 'message' => __( 'No receipts uploaded yet. Use the drop zone above to get started.', 'el-core' ), 'type' => 'info' ] ); // phpcs:ignore ?>
<?php else : ?>
<div class="el-bk-card">
    <div class="el-bk-card-header">
        <h3><?php echo esc_html( sprintf( __( 'All Receipts (%d)', 'el-core' ), count( $all ) ) ); ?></h3>
    </div>
    <table class="el-bk-receipts-table widefat">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Thumbnail', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Merchant', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Date', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Category', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Status', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Attached To', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $all as $r ) : ?>
            <tr>
                <td>
                    <?php if ( $r->file_url && in_array( $r->file_type, [ 'jpg', 'jpeg', 'png' ], true ) ) : ?>
                        <a href="<?php echo esc_url( $r->file_url ); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo esc_url( $r->file_url ); ?>" class="el-bk-receipt-mini" alt="">
                        </a>
                    <?php elseif ( $r->file_url ) : ?>
                        <a href="<?php echo esc_url( $r->file_url ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Open PDF', 'el-core' ); ?>">📄</a>
                    <?php else : ?>
                        <span title="<?php esc_attr_e( 'No file', 'el-core' ); ?>">📄</span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( $r->ai_extracted_merchant ?: '—' ); ?></td>
                <td><?php echo esc_html( $r->ai_extracted_date ?: '—' ); ?></td>
                <td class="el-bk-amount"><?php echo $r->ai_extracted_amount ? esc_html( '$' . number_format( (float) $r->ai_extracted_amount, 2 ) ) : '—'; // phpcs:ignore ?></td>
                <td><?php echo esc_html( $r->ai_extracted_category ?: '—' ); ?></td>
                <td>
                    <?php if ( $r->status === 'matched' ) : ?>
                        <span class="el-bk-status-badge el-bk-status-badge--matched"><?php esc_html_e( 'Matched', 'el-core' ); ?></span>
                    <?php else : ?>
                        <span class="el-bk-status-badge el-bk-status-badge--unmatched"><?php esc_html_e( 'Unmatched', 'el-core' ); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $r->transaction_id ) : ?>
                        <span class="el-bk-receipt-txn-label">
                            <?php echo esc_html( sprintf( __( 'Txn #%d', 'el-core' ), $r->transaction_id ) ); ?>
                        </span>
                    <?php else : ?>
                        <em class="el-bk-muted"><?php esc_html_e( 'None', 'el-core' ); ?></em>
                    <?php endif; ?>
                </td>
                <td class="el-bk-receipt-actions-cell">
                    <?php if ( $r->transaction_id ) : ?>
                        <button class="el-btn el-btn-outline el-btn-sm el-bk-detach-receipt-btn"
                                data-receipt-id="<?php echo esc_attr( $r->id ); ?>">
                            <?php esc_html_e( 'Detach', 'el-core' ); ?>
                        </button>
                    <?php endif; ?>
                    <button class="el-btn el-btn-outline el-btn-sm el-bk-delete-receipt-btn"
                            data-receipt-id="<?php echo esc_attr( $r->id ); ?>">
                        <?php esc_html_e( 'Delete', 'el-core' ); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
