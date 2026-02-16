<?php
/**
 * Shortcode: [el_register_form]
 * 
 * Renders a registration form with configurable fields, invite code
 * support, and workflow enforcement. This is a COMPONENT — it renders
 * just the form, not an entire page. The page layout is handled by
 * the WordPress block editor.
 * 
 * Parameters:
 *   redirect — URL to redirect after successful registration (overrides module setting)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_register_form( $atts ): string {
    $atts = shortcode_atts( [
        'redirect' => '',
    ], $atts, 'el_register_form' );

    $module = EL_Registration_Module::instance();
    $mode   = $module->get_mode();

    // Already logged in
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        return '<div class="el-component el-register-form">'
             . '<div class="el-notice el-notice-info">'
             . '<p>You are already logged in as <strong>' . esc_html( $user->display_name ) . '</strong>.</p>'
             . '</div></div>';
    }

    // Registration closed
    if ( $mode === 'closed' ) {
        return '<div class="el-component el-register-form">'
             . '<div class="el-notice el-notice-warning">'
             . '<p>Registration is currently closed. Please contact the site administrator for access.</p>'
             . '</div></div>';
    }

    // Gather settings
    $custom_fields  = $module->get_custom_fields();
    $allowed_roles  = $module->get_allowed_roles();
    $show_invite    = ( $mode === 'invite' );
    $redirect_url   = $atts['redirect'] ?: $module->get_setting( 'redirect_after_register', '' );

    $html = '<div class="el-component el-register-form">';

    // Mode-specific notice
    if ( $mode === 'invite' ) {
        $html .= '<div class="el-notice el-notice-info el-register-notice">'
               . '<p>Registration is by invitation only. Please enter your invite code below.</p>'
               . '</div>';
    } elseif ( $mode === 'approval' ) {
        $html .= '<div class="el-notice el-notice-info el-register-notice">'
               . '<p>After registering, your account will be reviewed before you can log in.</p>'
               . '</div>';
    }

    $html .= '<form class="el-form" id="el-register-form">';
    $html .= '<div class="el-form-status"></div>';

    // ── Invite code ──────────────────────────

    if ( $show_invite ) {
        // Required — shown prominently
        $html .= '<div class="el-field">';
        $html .= '<label for="el-reg-invite" class="el-label">Invite Code <span class="el-required">*</span></label>';
        $html .= '<input type="text" id="el-reg-invite" name="invite_code" class="el-input" placeholder="Enter your invite code" required>';
        $html .= '</div>';
    } elseif ( $mode !== 'closed' ) {
        // Optional — hidden behind toggle link
        $html .= '<div class="el-field">';
        $html .= '<button type="button" class="el-invite-toggle">Have an invite code?</button>';
        $html .= '<div class="el-invite-field-wrapper" style="display:none;">';
        $html .= '<label for="el-reg-invite" class="el-label">Invite Code</label>';
        $html .= '<input type="text" id="el-reg-invite" name="invite_code" class="el-input" placeholder="Enter your invite code">';
        $html .= '</div>';
        $html .= '</div>';
    }

    // ── Name fields (side by side) ───────────

    $html .= '<div class="el-field-row">';
    $html .= '<div class="el-field">';
    $html .= '<label for="el-reg-first-name" class="el-label">First Name</label>';
    $html .= '<input type="text" id="el-reg-first-name" name="first_name" class="el-input" placeholder="First name">';
    $html .= '</div>';
    $html .= '<div class="el-field">';
    $html .= '<label for="el-reg-last-name" class="el-label">Last Name</label>';
    $html .= '<input type="text" id="el-reg-last-name" name="last_name" class="el-input" placeholder="Last name">';
    $html .= '</div>';
    $html .= '</div>';

    // ── Username ─────────────────────────────

    $html .= '<div class="el-field">';
    $html .= '<label for="el-reg-username" class="el-label">Username <span class="el-required">*</span></label>';
    $html .= '<input type="text" id="el-reg-username" name="username" class="el-input" placeholder="Choose a username" required autocomplete="username">';
    $html .= '</div>';

    // ── Email ────────────────────────────────

    $html .= '<div class="el-field">';
    $html .= '<label for="el-reg-email" class="el-label">Email Address <span class="el-required">*</span></label>';
    $html .= '<input type="email" id="el-reg-email" name="email" class="el-input" placeholder="you@example.com" required autocomplete="email">';
    $html .= '</div>';

    // ── Password ─────────────────────────────

    $html .= '<div class="el-field">';
    $html .= '<label for="el-reg-password" class="el-label">Password <span class="el-required">*</span></label>';
    $html .= '<input type="password" id="el-reg-password" name="password" class="el-input" placeholder="Minimum 8 characters" required minlength="8" autocomplete="new-password">';
    $html .= '</div>';

    $html .= '<div class="el-field">';
    $html .= '<label for="el-reg-password-confirm" class="el-label">Confirm Password <span class="el-required">*</span></label>';
    $html .= '<input type="password" id="el-reg-password-confirm" name="password_confirm" class="el-input" placeholder="Re-enter your password" required minlength="8" autocomplete="new-password">';
    $html .= '</div>';

    // ── Role selection (if enabled) ──────────

    if ( ! empty( $allowed_roles ) ) {
        $html .= '<div class="el-field">';
        $html .= '<label for="el-reg-role" class="el-label">Role</label>';
        $html .= '<select id="el-reg-role" name="role" class="el-select">';
        foreach ( $allowed_roles as $slug => $name ) {
            $html .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';
    }

    // ── Custom fields ────────────────────────

    foreach ( $custom_fields as $field ) {
        $req_attr = ! empty( $field['required'] ) ? ' required' : '';
        $req_mark = ! empty( $field['required'] )
            ? ' <span class="el-required">*</span>' : '';

        $field_id   = 'el-reg-' . sanitize_html_class( $field['key'] );
        $input_type = $field['type'] ?? 'text';

        $html .= '<div class="el-field">';
        $html .= '<label for="' . esc_attr( $field_id ) . '" class="el-label">'
               . esc_html( $field['label'] ) . $req_mark . '</label>';

        if ( $input_type === 'textarea' ) {
            $html .= '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field['key'] ) . '" '
                   . 'class="el-textarea"' . $req_attr . '></textarea>';
        } elseif ( $input_type === 'select' && ! empty( $field['options'] ) ) {
            $html .= '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field['key'] ) . '" '
                   . 'class="el-select"' . $req_attr . '>';
            $html .= '<option value="">— Select —</option>';
            foreach ( $field['options'] as $opt ) {
                $html .= '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $field_id ) . '" '
                   . 'name="' . esc_attr( $field['key'] ) . '" class="el-input"' . $req_attr . '>';
        }

        $html .= '</div>';
    }

    // ── Honeypot (hidden from humans) ────────

    $html .= '<div class="el-hp-field" aria-hidden="true">';
    $html .= '<label for="el-reg-website-url">Website</label>';
    $html .= '<input type="text" id="el-reg-website-url" name="website_url" tabindex="-1" autocomplete="off">';
    $html .= '</div>';

    // ── Hidden redirect ──────────────────────

    if ( $redirect_url ) {
        $html .= '<input type="hidden" name="redirect_url" value="' . esc_attr( $redirect_url ) . '">';
    }

    // ── Submit ────────────────────────────────

    $html .= '<div class="el-field el-field-submit">';
    $html .= '<button type="submit" class="el-btn el-btn-primary">Create Account</button>';
    $html .= '</div>';

    $html .= '</form>';

    // ── Footer ────────────────────────────────

    $html .= '<div class="el-form-footer">';
    $html .= '<p>Already have an account? <a href="' . esc_url( wp_login_url() ) . '">Log in</a></p>';
    $html .= '</div>';

    $html .= '</div>'; // .el-register-form

    return $html;
}
