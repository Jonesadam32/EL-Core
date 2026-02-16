<?php
/**
 * Shortcode: [el_event_list]
 * 
 * Displays upcoming events. This is a COMPONENT — it renders
 * just the event list, not an entire page. The page layout
 * is handled by the WordPress block editor.
 * 
 * Parameters:
 *   limit  — Number of events to show (default: 6)
 *   layout — Display style: "cards" or "list" (default: cards)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_event_list( $atts ): string {
    $atts = shortcode_atts( [
        'limit'  => 6,
        'layout' => 'cards',
    ], $atts, 'el_event_list' );

    $events_module = EL_Events_Module::instance();
    $events = $events_module->get_upcoming_events( absint( $atts['limit'] ) );

    if ( empty( $events ) ) {
        return '<div class="el-component el-empty-state">'
             . '<p>No upcoming events scheduled.</p>'
             . '</div>';
    }

    $layout_class = $atts['layout'] === 'list' ? 'el-layout-list' : 'el-layout-cards';

    $html = '<div class="el-component el-event-list ' . esc_attr( $layout_class ) . '">';

    foreach ( $events as $event ) {
        $start    = strtotime( $event->start_date );
        $month    = date( 'M', $start );
        $day      = date( 'j', $start );
        $time     = date( 'g:i A', $start );
        $rsvp_count = $events_module->get_rsvp_count( $event->id );

        $html .= '<div class="el-event-card">';
        $html .= '  <div class="el-event-date-badge">';
        $html .= '    <span class="el-event-month">' . esc_html( $month ) . '</span>';
        $html .= '    <span class="el-event-day">' . esc_html( $day ) . '</span>';
        $html .= '  </div>';
        $html .= '  <div class="el-event-details">';
        $html .= '    <h3 class="el-event-title">' . esc_html( $event->title ) . '</h3>';
        $html .= '    <div class="el-event-meta">';
        $html .= '      <span class="el-event-time">🕐 ' . esc_html( $time ) . '</span>';

        if ( ! empty( $event->location ) ) {
            $html .= '      <span class="el-event-location">📍 ' . esc_html( $event->location ) . '</span>';
        }

        $html .= '      <span class="el-event-rsvps">👥 ' . $rsvp_count;
        if ( $event->max_attendees > 0 ) {
            $html .= ' / ' . $event->max_attendees;
        }
        $html .= ' attending</span>';

        $html .= '    </div>'; // .el-event-meta

        if ( ! empty( $event->description ) ) {
            $excerpt = wp_trim_words( $event->description, 20, '...' );
            $html .= '    <p class="el-event-excerpt">' . esc_html( $excerpt ) . '</p>';
        }

        $html .= '  </div>'; // .el-event-details
        $html .= '</div>'; // .el-event-card
    }

    $html .= '</div>'; // .el-event-list

    return $html;
}
