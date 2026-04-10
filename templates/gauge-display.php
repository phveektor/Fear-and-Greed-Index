<?php
/**
 * Gauge display template  
 * Variables available via extract(): $title, $size, $size_class, $show_chart,
 * $display_title, $data, $opts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -- Data fallbacks -------------------------------------------------------
$data          = isset( $data ) && is_array( $data ) ? $data : array();
$value         = intval( $data['current_value'] ?? 0 );
$change        = floatval( $data['change_24h'] ?? 0 );
$weekly        = is_array( $data['weekly_data'] ?? null ) ? $data['weekly_data'] : array();

// Ensure weekly is sorted oldest → newest
if ( count( $weekly ) >= 2 ) {
	$first = reset( $weekly );
	$last  = end( $weekly );
	if ( isset( $first['timestamp'], $last['timestamp'] ) && intval( $first['timestamp'] ) > intval( $last['timestamp'] ) ) {
		$weekly = array_reverse( $weekly );
	}
	foreach ( $weekly as &$pt ) {
		$pt['value']     = intval( $pt['value'] ?? 0 );
		$pt['timestamp'] = intval( $pt['timestamp'] ?? 0 );
	}
	unset( $pt );
}

// Prev-day value for animation start position (yesterday = second-to-last)
$prev_val = $value;
if ( count( $weekly ) >= 2 ) {
	$prev_val = intval( $weekly[ count( $weekly ) - 2 ]['value'] );
}

// -- Options / layout defaults -------------------------------------------
$opts               = isset( $opts ) && is_array( $opts ) ? array_merge( get_option( 'fgg_settings', array() ), $opts ) : get_option( 'fgg_settings', array() );
$admin_default_size = $opts['default_size'] ?? 'medium';
$size               = ( isset( $size ) && in_array( $size, [ 'small', 'medium', 'large' ], true ) ) ? $size : $admin_default_size;
$size_class         = 'gauge-' . $size;
$show_chart         = isset( $show_chart ) ? $show_chart : ( $opts['show_chart'] ?? 'yes' );
$legend_text        = $opts['legend_text'] ?? '';
$data_scope         = $opts['data_scope'] ?? 'crypto';
$show_change        = ( ( $opts['show_change'] ?? 'yes' ) === 'yes' );
$show_last_updated  = ( ( $opts['show_last_updated'] ?? 'yes' ) === 'yes' );
$bar_tooltips       = ( ( $opts['bar_tooltips'] ?? 'yes' ) === 'yes' );
$legend_position    = $opts['legend_position'] ?? 'below';
$show_week_heading  = ( ( $opts['show_week_heading'] ?? 'yes' ) === 'yes' );
$color_scheme       = $opts['color_scheme'] ?? 'classic';

// Respect instance-level flags (from widget or shortcode), fall back to admin option
$show_legend   = isset( $show_legend ) && in_array( $show_legend, [ 'yes', 'no' ], true )
	? $show_legend
	: ( ( $opts['show_legend'] ?? 'yes' ) === 'yes' ? 'yes' : 'no' );
$display_title = isset( $display_title ) && in_array( $display_title, [ 'yes', 'no' ], true )
	? $display_title
	: ( ( $opts['display_title'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
$title = isset( $title ) ? $title : '';

// -- Value label (textual classification) --------------------------------
function fgg_classify( int $v ): string {
	if ( $v <= 24 ) return 'Extreme Fear';
	if ( $v <= 49 ) return 'Fear';
	if ( $v === 50 ) return 'Neutral';
	if ( $v <= 74 ) return 'Greed';
	return 'Extreme Greed';
}
$value_label = fgg_classify( $value );

// -- Build the variable-width arc polygon (PHP geometry) -----------------
// SVG viewport: 0 0 240 130, centre of gauge at (120, 120)
// The arc goes from left (Extreme Fear) to right (Extreme Greed) across the top.
// Width varies: thin (≈8 px) at the Fear end, wide (≈32 px) at the Greed end.
$arc_cx      = 120;
$arc_cy      = 120;
$R_out       = 100;  // outer radius (constant)
$R_in_start  = 92;   // inner radius at θ=0 (left / Extreme Fear)  → stroke  ≈ 8
$R_in_end    = 68;   // inner radius at θ=π (right / Extreme Greed) → stroke ≈ 32
$N           = 60;   // polygon segments (higher = smoother curve)

$pts_o = [];
$pts_i = [];
for ( $i = 0; $i <= $N; $i++ ) {
	$theta = ( $i / $N ) * M_PI;
	$r_i   = $R_in_start + ( $R_in_end - $R_in_start ) * ( $i / $N );

	$pts_o[] = [ round( $arc_cx - $R_out * cos( $theta ), 2 ), round( $arc_cy - $R_out * sin( $theta ), 2 ) ];
	$pts_i[] = [ round( $arc_cx - $r_i  * cos( $theta ), 2 ), round( $arc_cy - $r_i  * sin( $theta ), 2 ) ];
}
// Outer: left → right; then inner: right → left; close.
$d_parts = [ 'M ' . $pts_o[0][0] . ' ' . $pts_o[0][1] ];
for ( $i = 1; $i <= $N; $i++ ) {
	$d_parts[] = 'L ' . $pts_o[ $i ][0] . ' ' . $pts_o[ $i ][1];
}
$d_parts[] = 'L ' . $pts_i[ $N ][0] . ' ' . $pts_i[ $N ][1];
for ( $i = $N - 1; $i >= 0; $i-- ) {
	$d_parts[] = 'L ' . $pts_i[ $i ][0] . ' ' . $pts_i[ $i ][1];
}
$d_parts[] = 'Z';
$arc_d = implode( ' ', $d_parts );

// -- Tick marks at 0, 25, 50, 75, 100 ------------------------------------
$tick_marks = [];
foreach ( [ 0, 25, 50, 75, 100 ] as $tv ) {
	$theta     = ( $tv / 100 ) * M_PI;
	$r_i_at    = $R_in_start + ( $R_in_end - $R_in_start ) * ( $tv / 100 );
	$tick_len  = ( $tv === 50 ) ? 10 : 7;
	$tick_marks[] = [
		'x1' => round( $arc_cx - ( $r_i_at - 2 )         * cos( $theta ), 2 ),
		'y1' => round( $arc_cy - ( $r_i_at - 2 )         * sin( $theta ), 2 ),
		'x2' => round( $arc_cx - ( $R_out + $tick_len )   * cos( $theta ), 2 ),
		'y2' => round( $arc_cy - ( $R_out + $tick_len )   * sin( $theta ), 2 ),
	];
}

// -- Last-updated timestamp ----------------------------------------------
$last_ts = 0;
foreach ( array_reverse( $weekly ) as $pt ) {
	if ( ! empty( $pt['timestamp'] ) ) { $last_ts = $pt['timestamp']; break; }
}
?>
<div class="fgg-widget <?php echo esc_attr( $size_class ); ?> scheme-<?php echo esc_attr( $color_scheme ); ?>"
	 role="region"
	 aria-label="<?php esc_attr_e( 'Fear and Greed Gauge', 'fear-greed-gauge' ); ?>"
	 data-value="<?php echo esc_attr( $value ); ?>"
	 data-prev="<?php echo esc_attr( $prev_val ); ?>"
	 data-change="<?php echo esc_attr( $change ); ?>">

	<?php if ( $display_title === 'yes' && ! empty( $title ) ) : ?>
		<div class="fgg-widget-title"><?php echo esc_html( $title ); ?></div>
	<?php endif; ?>

	<!-- ===== GAUGE SVG ===== -->
	<div class="fgg-gauge-wrap">
		<svg class="fgg-gauge-svg" viewBox="0 0 240 130" xmlns="http://www.w3.org/2000/svg"
			 role="img" aria-label="<?php echo esc_attr( 'Gauge: ' . $value . ' — ' . $value_label ); ?>">
			<title><?php echo esc_html( $title ?: 'Fear & Greed Gauge' ); ?></title>

			<defs>
				<!-- Gradient flows left (Fear) → right (Greed) in user-space coords -->
				<linearGradient id="fggTrackGrad" x1="20" y1="0" x2="220" y2="0" gradientUnits="userSpaceOnUse">
					<stop offset="0%"   stop-color="#e53935"/>
					<stop offset="28%"  stop-color="#ff7043"/>
					<stop offset="55%"  stop-color="#ffca28"/>
					<stop offset="100%" stop-color="#43a047"/>
				</linearGradient>
				<filter id="fggNeedleShadow" x="-50%" y="-50%" width="200%" height="200%">
					<feDropShadow dx="1" dy="2" stdDeviation="2" flood-color="rgba(0,0,0,0.25)"/>
				</filter>
			</defs>

			<!-- Subtle dark halo behind the arc for depth -->
			<path d="<?php echo $arc_d; ?>" fill="rgba(0,0,0,0.08)" transform="translate(0,2)"/>

			<!-- Main colour arc track (variable-width, gradient fill) -->
			<path class="fgg-arc-track" d="<?php echo $arc_d; ?>" fill="url(#fggTrackGrad)"/>

			<!-- Tick marks -->
			<?php foreach ( $tick_marks as $tm ) : ?>
				<line x1="<?php echo $tm['x1']; ?>" y1="<?php echo $tm['y1']; ?>"
					  x2="<?php echo $tm['x2']; ?>" y2="<?php echo $tm['y2']; ?>"
					  stroke="white" stroke-width="1.5" stroke-linecap="round" opacity="0.75"/>
			<?php endforeach; ?>

			<!--
				NEEDLE GROUP
				transform-origin is (120, 120) in SVG user-space.
				Requires: transform-box: view-box in CSS (see gauge-styles.css)
				Rotation formula: (value - 50) * 1.8  deg
				  value=0   → -90°  (points left)
				  value=50  →   0°  (points up)
				  value=100 → +90°  (points right)
			-->
			<g class="fgg-pointer-group" aria-hidden="true">
				<!-- Needle body: thin triangle, tip at y=33, base at y=117 (3px above pivot) -->
				<!-- Shadow clone -->
				<path d="M 120 33 L 117.5 117 L 122.5 117 Z"
					  fill="rgba(0,0,0,0.18)"
					  transform="translate(1.5,2)"
					  aria-hidden="true"/>
				<!-- Real needle  -->
				<path class="fgg-needle-body"
					  d="M 120 33 L 117.5 117 L 122.5 117 Z"
					  fill="#1e293b"/>
				<!-- Pivot outer cap -->
				<circle cx="120" cy="120" r="10" fill="#1e293b"/>
				<!-- Pivot inner highlight -->
				<circle cx="120" cy="120" r="4.5" fill="white"/>
			</g>
		</svg>
	</div>
	<!-- ===== END GAUGE SVG ===== -->

	<!-- Value + Classification label -->
	<div class="fgg-value-block">
		<span class="fgg-value fgg-value-visible" aria-live="polite">0</span>
		<span class="fgg-value-label <?php echo esc_attr( strtolower( str_replace( ' ', '-', $value_label ) ) ); ?>"><?php echo esc_html( $value_label ); ?></span>
		<?php if ( $show_change ) : ?>
			<div class="fgg-change" aria-live="polite"></div>
		<?php endif; ?>
		<?php if ( $last_ts && $show_last_updated ) : ?>
			<div class="fgg-last-updated"><?php echo esc_html( date_i18n( get_option( 'date_format', 'M j, Y' ), $last_ts ) ); ?></div>
		<?php endif; ?>
	</div>

	<?php if ( 'yes' === $show_legend && ! empty( $legend_text ) ) : ?>
		<div class="fgg-meta" role="note">
			<span class="fgg-legend-text"><?php echo esc_html( $legend_text ); ?></span>
			<span class="fgg-scope-pill"><?php echo esc_html( $data_scope === 'btc' ? 'Bitcoin' : 'Crypto Market' ); ?></span>
		</div>
	<?php endif; ?>

	<!-- Weekly bar chart -->
	<?php if ( 'yes' === $show_chart && ! empty( $weekly ) ) : ?>
		<div class="fgg-chart-section">
			<?php if ( $show_week_heading ) : ?>
				<div class="fgg-section-heading"><?php esc_html_e( 'This Week', 'fear-greed-gauge' ); ?></div>
			<?php endif; ?>

			<?php
			$count       = count( $weekly );
			$svg_w       = 220;
			$svg_h       = 110;
			$left_m      = 28;
			$bottom_m    = 6;
			$chart_w     = $svg_w - $left_m - 4;
			$chart_h     = $svg_h - $bottom_m - 10;
			$gap         = 5;
			$bar_w       = max( 6, (int) floor( ( $chart_w - ( $count - 1 ) * $gap ) / $count ) );

			if ( $legend_position === 'above' ) :
			?>
			<div class="fgg-chart-legend">
				<span class="fgg-legend-item"><span class="fgg-legend-swatch" style="background:#43a047"></span>Greed 75+</span>
				<span class="fgg-legend-item"><span class="fgg-legend-swatch" style="background:#ffca28"></span>Neutral 50+</span>
				<span class="fgg-legend-item"><span class="fgg-legend-swatch" style="background:#ff7043"></span>Fear 25+</span>
				<span class="fgg-legend-item"><span class="fgg-legend-swatch" style="background:#e53935"></span>Ext. Fear</span>
			</div>
			<?php endif; ?>

			<svg class="fgg-svg-chart" viewBox="0 0 <?php echo $svg_w; ?> <?php echo $svg_h; ?>" width="100%" height="<?php echo $svg_h; ?>" role="img" aria-label="<?php esc_attr_e( 'Weekly Fear and Greed values', 'fear-greed-gauge' ); ?>">
				<?php foreach ( [ 100, 75, 50, 25, 0 ] as $tick ) :
					$y_p = 8 + ( ( 100 - $tick ) / 100 ) * $chart_h; ?>
					<line x1="<?php echo $left_m; ?>" y1="<?php echo $y_p; ?>" x2="<?php echo $svg_w; ?>" y2="<?php echo $y_p; ?>" stroke="currentColor" stroke-opacity="0.07" stroke-width="1"/>
					<text x="<?php echo $left_m - 5; ?>" y="<?php echo $y_p + 4; ?>" class="fgg-svg-ylabel" text-anchor="end"><?php echo $tick; ?></text>
				<?php endforeach; ?>

				<g transform="translate(<?php echo $left_m; ?>,8)">
					<?php
					$bx = 0;
					foreach ( $weekly as $pt ) :
						$bv      = intval( $pt['value'] ?? 0 );
						$bh      = max( 3, round( ( $bv / 100 ) * $chart_h ) );
						$bcolor  = $bv >= 75 ? '#43a047' : ( $bv >= 50 ? '#ffca28' : ( $bv >= 25 ? '#ff7043' : '#e53935' ) );
						$blabel  = ! empty( $pt['timestamp'] ) ? date_i18n( 'M j', $pt['timestamp'] ) : '';
						$bttip   = trim( $blabel . ' — ' . $bv );
					?>
						<g transform="translate(<?php echo $bx; ?>,<?php echo $chart_h - $bh; ?>)"
						   <?php if ( $bar_tooltips ) echo 'aria-label="' . esc_attr( $bttip ) . '"'; ?>>
							<?php if ( $bar_tooltips ) : ?><title><?php echo esc_html( $bttip ); ?></title><?php endif; ?>
							<rect x="0" y="0" width="<?php echo $bar_w; ?>" height="<?php echo $bh; ?>"
								  fill="<?php echo esc_attr( $bcolor ); ?>"
								  rx="3" ry="3" class="fgg-svg-bar"
								  data-value="<?php echo esc_attr( $bv ); ?>"/>
						</g>
					<?php
						$bx += $bar_w + $gap;
					endforeach;
					?>
				</g>
			</svg>

			<?php if ( $legend_position !== 'above' ) : ?>
			<div class="fgg-chart-legend">
				<span class="fgg-legend-item"><span class="fgg-legend-swatch" style="background:#43a047"></span>Greed 75+</span>
				<span class="fgg-legend-item"><span class="fgg-legend-swatch" style="background:#ffca28"></span>Neutral 50+</span>
				<span class="fgg-legend-item"><span class="fgg-legend-swatch" style="background:#ff7043"></span>Fear 25+</span>
				<span class="fgg-legend-item"><span class="fgg-legend-swatch" style="background:#e53935"></span>Ext. Fear</span>
			</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( empty( $weekly ) ) : ?>
		<div class="fgg-loading">
			<div class="fgg-skeleton" style="width:80px;height:16px;margin:1rem auto;border-radius:4px;"></div>
		</div>
	<?php endif; ?>

	<!-- Schema.org structured data -->
	<script type="application/ld+json">
	<?php echo wp_json_encode( [
		'@context'      => 'https://schema.org',
		'@type'         => 'SpecialAnnouncement',
		'name'          => 'Crypto Fear and Greed Index',
		'text'          => $value . ' — ' . $value_label,
		'datePosted'    => gmdate( 'c', time() ),
	] ); ?>
	</script>

</div>