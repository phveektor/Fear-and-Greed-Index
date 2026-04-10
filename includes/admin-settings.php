<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin settings for Fear & Greed Gauge
 */
class FGG_Admin_Settings {

    const OPTION_KEY = 'fgg_settings';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
    add_action( 'admin_post_fgg_save_settings', array( __CLASS__, 'save_settings' ) );
    add_action( 'wp_ajax_fgg_test_api', array( __CLASS__, 'ajax_test_api' ) );
    add_action( 'wp_ajax_fgg_clear_cache', array( __CLASS__, 'ajax_clear_cache' ) );
    add_action( 'wp_ajax_fgg_preview_widget', array( __CLASS__, 'ajax_preview_widget' ) );
    }

    public static function add_menu() {
        add_options_page( 'Fear & Greed Gauge', 'Fear & Greed Gauge', 'manage_options', 'fgg-settings', array( __CLASS__, 'settings_page' ) );
    }

    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'fear-greed-gauge' ) );
        }

            $opts = get_option( self::OPTION_KEY, array(
            'cache_hours' => 24,
            'default_size' => 'medium',
            'color_scheme' => 'classic',
            'show_change' => 'yes',
            'show_last_updated' => 'yes',
            'display_title' => 'no',
            'show_week_heading' => 'yes',
            'show_legend' => 'yes',
            'bar_tooltips' => 'yes',
            'legend_position' => 'below',
            'api_provider' => 'alternative_me',
            'api_endpoint' => 'https://api.alternative.me/fng/?limit=7',
            'api_key' => '',
            'data_scope' => 'crypto',
            'legend_text' => 'Market sentiment index — 0 (Extreme Fear) to 100 (Extreme Greed). Use for high-level risk assessment.',
        ) );
        // Show warning if previous save detected incompatible selection
        if ( get_transient( 'fgg_warn_incompatible' ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Warning: You selected Bitcoin-only scope while using alternative.me provider which provides a market-wide index. Results may be misleading.', 'fear-greed-gauge' ) . '</p></div>';
            delete_transient( 'fgg_warn_incompatible' );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Fear & Greed Gauge Settings', 'fear-greed-gauge' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'fgg_save_settings', 'fgg_nonce' ); ?>
                <input type="hidden" name="action" value="fgg_save_settings">

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Default widget size', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <select name="fgg[default_size]">
                                <option value="small" <?php selected( $opts['default_size'] ?? '', 'small' ); ?>><?php esc_html_e( 'Small', 'fear-greed-gauge' ); ?></option>
                                <option value="medium" <?php selected( $opts['default_size'] ?? '', 'medium' ); ?>><?php esc_html_e( 'Medium', 'fear-greed-gauge' ); ?></option>
                                <option value="large" <?php selected( $opts['default_size'] ?? '', 'large' ); ?>><?php esc_html_e( 'Large', 'fear-greed-gauge' ); ?></option>
                            </select>
                            <p class="description" style="margin-top:.35rem;"><label><input type="checkbox" name="fgg[display_title]" value="yes" <?php checked( $opts['display_title'] ?? 'no', 'yes' ); ?>> <?php esc_html_e( 'Display widget title inside the widget (useful when widget areas do not supply a title)', 'fear-greed-gauge' ); ?></label></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'API cache duration (hours)', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <input type="number" name="fgg[cache_hours]" min="1" max="24" value="<?php echo esc_attr( $opts['cache_hours'] ?? 24 ); ?>"> 
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'API provider / endpoint', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <select id="fgg-api-provider" name="fgg[api_provider]">
                                <option value="alternative_me" <?php selected( $opts['api_provider'] ?? '', 'alternative_me' ); ?>><?php esc_html_e( 'alternative.me (no API key required)', 'fear-greed-gauge' ); ?></option>
                                <option value="custom" <?php selected( $opts['api_provider'] ?? '', 'custom' ); ?>><?php esc_html_e( 'Custom endpoint', 'fear-greed-gauge' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose the API provider. Use Custom to enter your own endpoint and optional API key.', 'fear-greed-gauge' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Data scope', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <select name="fgg[data_scope]">
                                <option value="crypto" <?php selected( $opts['data_scope'] ?? '', 'crypto' ); ?>><?php esc_html_e( 'Crypto market (default)', 'fear-greed-gauge' ); ?></option>
                                <option value="btc" <?php selected( $opts['data_scope'] ?? '', 'btc' ); ?>><?php esc_html_e( 'Bitcoin (BTC) only', 'fear-greed-gauge' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose whether the index should be considered market-wide or BTC-specific (requires a matching data source).', 'fear-greed-gauge' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Legend text (concise)', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <input type="text" name="fgg[legend_text]" value="<?php echo esc_attr( $opts['legend_text'] ?? '' ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Short explanatory text shown under the gauge. Keep it brief (one line).', 'fear-greed-gauge' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show percent change under value', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="fgg[show_change]" value="yes" <?php checked( $opts['show_change'] ?? 'yes', 'yes' ); ?>> <?php esc_html_e( 'Display the ▲/▼ percent change under the main value', 'fear-greed-gauge' ); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show last updated timestamp', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="fgg[show_last_updated]" value="yes" <?php checked( $opts['show_last_updated'] ?? 'yes', 'yes' ); ?>> <?php esc_html_e( 'Display a small updated timestamp under the value', 'fear-greed-gauge' ); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Bar tooltips', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="fgg[bar_tooltips]" value="yes" <?php checked( $opts['bar_tooltips'] ?? 'yes', 'yes' ); ?>> <?php esc_html_e( 'Show date+value tooltips when hovering each bar (recommended)', 'fear-greed-gauge' ); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show "This week" heading', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="fgg[show_week_heading]" value="yes" <?php checked( $opts['show_week_heading'] ?? 'yes', 'yes' ); ?>> <?php esc_html_e( 'Display the "This week" heading above the legend', 'fear-greed-gauge' ); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Legend placement', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <select name="fgg[legend_position]">
                                <option value="below" <?php selected( $opts['legend_position'] ?? '', 'below' ); ?>><?php esc_html_e( 'Below chart (default)', 'fear-greed-gauge' ); ?></option>
                                <option value="above" <?php selected( $opts['legend_position'] ?? '', 'above' ); ?>><?php esc_html_e( 'Above chart', 'fear-greed-gauge' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show legend text', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="fgg[show_legend]" value="yes" <?php checked( $opts['show_legend'] ?? 'yes', 'yes' ); ?>> <?php esc_html_e( 'Display the concise legend/description under the chart', 'fear-greed-gauge' ); ?></label>
                        </td>
                    </tr>

                    <tr class="fgg-endpoint-row" style="display:<?php echo ( ( $opts['api_provider'] ?? 'alternative_me' ) === 'custom' ) ? 'table-row' : 'none'; ?>;">
                        <th scope="row"><?php esc_html_e( 'Custom API endpoint', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <input type="url" name="fgg[api_endpoint]" value="<?php echo esc_attr( $opts['api_endpoint'] ?? 'https://api.alternative.me/fng/?limit=7' ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Full URL to fetch JSON data. Must return same data structure.', 'fear-greed-gauge' ); ?></p>
                        </td>
                    </tr>

                    <tr class="fgg-key-row" style="display:<?php echo ( ( $opts['api_provider'] ?? 'alternative_me' ) === 'custom' ) ? 'table-row' : 'none'; ?>;">
                        <th scope="row"><?php esc_html_e( 'API Key (optional)', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <input type="text" name="fgg[api_key]" value="<?php echo esc_attr( $opts['api_key'] ?? '' ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'If your API needs a key, paste it here. It will be sent as a Bearer token in the Authorization header.', 'fear-greed-gauge' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Color scheme', 'fear-greed-gauge' ); ?></th>
                        <td>
                            <select name="fgg[color_scheme]">
                                <option value="classic" <?php selected( $opts['color_scheme'] ?? '', 'classic' ); ?>><?php esc_html_e( 'Classic', 'fear-greed-gauge' ); ?></option>
                                <option value="dark" <?php selected( $opts['color_scheme'] ?? '', 'dark' ); ?>><?php esc_html_e( 'Dark', 'fear-greed-gauge' ); ?></option>
                                <option value="custom" <?php selected( $opts['color_scheme'] ?? '', 'custom' ); ?>><?php esc_html_e( 'Custom', 'fear-greed-gauge' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e( 'Tools', 'fear-greed-gauge' ); ?></h2>
            <p>
                <button id="fgg-test-api" class="button button-secondary"><?php esc_html_e( 'Test API connection', 'fear-greed-gauge' ); ?></button>
                <button id="fgg-clear-cache" class="button button-secondary"><?php esc_html_e( 'Clear cache', 'fear-greed-gauge' ); ?></button>
                <button id="fgg-preview" class="button button-primary"><?php esc_html_e( 'Preview Widget', 'fear-greed-gauge' ); ?></button>
            </p>

            <div id="fgg-tools-response" style="margin-top:1rem"></div>
            <div id="fgg-preview-area" style="margin-top:1rem;padding:1rem;border:1px solid #e5e7eb;background:#fff"></div>
        </div>

        <script>
        (function(){
            var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
            var testBtn = document.getElementById('fgg-test-api');
            var clearBtn = document.getElementById('fgg-clear-cache');
            var previewBtn = document.getElementById('fgg-preview');
            var resp = document.getElementById('fgg-tools-response');
            var previewArea = document.getElementById('fgg-preview-area');
            if (testBtn){ testBtn.addEventListener('click', function(){ fetch(ajaxurl + '?action=fgg_test_api&_wpnonce=<?php echo wp_create_nonce( 'fgg_admin' ); ?>').then(r=>r.json()).then(d=>{ resp.innerText = JSON.stringify(d); }); }); }
            if (clearBtn){ clearBtn.addEventListener('click', function(){ fetch(ajaxurl + '?action=fgg_clear_cache&_wpnonce=<?php echo wp_create_nonce( 'fgg_admin' ); ?>').then(r=>r.json()).then(d=>{ resp.innerText = JSON.stringify(d); }); }); }
            if (previewBtn){ previewBtn.addEventListener('click', function(){ previewArea.innerHTML = '<em>Loading preview…</em>'; fetch(ajaxurl + '?action=fgg_preview_widget&_wpnonce=<?php echo wp_create_nonce( 'fgg_admin' ); ?>').then(r=>r.text()).then(d=>{ previewArea.innerHTML = d; }); }); }
        })();
        </script>

        <?php
    }

    public static function save_settings(){
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'fgg_save_settings', 'fgg_nonce' );

        $data = isset( $_POST['fgg'] ) && is_array( $_POST['fgg'] ) ? wp_unslash( $_POST['fgg'] ) : array();
    $san = array();
    $san['default_size'] = in_array( $data['default_size'] ?? '', array( 'small','medium','large' ), true ) ? $data['default_size'] : 'medium';
    $san['cache_hours'] = intval( $data['cache_hours'] ?? 24 );
    $san['cache_hours'] = max(1, min(24, $san['cache_hours']));
    $san['color_scheme'] = in_array( $data['color_scheme'] ?? '', array('classic','dark','custom'), true ) ? $data['color_scheme'] : 'classic';

    // API provider and optional custom endpoint/key
    $san['api_provider'] = in_array( $data['api_provider'] ?? '', array( 'alternative_me', 'custom' ), true ) ? $data['api_provider'] : 'alternative_me';
    $san['api_endpoint'] = isset( $data['api_endpoint'] ) && filter_var( $data['api_endpoint'], FILTER_VALIDATE_URL ) ? esc_url_raw( $data['api_endpoint'] ) : 'https://api.alternative.me/fng/?limit=7';
    $san['api_key'] = isset( $data['api_key'] ) ? sanitize_text_field( $data['api_key'] ) : '';

    // Data scope (crypto/bitcoin) and legend
    $san['data_scope'] = in_array( $data['data_scope'] ?? '', array( 'crypto', 'btc' ), true ) ? $data['data_scope'] : 'crypto';
    $san['legend_text'] = isset( $data['legend_text'] ) ? sanitize_text_field( $data['legend_text'] ) : '';

    // New toggles
    $san['show_change'] = ( isset( $data['show_change'] ) && $data['show_change'] === 'yes' ) ? 'yes' : 'no';
    $san['show_last_updated'] = ( isset( $data['show_last_updated'] ) && $data['show_last_updated'] === 'yes' ) ? 'yes' : 'no';
    $san['show_legend'] = ( isset( $data['show_legend'] ) && $data['show_legend'] === 'yes' ) ? 'yes' : 'no';
    $san['display_title'] = ( isset( $data['display_title'] ) && $data['display_title'] === 'yes' ) ? 'yes' : 'no';
    $san['bar_tooltips'] = ( isset( $data['bar_tooltips'] ) && $data['bar_tooltips'] === 'yes' ) ? 'yes' : 'no';
    $san['legend_position'] = in_array( $data['legend_position'] ?? '', array( 'below', 'above' ), true ) ? $data['legend_position'] : 'below';
    // Show/hide the small 'This week' heading
    $san['show_week_heading'] = ( isset( $data['show_week_heading'] ) && $data['show_week_heading'] === 'yes' ) ? 'yes' : 'no';

        update_option( self::OPTION_KEY, $san );

        // If admin selected BTC scope but provider is alternative_me, set a transient warning
        if ( isset( $san['data_scope'] ) && 'btc' === $san['data_scope'] && isset( $san['api_provider'] ) && 'alternative_me' === $san['api_provider'] ) {
            // show notice on next load
            set_transient( 'fgg_warn_incompatible', 1, 30 );
        }
        wp_safe_redirect( add_query_arg( 'updated', 'true', wp_get_referer() ) );
        exit;
    }

    public static function ajax_test_api(){
        check_ajax_referer( 'fgg_admin' );
        $ok = false;
        $res = array('ok' => false);

        // Use public wrapper if available
        if ( class_exists( 'FG_Data' ) && method_exists( 'FG_Data', 'fetch_raw' ) ) {
            $data = FG_Data::fetch_raw();
            if ( $data && isset( $data['data'] ) ){
                $res = array('ok' => true, 'count' => count( $data['data'] ), 'source' => 'api' );
            } else {
                $res = array('ok' => false, 'error' => 'empty_or_invalid_response');
            }
        } else {
            $res = array('ok' => false, 'error' => 'no_fetch_method');
        }

        wp_send_json( $res );
    }

    public static function ajax_clear_cache(){
        check_ajax_referer( 'fgg_admin' );
        delete_transient( FG_Data::TRANSIENT_KEY );
        wp_send_json( array('cleared' => true) );
    }

    public static function ajax_preview_widget(){
        check_ajax_referer( 'fgg_admin' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }

        // Use shortcode for rendering to ensure same output pipeline
        $html = do_shortcode( '[fear_greed_gauge]' );
        echo $html;
        wp_die();
    }

}

FGG_Admin_Settings::init();