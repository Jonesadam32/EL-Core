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

<!-- Manual Rules Table -->
<div class="el-bk-card">
    <div class="el-bk-card-header">
        <h3><?php esc_html_e( 'Rule Table', 'el-core' ); ?></h3>
        <button class="el-btn el-btn-outline" id="el-bk-add-rule-btn"><?php esc_html_e( '+ Add Rule', 'el-core' ); ?></button>
    </div>

    <?php if ( empty( $rules ) ) : ?>
        <?php echo EL_Admin_UI::notice( [ 'message' => __( 'No rules defined yet. Use the AI chat above or click Add Rule.', 'el-core' ), 'type' => 'info' ] ); // phpcs:ignore ?>
    <?php else : ?>
    <table class="el-bk-rules-table widefat" id="el-bk-rules-table">
        <thead>
            <tr>
                <th class="el-bk-drag-handle-col"></th>
                <th><?php esc_html_e( 'Merchant / Keyword', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Match Type', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Category', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody id="el-bk-rules-tbody">
            <?php foreach ( $rules as $rule ) : ?>
            <tr data-rule-id="<?php echo esc_attr( $rule->id ); ?>">
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
