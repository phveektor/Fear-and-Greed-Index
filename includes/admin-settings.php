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

        if ( get_transient( 'fgg_warn_incompatible' ) ) {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Warning: You selected Bitcoin-only scope while using alternative.me provider which provides a market-wide index. Results may be misleading.', 'fear-greed-gauge' ) . '</p></div>';
            delete_transient( 'fgg_warn_incompatible' );
        }
        ?>
        <style>
            /* Premium Admin Dashboard Styles */
            .fgg-admin-wrap {
                max-width: 1200px;
                margin: 20px 20px 20px 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            .fgg-admin-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                color: #fff;
                padding: 2rem;
                border-radius: 12px;
                margin-bottom: 2rem;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            }
            .fgg-admin-title h1 {
                color: #fff;
                margin: 0 0 0.5rem 0;
                font-size: 2rem;
                font-weight: 700;
                letter-spacing: -0.025em;
            }
            .fgg-admin-title p {
                margin: 0;
                color: #94a3b8;
                font-size: 1rem;
            }
            .fgg-admin-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 2rem;
            }
            @media (max-width: 1024px) {
                .fgg-admin-grid { grid-template-columns: 1fr; }
            }
            
            /* Glassmorphism Cards */
            .fgg-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 2rem;
                margin-bottom: 2rem;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            }
            .fgg-card-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: #0f172a;
                margin-top: 0;
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid #e2e8f0;
            }
            
            /* Form Fields */
            .fgg-field-group {
                margin-bottom: 1.5rem;
            }
            .fgg-field-group label {
                display: block;
                font-weight: 600;
                color: #334155;
                margin-bottom: 0.5rem;
            }
            .fgg-field-group .description {
                color: #64748b;
                font-size: 0.875rem;
                margin-top: 0.35rem;
            }
            .fgg-field-group input[type="text"],
            .fgg-field-group input[type="url"],
            .fgg-field-group input[type="number"],
            .fgg-field-group select {
                width: 100%;
                max-width: 400px;
                padding: 0.5rem 0.75rem;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                background-color: #f8fafc;
                transition: all 0.2s;
            }
            .fgg-field-group input:focus,
            .fgg-field-group select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                outline: none;
            }
            
            /* Toggle Switches */
            .fgg-toggle-wrap {
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            .fgg-toggle {
                position: relative;
                display: inline-block;
                width: 44px;
                height: 24px;
            }
            .fgg-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .fgg-slider {
                position: absolute;
                cursor: pointer;
                top: 0; left: 0; right: 0; bottom: 0;
                background-color: #cbd5e1;
                transition: .3s;
                border-radius: 24px;
            }
            .fgg-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .3s;
                border-radius: 50%;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            input:checked + .fgg-slider { background-color: #3b82f6; }
            input:focus + .fgg-slider { box-shadow: 0 0 1px #3b82f6; }
            input:checked + .fgg-slider:before { transform: translateX(20px); }
            
            /* Tools Area */
            .fgg-tools-area {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }
            .fgg-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.6rem 1.2rem;
                border-radius: 6px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s;
                cursor: pointer;
                border: none;
            }
            .fgg-btn-primary { background: #3b82f6; color: #fff; }
            .fgg-btn-primary:hover { background: #2563eb; color: #fff; }
            .fgg-btn-secondary { background: #e2e8f0; color: #334155; }
            .fgg-btn-secondary:hover { background: #cbd5e1; }
            
            .fgg-preview-container {
                margin-top: 1.5rem;
                padding: 1.5rem;
                background: #f8fafc;
                border: 2px dashed #cbd5e1;
                border-radius: 8px;
                min-height: 200px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            #fgg-tools-response { margin-top: 1rem; color: #0f172a; font-family: monospace; background: #f1f5f9; padding: 0.5rem; border-radius:4px;}
            #fgg-tools-response:empty { display: none; }
        </style>

        <div class="fgg-admin-wrap">
            <div class="fgg-admin-header">
                <div class="fgg-admin-title">
                    <h1>Fear & Greed Gauge</h1>
                    <p>Configure the ultimate market sentiment widget for your site.</p>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'fgg_save_settings', 'fgg_nonce' ); ?>
                <input type="hidden" name="action" value="fgg_save_settings">

                <div class="fgg-admin-grid">
                    <div class="fgg-main-col">
                        
                        <div class="fgg-card">
                            <h2 class="fgg-card-title">Widget Appearance</h2>
                            
                            <div class="fgg-field-group">
                                <label>Default Size</label>
                                <select name="fgg[default_size]">
                                    <option value="small" <?php selected( $opts['default_size'] ?? '', 'small' ); ?>>Small</option>
                                    <option value="medium" <?php selected( $opts['default_size'] ?? '', 'medium' ); ?>>Medium</option>
                                    <option value="large" <?php selected( $opts['default_size'] ?? '', 'large' ); ?>>Large</option>
                                </select>
                            </div>

                            <div class="fgg-field-group">
                                <label>Color Scheme</label>
                                <select name="fgg[color_scheme]">
                                    <option value="classic" <?php selected( $opts['color_scheme'] ?? '', 'classic' ); ?>>Classic Light</option>
                                    <option value="dark" <?php selected( $opts['color_scheme'] ?? '', 'dark' ); ?>>Premium Dark</option>
                                </select>
                            </div>

                            <div class="fgg-field-group">
                                <label>Legend Text</label>
                                <input type="text" name="fgg[legend_text]" value="<?php echo esc_attr( $opts['legend_text'] ?? '' ); ?>">
                                <p class="description">Concise explanatory text under the chart.</p>
                            </div>

                            <div class="fgg-field-group">
                                <label>Legend Placement</label>
                                <select name="fgg[legend_position]">
                                    <option value="below" <?php selected( $opts['legend_position'] ?? '', 'below' ); ?>>Below Chart</option>
                                    <option value="above" <?php selected( $opts['legend_position'] ?? '', 'above' ); ?>>Above Chart</option>
                                </select>
                            </div>
                        </div>

                        <div class="fgg-card">
                            <h2 class="fgg-card-title">Display Toggles</h2>
                            
                            <?php
                            $toggles = array(
                                'display_title' => 'Show widget title inside element',
                                'show_change' => 'Show daily percent change',
                                'show_last_updated' => 'Show "Updated" timestamp',
                                'bar_tooltips' => 'Enable hover tooltips on chart bars',
                                'show_week_heading' => 'Show "This Week" string above chart',
                                'show_legend' => 'Display the main descriptive legend'
                            );
                            foreach ($toggles as $key => $label) :
                            ?>
                            <div class="fgg-field-group fgg-toggle-wrap">
                                <label class="fgg-toggle">
                                    <input type="checkbox" name="fgg[<?php echo esc_attr($key); ?>]" value="yes" <?php checked( $opts[$key] ?? 'yes', 'yes' ); ?>>
                                    <span class="fgg-slider"></span>
                                </label>
                                <span><?php echo esc_html($label); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="fgg-card">
                            <h2 class="fgg-card-title">Data Routing & API</h2>

                            <div class="fgg-field-group">
                                <label>Provider</label>
                                <select id="fgg-api-provider" name="fgg[api_provider]">
                                    <option value="alternative_me" <?php selected( $opts['api_provider'] ?? '', 'alternative_me' ); ?>>alternative.me (Crypto)</option>
                                    <option value="custom" <?php selected( $opts['api_provider'] ?? '', 'custom' ); ?>>Custom Endpoint</option>
                                </select>
                            </div>

                            <div class="fgg-field-group">
                                <label>Data Scope Context</label>
                                <select name="fgg[data_scope]">
                                    <option value="crypto" <?php selected( $opts['data_scope'] ?? '', 'crypto' ); ?>>Crypto Market-Wide</option>
                                    <option value="btc" <?php selected( $opts['data_scope'] ?? '', 'btc' ); ?>>Bitcoin Specific</option>
                                </select>
                            </div>

                            <div class="fgg-field-group">
                                <label>Cache Duration (Hours)</label>
                                <input type="number" name="fgg[cache_hours]" min="1" max="24" value="<?php echo esc_attr( $opts['cache_hours'] ?? 24 ); ?>">
                            </div>
                            
                            <div class="fgg-field-group fgg-custom-api-row" style="display:<?php echo ( ( $opts['api_provider'] ?? 'alternative_me' ) === 'custom' ) ? 'block' : 'none'; ?>;">
                                <label>Custom Endpoint URL</label>
                                <input type="url" name="fgg[api_endpoint]" value="<?php echo esc_attr( $opts['api_endpoint'] ?? 'https://api.alternative.me/fng/?limit=7' ); ?>">
                                <br><br>
                                <label>API Key (Optional)</label>
                                <input type="text" name="fgg[api_key]" value="<?php echo esc_attr( $opts['api_key'] ?? '' ); ?>">
                            </div>
                        </div>

                        <button type="submit" class="fgg-btn fgg-btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem; margin-bottom: 2rem;">Save All Changes</button>

                    </div>
                    
                    <div class="fgg-sidebar-col">
                        <div class="fgg-card" style="position: sticky; top: 40px;">
                            <h2 class="fgg-card-title">Diagnostic Tools</h2>
                            <div class="fgg-tools-area">
                                <button type="button" id="fgg-test-api" class="fgg-btn fgg-btn-secondary">Test Connection</button>
                                <button type="button" id="fgg-clear-cache" class="fgg-btn fgg-btn-secondary">Purge Data Cache</button>
                                <button type="button" id="fgg-preview" class="fgg-btn fgg-btn-primary" style="margin-top: 1rem;">Live Preview Widget</button>
                            </div>
                            <div id="fgg-tools-response"></div>
                            <div id="fgg-preview-area" class="fgg-preview-container">
                                <span style="color:#94a3b8">Click "Live Preview" to render gauge.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
            
            // Dynamic API rows toggle
            var providerSelect = document.getElementById('fgg-api-provider');
            var customRow = document.querySelector('.fgg-custom-api-row');
            if (providerSelect && customRow) {
                providerSelect.addEventListener('change', function(){
                    customRow.style.display = (this.value === 'custom') ? 'block' : 'none';
                });
            }

            // AJAX Handlers
            document.getElementById('fgg-test-api')?.addEventListener('click', function(){ 
                fetch(ajaxurl + '?action=fgg_test_api&_wpnonce=<?php echo wp_create_nonce('fgg_admin'); ?>')
                    .then(r=>r.json()).then(d=>{ document.getElementById('fgg-tools-response').innerText = 'API Status: '+JSON.stringify(d); }); 
            });
            document.getElementById('fgg-clear-cache')?.addEventListener('click', function(){ 
                fetch(ajaxurl + '?action=fgg_clear_cache&_wpnonce=<?php echo wp_create_nonce('fgg_admin'); ?>')
                    .then(r=>r.json()).then(d=>{ document.getElementById('fgg-tools-response').innerText = 'Cache Purged.'; }); 
            });
            document.getElementById('fgg-preview')?.addEventListener('click', function(){ 
                var pa = document.getElementById('fgg-preview-area');
                pa.innerHTML = '<em>Rendering engine...</em>'; 
                fetch(ajaxurl + '?action=fgg_preview_widget&_wpnonce=<?php echo wp_create_nonce('fgg_admin'); ?>')
                    .then(r=>r.text()).then(d=>{ pa.innerHTML = d; }); 
            });
        });
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

        $san['api_provider'] = in_array( $data['api_provider'] ?? '', array( 'alternative_me', 'custom' ), true ) ? $data['api_provider'] : 'alternative_me';
        $san['api_endpoint'] = isset( $data['api_endpoint'] ) && filter_var( $data['api_endpoint'], FILTER_VALIDATE_URL ) ? esc_url_raw( $data['api_endpoint'] ) : 'https://api.alternative.me/fng/?limit=7';
        $san['api_key'] = isset( $data['api_key'] ) ? sanitize_text_field( $data['api_key'] ) : '';

        $san['data_scope'] = in_array( $data['data_scope'] ?? '', array( 'crypto', 'btc' ), true ) ? $data['data_scope'] : 'crypto';
        $san['legend_text'] = isset( $data['legend_text'] ) ? sanitize_text_field( $data['legend_text'] ) : '';

        $san['show_change'] = ( isset( $data['show_change'] ) && $data['show_change'] === 'yes' ) ? 'yes' : 'no';
        $san['show_last_updated'] = ( isset( $data['show_last_updated'] ) && $data['show_last_updated'] === 'yes' ) ? 'yes' : 'no';
        $san['show_legend'] = ( isset( $data['show_legend'] ) && $data['show_legend'] === 'yes' ) ? 'yes' : 'no';
        $san['display_title'] = ( isset( $data['display_title'] ) && $data['display_title'] === 'yes' ) ? 'yes' : 'no';
        $san['bar_tooltips'] = ( isset( $data['bar_tooltips'] ) && $data['bar_tooltips'] === 'yes' ) ? 'yes' : 'no';
        $san['legend_position'] = in_array( $data['legend_position'] ?? '', array( 'below', 'above' ), true ) ? $data['legend_position'] : 'below';
        $san['show_week_heading'] = ( isset( $data['show_week_heading'] ) && $data['show_week_heading'] === 'yes' ) ? 'yes' : 'no';

        update_option( self::OPTION_KEY, $san );

        if ( isset( $san['data_scope'] ) && 'btc' === $san['data_scope'] && isset( $san['api_provider'] ) && 'alternative_me' === $san['api_provider'] ) {
            set_transient( 'fgg_warn_incompatible', 1, 30 );
        }
        wp_safe_redirect( add_query_arg( 'updated', 'true', wp_get_referer() ) );
        exit;
    }

    public static function ajax_test_api(){
        check_ajax_referer( 'fgg_admin' );
        $res = array('ok' => false);

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
        if ( ! current_user_can( 'manage_options' ) ) wp_die(-1);

        echo do_shortcode( '[fear_greed_gauge]' );
        wp_die();
    }
}

FGG_Admin_Settings::init();