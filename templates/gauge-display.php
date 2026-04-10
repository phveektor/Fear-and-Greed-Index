<?php
/**
 * Gauge display template
 * Expects variables available: $title, $value, $change, $weekly, $size, $show_chart, $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure assets are enqueued
if ( function_exists( 'FG_Gauge_Plugin' ) ) {
	// noop
}

// Fallbacks
$data = isset( $data ) && is_array( $data ) ? $data : array( 'current_value' => 0, 'change_24h' => 0, 'weekly_data' => array() );
$value = intval( $data['current_value'] );
$change = floatval( $data['change_24h'] );
$weekly = is_array( $data['weekly_data'] ) ? $data['weekly_data'] : array();
// Normalize weekly data: ensure each point has integer 'value' and timestamp; enforce oldest->newest order
if ( ! empty( $weekly ) ) {
	// If array keys look numeric and descending, reverse to make oldest->newest
	$first = reset( $weekly );
	$last  = end( $weekly );
	if ( isset( $first['timestamp'] ) && isset( $last['timestamp'] ) && intval( $first['timestamp'] ) > intval( $last['timestamp'] ) ) {
		$weekly = array_reverse( $weekly );
	}
	// Coerce value types
	foreach ( $weekly as &$pt ) {
		$pt['value'] = isset( $pt['value'] ) ? intval( $pt['value'] ) : 0;
		if ( isset( $pt['timestamp'] ) ) { $pt['timestamp'] = intval( $pt['timestamp'] ); }
	}
	unset( $pt );
}

// Additional variable fallbacks
$size = isset( $size ) ? $size : 'medium';
$size_class = isset( $size_class ) ? $size_class : 'gauge-' . $size;
$title = isset( $title ) ? $title : '';
$show_chart = isset( $show_chart ) ? $show_chart : 'yes';
$display_title = isset( $display_title ) ? $display_title : 'no';
// Ensure $opts is at least an array. We'll also read admin options early so
// variables like $legend_position are available before they're referenced
// later in the template (prevents undefined variable warnings in logs).
$opts = isset( $opts ) && is_array( $opts ) ? $opts : array();
$early_opts = get_option( 'fgg_settings', array() );
$opts = array_merge( $early_opts, $opts );

// Ensure legend position has a sensible default before it's used below
$legend_position = isset( $legend_position ) ? $legend_position : ( isset( $opts['legend_position'] ) ? $opts['legend_position'] : 'below' );

// Determine classes (moved after reading admin options so default_size can be applied)
// ... will be computed below after $opts is loaded

?>
<div class="fgg-widget <?php echo esc_attr( $size_class ); ?>" role="img" aria-label="Fear and Greed Gauge" data-value="<?php echo esc_attr( $value ); ?>" data-prev="<?php echo esc_attr( $weekly ? intval( end( $weekly )['value'] ) : $value ); ?>" data-change="<?php echo esc_attr( $change ); ?>">

	<!-- widget title removed from inside the widget to avoid duplicate titles; rely on theme/widget area title instead -->

	<div class="gauge-wrap">
		<!-- Completely rebuilt SVG gauge with proper structure -->
		<svg class="fgg-gauge-svg" viewBox="0 0 200 100" width="100%" height="100" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Fear and Greed gauge">
			<title><?php echo esc_html( $title ?: 'Fear & Greed Gauge' ); ?></title>
			<desc>Semicircle gauge showing cryptocurrency market sentiment from 0 (Extreme Fear) to 100 (Extreme Greed)</desc>
			
			<!-- Define gradient -->
			<defs>
				<linearGradient id="fggGradient" x1="0%" y1="0%" x2="100%" y2="0%">
					<stop offset="0%" style="stop-color:#e53935;stop-opacity:1" />
					<stop offset="50%" style="stop-color:#ffca28;stop-opacity:1" />
					<stop offset="100%" style="stop-color:#43a047;stop-opacity:1" />
				</linearGradient>
			</defs>
			
			<!-- Colored semicircle background (stroke-based for clean appearance) -->
			<path d="M 30,95 A 70,70 0 0,1 170,95" 
				  fill="none" 
				  stroke="url(#fggGradient)" 
				  stroke-width="20" 
				  stroke-linecap="round" />
			
			<!-- NEW, CLEANED NEEDLE -->
			<g class="fgg-needle-group" style="transform-origin: 100px 95px;">
				<path d="M100,95 L100,25" stroke="#1f2937" stroke-width="3" stroke-linecap="round" class="fgg-needle-line" />
				<circle cx="100" cy="95" r="7" fill="#1f2937" stroke="#fff" stroke-width="2" class="fgg-needle-pivot" />
			</g>
		</svg>
	</div>

	<!-- Physical spacer to prevent any overlap -->
	<div class="fgg-spacer" aria-hidden="true" style="height:30px;display:block;"></div>

	<?php if ( ! empty( $weekly ) && $legend_position === 'above' ): ?>
		<!-- Brief legend (above) -->
		<div class="fgg-chart-legend fgg-chart-legend--above" role="note" aria-hidden="false">
			<span class="fgg-legend-swatch" style="background:#43a047"></span>
			<span class="fgg-legend-text">Greed (75-100)</span>
			<span class="fgg-legend-swatch" style="background:#ffca28"></span>
			<span class="fgg-legend-text">Neutral (50-74)</span>
			<span class="fgg-legend-swatch" style="background:#e53935"></span>
			<span class="fgg-legend-text">Fear (0-49)</span>
		</div>
	<?php endif; ?>

	<!-- value display removed from frontend -->

	<?php
	// Show legend text and data scope if requested. Admin settings store defaults in option 'fgg_settings'.
	$opts = get_option( 'fgg_settings', array() );
	$legend_text = isset( $opts['legend_text'] ) ? $opts['legend_text'] : '';
	$data_scope = isset( $opts['data_scope'] ) ? $opts['data_scope'] : 'crypto';
	$show_change = ( isset( $opts['show_change'] ) && $opts['show_change'] === 'yes' );
	$show_last_updated = ( isset( $opts['show_last_updated'] ) && $opts['show_last_updated'] === 'yes' );
	$bar_tooltips = ( isset( $opts['bar_tooltips'] ) && $opts['bar_tooltips'] === 'yes' );
	$legend_position = isset( $opts['legend_position'] ) ? $opts['legend_position'] : 'below';
	$show_week_heading = ( isset( $opts['show_week_heading'] ) && $opts['show_week_heading'] === 'yes' );
	$show_legend = ( isset( $opts['show_legend'] ) && $opts['show_legend'] === 'yes' );
	// Normalize display_title: prefer widget-instance value when provided, otherwise use admin option
	if ( isset( $display_title ) ) {
		// $display_title passed from widget instance (yes/no)
		$display_title = ( $display_title === 'yes' ) ? 'yes' : 'no';
	} else {
		$display_title = ( isset( $opts['display_title'] ) && $opts['display_title'] === 'yes' ) ? 'yes' : 'no';
	}

	// Apply admin default size if widget $size not provided
	$admin_default_size = isset( $opts['default_size'] ) ? $opts['default_size'] : 'medium';
	$size = isset( $size ) && in_array( $size, array( 'small','medium','large' ), true ) ? $size : $admin_default_size;
	$size_class = 'gauge-' . $size;
	if ( ( isset( $show_legend ) && 'yes' === $show_legend ) && ! empty( $legend_text ) ) : ?>
		<div class="fgg-meta fgg-legend" role="status">
			<span class="fgg-legend-title"><?php echo esc_html( $legend_text ); ?></span>
			<span class="fgg-scope fgg-dim" style="display:block;margin-top:.4rem;"><?php echo esc_html( $data_scope === 'btc' ? 'Scope: Bitcoin (BTC) only' : 'Scope: Crypto market' ); ?></span>
		</div>
	<?php endif; ?>

	<!-- legend moved to header/title area -->

	<!-- detailed meta: side-by-side value and change, date underneath -->
	<div class="fgg-detailed-meta" role="group" aria-label="Gauge details">
		<?php if ( isset( $display_title ) && $display_title === 'yes' && ! empty( $title ) ): ?>
			<div class="fgg-widget-title" style="font-weight:600;margin-bottom:.35rem;">
				<?php echo esc_html( $title ); ?>
			</div>
		<?php endif; ?>
		
		<!-- Value and Change in one row -->
		<div class="fgg-value-row">
			<span class="fgg-value fgg-value-visible" aria-hidden="false">
				<?php echo intval( $value ); ?>
			</span>
			<?php if ( $show_change ): ?>
				<span class="fgg-change" aria-hidden="false"></span>
			<?php endif; ?>
		</div>
		
		<!-- Date on second row -->
		<?php if ( ! empty( $weekly ) ) :
			// last-updated: try to extract timestamp from weekly points if present
			$last_ts = 0;
			foreach ( array_reverse( $weekly ) as $pt ) {
				if ( ! empty( $pt['timestamp'] ) ) { $last_ts = intval( $pt['timestamp'] ); break; }
			}
			if ( $last_ts && $show_last_updated ) : ?>
				<div class="fgg-last-updated" aria-hidden="false"><?php echo esc_html( date_i18n( get_option( 'date_format', 'M j, Y' ), $last_ts ) ); ?></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( 'yes' === $show_chart && ! empty( $weekly ) ): ?>
				<!-- Inline SVG column chart (reliable across themes) -->
				<?php if ( 'yes' === $show_chart && ! empty( $weekly ) ):
					// Sidebar-friendly chart sizing: tuned to fit narrow sidebars safely
					$count = count( $weekly );
					$svg_w = 200; // compact svg width
					$svg_h = 150; // add extra vertical space so labels don't overlap or get clipped
					$left_margin = 34; // leave room for y-axis labels
					$bottom_margin = 28; // space for x-axis labels
					$chart_w = $svg_w - $left_margin - 8;
					$chart_h = $svg_h - $bottom_margin - 12;
					$gap = 4; // tighter gap between bars
					$bar_w = (int) floor( ( $chart_w - ( $count - 1 ) * $gap ) / $count );
					if ( $bar_w < 4 ) { $bar_w = 4; }
				?>
				<svg class="fgg-svg-chart" viewBox="0 0 <?php echo $svg_w; ?> <?php echo $svg_h; ?>" width="100%" height="<?php echo $svg_h; ?>" role="img" aria-label="Recent weekly Fear and Greed values">
					<!-- y-axis numeric ticks -->
					<?php
					$ticks = array(100,75,50,25,0);
					foreach ( $ticks as $tick ):
						$y_pos = 8 + ( ( 100 - $tick ) / 100 ) * $chart_h;
					?>
						<text x="<?php echo $left_margin - 6; ?>" y="<?php echo $y_pos + 4; ?>" class="fgg-svg-ylabel" text-anchor="end"><?php echo $tick; ?></text>
					<?php endforeach; ?>

					<g class="fgg-svg-bars" transform="translate(<?php echo $left_margin; ?>,8)">
						<?php
						$x = 0;
						foreach ( $weekly as $pt ):
							$val = isset( $pt['value'] ) ? intval( $pt['value'] ) : 0;
							$h_px = round( ( $val / 100 ) * $chart_h );
							// color
							if ( $val >= 75 ) { $bar_color = '#43a047'; }
							elseif ( $val >= 50 ) { $bar_color = '#ffca28'; }
							else { $bar_color = '#e53935'; }
							// prepare tooltip: date + value
							$label = '';
							if ( ! empty( $pt['timestamp'] ) ) {
								$label = date_i18n( 'M j', intval( $pt['timestamp'] ) );
							} else {
								$label = isset( $pt['label'] ) ? $pt['label'] : '';
							}
							$tooltip = trim( $label . ' — ' . $val );
						?>
							<g class="fgg-svg-bar" transform="translate(<?php echo $x; ?>,<?php echo $chart_h - $h_px; ?>)"<?php if ( $bar_tooltips ) { echo ' aria-label="' . esc_attr( $tooltip ) . '"'; } ?> >
								<?php if ( $bar_tooltips ): ?>
									<title><?php echo esc_html( $tooltip ); ?></title>
								<?php endif; ?>
								<rect x="0" y="0" width="<?php echo $bar_w; ?>" height="<?php echo $h_px; ?>" fill="<?php echo $bar_color; ?>" rx="3" data-value="<?php echo esc_attr( $val ); ?>" />
							</g>
						<?php
							$x += $bar_w + $gap;
						endforeach;
						?>
					</g>

					<!-- x-axis labels removed; dates are included in bar tooltips -->
				</svg>
				<?php endif; ?>
	<?php endif; ?>

		<?php if ( ! empty( $weekly ) && $legend_position === 'below' ): ?>
			<!-- Brief legend under the column chart -->
			<div class="fgg-chart-legend" role="note" aria-hidden="false">
				<span class="fgg-legend-swatch" style="background:#43a047"></span>
				<span class="fgg-legend-text">Greed (75-100)</span>
				<span class="fgg-legend-swatch" style="background:#ffca28"></span>
				<span class="fgg-legend-text">Neutral (50-74)</span>
				<span class="fgg-legend-swatch" style="background:#e53935"></span>
				<span class="fgg-legend-text">Fear (0-49)</span>
			</div>
		<?php endif; ?>

	<?php if ( empty( $weekly ) ): ?>
		<div class="fgg-loading">
			<div class="fgg-skeleton" style="width:100px;height:24px;margin:1rem auto;border-radius:4px;"></div>
		</div>
	<?php endif; ?>

	<!-- Schema.org structured data -->
	<script type="application/ld+json">
	<?php echo wp_json_encode( array(
		'@context' => 'https://schema.org',
		'@type' => 'FinancialProduct',
		'name' => 'Crypto Fear and Greed Index',
		'value' => $value,
		'datePublished' => gmdate( 'c', time() ),
	) ); ?>
	</script>

</div>