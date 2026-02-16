<?php
/**
 * Registration Module
 * 
 * Business logic for user registration workflows: open, approval-based,
 * invite-only, and closed modes. Extends WordPress user system with
 * custom profile fields and registration enforcement.
 * 
 * Infrastructure (database, shortcodes, settings) is handled by
 * EL Core based on module.json.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Registration_Module {

    private static ?EL_Registration_Module $instance = null;
    private EL_Core $core;

    /**
     * User meta key constants — single source of truth for all meta keys
     */
    private const META_STATUS         = 'el_registration_status';
    private const META_EMAIL_VERIFIED = 'el_email_verified';
    private const META_VERIFY_TOKEN   = 'el_email_verify_token';
    private const META_VERIFY_EXPIRES = 'el_email_verify_expires';
    private const META_INVITE_USED    = 'el_invite_code_used';
    private const META_REGISTERED_VIA = 'el_registered_via';

    /**
     * Rate limit: max registration attempts per IP in a 15-minute window
     */
    private const RATE_LIMIT_MAX     = 5;
    private const RATE_LIMIT_WINDOW  = 900; // 15 minutes in seconds

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->core = EL_Core::instance();
        $this->init_hooks();
    }

    /**
     * Register all module hooks
     */
    private function init_hooks(): void {
        // AJAX handlers — register_user and verify_email need nopriv (guest access)
        add_action( 'el_core_ajax_register_user',        [ $this, 'handle_register_user' ] );
        add_action( 'el_core_ajax_nopriv_register_user', [ $this, 'handle_register_user' ] );
        add_action( 'el_core_ajax_verify_email',         [ $this, 'handle_verify_email' ] );
        add_action( 'el_core_ajax_nopriv_verify_email',  [ $this, 'handle_verify_email' ] );
        add_action( 'el_core_ajax_approve_user',         [ $this, 'handle_approve_user' ] );
        add_action( 'el_core_ajax_reject_user',          [ $this, 'handle_reject_user' ] );
        add_action( 'el_core_ajax_create_invite',        [ $this, 'handle_create_invite' ] );
        add_action( 'el_core_ajax_resend_verification',  [ $this, 'handle_resend_verification' ] );
        add_action( 'el_core_ajax_update_profile',        [ $this, 'handle_update_profile' ] );

        // Intercept email verification links (GET requests from email)
        add_action( 'template_redirect', [ $this, 'handle_verification_link' ] );

        // Block pending/unverified users from logging in
        add_filter( 'authenticate', [ $this, 'enforce_registration_status' ], 30, 3 );

        // Disable WordPress default registration page
        add_action( 'login_init', [ $this, 'block_default_registration' ] );

        // Remove the "Register" link from wp-login.php
        add_filter( 'option_users_can_register', [ $this, 'disable_wp_registration_option' ] );
    }

    // ═══════════════════════════════════════════
    // SETTINGS HELPERS
    // ═══════════════════════════════════════════

    /**
     * Get a registration module setting
     */
    public function get_setting( string $key, mixed $default = null ): mixed {
        return $this->core->settings->get( 'mod_registration', $key, $default );
    }

    /**
     * Get the current registration mode
     */
    public function get_mode(): string {
        return $this->get_setting( 'registration_mode', 'closed' );
    }

    /**
     * Check if email verification is required
     */
    public function requires_email_verification(): bool {
        return (bool) $this->get_setting( 'email_verification', true );
    }

    /**
     * Get custom field definitions
     */
    public function get_custom_fields(): array {
        $json = $this->get_setting( 'custom_fields', '[]' );
        $fields = json_decode( $json, true );
        return is_array( $fields ) ? $fields : [];
    }

    /**
     * Get roles available for selection on registration form
     */
    public function get_allowed_roles(): array {
        if ( ! $this->get_setting( 'allow_role_selection', false ) ) {
            return [];
        }

        $allowed = $this->get_setting( 'allowed_roles', '' );
        if ( empty( $allowed ) ) {
            return [];
        }

        $slugs = array_map( 'trim', explode( ',', $allowed ) );
        $wp_roles = wp_roles()->roles;
        $result = [];

        foreach ( $slugs as $slug ) {
            if ( isset( $wp_roles[ $slug ] ) ) {
                $result[ $slug ] = translate_user_role( $wp_roles[ $slug ]['name'] );
            }
        }

        return $result;
    }

    // ═══════════════════════════════════════════
    // REGISTRATION LOGIC
    // ═══════════════════════════════════════════

    /**
     * Process a new user registration
     * 
     * This is the core business logic. The AJAX handler calls this
     * after validation. Other code (future REST API, admin creation)
     * can also call this directly.
     * 
     * @return int|WP_Error User ID on success, WP_Error on failure
     */
    public function register_user( array $data ): int|\WP_Error {
        $mode = $this->get_mode();

        // Mode check
        if ( $mode === 'closed' ) {
            return new \WP_Error( 'registration_closed', 'Registration is currently closed.' );
        }

        // Invite code validation (required in invite mode, optional in others)
        $invite = null;
        if ( $mode === 'invite' ) {
            if ( empty( $data['invite_code'] ) ) {
                return new \WP_Error( 'invite_required', 'An invite code is required to register.' );
            }
            $invite = $this->validate_invite_code( $data['invite_code'] );
            if ( is_wp_error( $invite ) ) {
                return $invite;
            }
        } elseif ( ! empty( $data['invite_code'] ) ) {
            // Optional invite in open/approval modes — validate if provided
            $invite = $this->validate_invite_code( $data['invite_code'] );
            if ( is_wp_error( $invite ) ) {
                return $invite;
            }
        }

        /**
         * Fires before registration validation.
         * Other modules can hook here to add their own validation.
         * 
         * @param array $data Registration form data
         * @param string $mode Current registration mode
         */
        do_action( 'el_registration_before_validate', $data, $mode );

        // Determine role
        $role = $this->get_setting( 'default_role', 'subscriber' );
        if ( $invite && ! empty( $invite->role ) ) {
            $role = $invite->role; // Invite overrides default
        } elseif ( ! empty( $data['role'] ) && $this->get_setting( 'allow_role_selection', false ) ) {
            $allowed = $this->get_allowed_roles();
            if ( isset( $allowed[ $data['role'] ] ) ) {
                $role = $data['role'];
            }
        }

        /**
         * Fires before user creation.
         * 
         * @param array $data Registration form data
         * @param string $role The role that will be assigned
         * @param string $mode Current registration mode
         */
        do_action( 'el_registration_before_create', $data, $role, $mode );

        // Create WordPress user
        $user_id = wp_insert_user( [
            'user_login'   => sanitize_user( $data['username'] ),
            'user_email'   => sanitize_email( $data['email'] ),
            'user_pass'    => $data['password'],
            'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
            'display_name' => sanitize_text_field( trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ) ) ?: sanitize_user( $data['username'] ),
            'role'         => $role,
        ] );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Set registration status based on mode
        $status = ( $mode === 'approval' ) ? 'pending' : 'approved';
        update_user_meta( $user_id, self::META_STATUS, $status );
        update_user_meta( $user_id, self::META_REGISTERED_VIA, 'el_core' );

        // Track invite usage
        if ( $invite ) {
            update_user_meta( $user_id, self::META_INVITE_USED, $invite->code );
            $this->increment_invite_usage( $invite->id );
        }

        // Save custom field values
        $custom_fields = $this->get_custom_fields();
        foreach ( $custom_fields as $field ) {
            $key = $field['key'];
            if ( isset( $data[ $key ] ) ) {
                update_user_meta( $user_id, $key, sanitize_text_field( $data[ $key ] ) );
            }
        }

        // Email verification
        if ( $this->requires_email_verification() ) {
            update_user_meta( $user_id, self::META_EMAIL_VERIFIED, '0' );
            $this->send_verification_email( $user_id );
        } else {
            update_user_meta( $user_id, self::META_EMAIL_VERIFIED, '1' );
        }

        // Notify admins if approval mode
        if ( $mode === 'approval' && $this->get_setting( 'approval_email_notify', true ) ) {
            $this->notify_admins_pending_user( $user_id );
        }

        /**
         * Fires after user registration is complete.
         * 
         * @param int    $user_id The new user's ID
         * @param array  $data    Registration form data
         * @param string $status  Registration status (pending or approved)
         * @param string $mode    Registration mode
         */
        do_action( 'el_registration_after_create', $user_id, $data, $status, $mode );

        return $user_id;
    }

    // ═══════════════════════════════════════════
    // INVITE CODE MANAGEMENT
    // ═══════════════════════════════════════════

    /**
     * Create a new invite code
     * 
     * @return array{id: int, code: string} Created invite details
     */
    public function create_invite( array $data ): array|false {
        $code = $data['code'] ?? $this->generate_invite_code();

        $id = $this->core->database->insert( 'el_invites', [
            'code'       => sanitize_text_field( $code ),
            'created_by' => get_current_user_id(),
            'role'       => sanitize_text_field( $data['role'] ?? '' ),
            'max_uses'   => absint( $data['max_uses'] ?? 1 ),
            'use_count'  => 0,
            'expires_at' => ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null,
            'status'     => 'active',
        ] );

        if ( ! $id ) {
            return false;
        }

        return [ 'id' => $id, 'code' => $code ];
    }

    /**
     * Validate an invite code
     * 
     * @return object|WP_Error Invite record on success, WP_Error on failure
     */
    public function validate_invite_code( string $code ): object|\WP_Error {
        $results = $this->core->database->query(
            'el_invites',
            [ 'code' => sanitize_text_field( $code ), 'status' => 'active' ]
        );

        if ( empty( $results ) ) {
            return new \WP_Error( 'invalid_invite', 'This invite code is not valid.' );
        }

        $invite = $results[0];

        // Check expiration
        if ( ! empty( $invite->expires_at ) && strtotime( $invite->expires_at ) < time() ) {
            return new \WP_Error( 'invite_expired', 'This invite code has expired.' );
        }

        // Check usage limit (0 = unlimited)
        if ( $invite->max_uses > 0 && $invite->use_count >= $invite->max_uses ) {
            return new \WP_Error( 'invite_exhausted', 'This invite code has reached its usage limit.' );
        }

        return $invite;
    }

    /**
     * Increment invite usage count
     */
    private function increment_invite_usage( int $invite_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'el_invites';
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET use_count = use_count + 1 WHERE id = %d",
            $invite_id
        ) );
    }

    /**
     * Generate a random invite code
     */
    private function generate_invite_code(): string {
        return strtoupper( wp_generate_password( 8, false, false ) );
    }

    /**
     * Get all invite codes (for admin)
     */
    public function get_invites( int $limit = 50 ): array {
        return $this->core->database->query(
            'el_invites',
            [],
            [ 'orderby' => 'created_at', 'order' => 'DESC', 'limit' => $limit ]
        );
    }

    /**
     * Disable an invite code
     */
    public function disable_invite( int $invite_id ): bool {
        return (bool) $this->core->database->update(
            'el_invites',
            [ 'status' => 'disabled' ],
            [ 'id' => $invite_id ]
        );
    }

    // ═══════════════════════════════════════════
    // EMAIL VERIFICATION
    // ═══════════════════════════════════════════

    /**
     * Send a verification email to a user
     */
    public function send_verification_email( int $user_id ): bool {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Generate token (random string), store hash
        $raw_token = wp_generate_password( 32, false, false );
        $hashed    = wp_hash_password( $raw_token );
        $expires   = time() + ( 24 * HOUR_IN_SECONDS ); // 24-hour expiry

        update_user_meta( $user_id, self::META_VERIFY_TOKEN, $hashed );
        update_user_meta( $user_id, self::META_VERIFY_EXPIRES, $expires );

        // Build verification URL
        $verify_url = add_query_arg( [
            'el_action' => 'verify_email',
            'user'      => $user_id,
            'token'     => $raw_token,
        ], home_url( '/' ) );

        $org_name = el_core_get_org_name();
        $subject  = sprintf( '[%s] Verify your email address', $org_name );

        $message  = sprintf( "Hi %s,\n\n", esc_html( $user->first_name ?: $user->user_login ) );
        $message .= sprintf( "Thank you for registering at %s.\n\n", esc_html( $org_name ) );
        $message .= "Please click the link below to verify your email address:\n\n";
        $message .= $verify_url . "\n\n";
        $message .= "This link will expire in 24 hours.\n\n";
        $message .= "If you did not create this account, you can ignore this email.\n";

        return wp_mail( $user->user_email, $subject, $message );
    }

    /**
     * Verify an email token
     * 
     * @return true|WP_Error
     */
    public function verify_email_token( int $user_id, string $raw_token ): true|\WP_Error {
        $stored_hash = get_user_meta( $user_id, self::META_VERIFY_TOKEN, true );
        $expires     = (int) get_user_meta( $user_id, self::META_VERIFY_EXPIRES, true );

        if ( empty( $stored_hash ) ) {
            return new \WP_Error( 'no_token', 'No verification token found. It may have already been used.' );
        }

        if ( $expires < time() ) {
            // Clean up expired token
            delete_user_meta( $user_id, self::META_VERIFY_TOKEN );
            delete_user_meta( $user_id, self::META_VERIFY_EXPIRES );
            return new \WP_Error( 'token_expired', 'This verification link has expired. Please request a new one.' );
        }

        if ( ! wp_check_password( $raw_token, $stored_hash ) ) {
            return new \WP_Error( 'invalid_token', 'Invalid verification link.' );
        }

        // Mark as verified and clean up token (one-time use)
        update_user_meta( $user_id, self::META_EMAIL_VERIFIED, '1' );
        delete_user_meta( $user_id, self::META_VERIFY_TOKEN );
        delete_user_meta( $user_id, self::META_VERIFY_EXPIRES );

        /**
         * Fires after a user verifies their email.
         * 
         * @param int $user_id The user who verified
         */
        do_action( 'el_registration_after_verify_email', $user_id );

        return true;
    }

    // ═══════════════════════════════════════════
    // APPROVAL WORKFLOW
    // ═══════════════════════════════════════════

    /**
     * Approve a pending user
     */
    public function approve_user( int $user_id ): bool {
        $status = get_user_meta( $user_id, self::META_STATUS, true );
        if ( $status !== 'pending' ) {
            return false;
        }

        update_user_meta( $user_id, self::META_STATUS, 'approved' );

        // Notify the user
        $user = get_userdata( $user_id );
        if ( $user ) {
            $org_name = el_core_get_org_name();
            $subject  = sprintf( '[%s] Your registration has been approved', $org_name );
            $message  = sprintf( "Hi %s,\n\n", esc_html( $user->first_name ?: $user->user_login ) );
            $message .= sprintf( "Your registration at %s has been approved. You can now log in:\n\n", esc_html( $org_name ) );
            $message .= wp_login_url() . "\n\n";
            $message .= "Welcome aboard!\n";

            wp_mail( $user->user_email, $subject, $message );
        }

        /**
         * Fires after a user is approved.
         * Future modules (LMS) can hook here to auto-enroll in cohorts.
         * 
         * @param int $user_id The approved user
         */
        do_action( 'el_registration_after_approval', $user_id );

        return true;
    }

    /**
     * Reject a pending user
     */
    public function reject_user( int $user_id, string $reason = '' ): bool {
        $status = get_user_meta( $user_id, self::META_STATUS, true );
        if ( $status !== 'pending' ) {
            return false;
        }

        update_user_meta( $user_id, self::META_STATUS, 'rejected' );

        // Notify the user
        $user = get_userdata( $user_id );
        if ( $user ) {
            $org_name = el_core_get_org_name();
            $subject  = sprintf( '[%s] Registration update', $org_name );
            $message  = sprintf( "Hi %s,\n\n", esc_html( $user->first_name ?: $user->user_login ) );
            $message .= sprintf( "Unfortunately, your registration at %s was not approved at this time.\n\n", esc_html( $org_name ) );
            if ( $reason ) {
                $message .= "Reason: " . esc_html( $reason ) . "\n\n";
            }
            $message .= "If you believe this is an error, please contact the site administrator.\n";

            wp_mail( $user->user_email, $subject, $message );
        }

        /**
         * Fires after a user registration is rejected.
         * 
         * @param int    $user_id The rejected user
         * @param string $reason  Optional rejection reason
         */
        do_action( 'el_registration_after_rejection', $user_id, $reason );

        return true;
    }

    /**
     * Get users with a specific registration status
     */
    public function get_users_by_status( string $status, int $limit = 50 ): array {
        return get_users( [
            'meta_key'   => self::META_STATUS,
            'meta_value' => $status,
            'number'     => $limit,
            'orderby'    => 'registered',
            'order'      => 'DESC',
        ] );
    }

    /**
     * Get pending user count (for admin badge)
     */
    public function get_pending_count(): int {
        $users = get_users( [
            'meta_key'   => self::META_STATUS,
            'meta_value' => 'pending',
            'count_total' => true,
            'fields'     => 'ID',
        ] );
        return count( $users );
    }

    // ═══════════════════════════════════════════
    // SECURITY: LOGIN ENFORCEMENT
    // ═══════════════════════════════════════════

    /**
     * Block pending or unverified users from logging in.
     * 
     * Hooks into WordPress 'authenticate' filter at priority 30
     * (after WordPress has already validated username/password at priority 20).
     */
    public function enforce_registration_status( $user, string $username, string $password ): mixed {
        // Only act on successful authentication (user object returned)
        if ( ! ( $user instanceof \WP_User ) ) {
            return $user;
        }

        // Only check users who registered through our system
        $registered_via = get_user_meta( $user->ID, self::META_REGISTERED_VIA, true );
        if ( $registered_via !== 'el_core' ) {
            return $user;
        }

        // Check registration status
        $status = get_user_meta( $user->ID, self::META_STATUS, true );
        if ( $status === 'pending' ) {
            return new \WP_Error(
                'registration_pending',
                'Your registration is pending approval. You will receive an email when your account is approved.'
            );
        }

        if ( $status === 'rejected' ) {
            return new \WP_Error(
                'registration_rejected',
                'Your registration was not approved. Please contact the site administrator.'
            );
        }

        // Check email verification
        if ( $this->requires_email_verification() ) {
            $verified = get_user_meta( $user->ID, self::META_EMAIL_VERIFIED, true );
            if ( $verified !== '1' ) {
                return new \WP_Error(
                    'email_not_verified',
                    'Please verify your email address before logging in. Check your inbox for a verification link.'
                );
            }
        }

        return $user;
    }

    /**
     * Block access to wp-login.php?action=register
     * Redirect to our registration page or home if registration is closed.
     */
    public function block_default_registration(): void {
        $action = $_REQUEST['action'] ?? '';

        if ( $action !== 'register' ) {
            return;
        }

        $mode = $this->get_mode();
        if ( $mode === 'closed' ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        // If a registration page exists with our shortcode, redirect there
        $redirect = $this->get_setting( 'redirect_after_register', '' );
        if ( $redirect ) {
            wp_safe_redirect( esc_url( $redirect ) );
        } else {
            wp_safe_redirect( home_url( '/' ) );
        }
        exit;
    }

    /**
     * Force users_can_register to false so WordPress doesn't show 
     * "Register" link on wp-login.php. Our module handles registration.
     */
    public function disable_wp_registration_option( $value ): int {
        return 0;
    }

    // ═══════════════════════════════════════════
    // SECURITY: RATE LIMITING & HONEYPOT
    // ═══════════════════════════════════════════

    /**
     * Check if the current IP has exceeded the registration rate limit
     */
    public function is_rate_limited(): bool {
        $ip = $this->get_client_ip();
        $key = 'el_reg_rate_' . md5( $ip );
        $attempts = (int) get_transient( $key );

        return $attempts >= self::RATE_LIMIT_MAX;
    }

    /**
     * Increment the rate limit counter for the current IP
     */
    private function increment_rate_limit(): void {
        $ip = $this->get_client_ip();
        $key = 'el_reg_rate_' . md5( $ip );
        $attempts = (int) get_transient( $key );

        set_transient( $key, $attempts + 1, self::RATE_LIMIT_WINDOW );
    }

    /**
     * Check honeypot field — should be empty (bots fill it in)
     */
    public function honeypot_triggered( array $data ): bool {
        return ! empty( $data['website_url'] ?? '' );
    }

    /**
     * Get the client IP address
     */
    private function get_client_ip(): string {
        // Check for proxy headers, but prefer REMOTE_ADDR for security
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return sanitize_text_field( $ip );
    }

    // ═══════════════════════════════════════════
    // ADMIN NOTIFICATIONS
    // ═══════════════════════════════════════════

    /**
     * Notify admins that a new user is awaiting approval
     */
    private function notify_admins_pending_user( int $user_id ): void {
        $user     = get_userdata( $user_id );
        $org_name = el_core_get_org_name();
        $subject  = sprintf( '[%s] New registration awaiting approval', $org_name );

        $message  = "A new user has registered and is waiting for approval:\n\n";
        $message .= sprintf( "Name: %s %s\n", esc_html( $user->first_name ), esc_html( $user->last_name ) );
        $message .= sprintf( "Email: %s\n", esc_html( $user->user_email ) );
        $message .= sprintf( "Username: %s\n\n", esc_html( $user->user_login ) );
        $message .= "Log in to approve or reject this registration:\n";
        $message .= admin_url( 'admin.php?page=el-core-settings&tab=modules' ) . "\n";

        // Send to all admins with manage_registration capability
        $admins = get_users( [ 'role' => 'administrator' ] );
        foreach ( $admins as $admin ) {
            wp_mail( $admin->user_email, $subject, $message );
        }
    }

    // ═══════════════════════════════════════════
    // AJAX HANDLERS
    // ═══════════════════════════════════════════

    /**
     * Handle registration form submission (works for both logged-in and guest)
     */
    public function handle_register_user( array $data ): void {
        // Already logged in?
        if ( is_user_logged_in() ) {
            EL_AJAX_Handler::error( 'You are already registered and logged in.' );
            return;
        }

        // Rate limiting
        if ( $this->is_rate_limited() ) {
            EL_AJAX_Handler::error( 'Too many registration attempts. Please try again later.' );
            return;
        }

        // Honeypot
        if ( $this->honeypot_triggered( $data ) ) {
            // Silent success — don't reveal to bots that they were caught
            EL_AJAX_Handler::success( null, 'Registration submitted.' );
            return;
        }

        // Basic field validation
        $required = [ 'username', 'email', 'password' ];
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                EL_AJAX_Handler::error( sprintf( 'The %s field is required.', $field ) );
                return;
            }
        }

        // Validate email format
        if ( ! is_email( $data['email'] ) ) {
            EL_AJAX_Handler::error( 'Please enter a valid email address.' );
            return;
        }

        // Password strength (minimum 8 characters)
        if ( strlen( $data['password'] ) < 8 ) {
            EL_AJAX_Handler::error( 'Password must be at least 8 characters.' );
            return;
        }

        // Validate required custom fields
        $custom_fields = $this->get_custom_fields();
        foreach ( $custom_fields as $field ) {
            if ( ! empty( $field['required'] ) && empty( $data[ $field['key'] ] ) ) {
                EL_AJAX_Handler::error( sprintf( '%s is required.', esc_html( $field['label'] ) ) );
                return;
            }
        }

        // Increment rate limit before attempting registration
        $this->increment_rate_limit();

        // Attempt registration
        $result = $this->register_user( $data );

        if ( is_wp_error( $result ) ) {
            EL_AJAX_Handler::error( $result->get_error_message() );
            return;
        }

        // Build success message based on mode and verification settings
        $mode = $this->get_mode();
        $message = 'Registration successful!';

        if ( $mode === 'approval' && $this->requires_email_verification() ) {
            $message = 'Registration submitted! Please check your email to verify your address. Your account will also need admin approval before you can log in.';
        } elseif ( $mode === 'approval' ) {
            $message = 'Registration submitted! Your account is pending admin approval. You will receive an email when approved.';
        } elseif ( $this->requires_email_verification() ) {
            $message = 'Registration successful! Please check your email to verify your address before logging in.';
        }

        $redirect = $this->get_setting( 'redirect_after_register', '' );

        EL_AJAX_Handler::success( [
            'user_id'  => $result,
            'redirect' => $redirect ?: null,
        ], $message );
    }

    /**
     * Handle email verification (via AJAX or direct URL click)
     */
    public function handle_verify_email( array $data ): void {
        $user_id   = absint( $data['user'] ?? 0 );
        $raw_token = sanitize_text_field( $data['token'] ?? '' );

        if ( ! $user_id || ! $raw_token ) {
            EL_AJAX_Handler::error( 'Invalid verification link.' );
            return;
        }

        $result = $this->verify_email_token( $user_id, $raw_token );

        if ( is_wp_error( $result ) ) {
            EL_AJAX_Handler::error( $result->get_error_message() );
            return;
        }

        $message = 'Email verified successfully!';
        $status  = get_user_meta( $user_id, self::META_STATUS, true );
        if ( $status === 'pending' ) {
            $message .= ' Your account is still pending admin approval.';
        } else {
            $message .= ' You can now log in.';
        }

        EL_AJAX_Handler::success( [
            'verified'  => true,
            'login_url' => wp_login_url(),
        ], $message );
    }

    /**
     * Handle admin approving a user
     */
    public function handle_approve_user( array $data ): void {
        if ( ! el_core_can( 'manage_registration' ) ) {
            EL_AJAX_Handler::error( 'You do not have permission to approve users.', 403 );
            return;
        }

        $user_id = absint( $data['user_id'] ?? 0 );
        if ( ! $user_id ) {
            EL_AJAX_Handler::error( 'Invalid user.' );
            return;
        }

        if ( $this->approve_user( $user_id ) ) {
            EL_AJAX_Handler::success( [ 'user_id' => $user_id ], 'User approved successfully.' );
        } else {
            EL_AJAX_Handler::error( 'Could not approve user. They may not be in pending status.' );
        }
    }

    /**
     * Handle admin rejecting a user
     */
    public function handle_reject_user( array $data ): void {
        if ( ! el_core_can( 'manage_registration' ) ) {
            EL_AJAX_Handler::error( 'You do not have permission to reject users.', 403 );
            return;
        }

        $user_id = absint( $data['user_id'] ?? 0 );
        $reason  = sanitize_text_field( $data['reason'] ?? '' );

        if ( ! $user_id ) {
            EL_AJAX_Handler::error( 'Invalid user.' );
            return;
        }

        if ( $this->reject_user( $user_id, $reason ) ) {
            EL_AJAX_Handler::success( [ 'user_id' => $user_id ], 'User rejected.' );
        } else {
            EL_AJAX_Handler::error( 'Could not reject user. They may not be in pending status.' );
        }
    }

    /**
     * Handle creating an invite code
     */
    public function handle_create_invite( array $data ): void {
        if ( ! el_core_can( 'create_invites' ) ) {
            EL_AJAX_Handler::error( 'You do not have permission to create invite codes.', 403 );
            return;
        }

        $result = $this->create_invite( $data );

        if ( $result ) {
            EL_AJAX_Handler::success( $result, 'Invite code created.' );
        } else {
            EL_AJAX_Handler::error( 'Failed to create invite code. The code may already exist.' );
        }
    }

    /**
     * Handle resending a verification email.
     * 
     * Two modes:
     * - Logged-in user resending to themselves (no user_id param needed)
     * - Admin resending to another user (user_id param, requires manage_registration)
     */
    public function handle_resend_verification( array $data ): void {
        $target_user_id = absint( $data['user_id'] ?? 0 );

        if ( $target_user_id && $target_user_id !== get_current_user_id() ) {
            // Admin resending for another user
            if ( ! el_core_can( 'manage_registration' ) ) {
                EL_AJAX_Handler::error( 'You do not have permission to resend verifications.', 403 );
                return;
            }
        } else {
            // User resending for themselves
            if ( ! is_user_logged_in() ) {
                EL_AJAX_Handler::error( 'You must be logged in.', 401 );
                return;
            }
            $target_user_id = get_current_user_id();
        }

        // Check they actually need verification
        $verified = get_user_meta( $target_user_id, self::META_EMAIL_VERIFIED, true );
        if ( $verified === '1' ) {
            EL_AJAX_Handler::success( null, 'Your email is already verified.' );
            return;
        }

        if ( $this->send_verification_email( $target_user_id ) ) {
            EL_AJAX_Handler::success( null, 'Verification email sent. Please check your inbox.' );
        } else {
            EL_AJAX_Handler::error( 'Failed to send verification email.' );
        }
    }

    /**
     * Handle profile update from the frontend profile form
     */
    public function handle_update_profile( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( 'You must be logged in to update your profile.', 401 );
            return;
        }

        $user_id = get_current_user_id();

        // Update core WordPress fields
        $update_data = [ 'ID' => $user_id ];

        if ( isset( $data['first_name'] ) ) {
            $update_data['first_name'] = sanitize_text_field( $data['first_name'] );
        }
        if ( isset( $data['last_name'] ) ) {
            $update_data['last_name'] = sanitize_text_field( $data['last_name'] );
        }
        if ( isset( $data['first_name'] ) || isset( $data['last_name'] ) ) {
            $update_data['display_name'] = trim(
                sanitize_text_field( $data['first_name'] ?? '' ) . ' ' .
                sanitize_text_field( $data['last_name'] ?? '' )
            ) ?: get_userdata( $user_id )->user_login;
        }

        $result = wp_update_user( $update_data );
        if ( is_wp_error( $result ) ) {
            EL_AJAX_Handler::error( $result->get_error_message() );
            return;
        }

        // Update custom fields
        $this->update_user_custom_fields( $user_id, $data );

        EL_AJAX_Handler::success( null, 'Profile updated successfully.' );
    }

    // ═══════════════════════════════════════════
    // EMAIL VERIFICATION LINK (GET from email)
    // ═══════════════════════════════════════════

    /**
     * Intercept email verification links.
     *
     * When a user clicks the link in their verification email, it hits
     * the front page with query parameters: ?el_action=verify_email&user=X&token=Y
     * This method catches that on template_redirect, processes it,
     * and displays a result page.
     */
    public function handle_verification_link(): void {
        if ( ! isset( $_GET['el_action'] ) || $_GET['el_action'] !== 'verify_email' ) {
            return;
        }

        $user_id   = absint( $_GET['user'] ?? 0 );
        $raw_token = sanitize_text_field( $_GET['token'] ?? '' );

        if ( ! $user_id || ! $raw_token ) {
            $this->render_verification_page( 'error', 'Invalid verification link.' );
            return;
        }

        $result = $this->verify_email_token( $user_id, $raw_token );

        if ( is_wp_error( $result ) ) {
            $this->render_verification_page( 'error', $result->get_error_message() );
            return;
        }

        // Check if they still need approval
        $status = get_user_meta( $user_id, self::META_STATUS, true );
        if ( $status === 'pending' ) {
            $this->render_verification_page(
                'success',
                'Your email has been verified! Your account is still awaiting admin approval. You will receive an email when your account is approved.'
            );
        } else {
            $this->render_verification_page(
                'success',
                'Your email has been verified! You can now log in.',
                wp_login_url()
            );
        }
    }

    /**
     * Render a simple standalone page for verification results.
     * Uses the site's active theme via get_header()/get_footer().
     */
    private function render_verification_page( string $type, string $message, string $login_url = '' ): void {
        // Prevent WordPress from loading the normal page content
        // by outputting a full page and exiting
        status_header( 200 );

        $org_name = el_core_get_org_name();
        $notice_class = ( $type === 'error' ) ? 'el-notice-error' : 'el-notice-success';

        // Try to use the theme's header/footer for consistent appearance
        get_header();

        echo '<div style="max-width: 520px; margin: 60px auto; padding: 0 20px;">';
        echo '<div class="el-component">';
        echo '<h2 style="margin-bottom: 16px;">Email Verification</h2>';
        echo '<div class="el-notice ' . esc_attr( $notice_class ) . '">';
        echo '<p>' . esc_html( $message ) . '</p>';
        echo '</div>';

        if ( $login_url ) {
            echo '<p><a href="' . esc_url( $login_url ) . '" class="el-btn el-btn-primary">Log In</a></p>';
        }

        echo '</div></div>';

        get_footer();
        exit;
    }

    // ═══════════════════════════════════════════
    // USER PROFILE HELPERS (for shortcode)
    // ═══════════════════════════════════════════

    /**
     * Get all custom field values for a user
     */
    public function get_user_custom_fields( int $user_id ): array {
        $fields = $this->get_custom_fields();
        $values = [];

        foreach ( $fields as $field ) {
            $entry = [
                'label'    => $field['label'],
                'type'     => $field['type'],
                'value'    => get_user_meta( $user_id, $field['key'], true ),
                'required' => ! empty( $field['required'] ),
            ];

            // Include options for select fields so the profile form can render them
            if ( ! empty( $field['options'] ) ) {
                $entry['options'] = $field['options'];
            }

            $values[ $field['key'] ] = $entry;
        }

        return $values;
    }

    /**
     * Update a user's custom field values
     */
    public function update_user_custom_fields( int $user_id, array $data ): void {
        $fields = $this->get_custom_fields();

        foreach ( $fields as $field ) {
            $key = $field['key'];
            if ( isset( $data[ $key ] ) ) {
                update_user_meta( $user_id, $key, sanitize_text_field( $data[ $key ] ) );
            }
        }
    }
}
