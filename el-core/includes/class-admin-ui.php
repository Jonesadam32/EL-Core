<?php
/**
 * EL Core — Admin UI Framework
 *
 * Provides static methods for rendering all shared admin components.
 * Every component returns an HTML string — nothing is echoed directly.
 * All styling uses --el-admin-* CSS variables defined in admin.css.
 *
 * Usage:
 *   echo EL_Admin_UI::page_header([ 'title' => 'Organizations', 'actions' => [...] ]);
 *   echo EL_Admin_UI::stat_card([ 'icon' => 'groups', 'number' => 42, 'label' => 'Active Clients' ]);
 *
 * @package EL_Core
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Admin_UI {

    // -------------------------------------------------------------------------
    // PAGE LAYOUT
    // -------------------------------------------------------------------------

    /**
     * Render the outer page wrapper.
     *
     * Wraps content in the standard .el-admin-wrap div.
     * Use this as the outermost container on every admin page.
     *
     * @param string $content  Inner HTML content.
     * @return string
     */
    public static function wrap( string $content ): string {
        return '<div class="el-admin-wrap">' . $content . '</div>';
    }

    /**
     * Render a page header with title, optional subtitle, and action buttons.
     *
     * @param array $args {
     *     @type string $title     Required. Page title.
     *     @type string $subtitle  Optional. Subtitle or description below title.
     *     @type string $back_url  Optional. URL for a back link shown above the title.
     *     @type string $back_label Optional. Label for the back link. Default '← Back'.
     *     @type array  $actions   Optional. Array of button args, each passed to EL_Admin_UI::btn().
     * }
     * @return string
     */
    public static function page_header( array $args ): string {
        $title      = $args['title']      ?? '';
        $subtitle   = $args['subtitle']   ?? '';
        $back_url   = $args['back_url']   ?? '';
        $back_label = $args['back_label'] ?? '← Back';
        $actions    = $args['actions']    ?? [];

        $html = '<div class="el-page-header">';

        // Left side
        $html .= '<div class="el-page-header-left">';
        if ( $back_url ) {
            $html .= '<a href="' . esc_url( $back_url ) . '" class="el-back-link">'
                   . esc_html( $back_label )
                   . '</a>';
        }
        $html .= '<h1 class="el-page-title">' . esc_html( $title ) . '</h1>';
        if ( $subtitle ) {
            $html .= '<p class="el-page-subtitle">' . esc_html( $subtitle ) . '</p>';
        }
        $html .= '</div>';

        // Right side — action buttons
        if ( ! empty( $actions ) ) {
            $html .= '<div class="el-page-header-actions">';
            foreach ( $actions as $action ) {
                $html .= self::btn( $action );
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // .el-page-header

        return $html;
    }

    // -------------------------------------------------------------------------
    // CARDS
    // -------------------------------------------------------------------------

    /**
     * Render a standard content card.
     *
     * @param array $args {
     *     @type string $title    Optional. Card header title.
     *     @type string $icon     Optional. Dashicon name (without 'dashicons-') for header.
     *     @type string $content  Required. Inner HTML content of the card body.
     *     @type array  $actions  Optional. Button args for the card header right side.
     *     @type string $class    Optional. Additional CSS classes on the card wrapper.
     * }
     * @return string
     */
    public static function card( array $args ): string {
        $title   = $args['title']   ?? '';
        $icon    = $args['icon']    ?? '';
        $content = $args['content'] ?? '';
        $actions = $args['actions'] ?? [];
        $class   = $args['class']   ?? '';

        $classes = 'el-card' . ( $class ? ' ' . esc_attr( $class ) : '' );

        $html = '<div class="' . $classes . '">';

        if ( $title || ! empty( $actions ) ) {
            $html .= '<div class="el-card-header">';
            $html .= '<h2 class="el-card-title">';
            if ( $icon ) {
                $html .= '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span>';
            }
            $html .= esc_html( $title );
            $html .= '</h2>';
            if ( ! empty( $actions ) ) {
                $html .= '<div class="el-card-actions">';
                foreach ( $actions as $action ) {
                    $html .= self::btn( $action );
                }
                $html .= '</div>';
            }
            $html .= '</div>'; // .el-card-header
        }

        $html .= '<div class="el-card-body">' . $content . '</div>';
        $html .= '</div>'; // .el-card

        return $html;
    }

    /**
     * Render a stat metric card (icon + large number + label).
     *
     * @param array $args {
     *     @type string     $icon    Required. Dashicon name (without 'dashicons-').
     *     @type string|int $number  Required. The metric value to display.
     *     @type string     $label   Required. Description of the metric.
     *     @type string     $variant Optional. Color variant: 'primary' (default), 'success', 'warning', 'info'.
     *     @type string     $url     Optional. Makes the entire card a clickable link.
     * }
     * @return string
     */
    public static function stat_card( array $args ): string {
        $icon    = $args['icon']    ?? 'chart-bar';
        $number  = $args['number']  ?? '0';
        $label   = $args['label']   ?? '';
        $variant = $args['variant'] ?? 'primary';
        $url     = $args['url']     ?? '';

        $inner = '<div class="el-stat-icon el-stat-' . esc_attr( $variant ) . '">'
               . '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span>'
               . '</div>'
               . '<div class="el-stat-content">'
               . '<div class="el-stat-number">' . esc_html( $number ) . '</div>'
               . '<div class="el-stat-label">' . esc_html( $label ) . '</div>'
               . '</div>';

        if ( $url ) {
            return '<a href="' . esc_url( $url ) . '" class="el-stat-card el-stat-card-link">' . $inner . '</a>';
        }

        return '<div class="el-stat-card">' . $inner . '</div>';
    }

    /**
     * Render a grid of stat cards.
     *
     * @param array $cards  Array of $args arrays, each passed to EL_Admin_UI::stat_card().
     * @return string
     */
    public static function stats_grid( array $cards ): string {
        $html = '<div class="el-stats-grid">';
        foreach ( $cards as $card ) {
            $html .= self::stat_card( $card );
        }
        $html .= '</div>';
        return $html;
    }

    // -------------------------------------------------------------------------
    // BADGES & STATUS
    // -------------------------------------------------------------------------

    /**
     * Render a status/type badge pill.
     *
     * @param array $args {
     *     @type string $label   Required. Badge text.
     *     @type string $variant Optional. Visual variant: 'default', 'success', 'warning', 'error', 'info', 'primary'. Default 'default'.
     *     @type string $class   Optional. Additional CSS class.
     * }
     * @return string
     */
    public static function badge( array $args ): string {
        $label   = $args['label']   ?? '';
        $variant = $args['variant'] ?? 'default';
        $class   = $args['class']   ?? '';

        $classes = 'el-badge el-badge-' . esc_attr( $variant );
        if ( $class ) {
            $classes .= ' ' . esc_attr( $class );
        }

        return '<span class="' . $classes . '">' . esc_html( $label ) . '</span>';
    }

    // -------------------------------------------------------------------------
    // EMPTY STATE
    // -------------------------------------------------------------------------

    /**
     * Render an empty state — shown when a list or table has no records.
     *
     * @param array $args {
     *     @type string $icon    Optional. Dashicon name. Default 'inbox'.
     *     @type string $title   Required. Main heading.
     *     @type string $message Optional. Supporting text below the heading.
     *     @type array  $action  Optional. Button args passed to EL_Admin_UI::btn().
     * }
     * @return string
     */
    public static function empty_state( array $args ): string {
        $icon    = $args['icon']    ?? 'inbox';
        $title   = $args['title']   ?? 'Nothing here yet';
        $message = $args['message'] ?? '';
        $action  = $args['action']  ?? [];

        $html  = '<div class="el-empty-state">';
        $html .= '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span>';
        $html .= '<h3>' . esc_html( $title ) . '</h3>';
        if ( $message ) {
            $html .= '<p>' . esc_html( $message ) . '</p>';
        }
        if ( ! empty( $action ) ) {
            $html .= self::btn( $action );
        }
        $html .= '</div>';

        return $html;
    }

    // -------------------------------------------------------------------------
    // NOTICES
    // -------------------------------------------------------------------------

    /**
     * Render an inline notice/alert.
     *
     * @param array $args {
     *     @type string $message   Required. Notice text. May contain safe HTML.
     *     @type string $type      Optional. 'success', 'warning', 'error', 'info'. Default 'info'.
     *     @type bool   $dismissible Optional. Whether to show a close button. Default false.
     * }
     * @return string
     */
    public static function notice( array $args ): string {
        $message     = $args['message']     ?? '';
        $type        = $args['type']        ?? 'info';
        $dismissible = $args['dismissible'] ?? false;

        $icons = [
            'success' => 'yes-alt',
            'warning' => 'warning',
            'error'   => 'dismiss',
            'info'    => 'info',
        ];
        $icon = $icons[ $type ] ?? 'info';

        $classes = 'el-notice el-notice-' . esc_attr( $type );
        if ( $dismissible ) {
            $classes .= ' el-notice-dismissible';
        }

        $html  = '<div class="' . $classes . '">';
        $html .= '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span>';
        $html .= '<div class="el-notice-message">' . wp_kses_post( $message ) . '</div>';
        if ( $dismissible ) {
            $html .= '<button type="button" class="el-notice-close" aria-label="Dismiss">'
                   . '<span class="dashicons dashicons-no-alt"></span>'
                   . '</button>';
        }
        $html .= '</div>';

        return $html;
    }

    // -------------------------------------------------------------------------
    // DETAIL VIEW
    // -------------------------------------------------------------------------

    /**
     * Render a label/value detail row for profile/detail pages.
     *
     * @param array $args {
     *     @type string $label  Required. Field label.
     *     @type string $value  Required. Field value. May contain safe HTML.
     *     @type string $icon   Optional. Dashicon name to show beside the label.
     * }
     * @return string
     */
    public static function detail_row( array $args ): string {
        $label = $args['label'] ?? '';
        $value = $args['value'] ?? '';
        $icon  = $args['icon']  ?? '';

        $html  = '<div class="el-detail-row">';
        $html .= '<div class="el-detail-label">';
        if ( $icon ) {
            $html .= '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span>';
        }
        $html .= esc_html( $label );
        $html .= '</div>';
        $html .= '<div class="el-detail-value">' . wp_kses_post( $value ) . '</div>';
        $html .= '</div>';

        return $html;
    }

    // -------------------------------------------------------------------------
    // TABS
    // -------------------------------------------------------------------------

    /**
     * Render a tab navigation bar.
     *
     * @param array $args {
     *     @type string $group  Required. Unique ID for this tab group. Used to scope tab JS.
     *     @type array  $tabs   Required. Array of tab definitions:
     *         [
     *             'id'     => 'tab-slug',       // Required. Unique within group.
     *             'label'  => 'Tab Label',       // Required.
     *             'icon'   => 'dashicon-name',   // Optional.
     *             'badge'  => 5,                 // Optional. Count badge.
     *             'active' => true,              // Optional. Marks the default active tab.
     *         ]
     * }
     * @return string
     */
    public static function tab_nav( array $args ): string {
        $group = $args['group'] ?? 'tabs';
        $tabs  = $args['tabs']  ?? [];

        $html = '<div class="el-tab-nav" data-tab-group="' . esc_attr( $group ) . '">';

        foreach ( $tabs as $tab ) {
            $id     = $tab['id']     ?? '';
            $label  = $tab['label']  ?? '';
            $icon   = $tab['icon']   ?? '';
            $badge  = $tab['badge']  ?? null;
            $active = $tab['active'] ?? false;

            $classes = 'el-tab-btn' . ( $active ? ' active' : '' );

            $html .= '<button class="' . $classes . '" '
                   . 'data-tab="' . esc_attr( $id ) . '" '
                   . 'data-group="' . esc_attr( $group ) . '">';
            if ( $icon ) {
                $html .= '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span>';
            }
            $html .= esc_html( $label );
            if ( $badge !== null ) {
                $html .= '<span class="el-tab-badge">' . intval( $badge ) . '</span>';
            }
            $html .= '</button>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render a tab content panel.
     *
     * Pair with tab_nav() — one tab_panel() per tab defined there.
     *
     * @param array $args {
     *     @type string $id      Required. Must match the tab 'id' in tab_nav().
     *     @type string $group   Required. Must match the tab_nav() group.
     *     @type string $content Required. Inner HTML content.
     *     @type bool   $active  Optional. Whether this panel starts visible. Default false.
     * }
     * @return string
     */
    public static function tab_panel( array $args ): string {
        $id      = $args['id']      ?? '';
        $group   = $args['group']   ?? 'tabs';
        $content = $args['content'] ?? '';
        $active  = $args['active']  ?? false;

        $style = $active ? '' : ' style="display:none;"';

        return '<div class="el-tab-content"'
             . ' data-tab="' . esc_attr( $id ) . '"'
             . ' data-group="' . esc_attr( $group ) . '"'
             . $style . '>'
             . $content
             . '</div>';
    }

    // -------------------------------------------------------------------------
    // FORMS
    // -------------------------------------------------------------------------

    /**
     * Render a form section heading (groups related fields).
     *
     * @param array $args {
     *     @type string $title       Required. Section heading text.
     *     @type string $description Optional. Short description below the heading.
     * }
     * @return string
     */
    public static function form_section( array $args ): string {
        $title       = $args['title']       ?? '';
        $description = $args['description'] ?? '';

        $html  = '<div class="el-form-section">';
        $html .= '<h3 class="el-form-section-title">' . esc_html( $title ) . '</h3>';
        if ( $description ) {
            $html .= '<p class="el-form-section-desc">' . esc_html( $description ) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Render a form field row (label + input + optional helper text).
     *
     * Supports: text, email, url, number, date, time, textarea, select, checkbox.
     *
     * @param array $args {
     *     @type string $name        Required. Input name attribute.
     *     @type string $label       Required. Field label.
     *     @type string $type        Optional. Input type. Default 'text'.
     *     @type string $value       Optional. Current value.
     *     @type string $placeholder Optional. Placeholder text.
     *     @type string $helper      Optional. Helper/description text below the field.
     *     @type bool   $required    Optional. Adds required attribute. Default false.
     *     @type array  $options     Optional. For 'select' type: ['value' => 'Label'] pairs.
     *     @type string $class       Optional. Additional class on the row wrapper.
     * }
     * @return string
     */
    public static function form_row( array $args ): string {
        $name        = $args['name']        ?? '';
        $label       = $args['label']       ?? '';
        $type        = $args['type']        ?? 'text';
        $value       = $args['value']       ?? '';
        $placeholder = $args['placeholder'] ?? '';
        $helper      = $args['helper']      ?? '';
        $required    = $args['required']    ?? false;
        $options     = $args['options']     ?? [];
        $class       = $args['class']       ?? '';
        $custom_id   = $args['id']          ?? '';

        $field_id    = $custom_id ? sanitize_html_class( $custom_id ) : 'el-field-' . sanitize_html_class( $name );
        $req_attr    = $required ? ' required' : '';
        $req_mark    = $required ? ' <span class="el-required" aria-hidden="true">*</span>' : '';

        $classes = 'el-form-row' . ( $class ? ' ' . esc_attr( $class ) : '' );

        $html  = '<div class="' . $classes . '">';
        $html .= '<label for="' . esc_attr( $field_id ) . '" class="el-form-label">'
               . esc_html( $label ) . $req_mark
               . '</label>';
        $html .= '<div class="el-form-field">';

        switch ( $type ) {
            case 'textarea':
                $html .= '<textarea id="' . esc_attr( $field_id ) . '" '
                       . 'name="' . esc_attr( $name ) . '" '
                       . 'class="el-input el-textarea" '
                       . ( $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '" ' : '' )
                       . $req_attr . '>'
                       . esc_textarea( $value )
                       . '</textarea>';
                break;

            case 'select':
                $html .= '<select id="' . esc_attr( $field_id ) . '" '
                       . 'name="' . esc_attr( $name ) . '" '
                       . 'class="el-input el-select"' . $req_attr . '>';
                foreach ( $options as $opt_val => $opt_label ) {
                    $selected = selected( $value, $opt_val, false );
                    $html .= '<option value="' . esc_attr( $opt_val ) . '"' . $selected . '>'
                           . esc_html( $opt_label ) . '</option>';
                }
                $html .= '</select>';
                break;

            case 'checkbox':
                $checked = checked( $value, true, false );
                $html .= '<label class="el-checkbox-label">'
                       . '<input type="checkbox" id="' . esc_attr( $field_id ) . '" '
                       . 'name="' . esc_attr( $name ) . '" '
                       . 'class="el-checkbox" value="1"' . $checked . $req_attr . '> '
                       . esc_html( $placeholder ) // checkbox uses placeholder as inline label
                       . '</label>';
                break;

            default:
                $html .= '<input type="' . esc_attr( $type ) . '" '
                       . 'id="' . esc_attr( $field_id ) . '" '
                       . 'name="' . esc_attr( $name ) . '" '
                       . 'value="' . esc_attr( $value ) . '" '
                       . 'class="el-input" '
                       . ( $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '" ' : '' )
                       . $req_attr . '>';
                break;
        }

        if ( $helper ) {
            $html .= '<p class="el-form-helper">' . esc_html( $helper ) . '</p>';
        }

        $html .= '</div>'; // .el-form-field
        $html .= '</div>'; // .el-form-row

        return $html;
    }

    // -------------------------------------------------------------------------
    // FILTER BAR
    // -------------------------------------------------------------------------

    /**
     * Render a search + filter bar above a data list.
     *
     * @param array $args {
     *     @type string $action       Required. Form action URL.
     *     @type string $search_name  Optional. Search input name. Default 's'.
     *     @type string $search_value Optional. Current search value.
     *     @type string $placeholder  Optional. Search placeholder. Default 'Search...'.
     *     @type array  $filters      Optional. Array of select filter definitions:
     *         [
     *             'name'    => 'status',
     *             'value'   => 'active',
     *             'options' => ['all' => 'All Statuses', 'active' => 'Active'],
     *         ]
     *     @type array  $hidden       Optional. Hidden fields as ['name' => 'value'] pairs.
     * }
     * @return string
     */
    public static function filter_bar( array $args ): string {
        $action       = $args['action']       ?? '';
        $search_name  = $args['search_name']  ?? 's';
        $search_value = $args['search_value'] ?? '';
        $placeholder  = $args['placeholder']  ?? 'Search...';
        $filters      = $args['filters']      ?? [];
        $hidden       = $args['hidden']       ?? [];

        $html  = '<div class="el-filter-bar">';
        $html .= '<form method="get" action="' . esc_url( $action ) . '" class="el-filter-form">';

        // Hidden fields
        foreach ( $hidden as $h_name => $h_value ) {
            $html .= '<input type="hidden" name="' . esc_attr( $h_name ) . '" '
                   . 'value="' . esc_attr( $h_value ) . '">';
        }

        // Search input
        $html .= '<div class="el-filter-search">'
               . '<span class="dashicons dashicons-search"></span>'
               . '<input type="search" '
               . 'name="' . esc_attr( $search_name ) . '" '
               . 'value="' . esc_attr( $search_value ) . '" '
               . 'placeholder="' . esc_attr( $placeholder ) . '" '
               . 'class="el-search-input">'
               . '</div>';

        // Dropdown filters
        foreach ( $filters as $filter ) {
            $f_name    = $filter['name']    ?? '';
            $f_value   = $filter['value']   ?? '';
            $f_options = $filter['options'] ?? [];

            $html .= '<div class="el-filter-group">'
                   . '<select name="' . esc_attr( $f_name ) . '" class="el-filter-select">';
            foreach ( $f_options as $opt_val => $opt_label ) {
                $selected = selected( $f_value, $opt_val, false );
                $html .= '<option value="' . esc_attr( $opt_val ) . '"' . $selected . '>'
                       . esc_html( $opt_label ) . '</option>';
            }
            $html .= '</select></div>';
        }

        $html .= '<button type="submit" class="el-btn el-btn-secondary">Filter</button>';
        $html .= '</form>';
        $html .= '</div>'; // .el-filter-bar

        return $html;
    }

    // -------------------------------------------------------------------------
    // MODAL
    // -------------------------------------------------------------------------

    /**
     * Render a modal dialog.
     *
     * The modal starts hidden. Open it with: elAdmin.openModal('modal-id')
     * Close it with: elAdmin.closeModal('modal-id') or the built-in close button.
     *
     * @param array $args {
     *     @type string $id      Required. Unique modal ID. Used by JS to target it.
     *     @type string $title   Required. Modal header title.
     *     @type string $content Required. Inner HTML content (the form or body).
     *     @type string $size    Optional. 'default' (600px) or 'large' (900px). Default 'default'.
     * }
     * @return string
     */
    public static function modal( array $args ): string {
        $id      = $args['id']      ?? '';
        $title   = $args['title']   ?? '';
        $content = $args['content'] ?? '';
        $size    = $args['size']    ?? 'default';

        $html  = '<div id="' . esc_attr( $id ) . '" class="el-modal" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $id ) . '-title" style="display:none;">';
        $html .= '<div class="el-modal-overlay" data-modal-close="' . esc_attr( $id ) . '"></div>';
        $html .= '<div class="el-modal-content el-modal-' . esc_attr( $size ) . '">';

        // Header
        $html .= '<div class="el-modal-header">';
        $html .= '<h2 id="' . esc_attr( $id ) . '-title" class="el-modal-title">' . esc_html( $title ) . '</h2>';
        $html .= '<button type="button" class="el-modal-close" data-modal-close="' . esc_attr( $id ) . '" aria-label="Close">'
               . '<span class="dashicons dashicons-no-alt"></span>'
               . '</button>';
        $html .= '</div>'; // .el-modal-header

        // Body
        $html .= '<div class="el-modal-body">' . $content . '</div>';

        $html .= '</div>'; // .el-modal-content
        $html .= '</div>'; // .el-modal

        return $html;
    }

    // -------------------------------------------------------------------------
    // BUTTONS
    // -------------------------------------------------------------------------

    /**
     * Render a button or link-styled button.
     *
     * @param array $args {
     *     @type string $label    Required. Button text.
     *     @type string $variant  Optional. 'primary', 'secondary', 'danger', 'ghost'. Default 'primary'.
     *     @type string $url      Optional. If set, renders as <a> instead of <button>.
     *     @type string $icon     Optional. Dashicon name shown before label.
     *     @type string $type     Optional. Button type attribute. Default 'button'.
     *     @type string $id       Optional. Element ID.
     *     @type string $class    Optional. Additional CSS classes.
     *     @type array  $data     Optional. Data attributes as ['key' => 'value'] pairs.
     * }
     * @return string
     */
    public static function btn( array $args ): string {
        $label   = $args['label']   ?? '';
        $variant = $args['variant'] ?? 'primary';
        $url     = $args['url']     ?? '';
        $icon    = $args['icon']    ?? '';
        $type    = $args['type']    ?? 'button';
        $id      = $args['id']      ?? '';
        $class   = $args['class']   ?? '';
        $data    = $args['data']    ?? [];

        $classes = 'el-btn el-btn-' . esc_attr( $variant );
        if ( $class ) {
            $classes .= ' ' . esc_attr( $class );
        }

        $data_attrs = '';
        foreach ( $data as $key => $val ) {
            $data_attrs .= ' data-' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
        }

        $id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';

        $inner = '';
        if ( $icon ) {
            $inner .= '<span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span>';
        }
        $inner .= esc_html( $label );

        if ( $url ) {
            return '<a href="' . esc_url( $url ) . '" class="' . $classes . '"' . $id_attr . $data_attrs . '>' . $inner . '</a>';
        }

        return '<button type="' . esc_attr( $type ) . '" class="' . $classes . '"' . $id_attr . $data_attrs . '>' . $inner . '</button>';
    }

    // -------------------------------------------------------------------------
    // DATA TABLE
    // -------------------------------------------------------------------------

    /**
     * Render an HTML data table.
     *
     * @param array $args {
     *     @type array  $columns  Required. Column definitions:
     *         [
     *             'key'   => 'field_key',   // maps to row data key
     *             'label' => 'Column Label',
     *             'class' => 'optional-td-class',
     *         ]
     *     @type array  $rows     Required. Array of associative arrays (keyed by column 'key').
     *                            A row may contain a '__actions' key with raw HTML for an actions column.
     *     @type string $class    Optional. Additional class on the <table>.
     *     @type array  $empty    Optional. Args passed to EL_Admin_UI::empty_state() if rows is empty.
     * }
     * @return string
     */
    public static function data_table( array $args ): string {
        $columns = $args['columns'] ?? [];
        $rows    = $args['rows']    ?? [];
        $class   = $args['class']   ?? '';
        $empty   = $args['empty']   ?? [];

        if ( empty( $rows ) ) {
            return self::empty_state( $empty ?: [ 'title' => 'No records found.' ] );
        }

        $classes = 'el-data-table widefat' . ( $class ? ' ' . esc_attr( $class ) : '' );

        $html  = '<table class="' . $classes . '">';
        $html .= '<thead><tr>';
        foreach ( $columns as $col ) {
            $th_class = $col['class'] ?? '';
            $html .= '<th' . ( $th_class ? ' class="' . esc_attr( $th_class ) . '"' : '' ) . '>'
                   . esc_html( $col['label'] ?? '' ) . '</th>';
        }
        if ( isset( $rows[0]['__actions'] ) ) {
            $html .= '<th class="el-col-actions">Actions</th>';
        }
        $html .= '</tr></thead>';

        $html .= '<tbody>';
        foreach ( $rows as $row ) {
            $html .= '<tr>';
            foreach ( $columns as $col ) {
                $key      = $col['key']   ?? '';
                $td_class = $col['class'] ?? '';
                $cell     = $row[ $key ]  ?? '';
                $html .= '<td' . ( $td_class ? ' class="' . esc_attr( $td_class ) . '"' : '' ) . '>'
                       . wp_kses_post( (string) $cell ) . '</td>';
            }
            if ( isset( $row['__actions'] ) ) {
                $html .= '<td class="el-col-actions">' . $row['__actions'] . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    // -------------------------------------------------------------------------
    // RECORD CARD (for grid list views)
    // -------------------------------------------------------------------------

    /**
     * Render a clickable record card for grid/list views (clients, projects, etc).
     *
     * @param array $args {
     *     @type string $title    Required. Primary name/title of the record.
     *     @type string $url      Required. URL the card links to.
     *     @type string $subtitle Optional. Secondary line below the title.
     *     @type array  $badges   Optional. Array of badge args, each passed to EL_Admin_UI::badge().
     *     @type array  $meta     Optional. Array of ['icon' => '', 'text' => ''] metadata items.
     *     @type array  $footer   Optional. Array of ['icon' => '', 'text' => ''] stats for card footer.
     *     @type array  $actions  Optional. Button args for action buttons on the card.
     * }
     * @return string
     */
    public static function record_card( array $args ): string {
        $title    = $args['title']    ?? '';
        $url      = $args['url']      ?? '#';
        $subtitle = $args['subtitle'] ?? '';
        $badges   = $args['badges']   ?? [];
        $meta     = $args['meta']     ?? [];
        $footer   = $args['footer']   ?? [];
        $actions  = $args['actions']  ?? [];

        $html  = '<div class="el-record-card">';
        $html .= '<div class="el-record-card-body">';

        // Title
        $html .= '<h3 class="el-record-title">'
               . '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>'
               . '</h3>';

        // Subtitle
        if ( $subtitle ) {
            $html .= '<p class="el-record-subtitle">' . esc_html( $subtitle ) . '</p>';
        }

        // Badges
        if ( ! empty( $badges ) ) {
            $html .= '<div class="el-record-badges">';
            foreach ( $badges as $badge ) {
                $html .= self::badge( $badge );
            }
            $html .= '</div>';
        }

        // Meta items (icon + text)
        if ( ! empty( $meta ) ) {
            $html .= '<div class="el-record-meta">';
            foreach ( $meta as $item ) {
                $html .= '<span class="el-record-meta-item">';
                if ( ! empty( $item['icon'] ) ) {
                    $html .= '<span class="dashicons dashicons-' . esc_attr( $item['icon'] ) . '"></span>';
                }
                $html .= esc_html( $item['text'] ?? '' );
                $html .= '</span>';
            }
            $html .= '</div>';
        }

        // Actions
        if ( ! empty( $actions ) ) {
            $html .= '<div class="el-record-actions">';
            foreach ( $actions as $action ) {
                $html .= self::btn( $action );
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // .el-record-card-body

        // Footer stats
        if ( ! empty( $footer ) ) {
            $html .= '<div class="el-record-card-footer">';
            foreach ( $footer as $item ) {
                $html .= '<span class="el-record-footer-item">';
                if ( ! empty( $item['icon'] ) ) {
                    $html .= '<span class="dashicons dashicons-' . esc_attr( $item['icon'] ) . '"></span>';
                }
                $html .= esc_html( $item['text'] ?? '' );
                $html .= '</span>';
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // .el-record-card

        return $html;
    }

    // -------------------------------------------------------------------------
    // RECORD GRID (wraps multiple record cards)
    // -------------------------------------------------------------------------

    /**
     * Render a responsive grid of record cards.
     *
     * @param array $cards  Array of $args arrays, each passed to EL_Admin_UI::record_card().
     * @return string
     */
    public static function record_grid( array $cards ): string {
        if ( empty( $cards ) ) {
            return '';
        }
        $html = '<div class="el-record-grid">';
        foreach ( $cards as $card ) {
            $html .= self::record_card( $card );
        }
        $html .= '</div>';
        return $html;
    }
}
