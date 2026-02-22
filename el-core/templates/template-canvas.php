<?php
/**
 * Template Name: Canvas (EL Core)
 * Template Post Type: page
 * 
 * Canvas page template - renders raw HTML content without WordPress content filters.
 * Bypasses Gutenberg, wpautop, and all content processing.
 * 
 * When Canvas Mode is enabled, hides theme header and footer.
 * Otherwise, wraps content in standard WordPress theme structure.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get Canvas settings
$canvas_content = get_post_meta( get_the_ID(), '_el_canvas_content', true );
$canvas_mode = get_post_meta( get_the_ID(), '_el_canvas_mode', true );

// Canvas Mode: full page control (no header/footer)
if ( $canvas_mode === '1' ) {
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>
    <body <?php body_class( 'el-canvas-mode' ); ?>>
        <?php
        // Output raw HTML content - NO FILTERS
        if ( ! empty( $canvas_content ) ) {
            echo $canvas_content;
        } else {
            echo '<div style="padding: 40px; text-align: center; font-family: sans-serif;">';
            echo '<h1>Canvas Page</h1>';
            echo '<p>No content added yet. Edit this page and add HTML in the Canvas HTML Content meta box.</p>';
            echo '</div>';
        }
        ?>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
} else {
    // Normal mode: use theme header/footer
    get_header();
    ?>
    
    <main id="main" class="site-main el-canvas-page">
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <div class="entry-content">
                <?php
                // Output raw HTML content - NO FILTERS
                if ( ! empty( $canvas_content ) ) {
                    echo $canvas_content;
                } else {
                    ?>
                    <div style="padding: 40px; text-align: center;">
                        <h1><?php the_title(); ?></h1>
                        <p><?php esc_html_e( 'No content added yet. Edit this page and add HTML in the Canvas HTML Content meta box.', 'el-core' ); ?></p>
                    </div>
                    <?php
                }
                ?>
            </div>
        </article>
    </main>
    
    <?php
    get_footer();
}
