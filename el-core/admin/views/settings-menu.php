<?php
/**
 * EL Core — Menu Visibility Settings
 *
 * Lets admins control which nav menu items are shown based on user status:
 *   always    — visible to everyone (default)
 *   logged_in — visible only to logged-in users
 *   client    — visible only to logged-in users who are linked to a client org
 *
 * @package EL_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$output = '';

$output .= EL_Admin_UI::page_header( [
    'title'    => __( 'Menu Visibility', 'el-core' ),
    'subtitle' => __( 'Control which nav menu items are shown to guests, logged-in users, or clients.', 'el-core' ),
] );

// ── Load stored rules ─────────────────────────────────────────────────────────
$rules = get_option( 'el_core_menu_visibility', [] ); // [ item_id => 'always' | 'logged_in' | 'client' ]
if ( ! is_array( $rules ) ) $rules = [];

// ── Load all registered nav menus and their items ────────────────────────────
$nav_menus = wp_get_nav_menus();

$menu_locations = get_nav_menu_locations(); // [ location_slug => menu_term_id ]
$location_names = get_registered_nav_menus(); // [ location_slug => label ]

// Build a map: menu term_id => location label
$menu_location_labels = [];
foreach ( $menu_locations as $loc_slug => $term_id ) {
    if ( $term_id ) {
        $menu_location_labels[ $term_id ][] = $location_names[ $loc_slug ] ?? $loc_slug;
    }
}

if ( empty( $nav_menus ) ) {
    $output .= EL_Admin_UI::notice( [
        'type'    => 'info',
        'message' => __( 'No nav menus found. Create menus in <a href="' . admin_url( 'nav-menus.php' ) . '">Appearance → Menus</a> first.', 'el-core' ),
    ] );
    echo EL_Admin_UI::wrap( $output );
    return;
}

// ── Intro notice ──────────────────────────────────────────────────────────────
$output .= EL_Admin_UI::notice( [
    'type'    => 'info',
    'message' => __( 'Set the visibility for each menu item below. <strong>Always</strong> = shown to everyone. <strong>Logged-in only</strong> = hidden from guests. <strong>Clients only</strong> = only shown to users who have been added as a contact in an organization.', 'el-core' ),
] );

echo EL_Admin_UI::wrap( $output );

// ── Per-menu cards ────────────────────────────────────────────────────────────
?>
<div class="wrap el-admin-wrap">
<form id="el-menu-visibility-form" method="post">
<?php wp_nonce_field( 'el_save_menu_visibility', 'el_menu_nonce' ); ?>

<?php foreach ( $nav_menus as $menu ) :
    $items = wp_get_nav_menu_items( $menu->term_id );
    if ( ! $items ) continue;

    $loc_labels = isset( $menu_location_labels[ $menu->term_id ] )
        ? implode( ', ', $menu_location_labels[ $menu->term_id ] )
        : __( '(not assigned to a location)', 'el-core' );
?>
<div class="el-admin-card" style="margin-bottom:24px;">
    <div class="el-admin-card-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <div>
            <h3 style="margin:0;font-size:1rem;font-weight:700;color:#0f172a;"><?php echo esc_html( $menu->name ); ?></h3>
            <p style="margin:4px 0 0;font-size:0.8125rem;color:#64748b;"><?php echo esc_html( $loc_labels ); ?></p>
        </div>
    </div>
    <table class="widefat striped" style="border-radius:8px;overflow:hidden;">
        <thead>
            <tr>
                <th style="width:40%;"><?php esc_html_e( 'Menu Item', 'el-core' ); ?></th>
                <th style="width:20%;text-align:center;"><?php esc_html_e( 'Always', 'el-core' ); ?></th>
                <th style="width:20%;text-align:center;"><?php esc_html_e( 'Logged-in only', 'el-core' ); ?></th>
                <th style="width:20%;text-align:center;"><?php esc_html_e( 'Clients only', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $items as $item ) :
            $item_id   = (int) $item->ID;
            $current   = $rules[ $item_id ] ?? 'always';
            $depth_pad = (int) $item->menu_item_parent ? 'padding-left:' . ( (int) $item->depth * 20 + 8 ) . 'px;' : 'padding-left:8px;';
        ?>
            <tr>
                <td style="<?php echo esc_attr( $depth_pad ); ?>">
                    <?php if ( $item->menu_item_parent ) : ?><span style="color:#94a3b8;margin-right:6px;">↳</span><?php endif; ?>
                    <strong><?php echo esc_html( $item->title ?: $item->post_title ); ?></strong>
                    <span style="color:#94a3b8;font-size:0.75rem;margin-left:6px;"><?php echo esc_html( $item->url ); ?></span>
                </td>
                <td style="text-align:center;">
                    <input type="radio" name="visibility[<?php echo $item_id; ?>]" value="always" <?php checked( $current, 'always' ); ?>>
                </td>
                <td style="text-align:center;">
                    <input type="radio" name="visibility[<?php echo $item_id; ?>]" value="logged_in" <?php checked( $current, 'logged_in' ); ?>>
                </td>
                <td style="text-align:center;">
                    <input type="radio" name="visibility[<?php echo $item_id; ?>]" value="client" <?php checked( $current, 'client' ); ?>>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<p>
    <button type="submit" class="el-btn el-btn-primary" style="padding:10px 24px;font-size:0.9375rem;">
        <span class="dashicons dashicons-saved" style="margin-top:3px;"></span>
        <?php esc_html_e( 'Save Menu Visibility', 'el-core' ); ?>
    </button>
    <span id="el-menu-save-status" style="margin-left:12px;font-size:0.875rem;color:#10b981;display:none;">✓ Saved!</span>
</p>
</form>
</div>

<script>
(function() {
    var form = document.getElementById('el-menu-visibility-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var data = new FormData(form);
        data.append('action', 'el_save_menu_visibility');
        var status = document.getElementById('el-menu-save-status');
        var btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                btn.disabled = false;
                if (res.success) {
                    if (status) { status.style.display = 'inline'; setTimeout(function(){ status.style.display = 'none'; }, 3000); }
                } else {
                    alert(res.data && res.data.message || 'Save failed.');
                }
            })
            .catch(function() { btn.disabled = false; alert('Save failed.'); });
    });
})();
</script>
<?php
