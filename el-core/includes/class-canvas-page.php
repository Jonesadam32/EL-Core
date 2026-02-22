<?php
/**
 * Canvas Page System
 * 
 * Allows AI-generated pages (full HTML/CSS/JS) to be dropped into WordPress
 * without Gutenberg breaking them. Provides meta boxes for raw HTML content,
 * custom CSS, and Canvas Mode toggle (hides theme header/footer).
 * 
 * This bypasses WordPress content parsing entirely via a custom page template.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Canvas_Page {

    private static ?EL_Canvas_Page $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_meta_boxes' ], 10, 2 );
        add_action( 'admin_head', [ $this, 'add_admin_styles' ] );
        add_filter( 'template_include', [ $this, 'load_canvas_template' ], 99 );
        add_action( 'wp_head', [ $this, 'inject_custom_css' ] );
        
        // Disable Gutenberg for pages (use Classic Editor instead)
        add_filter( 'use_block_editor_for_post_type', [ $this, 'disable_gutenberg_for_pages' ], 10, 2 );
    }

    /**
     * Disable Gutenberg for pages (Canvas needs Classic Editor)
     */
    public function disable_gutenberg_for_pages( bool $use_block_editor, string $post_type ): bool {
        if ( $post_type === 'page' ) {
            return false;
        }
        return $use_block_editor;
    }

    /**
     * Register meta boxes for Canvas page editor
     */
    public function register_meta_boxes(): void {
        add_meta_box(
            'el_canvas_content',
            __( 'Canvas HTML Content', 'el-core' ),
            [ $this, 'render_html_meta_box' ],
            'page',
            'normal',
            'default'
        );

        add_meta_box(
            'el_canvas_css',
            __( 'Canvas Custom CSS', 'el-core' ),
            [ $this, 'render_css_meta_box' ],
            'page',
            'normal',
            'default'
        );

        add_meta_box(
            'el_canvas_mode',
            __( 'Canvas Mode', 'el-core' ),
            [ $this, 'render_mode_meta_box' ],
            'page',
            'side',
            'default'
        );
    }

    /**
     * Render HTML Content meta box
     */
    public function render_html_meta_box( $post ): void {
        $content = get_post_meta( $post->ID, '_el_canvas_content', true );
        
        echo '<p class="description">' . esc_html__( 'Paste raw HTML here. No sanitization — this content renders exactly as written. JavaScript is allowed.', 'el-core' ) . '</p>';
        echo '<textarea name="el_canvas_content" id="el_canvas_content" rows="30" style="width: 100%; font-family: monospace; font-size: 13px;">';
        echo esc_textarea( $content );
        echo '</textarea>';
    }

    /**
     * Render Custom CSS meta box
     */
    public function render_css_meta_box( $post ): void {
        $css = get_post_meta( $post->ID, '_el_canvas_css', true );
        
        echo '<p class="description">' . esc_html__( 'Custom CSS rules for this page only. Injected into <style> tag in <head>.', 'el-core' ) . '</p>';
        echo '<textarea name="el_canvas_css" id="el_canvas_css" rows="15" style="width: 100%; font-family: monospace; font-size: 13px;">';
        echo esc_textarea( $css );
        echo '</textarea>';
    }

    /**
     * Render Canvas Mode meta box
     */
    public function render_mode_meta_box( $post ): void {
        // Nonce field - placed here because this meta box is always visible in sidebar
        wp_nonce_field( 'el_canvas_save', 'el_canvas_nonce' );
        
        $canvas_mode = get_post_meta( $post->ID, '_el_canvas_mode', true );
        
        echo '<p><strong>' . esc_html__( 'Canvas pages render raw HTML without WordPress content filters.', 'el-core' ) . '</strong></p>';
        echo '<label style="display: block; margin: 12px 0;">';
        echo '<input type="checkbox" name="el_canvas_mode" value="1" style="margin-right: 8px;" ' . checked( $canvas_mode, '1', false ) . '>';
        echo esc_html__( 'Enable Canvas Mode', 'el-core' );
        echo '</label>';
        echo '<p class="description">' . esc_html__( 'When enabled, this page uses the Canvas template automatically. The HTML content below will render exactly as written, and the theme header/footer will be hidden.', 'el-core' ) . '</p>';
    }

    /**
     * Save meta box data
     */
    public function save_meta_boxes( int $post_id, $post ): void {
        // Verify nonce
        if ( ! isset( $_POST['el_canvas_nonce'] ) || ! wp_verify_nonce( $_POST['el_canvas_nonce'], 'el_canvas_save' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Only save for pages
        if ( $post->post_type !== 'page' ) {
            return;
        }

        // Save HTML Content (NO sanitization — we want raw HTML/JS)
        if ( isset( $_POST['el_canvas_content'] ) ) {
            $content = wp_unslash( $_POST['el_canvas_content'] );
            update_post_meta( $post_id, '_el_canvas_content', $content );
        } else {
            delete_post_meta( $post_id, '_el_canvas_content' );
        }

        // Save Custom CSS
        if ( isset( $_POST['el_canvas_css'] ) ) {
            $css = wp_unslash( $_POST['el_canvas_css'] );
            update_post_meta( $post_id, '_el_canvas_css', $css );
        } else {
            delete_post_meta( $post_id, '_el_canvas_css' );
        }

        // Save Canvas Mode
        if ( isset( $_POST['el_canvas_mode'] ) && $_POST['el_canvas_mode'] === '1' ) {
            update_post_meta( $post_id, '_el_canvas_mode', '1' );
        } else {
            delete_post_meta( $post_id, '_el_canvas_mode' );
        }
    }

    /**
     * Add admin styles for meta boxes
     */
    public function add_admin_styles(): void {
        $screen = get_current_screen();
        
        if ( ! $screen || ( $screen->id !== 'page' && $screen->id !== 'page-new' ) ) {
            return;
        }

        echo '<style>
            #el_canvas_content textarea,
            #el_canvas_css textarea {
                font-family: "Monaco", "Menlo", "Consolas", monospace;
                font-size: 13px;
                line-height: 1.5;
                tab-size: 4;
            }
            #el_canvas_mode .description {
                margin-top: 8px;
                font-style: italic;
            }
        </style>';
    }

    /**
     * Load Canvas template if Canvas Mode is enabled
     */
    public function load_canvas_template( string $template ): string {
        if ( ! is_page() ) {
            return $template;
        }

        $canvas_mode = get_post_meta( get_the_ID(), '_el_canvas_mode', true );
        
        // If Canvas Mode is enabled, use Canvas template
        if ( $canvas_mode === '1' ) {
            $canvas_template = EL_CORE_DIR . 'templates/template-canvas.php';
            
            if ( file_exists( $canvas_template ) ) {
                return $canvas_template;
            }
        }

        return $template;
    }

    /**
     * Inject custom CSS into page head
     */
    public function inject_custom_css(): void {
        if ( ! is_page() ) {
            return;
        }

        $canvas_mode = get_post_meta( get_the_ID(), '_el_canvas_mode', true );
        
        // Only inject if Canvas Mode is enabled
        if ( $canvas_mode !== '1' ) {
            return;
        }

        $css = get_post_meta( get_the_ID(), '_el_canvas_css', true );
        
        if ( ! empty( $css ) ) {
            echo '<style id="el-canvas-custom-css">' . "\n";
            echo $css . "\n";
            echo '</style>' . "\n";
        }
    }
}
