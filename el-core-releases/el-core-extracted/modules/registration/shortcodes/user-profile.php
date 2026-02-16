<?php
/**
 * Shortcode: [el_user_profile]
 * 
 * Displays the current user's profile with custom registration fields.
 * When editable, provides a form to update field values.
 * This is a COMPONENT — renders the profile card/form, not a full page.
 * 
 * Parameters:
 *   editable — Whether users can edit their fields (default: true)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_user_profile( $atts ): string {
    $atts = shortcode_atts( [
        'editable' => 'true',
    ], $atts, 'el_user_profile' );

    $editable = filter_var( $atts['editable'], FILTER_VALIDATE_BOOLEAN );

    // Must be logged in
    if ( ! is_user_logged_in() ) {
        return '<div class="el-component el-user-profile">'
             . '<div class="el-notice el-notice-warning">'
             . '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your profile.</p>'
             . '</div></div>';
    }

    $user    = wp_get_current_user();
    $user_id = $user->ID;
    $module  = EL_Registration_Module::instance();
    $fields  = $module->get_user_custom_fields( $user_id );

    // Check registration status
    $reg_status     = get_user_meta( $user_id, 'el_registration_status', true );
    $email_verified = get_user_meta( $user_id, 'el_email_verified', true );

    $html = '<div class="el-component el-user-profile">';

    // ── Status banners ───────────────────────

    if ( $reg_status === 'pending' ) {
        $html .= '<div class="el-notice el-notice-warning el-profile-banner">'
               . '<p>Your account is pending approval. Some features may be limited.</p>'
               . '</div>';
    }

    if ( $email_verified === '0' ) {
        $html .= '<div class="el-notice el-notice-warning el-profile-banner">'
               . '<p>Your email address has not been verified. '
               . '<button type="button" class="el-resend-verification el-invite-toggle">Resend verification email</button>'
               . '</p></div>';
    }

    // ── Profile header ───────────────────────

    $avatar_url = get_avatar_url( $user_id, [ 'size' => 144 ] );

    $html .= '<div class="el-profile-header">';
    $html .= '<img src="' . esc_url( $avatar_url ) . '" alt="" class="el-profile-avatar">';
    $html .= '<div>';
    $html .= '<h3 class="el-profile-name">' . esc_html( $user->display_name ) . '</h3>';
    $html .= '<p class="el-profile-email">' . esc_html( $user->user_email ) . '</p>';

    // Role badge
    $roles = $user->roles;
    if ( ! empty( $roles ) ) {
        $wp_roles  = wp_roles()->roles;
        $role_slug = $roles[0];
        $role_name = isset( $wp_roles[ $role_slug ] )
            ? translate_user_role( $wp_roles[ $role_slug ]['name'] )
            : $role_slug;
        $html .= '<span class="el-role-badge">' . esc_html( $role_name ) . '</span>';
    }

    $html .= '</div>';
    $html .= '</div>'; // .el-profile-header

    // ── Editable form ────────────────────────

    if ( $editable ) {
        $html .= '<form class="el-form" id="el-profile-form">';
        $html .= '<div class="el-form-status"></div>';

        // Core name fields
        $html .= '<div class="el-field-row">';
        $html .= '<div class="el-field">';
        $html .= '<label for="el-profile-first-name" class="el-label">First Name</label>';
        $html .= '<input type="text" id="el-profile-first-name" name="first_name" class="el-input" '
               . 'value="' . esc_attr( $user->first_name ) . '">';
        $html .= '</div>';
        $html .= '<div class="el-field">';
        $html .= '<label for="el-profile-last-name" class="el-label">Last Name</label>';
        $html .= '<input type="text" id="el-profile-last-name" name="last_name" class="el-input" '
               . 'value="' . esc_attr( $user->last_name ) . '">';
        $html .= '</div>';
        $html .= '</div>';

        // Custom fields
        foreach ( $fields as $key => $field ) {
            $req_attr = $field['required'] ? ' required' : '';
            $req_mark = $field['required'] ? ' <span class="el-required">*</span>' : '';
            $field_id = 'el-profile-' . sanitize_html_class( $key );

            $html .= '<div class="el-field">';
            $html .= '<label for="' . esc_attr( $field_id ) . '" class="el-label">'
                   . esc_html( $field['label'] ) . $req_mark . '</label>';

            if ( $field['type'] === 'textarea' ) {
                $html .= '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $key ) . '" '
                       . 'class="el-textarea"' . $req_attr . '>'
                       . esc_textarea( $field['value'] ) . '</textarea>';
            } elseif ( $field['type'] === 'select' && ! empty( $field['options'] ) ) {
                $html .= '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $key ) . '" '
                       . 'class="el-select"' . $req_attr . '>';
                foreach ( $field['options'] as $opt ) {
                    $selected = ( $field['value'] === $opt ) ? ' selected' : '';
                    $html .= '<option value="' . esc_attr( $opt ) . '"' . $selected . '>' . esc_html( $opt ) . '</option>';
                }
                $html .= '</select>';
            } else {
                $html .= '<input type="' . esc_attr( $field['type'] ) . '" '
                       . 'id="' . esc_attr( $field_id ) . '" '
                       . 'name="' . esc_attr( $key ) . '" '
                       . 'class="el-input" '
                       . 'value="' . esc_attr( $field['value'] ) . '"'
                       . $req_attr . '>';
            }

            $html .= '</div>';
        }

        // Submit
        $html .= '<div class="el-field el-field-submit">';
        $html .= '<button type="submit" class="el-btn el-btn-primary">Save Changes</button>';
        $html .= '</div>';

        $html .= '</form>';

    // ── Read-only display ────────────────────

    } else {
        if ( ! empty( $fields ) ) {
            $html .= '<dl class="el-profile-fields">';
            foreach ( $fields as $key => $field ) {
                if ( empty( $field['value'] ) ) continue;
                $html .= '<dt>' . esc_html( $field['label'] ) . '</dt>';
                $html .= '<dd>' . esc_html( $field['value'] ) . '</dd>';
            }
            $html .= '</dl>';
        }
    }

    $html .= '</div>'; // .el-user-profile

    return $html;
}
