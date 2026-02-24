<?php
/**
 * EL Core Asset Loader
 * 
 * Manages CSS and JavaScript loading for both frontend and admin.
 * Injects brand settings as CSS custom properties so all components
 * use the client's colors, fonts, and branding automatically.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Asset_Loader {

    private EL_Settings $settings;

    public function __construct( EL_Settings $settings ) {
        $this->settings = $settings;

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
        add_action( 'wp_head', [ $this, 'inject_brand_variables' ], 1 );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend(): void {
        wp_enqueue_style(
            'el-core',
            EL_CORE_URL . 'assets/css/el-core.css',
            [],
            EL_CORE_VERSION
        );

        wp_enqueue_script(
            'el-core',
            EL_CORE_URL . 'assets/js/el-core.js',
            [],
            EL_CORE_VERSION,
            true
        );

        // Pass data to JavaScript
        wp_localize_script( 'el-core', 'elCore', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'el_core_nonce' ),
            'restUrl' => rest_url( 'el-core/v1/' ),
        ]);
    }

    /**
     * Inject brand CSS custom properties into the page head.
     * Replaces the old 5-variable output with the full ~25-token design system.
     */
    public function inject_brand_variables(): void {
        $brand  = $this->settings->get_brand();
        $tokens = $this->generate_full_token_set( $brand );

        $css = ":root {\n";
        foreach ( $tokens as $var => $value ) {
            $css .= "    {$var}: {$value};\n";
        }
        $css .= "}\n";

        echo "<style id=\"el-core-brand-variables\">\n{$css}</style>\n";
    }

    /**
     * Generate the full CSS design token set from brand settings.
     *
     * Returns ~25 CSS custom properties:
     * - Brand colors + dark/text variants for primary, secondary, accent
     * - Neutral scale derived from primary (desaturated)
     * - Semantic colors (success, warning, error, info) via hue-shift
     * - Typography tokens
     *
     * @param array $brand The el_core_brand settings array
     * @return array Map of CSS variable name => value
     */
    private function generate_full_token_set( array $brand ): array {
        $primary   = $brand['primary_color']   ?? '#1a1a2e';
        $secondary = $brand['secondary_color'] ?? '#16213e';
        $accent    = $brand['accent_color']    ?? '#e94560';

        $tokens = [];

        // ── Brand colors ──
        foreach ( [ 'primary' => $primary, 'secondary' => $secondary, 'accent' => $accent ] as $name => $hex ) {
            $tokens["--el-{$name}"]      = $hex;
            $tokens["--el-{$name}-dark"] = $this->darken_hex( $hex, 12 );
            $tokens["--el-{$name}-text"] = $this->contrast_text( $hex );
        }

        // ── Neutral scale (derived from primary, heavily desaturated) ──
        $hsl = $this->hex_to_hsl( $primary );
        $tokens['--el-white']  = '#FFFFFF';
        $tokens['--el-bg']     = $this->hsl_to_hex( $hsl['h'], 8, 97 );
        $tokens['--el-border'] = $this->hsl_to_hex( $hsl['h'], 8, 88 );
        $tokens['--el-muted']  = $this->hsl_to_hex( $hsl['h'], 8, 60 );
        $tokens['--el-text']   = $this->hsl_to_hex( $hsl['h'], 8, 20 );
        $tokens['--el-dark']   = $this->hsl_to_hex( $hsl['h'], 8, 8 );

        // ── Semantic colors (hue-shift from primary) ──
        $s = min( 70, max( 50, $hsl['s'] ) );
        $l = min( 55, max( 40, $hsl['l'] ) );
        $tokens['--el-success'] = $this->hsl_to_hex( 145, $s, $l );
        $tokens['--el-warning'] = $this->hsl_to_hex( 45,  $s, $l );
        $tokens['--el-error']   = $this->hsl_to_hex( 5,   $s, $l );
        $tokens['--el-info']    = $this->hsl_to_hex( 210, $s, $l );

        // ── Typography ──
        $tokens['--el-font-heading'] = $brand['font_heading'] ?? 'Inter, sans-serif';
        $tokens['--el-font-body']    = $brand['font_body']    ?? 'Inter, sans-serif';

        return $tokens;
    }

    // ═══════════════════════════════════════════
    // COLOR MATH HELPERS
    // ═══════════════════════════════════════════

    /**
     * Darken a hex color by reducing HSL lightness by $amount points.
     */
    private function darken_hex( string $hex, int $amount ): string {
        $hsl = $this->hex_to_hsl( $hex );
        return $this->hsl_to_hex( $hsl['h'], $hsl['s'], max( 0, $hsl['l'] - $amount ) );
    }

    /**
     * Return white or near-black text color based on WCAG relative luminance.
     */
    private function contrast_text( string $hex ): string {
        return $this->relative_luminance( $hex ) < 0.4 ? '#FFFFFF' : '#1a1a1a';
    }

    /**
     * Calculate WCAG relative luminance of a hex color (0–1).
     */
    private function relative_luminance( string $hex ): float {
        [ $r, $g, $b ] = $this->hex_to_rgb_floats( $hex );
        $linearize = fn( float $c ): float => $c <= 0.03928
            ? $c / 12.92
            : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
        return 0.2126 * $linearize( $r ) + 0.7152 * $linearize( $g ) + 0.0722 * $linearize( $b );
    }

    /**
     * Convert hex string to RGB floats in 0–1 range.
     * Returns [r, g, b].
     */
    private function hex_to_rgb_floats( string $hex ): array {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec( substr( $hex, 0, 2 ) ) / 255,
            hexdec( substr( $hex, 2, 2 ) ) / 255,
            hexdec( substr( $hex, 4, 2 ) ) / 255,
        ];
    }

    /**
     * Convert hex to HSL. Returns ['h' => 0-360, 's' => 0-100, 'l' => 0-100].
     */
    private function hex_to_hsl( string $hex ): array {
        [ $r, $g, $b ] = $this->hex_to_rgb_floats( $hex );

        $max  = max( $r, $g, $b );
        $min  = min( $r, $g, $b );
        $l    = ( $max + $min ) / 2;
        $diff = $max - $min;

        if ( $diff === 0.0 ) {
            return [ 'h' => 0, 's' => 0, 'l' => (int) round( $l * 100 ) ];
        }

        $s = $l > 0.5 ? $diff / ( 2 - $max - $min ) : $diff / ( $max + $min );

        $h = match ( $max ) {
            $r      => fmod( ( $g - $b ) / $diff + 6, 6 ),
            $g      => ( $b - $r ) / $diff + 2,
            default => ( $r - $g ) / $diff + 4,
        };
        $h *= 60;

        return [
            'h' => (int) round( $h ),
            's' => (int) round( $s * 100 ),
            'l' => (int) round( $l * 100 ),
        ];
    }

    /**
     * Convert HSL (h: 0-360, s: 0-100, l: 0-100) to hex string.
     */
    private function hsl_to_hex( int $h, int $s, int $l ): string {
        $s /= 100;
        $l /= 100;

        $c  = ( 1 - abs( 2 * $l - 1 ) ) * $s;
        $x  = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
        $m  = $l - $c / 2;

        if ( $h < 60 )       { $r = $c; $g = $x; $b = 0; }
        elseif ( $h < 120 )  { $r = $x; $g = $c; $b = 0; }
        elseif ( $h < 180 )  { $r = 0;  $g = $c; $b = $x; }
        elseif ( $h < 240 )  { $r = 0;  $g = $x; $b = $c; }
        elseif ( $h < 300 )  { $r = $x; $g = 0;  $b = $c; }
        else                 { $r = $c; $g = 0;  $b = $x; }

        return sprintf( '#%02x%02x%02x',
            (int) round( ( $r + $m ) * 255 ),
            (int) round( ( $g + $m ) * 255 ),
            (int) round( ( $b + $m ) * 255 )
        );
    }
}
