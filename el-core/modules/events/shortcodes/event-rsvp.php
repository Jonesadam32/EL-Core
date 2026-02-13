<?php
/**
 * Shortcode: [el_event_rsvp]
 * 
 * Displays an RSVP button for a specific event.
 * Handles toggle (RSVP / Cancel) via AJAX.
 * 
 * Parameters:
 *   event_id — Required. The event to RSVP for.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_event_rsvp( $atts ): string {
    $atts = shortcode_atts( [
        'event_id' => 0,
    ], $atts, 'el_event_rsvp' );

    $event_id = absint( $atts['event_id'] );

    if ( ! $event_id ) {
        return '<div class="el-component el-error">Event ID required.</div>';
    }

    if ( ! is_user_logged_in() ) {
        return '<div class="el-component el-event-rsvp">'
             . '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="el-btn el-btn-outline">Log in to RSVP</a>'
             . '</div>';
    }

    $events_module = EL_Events_Module::instance();
    $has_rsvp = $events_module->user_has_rsvp( $event_id );

    $btn_text  = $has_rsvp ? 'Cancel RSVP' : 'RSVP Now';
    $btn_class = $has_rsvp ? 'el-btn el-btn-outline' : 'el-btn el-btn-primary';

    $html  = '<div class="el-component el-event-rsvp" data-event-id="' . $event_id . '">';
    $html .= '  <button class="' . $btn_class . ' el-rsvp-btn" data-event-id="' . $event_id . '">';
    $html .= esc_html( $btn_text );
    $html .= '  </button>';
    $html .= '  <span class="el-rsvp-status" style="display:none;"></span>';
    $html .= '</div>';

    return $html;
}
