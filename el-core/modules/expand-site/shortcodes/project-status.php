<?php
/**
 * Shortcode: [el_project_status]
 * 
 * Visual 8-step progress bar showing project stage progression.
 * Can be used standalone or embedded in other views.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_project_status( $atts ) {
    $atts = shortcode_atts( [
        'project_id' => 0,
    ], $atts );

    $project_id = absint( $atts['project_id'] );

    if ( ! $project_id ) {
        return '<div class="el-component el-es-stage-bar"><p class="el-notice el-notice-warning">' 
            . esc_html__( 'Project ID required.', 'el-core' ) . '</p></div>';
    }

    $module = EL_Expand_Site_Module::instance();
    $project = $module->get_project( $project_id );

    if ( ! $project ) {
        return '<div class="el-component el-es-stage-bar"><p class="el-notice el-notice-error">' 
            . esc_html__( 'Project not found.', 'el-core' ) . '</p></div>';
    }

    $current_stage = (int) $project->current_stage;
    $stages = EL_Expand_Site_Module::STAGES;

    $html = '<div class="el-component el-es-stage-bar">';
    $html .= '<div class="el-es-stage-track">';

    foreach ( $stages as $num => $stage ) {
        $status = '';
        if ( $num < $current_stage ) {
            $status = 'completed';
        } elseif ( $num === $current_stage ) {
            $status = 'current';
        } else {
            $status = 'upcoming';
        }

        $html .= '<div class="el-es-stage-step el-es-stage-' . esc_attr( $status ) . '" data-stage="' . esc_attr( $num ) . '">';
        $html .= '<div class="el-es-stage-marker">';
        
        if ( $status === 'completed' ) {
            $html .= '<svg class="el-es-stage-check" width="20" height="20" viewBox="0 0 20 20" fill="none">';
            $html .= '<path d="M16.7 5.3L8 14 3.3 9.3l1.4-1.4L8 11.2l7.3-7.3 1.4 1.4z" fill="currentColor"/>';
            $html .= '</svg>';
        } else {
            $html .= '<span class="el-es-stage-number">' . esc_html( $num ) . '</span>';
        }
        
        $html .= '</div>';
        $html .= '<div class="el-es-stage-label">' . esc_html( $stage['name'] ) . '</div>';
        $html .= '</div>';

        // Add connector line (except after last stage)
        if ( $num < 8 ) {
            $connector_status = $num < $current_stage ? 'completed' : 'upcoming';
            $html .= '<div class="el-es-stage-connector el-es-stage-' . esc_attr( $connector_status ) . '"></div>';
        }
    }

    $html .= '</div>'; // .el-es-stage-track
    $html .= '</div>'; // .el-component

    return $html;
}
