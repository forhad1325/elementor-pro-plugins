<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFLT_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
    }

    public static function register_menus() {
        add_menu_page( 'Elementor Form Lead Tracker', 'Elementor Form Lead Tracker', 'manage_options', 'elementor-form-lead-tracker', [ __CLASS__, 'page_settings' ], 'dashicons-lock', 80 );
        add_submenu_page( 'elementor-form-lead-tracker', 'Settings', 'Settings', 'manage_options', 'elementor-form-lead-tracker', [ __CLASS__, 'page_settings' ] );
        add_submenu_page( 'elementor-form-lead-tracker', 'Submissions', 'Submissions', 'manage_options', 'eflt-submissions', [ __CLASS__, 'page_submissions' ] );
        add_submenu_page( 'elementor-form-lead-tracker', 'Gated Pages', 'Gated Pages', 'manage_options', 'eflt-gated-pages', [ __CLASS__, 'page_gated_pages' ] );
    }

    public static function page_settings() {
        if ( isset( $_POST['eflt_save'] ) && check_admin_referer( 'dg_settings' ) ) {
            update_option( 'eflt_freshsales_domain', sanitize_text_field( $_POST['eflt_freshsales_domain'] ?? '' ) );
            update_option( 'eflt_freshsales_api_key', sanitize_text_field( $_POST['eflt_freshsales_api_key'] ?? '' ) );
            update_option( 'eflt_ga4_measurement_id', sanitize_text_field( $_POST['eflt_ga4_measurement_id'] ?? '' ) );
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }

        $fs_domain = get_option( 'eflt_freshsales_domain', '' );
        $fs_key    = get_option( 'eflt_freshsales_api_key', '' );
        $ga4_id    = get_option( 'eflt_ga4_measurement_id', '' );
        ?>
        <div class="wrap">
            <h1>Elementor Form Lead Tracker — Global Settings</h1>
            <form method="post">
                <?php wp_nonce_field( 'dg_settings' ); ?>
                <h2>Freshsales CRM</h2>
                <table class="form-table">
                    <tr>
                        <th>Freshsales Domain</th>
                        <td><input type="text" name="eflt_freshsales_domain" value="<?php echo esc_attr( $fs_domain ); ?>" class="regular-text" placeholder="yourcompany.freshsales.io"></td>
                    </tr>
                    <tr>
                        <th>Freshsales API Key</th>
                        <td><input type="password" name="eflt_freshsales_api_key" value="<?php echo esc_attr( $fs_key ); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <h2>Google Analytics</h2>
                <table class="form-table">
                    <tr>
                        <th>GA4 Measurement ID</th>
                        <td><input type="text" name="eflt_ga4_measurement_id" value="<?php echo esc_attr( $ga4_id ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX"></td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="eflt_save" class="button-primary" value="Save Settings"></p>
            </form>
        </div>
        <?php
    }

    public static function page_submissions() {
        if ( isset( $_GET['eflt_export'] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'eflt_export_csv' ) ) {
            global $wpdb;
            $table = $wpdb->prefix . EFLT_TABLE;
            $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=eflt-submissions-' . date( 'Y-m-d' ) . '.csv' );
            $out = fopen( 'php://output', 'w' );
            if ( ! empty( $rows ) ) {
                fputcsv( $out, array_keys( $rows[0] ) );
                foreach ( $rows as $r ) fputcsv( $out, $r );
            }
            fclose( $out );
            exit;
        }

        $page_num    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $form_filter = sanitize_text_field( $_GET['form_filter'] ?? '' );
        $result      = EFLT_Database::get_submissions( $page_num, 25, $form_filter );
        $rows        = $result['rows'];
        $total       = $result['total'];
        $total_pages = ceil( $total / 25 );
        $form_ids    = EFLT_Database::get_form_ids();
        ?>
        <div class="wrap">
            <h1>Submissions <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=eflt-submissions&eflt_export=1' ), 'eflt_export_csv' ); ?>" class="page-title-action">Export CSV</a></h1>
            <?php if ( ! empty( $form_ids ) ) : ?>
            <div style="margin: 10px 0;">
                <strong>Filter by Form:</strong>
                <a href="<?php echo admin_url( 'admin.php?page=eflt-submissions' ); ?>" class="<?php echo empty( $form_filter ) ? 'button button-primary' : 'button'; ?>">All Forms</a>
                <?php foreach ( $form_ids as $fid ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=eflt-submissions&form_filter=' . urlencode( $fid ) ); ?>" class="<?php echo $form_filter === $fid ? 'button button-primary' : 'button'; ?>"><?php echo esc_html( $fid ); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <p>Total: <strong><?php echo $total; ?></strong></p>
            <table class="widefat striped">
                <thead><tr><th>ID</th><th>Form</th><th>Name</th><th>Email</th><th>Downloads</th><th>Submitted</th></tr></thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr><td colspan="6">No submissions yet.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) :
                            $downloads = json_decode( $row->downloads, true );
                            $dl_html = '<em>None</em>';
                            if ( is_array( $downloads ) && count( $downloads ) > 0 ) {
                                $dl_html = implode( '<br>', array_map( function( $d ) {
                                    return esc_html( $d['label'] ) . ' <small>(' . esc_html( $d['time'] ) . ')</small>';
                                }, $downloads ) );
                            }
                        ?>
                        <tr>
                            <td><?php echo $row->id; ?></td>
                            <td><code><?php echo esc_html( $row->form_id ); ?></code></td>
                            <td><?php echo esc_html( $row->name ); ?></td>
                            <td><?php echo esc_html( $row->email ); ?></td>
                            <td><?php echo $dl_html; ?></td>
                            <td><?php echo $row->created_at; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_gated_pages() {
        if ( isset( $_GET['eflt_unprotect'] ) && check_admin_referer( 'eflt_unprotect' ) ) {
            $pid = absint( $_GET['eflt_unprotect'] );
            EFLT_Page_Gate::unprotect_page( $pid );
            echo '<div class="updated"><p>Page "' . esc_html( get_the_title( $pid ) ) . '" is no longer protected.</p></div>';
        }

        $protected = get_option( 'eflt_protected_pages', [] );
        ?>
        <div class="wrap">
            <h1>Gated Pages</h1>
            <?php if ( empty( $protected ) ) : ?>
                <p>No pages are currently protected.</p>
            <?php else : ?>
                <table class="widefat striped" style="max-width: 1000px;">
                    <thead><tr><th>Page</th><th>URL</th><th>Form</th><th>Tracking</th><th>GA4</th><th>Freshsales</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ( $protected as $pid => $cfg ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( get_the_title( $pid ) ); ?></strong></td>
                            <td><code><?php echo esc_url( get_permalink( $pid ) ); ?></code></td>
                            <td><code><?php echo esc_html( $cfg['form_id'] ?? '—' ); ?></code></td>
                            <td><?php echo ! empty( $cfg['track_downloads'] ) ? '✅' : '❌'; ?></td>
                            <td><?php echo ! empty( $cfg['enable_ga4'] ) ? '✅' : '❌'; ?></td>
                            <td><?php echo ! empty( $cfg['enable_freshsales'] ) ? '✅' : '❌'; ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=eflt-gated-pages&eflt_unprotect=' . $pid ), 'eflt_unprotect' ); ?>" class="button button-small" onclick="return confirm('Remove protection from this page?');">Remove Protection</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
