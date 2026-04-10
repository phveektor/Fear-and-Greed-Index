<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FG_Widget
 *
 * WordPress Widget to display the Fear & Greed Gauge.
 */
class FG_Widget extends WP_Widget {

	/**
	 * Default settings
	 *
	 * @var array
	 */
	protected $defaults = array(
		'title'      => 'Fear & Greed Gauge',
		'size'       => 'medium', // small, medium, large
		'show_chart' => 'yes', // yes/no
		'show_legend' => 'yes', // yes/no
		'display_title' => 'no', // whether to output the title inside widget (avoids duplicate titles)
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'fear_greed_gauge_widget', // ID
			__( 'Fear & Greed Gauge', 'fear-greed-gauge' ), // Name
			array( 'description' => __( 'Displays the Fear & Greed Index as an animated gauge.', 'fear-greed-gauge' ) )
		);
	}

	/**
	 * Front-end display of widget
	 *
	 * @param array $args Widget args from theme
	 * @param array $instance Saved values from DB
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults );

		$title = apply_filters( 'widget_title', $instance['title'] );
		$size = in_array( $instance['size'], array( 'small', 'medium', 'large' ), true ) ? $instance['size'] : 'medium';
		$show_chart = ( 'yes' === $instance['show_chart'] ) ? 'yes' : 'no';
		$display_title = ( isset( $instance['display_title'] ) && 'yes' === $instance['display_title'] ) ? true : false;

		echo $args['before_widget'];

		// Output widget title only when explicitly enabled to avoid duplicates with theme/widget area
		if ( $display_title && ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		// Ensure frontend assets are enqueued when widget is output
		if ( class_exists( 'FG_Gauge_Plugin' ) && method_exists( 'FG_Gauge_Plugin', 'enqueue_frontend_assets' ) ) {
			FG_Gauge_Plugin::enqueue_frontend_assets();
		}

		// Retrieve data using FG_Data class if available
		if ( class_exists( 'FG_Data' ) ) {
			$data = FG_Data::get_data();
		} else {
			// Minimal fallback structure
			$data = array(
				'current_value' => 0,
				'change_24h'    => 0.0,
				'weekly_data'   => array(),
				'timestamp'     => time(),
			);
		}

		// Provide variables to template
		$template_vars = array(
			'title'      => $title,
			'size'       => $size,
			'size_class' => 'gauge-' . $size,
			'show_chart' => $show_chart,
			'show_legend' => $instance['show_legend'] ?? $this->defaults['show_legend'],
			// Pass the widget-instance display_title so template can honor instance preference
			'display_title' => isset( $instance['display_title'] ) ? $instance['display_title'] : $this->defaults['display_title'],
			'data'       => $data,
		);

		$template = defined( 'FGG_TEMPLATES_DIR' ) ? FGG_TEMPLATES_DIR . 'gauge-display.php' : false;

		if ( $template && file_exists( $template ) ) {
			// Make variables available to template
			extract( $template_vars ); // phpcs:ignore WordPress.PHP.DontExtract
			include $template;
		} else {
			// Minimal accessible fallback if template missing
			$val = isset( $data['current_value'] ) ? intval( $data['current_value'] ) : 0;
			echo '<div class="fgg-widget fgg-size-' . esc_attr( $size ) . '" role="region" aria-label="Fear & Greed Gauge">';
			// Keep numeric value accessible to screen readers; no visible value in frontend.
			echo '<div class="sr-only" aria-hidden="false">' . esc_html( $val ) . '</div>';
			echo '<div class="fgg-meta"><span class="fgg-dim">Size: 200×120px</span></div>';
			echo '</div>';
		}

		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form
	 *
	 * @param array $instance Previously saved values from DB
	 */
    public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults );

		$title = esc_attr( $instance['title'] );
		$size = esc_attr( $instance['size'] );
		$show = esc_attr( $instance['show_chart'] );
		$display_title = esc_attr( $instance['display_title'] );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'fear-greed-gauge' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo $title; ?>">
		</p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'display_title' ) ); ?>"><?php esc_html_e( 'Display title inside widget (avoid duplicate titles)', 'fear-greed-gauge' ); ?></label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'display_title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'display_title' ) ); ?>">
				<option value="yes" <?php selected( $display_title, 'yes' ); ?>><?php esc_html_e( 'Yes', 'fear-greed-gauge' ); ?></option>
				<option value="no" <?php selected( $display_title, 'no' ); ?>><?php esc_html_e( 'No', 'fear-greed-gauge' ); ?></option>
			</select>
		</p>


		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'size' ) ); ?>"><?php esc_html_e( 'Size:', 'fear-greed-gauge' ); ?></label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'size' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'size' ) ); ?>">
				<option value="small" <?php selected( $size, 'small' ); ?>><?php esc_html_e( 'Small', 'fear-greed-gauge' ); ?></option>
				<option value="medium" <?php selected( $size, 'medium' ); ?>><?php esc_html_e( 'Medium', 'fear-greed-gauge' ); ?></option>
				<option value="large" <?php selected( $size, 'large' ); ?>><?php esc_html_e( 'Large', 'fear-greed-gauge' ); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_legend' ) ); ?>"><?php esc_html_e( 'Show legend under title', 'fear-greed-gauge' ); ?></label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'show_legend' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_legend' ) ); ?>">
				<option value="yes" <?php selected( $instance['show_legend'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'fear-greed-gauge' ); ?></option>
				<option value="no" <?php selected( $instance['show_legend'], 'no' ); ?>><?php esc_html_e( 'No', 'fear-greed-gauge' ); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_chart' ) ); ?>"><?php esc_html_e( 'Show chart:', 'fear-greed-gauge' ); ?></label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'show_chart' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_chart' ) ); ?>">
				<option value="yes" <?php selected( $show, 'yes' ); ?>><?php esc_html_e( 'Yes', 'fear-greed-gauge' ); ?></option>
				<option value="no" <?php selected( $show, 'no' ); ?>><?php esc_html_e( 'No', 'fear-greed-gauge' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved
	 *
	 * @param array $new_instance New values from form
	 * @param array $old_instance Old saved values
	 * @return array Sanitized values
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : $this->defaults['title'];

		$instance['size'] = isset( $new_instance['size'] ) && in_array( $new_instance['size'], array( 'small', 'medium', 'large' ), true ) ? $new_instance['size'] : $this->defaults['size'];

		$instance['show_chart'] = ( isset( $new_instance['show_chart'] ) && 'yes' === $new_instance['show_chart'] ) ? 'yes' : 'no';
		$instance['show_legend'] = ( isset( $new_instance['show_legend'] ) && 'yes' === $new_instance['show_legend'] ) ? 'yes' : 'no';
		$instance['display_title'] = ( isset( $new_instance['display_title'] ) && 'yes' === $new_instance['display_title'] ) ? 'yes' : 'no';

		return $instance;
	}

	/**
	 * Helper to register this widget. Called on widgets_init.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}

}

// Register widget on widgets_init if not already registered elsewhere
add_action( 'widgets_init', array( 'FG_Widget', 'register' ) );