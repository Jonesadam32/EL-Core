<?php
/**
 * Bookkeeping — Known Expenses Tab (AI Rule Builder)
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$rules = $module->get_rules();
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php esc_html_e( 'Known Expenses', 'el-core' ); ?></h2>
        <p class="el-bk-tab-desc"><?php esc_html_e( 'Define merchant → category rules. These are applied automatically when importing CSV files.', 'el-core' ); ?></p>
    </div>
</div>

<!-- AI Chat Interface -->
<div class="el-bk-card el-bk-ai-chat-card">
    <h3><?php esc_html_e( 'Add Rules with AI', 'el-core' ); ?></h3>
    <p class="el-bk-hint"><?php esc_html_e( 'Type merchant names and categories naturally. Example: "Adobe is Software and Application Fees, Google Workspace is Membership and Subscription"', 'el-core' ); ?></p>

    <div class="el-bk-chat-log" id="el-bk-chat-log">
        <p class="el-bk-chat-placeholder"><?php esc_html_e( 'Your conversation will appear here…', 'el-core' ); ?></p>
    </div>

    <div class="el-bk-chat-input-row">
        <textarea id="el-bk-chat-input" class="el-textarea" rows="2"
            placeholder="<?php esc_attr_e( 'Adobe is Software and Application Fees, Fathom is Software…', 'el-core' ); ?>"></textarea>
        <button class="el-btn el-btn-primary" id="el-bk-chat-send-btn">
            <?php esc_html_e( 'Process Rules', 'el-core' ); ?>
        </button>
    </div>
</div>

<!-- Import Rules from Prior-Year CSV -->
<div class="el-bk-card el-bk-csv-import-card">
    <h3><?php esc_html_e( 'Import Rules from Prior-Year Expenses', 'el-core' ); ?></h3>
    <p class="el-bk-hint"><?php esc_html_e( 'Upload one CSV per category (e.g. your "Accounting Fees" tab exported as CSV). Pick the category, map the description column, and every unique description becomes a rule.', 'el-core' ); ?></p>

    <!-- Step 1: Pick category + upload file -->
    <div id="el-bk-csv-rules-step1">
        <div class="el-bk-form-row" style="align-items:flex-end; gap:12px; flex-wrap:wrap;">
            <label><?php esc_html_e( 'Category for this CSV:', 'el-core' ); ?>
                <select id="el-bk-csv-rules-category" class="el-select" style="min-width:220px;">
                    <option value=""><?php esc_html_e( '— Select Category —', 'el-core' ); ?></option>
                    <?php foreach ( EL_Bookkeeping_Module::get_expense_categories() as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?php esc_html_e( 'CSV File:', 'el-core' ); ?>
                <input type="file" id="el-bk-csv-rules-file" accept=".csv" style="max-width:320px;">
            </label>
            <button class="el-btn el-btn-primary" id="el-bk-csv-rules-upload-btn" disabled>
                <?php esc_html_e( 'Upload & Detect Columns', 'el-core' ); ?>
            </button>
        </div>
    </div>

    <!-- Step 2: Map the description column -->
    <div id="el-bk-csv-rules-step2" style="display:none;">
        <p><strong><?php esc_html_e( 'Map the description/merchant column:', 'el-core' ); ?></strong></p>
        <p class="el-bk-hint" id="el-bk-csv-rules-cat-label"></p>
        <div class="el-bk-form-row" style="align-items:flex-end; gap:12px;">
            <label><?php esc_html_e( 'Description / Merchant column:', 'el-core' ); ?>
                <select id="el-bk-csv-rules-desc-col" class="el-select"></select>
            </label>
            <button class="el-btn el-btn-primary" id="el-bk-csv-rules-import-btn">
                <?php esc_html_e( 'Import as Rules', 'el-core' ); ?>
            </button>
            <button class="el-btn el-btn-outline" id="el-bk-csv-rules-cancel-btn">
                <?php esc_html_e( 'Cancel', 'el-core' ); ?>
            </button>
        </div>
    </div>

    <div id="el-bk-csv-rules-result" style="display:none; margin-top:10px;"></div>
</div>

<!-- Manual Rules Table -->
<div class="el-bk-card">
    <div class="el-bk-card-header">
        <h3><?php esc_html_e( 'Rule Table', 'el-core' ); ?>
            <?php if ( ! empty( $rules ) ) : ?>
                <span id="el-bk-rules-count" style="font-weight:normal;font-size:13px;color:#64748b;margin-left:8px;">(<?php echo count( $rules ); ?> rules)</span>
            <?php endif; ?>
        </h3>
        <button class="el-btn el-btn-outline" id="el-bk-add-rule-btn"><?php esc_html_e( '+ Add Rule', 'el-core' ); ?></button>
    </div>

    <?php if ( empty( $rules ) ) : ?>
        <?php echo EL_Admin_UI::notice( [ 'message' => __( 'No rules defined yet. Use the AI chat above or click Add Rule.', 'el-core' ), 'type' => 'info' ] ); // phpcs:ignore ?>
    <?php else : ?>

    <?php
    $rule_categories = array_values( array_unique( array_map( fn( $r ) => $r->category, $rules ) ) );
    sort( $rule_categories );
    ?>

    <!-- Filter bar -->
    <div class="el-bk-rules-filter-bar" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
        <label style="display:flex; align-items:center; gap:4px;">
            <?php esc_html_e( 'Category:', 'el-core' ); ?>
            <select id="el-bk-rules-filter-cat" class="el-select" style="min-width:180px;">
                <option value=""><?php esc_html_e( '— All Categories —', 'el-core' ); ?></option>
                <?php foreach ( $rule_categories as $rc ) : ?>
                    <option value="<?php echo esc_attr( $rc ); ?>"><?php echo esc_html( $rc ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="display:flex; align-items:center; gap:4px;">
            <?php esc_html_e( 'Search:', 'el-core' ); ?>
            <input type="text" id="el-bk-rules-search" class="el-input" placeholder="<?php esc_attr_e( 'Filter by keyword…', 'el-core' ); ?>" style="min-width:200px;">
        </label>
        <span id="el-bk-rules-visible-count" style="font-size:13px;color:#64748b;"></span>
        <button class="el-btn el-btn-outline" id="el-bk-bulk-delete-btn" style="margin-left:auto; color:#dc2626; border-color:#dc2626; display:none;">
            <?php esc_html_e( 'Delete Selected', 'el-core' ); ?>
        </button>
    </div>

    <table class="el-bk-rules-table widefat" id="el-bk-rules-table">
        <thead>
            <tr>
                <th style="width:32px;"><input type="checkbox" id="el-bk-rules-select-all" title="<?php esc_attr_e( 'Select all visible', 'el-core' ); ?>"></th>
                <th class="el-bk-drag-handle-col"></th>
                <th><?php esc_html_e( 'Merchant / Keyword', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Match Type', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Category', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody id="el-bk-rules-tbody">
            <?php foreach ( $rules as $rule ) : ?>
            <tr data-rule-id="<?php echo esc_attr( $rule->id ); ?>" data-category="<?php echo esc_attr( $rule->category ); ?>" data-keyword="<?php echo esc_attr( strtolower( $rule->keyword ) ); ?>">
                <td><input type="checkbox" class="el-bk-rule-check" value="<?php echo esc_attr( $rule->id ); ?>"></td>
                <td class="el-bk-drag-handle">⠿</td>
                <td><?php echo esc_html( $rule->keyword ); ?></td>
                <td><?php echo esc_html( ucfirst( $rule->match_type ) ); ?></td>
                <td><?php echo esc_html( $rule->category ); ?></td>
                <td>
                    <button class="el-btn el-btn-outline el-bk-edit-rule-btn"
                        data-id="<?php echo esc_attr( $rule->id ); ?>"
                        data-keyword="<?php echo esc_attr( $rule->keyword ); ?>"
                        data-match-type="<?php echo esc_attr( $rule->match_type ); ?>"
                        data-category="<?php echo esc_attr( $rule->category ); ?>">
                        <?php esc_html_e( 'Edit', 'el-core' ); ?>
                    </button>
                    <button class="el-btn el-btn-outline el-bk-delete-rule-btn" data-id="<?php echo esc_attr( $rule->id ); ?>">
                        <?php esc_html_e( 'Delete', 'el-core' ); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Add/Edit Rule Inline Form (hidden until triggered) -->
<div id="el-bk-rule-form" class="el-bk-card" style="display:none;">
    <h3><?php esc_html_e( 'Rule Details', 'el-core' ); ?></h3>
    <input type="hidden" id="el-bk-rule-id" value="">
    <div class="el-bk-form-row">
        <label><?php esc_html_e( 'Merchant / Keyword', 'el-core' ); ?>
            <input type="text" id="el-bk-rule-keyword" class="el-input">
        </label>
        <label><?php esc_html_e( 'Match Type', 'el-core' ); ?>
            <select id="el-bk-rule-match-type" class="el-select">
                <option value="contains"><?php esc_html_e( 'Contains', 'el-core' ); ?></option>
                <option value="all_words"><?php esc_html_e( 'All Words', 'el-core' ); ?></option>
                <option value="exact"><?php esc_html_e( 'Exact', 'el-core' ); ?></option>
            </select>
        </label>
        <label><?php esc_html_e( 'Category', 'el-core' ); ?>
            <select id="el-bk-rule-category" class="el-select">
                <option value=""><?php esc_html_e( '— Select —', 'el-core' ); ?></option>
                <?php foreach ( EL_Bookkeeping_Module::get_expense_categories() as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="el-bk-form-actions">
        <button class="el-btn el-btn-primary" id="el-bk-save-rule-btn"><?php esc_html_e( 'Save Rule', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline" id="el-bk-cancel-rule-btn"><?php esc_html_e( 'Cancel', 'el-core' ); ?></button>
    </div>
</div>
