<?php
/**
 * EL Core AJAX Handler
 * 
 * Provides standardized AJAX processing with automatic nonce verification,
 * error handling, and consistent response formatting.
 * 
 * Modules register AJAX actions through WordPress hooks.
 * The handler provides utility methods for common patterns.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_AJAX_Handler {

    public function __construct() {
        // Register the unified AJAX endpoint
        add_action( 'wp_ajax_el_core_action', [ $this, 'handle_request' ] );
        add_action( 'wp_ajax_nopriv_el_core_action', [ $this, 'handle_guest_request' ] );
    }

    /**
     * Handle authenticated AJAX requests
     * 
     * Modules hook into: el_core_ajax_{action_name}
     * Frontend sends: { action: 'el_core_action', el_action: 'rsvp_event', ... }
     */
    public function handle_request(): void {
        // Verify nonce
        if ( ! check_ajax_referer( 'el_core_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
        }

        $el_action = sanitize_text_field( $_POST['el_action'] ?? '' );

        if ( empty( $el_action ) ) {
            wp_send_json_error( [ 'message' => 'No action specified.' ], 400 );
        }

        // Let modules handle the action
        // Modules hook: add_action('el_core_ajax_rsvp_event', [$this, 'handle_rsvp']);
        if ( has_action( "el_core_ajax_{$el_action}" ) ) {
            $data = $this->sanitize_input( $_POST );
            do_action( "el_core_ajax_{$el_action}", $data );
        } else {
            wp_send_json_error( [ 'message' => 'Unknown action.' ], 404 );
        }

        // If the hook didn't send a response, send generic success
        wp_send_json_success();
    }

    /**
     * Handle guest (non-authenticated) AJAX requests
     * More restrictive — only specific actions allowed
     */
    public function handle_guest_request(): void {
        if ( ! check_ajax_referer( 'el_core_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
        }

        $el_action = sanitize_text_field( $_POST['el_action'] ?? '' );

        // Only allow actions that explicitly support guest access
        if ( has_action( "el_core_ajax_nopriv_{$el_action}" ) ) {
            $data = $this->sanitize_input( $_POST );
            do_action( "el_core_ajax_nopriv_{$el_action}", $data );
        } else {
            wp_send_json_error( [ 'message' => 'Authentication required.' ], 401 );
        }

        wp_send_json_success();
    }

    /**
     * Basic input sanitization
     * Modules should do additional sanitization specific to their data
     */
    private function sanitize_input( array $data ): array {
        $clean = [];
        foreach ( $data as $key => $value ) {
            $key = sanitize_key( $key );
            if ( is_array( $value ) ) {
                $clean[ $key ] = $this->sanitize_input( $value );
            } else {
                $clean[ $key ] = sanitize_text_field( $value );
            }
        }
        return $clean;
    }

    // ═══════════════════════════════════════════
    // RESPONSE HELPERS (for modules to use)
    // ═══════════════════════════════════════════

    /**
     * Send a success response
     */
    public static function success( mixed $data = null, string $message = '' ): void {
        wp_send_json_success( [
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * Send an error response
     */
    public static function error( string $message, int $code = 400, mixed $data = null ): void {
        wp_send_json_error( [
            'message' => $message,
            'data'    => $data,
        ], $code );
    }
}
