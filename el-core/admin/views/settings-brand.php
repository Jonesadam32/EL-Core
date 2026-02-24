<?php
/**
 * EL Core — Brand Settings Page
 *
 * Five sections: Logo & Identity, Brand Colors, Typography, Brand Voice, Dark Mode.
 * Fred's tool for configuring ELS's own global brand.
 * All fields use EL_Admin_UI::* framework.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core  = EL_Core::instance();
$brand = $core->settings->get_brand();
$ai    = $core->settings->get_group( 'ai' );

$font_options = [
    'Inter, sans-serif'       => 'Inter',
    'Poppins, sans-serif'     => 'Poppins',
    'Montserrat, sans-serif'  => 'Montserrat',
    'Raleway, sans-serif'     => 'Raleway',
    'Playfair Display, serif' => 'Playfair Display',
    'Merriweather, serif'     => 'Merriweather',
    'Lora, serif'             => 'Lora',
    'Source Serif Pro, serif' => 'Source Serif Pro',
];

$tone_options = [
    'professional'  => __( 'Professional', 'el-core' ),
    'friendly'      => __( 'Friendly', 'el-core' ),
    'inspirational' => __( 'Inspirational', 'el-core' ),
    'bold'          => __( 'Bold', 'el-core' ),
    'calm'          => __( 'Calm', 'el-core' ),
];

ob_start();

echo EL_Admin_UI::page_header( [
    'title'    => __( 'Brand & Configuration', 'el-core' ),
    'subtitle' => __( 'Global brand identity, colors, typography, and AI settings.', 'el-core' ),
] );
?>
<form method="post" action="options.php" id="el-brand-settings-form">
    <?php settings_fields( 'el_core_brand_group' ); ?>

    <?php
    // ─── SECTION 1: Logo & Identity ───────────────────────────────────────────

    $logo_content = EL_Admin_UI::form_section( [
        'title'       => __( 'Logo & Identity', 'el-core' ),
        'description' => __( 'Upload your logo and favicon. These are used in the site header, email templates, and browser tabs.', 'el-core' ),
    ] );

    // Helper: build a media-upload field row
    $media_field = function( string $field_key, string $label, string $value, string $preview_id, string $helper ) {
        $html  = '<div class="el-form-row">';
        $html .= '<label class="el-form-label">' . esc_html( $label ) . '</label>';
        $html .= '<div class="el-form-field">';
        $html .= '<div class="el-media-row">';
        $html .= '<input type="url" name="el_core_brand[' . esc_attr( $field_key ) . ']" value="' . esc_attr( $value ) . '" class="el-input el-input-url" />';
        $html .= ' <button type="button" class="el-btn el-btn-secondary el-media-upload-btn" data-target="' . esc_attr( $field_key ) . '" data-preview="' . esc_attr( $preview_id ) . '">';
        $html .= '<span class="dashicons dashicons-upload"></span> ' . esc_html__( 'Upload', 'el-core' );
        $html .= '</button>';
        $html .= '</div>';
        $has_value = ! empty( $value );
        $html .= '<div class="el-logo-preview-wrap" id="' . esc_attr( $preview_id ) . '"' . ( $has_value ? '' : ' style="display:none;"' ) . '>';
        if ( $has_value ) {
            $html .= '<img src="' . esc_url( $value ) . '" class="el-logo-preview" alt="" />';
        }
        $html .= '</div>';
        $html .= '<p class="el-form-helper">' . esc_html( $helper ) . '</p>';
        $html .= '</div></div>';
        return $html;
    };

    $logo_content .= $media_field( 'logo_url', __( 'Primary Logo', 'el-core' ), $brand['logo_url'], 'el-preview-logo', __( 'Used in the site header and email templates.', 'el-core' ) );
    $logo_content .= $media_field( 'logo_variant_dark', __( 'Logo — Dark Background Variant', 'el-core' ), $brand['logo_variant_dark'], 'el-preview-logo-dark', __( 'White or light version of your logo for dark backgrounds.', 'el-core' ) );
    $logo_content .= $media_field( 'logo_variant_light', __( 'Logo — Light Background Variant', 'el-core' ), $brand['logo_variant_light'], 'el-preview-logo-light', __( 'Dark version of your logo for light or white backgrounds.', 'el-core' ) );
    $logo_content .= $media_field( 'favicon_url', __( 'Favicon', 'el-core' ), $brand['favicon_url'], 'el-preview-favicon', __( 'Square image, 512×512px recommended. Appears in browser tabs.', 'el-core' ) );

    echo EL_Admin_UI::card( [ 'content' => $logo_content, 'class' => 'el-settings-card' ] );

    // ─── SECTION 2: Brand Colors ──────────────────────────────────────────────

    $color_content = EL_Admin_UI::form_section( [
        'title'       => __( 'Brand Colors', 'el-core' ),
        'description' => __( 'Enter hex color codes for your brand palette. These generate a full design token set used across the site.', 'el-core' ),
    ] );

    $color_content .= EL_Admin_UI::form_row( [
        'name'        => 'el_core_brand[primary_color]',
        'id'          => 'el-color-primary',
        'label'       => __( 'Primary Color', 'el-core' ),
        'type'        => 'text',
        'value'       => $brand['primary_color'],
        'placeholder' => '#1a1a2e',
        'helper'      => __( 'Main brand color — used for buttons, headings, and key UI elements.', 'el-core' ),
    ] );

    $color_content .= EL_Admin_UI::form_row( [
        'name'        => 'el_core_brand[secondary_color]',
        'id'          => 'el-color-secondary',
        'label'       => __( 'Secondary Color', 'el-core' ),
        'type'        => 'text',
        'value'       => $brand['secondary_color'],
        'placeholder' => '#16213e',
        'helper'      => __( 'Supporting color — used for backgrounds, cards, and secondary UI.', 'el-core' ),
    ] );

    $color_content .= EL_Admin_UI::form_row( [
        'name'        => 'el_core_brand[accent_color]',
        'id'          => 'el-color-accent',
        'label'       => __( 'Accent Color', 'el-core' ),
        'type'        => 'text',
        'value'       => $brand['accent_color'],
        'placeholder' => '#e94560',
        'helper'      => __( 'Highlight color — used for CTAs, badges, and links.', 'el-core' ),
    ] );

    // Live color swatch preview strip (read-only visual feedback)
    $swatch_content  = '<div class="el-color-swatch-row">';
    $swatch_content .= '<div class="el-color-swatch-item"><span class="el-color-swatch" id="el-swatch-primary" style="background:' . esc_attr( $brand['primary_color'] ) . '"></span><small>' . esc_html__( 'Primary', 'el-core' ) . '</small></div>';
    $swatch_content .= '<div class="el-color-swatch-item"><span class="el-color-swatch" id="el-swatch-secondary" style="background:' . esc_attr( $brand['secondary_color'] ) . '"></span><small>' . esc_html__( 'Secondary', 'el-core' ) . '</small></div>';
    $swatch_content .= '<div class="el-color-swatch-item"><span class="el-color-swatch" id="el-swatch-accent" style="background:' . esc_attr( $brand['accent_color'] ) . '"></span><small>' . esc_html__( 'Accent', 'el-core' ) . '</small></div>';
    $swatch_content .= '</div>';
    $color_content .= '<div class="el-form-row"><label class="el-form-label">' . esc_html__( 'Preview', 'el-core' ) . '</label><div class="el-form-field">' . $swatch_content . '</div></div>';

    echo EL_Admin_UI::card( [ 'content' => $color_content, 'class' => 'el-settings-card' ] );

    // ─── SECTION 3: Typography ────────────────────────────────────────────────

    $type_content = EL_Admin_UI::form_section( [
        'title'       => __( 'Typography', 'el-core' ),
        'description' => __( 'Choose heading and body fonts. Select from the list or enter a custom font name.', 'el-core' ),
    ] );

    $type_content .= EL_Admin_UI::form_row( [
        'name'    => 'el_core_brand[font_heading]',
        'id'      => 'el-font-heading',
        'label'   => __( 'Heading Font', 'el-core' ),
        'type'    => 'select',
        'value'   => $brand['font_heading'],
        'options' => $font_options,
        'helper'  => __( 'Applied to all headings (H1–H4).', 'el-core' ),
    ] );

    $type_content .= EL_Admin_UI::form_row( [
        'name'        => 'el_core_brand[font_heading_custom]',
        'id'          => 'el-font-heading-custom',
        'label'       => __( 'Or enter a custom heading font', 'el-core' ),
        'type'        => 'text',
        'value'       => isset( $font_options[ $brand['font_heading'] ] ) ? '' : $brand['font_heading'],
        'placeholder' => 'e.g. Nunito, sans-serif',
        'helper'      => __( 'Overrides the dropdown above. Make sure this font is loaded by your theme.', 'el-core' ),
    ] );

    $type_content .= EL_Admin_UI::form_row( [
        'name'    => 'el_core_brand[font_body]',
        'id'      => 'el-font-body',
        'label'   => __( 'Body Font', 'el-core' ),
        'type'    => 'select',
        'value'   => $brand['font_body'],
        'options' => $font_options,
        'helper'  => __( 'Used for paragraphs and general content text.', 'el-core' ),
    ] );

    $type_content .= EL_Admin_UI::form_row( [
        'name'        => 'el_core_brand[font_body_custom]',
        'id'          => 'el-font-body-custom',
        'label'       => __( 'Or enter a custom body font', 'el-core' ),
        'type'        => 'text',
        'value'       => isset( $font_options[ $brand['font_body'] ] ) ? '' : $brand['font_body'],
        'placeholder' => 'e.g. Nunito, sans-serif',
        'helper'      => __( 'Overrides the dropdown above. Make sure this font is loaded by your theme.', 'el-core' ),
    ] );

    echo EL_Admin_UI::card( [ 'content' => $type_content, 'class' => 'el-settings-card' ] );

    // ─── SECTION 4: Brand Voice ───────────────────────────────────────────────

    $voice_content = EL_Admin_UI::form_section( [
        'title'       => __( 'Brand Voice', 'el-core' ),
        'description' => __( 'These fields feed AI-generated content with tone and audience context.', 'el-core' ),
    ] );

    $voice_content .= EL_Admin_UI::form_row( [
        'name'    => 'el_core_brand[brand_tone]',
        'id'      => 'el-brand-tone',
        'label'   => __( 'Tone', 'el-core' ),
        'type'    => 'select',
        'value'   => $brand['brand_tone'],
        'options' => $tone_options,
        'helper'  => __( 'How should AI-generated content sound to your audience?', 'el-core' ),
    ] );

    $voice_content .= EL_Admin_UI::form_row( [
        'name'        => 'el_core_brand[brand_audience]',
        'id'          => 'el-brand-audience',
        'label'       => __( 'Target Audience', 'el-core' ),
        'type'        => 'text',
        'value'       => $brand['brand_audience'],
        'placeholder' => __( 'K-12 educators and district administrators', 'el-core' ),
        'helper'      => __( 'Describe who you are primarily speaking to.', 'el-core' ),
    ] );

    $voice_content .= EL_Admin_UI::form_row( [
        'name'        => 'el_core_brand[brand_values]',
        'id'          => 'el-brand-values',
        'label'       => __( 'Brand Values', 'el-core' ),
        'type'        => 'textarea',
        'value'       => $brand['brand_values'],
        'placeholder' => __( '2-3 sentences describing what your organization stands for.', 'el-core' ),
        'helper'      => __( 'Used to guide AI-generated content to match your voice.', 'el-core' ),
    ] );

    echo EL_Admin_UI::card( [ 'content' => $voice_content, 'class' => 'el-settings-card' ] );

    // ─── SECTION 5: Dark Mode ─────────────────────────────────────────────────

    $dark_content = EL_Admin_UI::form_section( [
        'title'       => __( 'Dark Mode', 'el-core' ),
        'description' => '',
    ] );

    $dark_content .= EL_Admin_UI::form_row( [
        'name'        => 'el_core_brand[dark_mode_preference]',
        'id'          => 'el-dark-mode',
        'label'       => __( 'Dark Mode Support', 'el-core' ),
        'type'        => 'checkbox',
        'value'       => (bool) $brand['dark_mode_preference'],
        'placeholder' => __( 'This installation should support dark mode', 'el-core' ),
        'helper'      => __( 'Records your preference. No dark styles are generated yet — this will be used in a future update.', 'el-core' ),
    ] );

    echo EL_Admin_UI::card( [ 'content' => $dark_content, 'class' => 'el-settings-card' ] );
    ?>

    <?php
    // Hidden fields — preserve AI suggestions set by the portal workflow (not edited here)
    echo '<input type="hidden" name="el_core_brand[org_name]" value="' . esc_attr( $brand['org_name'] ) . '" />';
    echo '<input type="hidden" name="el_core_brand[ai_palette_suggestions]" value="' . esc_attr( $brand['ai_palette_suggestions'] ) . '" />';
    echo '<input type="hidden" name="el_core_brand[palette_selected]" value="' . esc_attr( $brand['palette_selected'] ?? '' ) . '" />';
    ?>

    <div class="el-settings-save-row">
        <?php submit_button( __( 'Save Brand Settings', 'el-core' ), 'primary large', 'submit', false ); ?>
    </div>
</form>

<?php
// ─── AI SETTINGS (separate form) ──────────────────────────────────────────────

$ai_content = EL_Admin_UI::form_section( [
    'title'       => __( 'AI Configuration', 'el-core' ),
    'description' => __( 'API credentials for AI features across all modules.', 'el-core' ),
] );
?>
<form method="post" action="options.php">
    <?php settings_fields( 'el_core_ai_group' ); ?>
    <?php
    $ai_content .= EL_Admin_UI::form_row( [
        'name'    => 'el_core_ai[provider]',
        'id'      => 'el-ai-provider',
        'label'   => __( 'AI Provider', 'el-core' ),
        'type'    => 'select',
        'value'   => $ai['provider'],
        'options' => [
            'anthropic' => 'Anthropic (Claude)',
            'openai'    => 'OpenAI (GPT)',
        ],
    ] );

    $ai_content .= EL_Admin_UI::form_row( [
        'name'   => 'el_core_ai[api_key]',
        'id'     => 'el-ai-api-key',
        'label'  => __( 'API Key', 'el-core' ),
        'type'   => 'password',
        'value'  => $ai['api_key'],
        'helper' => __( 'Stored in the WordPress database.', 'el-core' ),
    ] );

    $ai_content .= EL_Admin_UI::form_row( [
        'name'   => 'el_core_ai[model]',
        'id'     => 'el-ai-model',
        'label'  => __( 'Model', 'el-core' ),
        'type'   => 'text',
        'value'  => $ai['model'],
        'helper' => __( 'Anthropic: claude-sonnet-4-5-20250929 | OpenAI: gpt-4o', 'el-core' ),
    ] );

    $ai_content .= EL_Admin_UI::form_row( [
        'name'  => 'el_core_ai[max_tokens]',
        'id'    => 'el-ai-max-tokens',
        'label' => __( 'Default Max Tokens', 'el-core' ),
        'type'  => 'number',
        'value' => $ai['max_tokens'],
    ] );

    echo EL_Admin_UI::card( [ 'content' => $ai_content, 'class' => 'el-settings-card' ] );
    ?>
    <div class="el-settings-save-row">
        <?php submit_button( __( 'Save AI Settings', 'el-core' ), 'primary large', 'submit', false ); ?>
    </div>
</form>

<?php
$page_html = ob_get_clean();
echo EL_Admin_UI::wrap( $page_html );
