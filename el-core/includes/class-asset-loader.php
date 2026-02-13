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
     * Inject brand CSS custom properties into the page head
     * This is what makes brand settings available to all CSS
     */
    public function inject_brand_variables(): void {
        $css = $this->settings->get_brand_css_variables();
        echo "<style id=\"el-core-brand-variables\">\n{$css}</style>\n";
    }
}
