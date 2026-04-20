<?php
/**
 * Plugin Name:       Exit Intent Popup
 * Plugin URI:        https://example.com/
 * Description:       Lightweight exit-intent popup with configurable desktop and mobile triggers. Paste any signup form embed code into the content area.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Custom
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       exit-intent-popup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EIP_VERSION', '1.0.0' );
define( 'EIP_PATH', plugin_dir_path( __FILE__ ) );
define( 'EIP_URL', plugin_dir_url( __FILE__ ) );

class Exit_Intent_Popup {

	const OPTION_NAME = 'eip_settings';

	private $defaults;

	public function __construct() {
		$this->defaults = array(
			'enabled'             => 1,
			'title'               => 'Wait — before you go',
			'content'             => "<p>Join the list and we'll keep you posted.</p>\n<!-- Paste your signup form embed code here (Mailchimp / ConvertKit / MailerLite / custom HTML) -->",
			'trigger_desktop'     => 1,
			'trigger_scroll_up'   => 1,
			'trigger_back_button' => 0,
			'trigger_inactivity'  => 0,
			'inactivity_seconds'  => 60,
			'trigger_timed'       => 0,
			'timed_seconds'       => 45,
			'frequency'           => 'week',
			'initial_delay'       => 3,
			'max_width'           => 520,
			'border_radius'       => 12,
			'overlay_opacity'     => 65,
			'accent_color'        => '#111111',
			'close_on_overlay'    => 1,
			'close_on_esc'        => 1,
			'hide_for_logged_in'  => 0,
		);

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_popup' ) );
	}

	/**
	 * Merge saved settings with defaults.
	 */
	public function get_settings() {
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $this->defaults );
	}

	/**
	 * Decide whether to render on current request.
	 */
	private function should_render() {
		$s = $this->get_settings();

		if ( empty( $s['enabled'] ) ) {
			return false;
		}
		if ( is_admin() ) {
			return false;
		}
		if ( ! empty( $s['hide_for_logged_in'] ) && is_user_logged_in() ) {
			return false;
		}
		return true;
	}

	public function add_admin_menu() {
		add_options_page(
			__( 'Exit Intent Popup', 'exit-intent-popup' ),
			__( 'Exit Intent Popup', 'exit-intent-popup' ),
			'manage_options',
			'exit-intent-popup',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'eip_settings_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults,
			)
		);
	}

	/**
	 * Sanitise settings on save. Content is intentionally preserved
	 * unescaped so users can paste <script>/<iframe> embed codes.
	 * Access is already restricted to manage_options via the Settings
	 * API nonce and capability checks.
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return $this->defaults;
		}

		$clean = array();

		$clean['enabled']            = ! empty( $input['enabled'] ) ? 1 : 0;
		$clean['hide_for_logged_in'] = ! empty( $input['hide_for_logged_in'] ) ? 1 : 0;
		$clean['title']              = sanitize_text_field( $input['title'] ?? '' );
		$clean['content']            = isset( $input['content'] ) ? (string) $input['content'] : '';

		$clean['trigger_desktop']     = ! empty( $input['trigger_desktop'] ) ? 1 : 0;
		$clean['trigger_scroll_up']   = ! empty( $input['trigger_scroll_up'] ) ? 1 : 0;
		$clean['trigger_back_button'] = ! empty( $input['trigger_back_button'] ) ? 1 : 0;
		$clean['trigger_inactivity']  = ! empty( $input['trigger_inactivity'] ) ? 1 : 0;
		$clean['trigger_timed']       = ! empty( $input['trigger_timed'] ) ? 1 : 0;

		$clean['inactivity_seconds'] = max( 5, intval( $input['inactivity_seconds'] ?? 60 ) );
		$clean['timed_seconds']      = max( 3, intval( $input['timed_seconds'] ?? 45 ) );
		$clean['initial_delay']      = max( 0, min( 60, intval( $input['initial_delay'] ?? 3 ) ) );

		$allowed_freq        = array( 'always', 'session', 'day', 'week' );
		$clean['frequency']  = in_array( $input['frequency'] ?? '', $allowed_freq, true ) ? $input['frequency'] : 'week';

		$clean['max_width']       = max( 280, min( 1200, intval( $input['max_width'] ?? 520 ) ) );
		$clean['border_radius']   = max( 0, min( 40, intval( $input['border_radius'] ?? 12 ) ) );
		$clean['overlay_opacity'] = max( 0, min( 95, intval( $input['overlay_opacity'] ?? 65 ) ) );

		$accent              = sanitize_hex_color( $input['accent_color'] ?? '#111111' );
		$clean['accent_color'] = $accent ? $accent : '#111111';

		$clean['close_on_overlay'] = ! empty( $input['close_on_overlay'] ) ? 1 : 0;
		$clean['close_on_esc']     = ! empty( $input['close_on_esc'] ) ? 1 : 0;

		return $clean;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = $this->get_settings();
		$opt = self::OPTION_NAME;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Exit Intent Popup', 'exit-intent-popup' ); ?></h1>
			<p style="max-width:780px;">
				<?php esc_html_e( 'A lightweight popup that appears when visitors look like they\'re about to leave. Paste any HTML into the content field — including Mailchimp, ConvertKit, MailerLite, or custom form embed codes. No external services required.', 'exit-intent-popup' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'eip_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'General', 'exit-intent-popup' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable popup', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?>>
								<?php esc_html_e( 'Show the popup on the front end', 'exit-intent-popup' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hide from logged-in users', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_for_logged_in]" value="1" <?php checked( $s['hide_for_logged_in'], 1 ); ?>>
								<?php esc_html_e( 'Useful while editing the site so the popup doesn\'t keep appearing', 'exit-intent-popup' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eip_title"><?php esc_html_e( 'Heading', 'exit-intent-popup' ); ?></label></th>
						<td>
							<input id="eip_title" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[title]" value="<?php echo esc_attr( $s['title'] ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to omit the heading entirely.', 'exit-intent-popup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eip_content"><?php esc_html_e( 'Content (HTML)', 'exit-intent-popup' ); ?></label></th>
						<td>
							<textarea id="eip_content" name="<?php echo esc_attr( $opt ); ?>[content]" rows="14" class="large-text code" style="font-family:Menlo,Consolas,monospace;font-size:13px;"><?php echo esc_textarea( $s['content'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Any HTML. Paste form embed codes (Mailchimp / ConvertKit / MailerLite / custom) directly here. Script and iframe tags are preserved.', 'exit-intent-popup' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Triggers', 'exit-intent-popup' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Enable any combination. The popup fires on whichever trigger happens first.', 'exit-intent-popup' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Desktop exit-intent', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[trigger_desktop]" value="1" <?php checked( $s['trigger_desktop'], 1 ); ?>>
								<?php esc_html_e( 'Fire when the cursor exits the top of the viewport', 'exit-intent-popup' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mobile: scroll-up', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[trigger_scroll_up]" value="1" <?php checked( $s['trigger_scroll_up'], 1 ); ?>>
								<?php esc_html_e( 'Fire after user scrolls down, then scrolls back up quickly (common exit tell on mobile)', 'exit-intent-popup' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mobile: back button', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[trigger_back_button]" value="1" <?php checked( $s['trigger_back_button'], 1 ); ?>>
								<?php esc_html_e( 'Intercept the first browser back-press', 'exit-intent-popup' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Use with care — some visitors find this aggressive.', 'exit-intent-popup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Inactivity', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[trigger_inactivity]" value="1" <?php checked( $s['trigger_inactivity'], 1 ); ?>>
								<?php esc_html_e( 'Fire after', 'exit-intent-popup' ); ?>
								<input type="number" min="5" step="1" name="<?php echo esc_attr( $opt ); ?>[inactivity_seconds]" value="<?php echo esc_attr( $s['inactivity_seconds'] ); ?>" class="small-text">
								<?php esc_html_e( 'seconds of no interaction', 'exit-intent-popup' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Timed fallback', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[trigger_timed]" value="1" <?php checked( $s['trigger_timed'], 1 ); ?>>
								<?php esc_html_e( 'Always fire after', 'exit-intent-popup' ); ?>
								<input type="number" min="3" step="1" name="<?php echo esc_attr( $opt ); ?>[timed_seconds]" value="<?php echo esc_attr( $s['timed_seconds'] ); ?>" class="small-text">
								<?php esc_html_e( 'seconds on the page', 'exit-intent-popup' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Minimum dwell time', 'exit-intent-popup' ); ?></th>
						<td>
							<input type="number" min="0" max="60" name="<?php echo esc_attr( $opt ); ?>[initial_delay]" value="<?php echo esc_attr( $s['initial_delay'] ); ?>" class="small-text">
							<?php esc_html_e( 'seconds before any trigger is armed', 'exit-intent-popup' ); ?>
							<p class="description"><?php esc_html_e( 'Prevents the popup from firing immediately on page load.', 'exit-intent-popup' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Frequency', 'exit-intent-popup' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="eip_frequency"><?php esc_html_e( 'Show at most', 'exit-intent-popup' ); ?></label></th>
						<td>
							<select id="eip_frequency" name="<?php echo esc_attr( $opt ); ?>[frequency]">
								<option value="always"  <?php selected( $s['frequency'], 'always' ); ?>><?php esc_html_e( 'Every page load (testing)', 'exit-intent-popup' ); ?></option>
								<option value="session" <?php selected( $s['frequency'], 'session' ); ?>><?php esc_html_e( 'Once per browser session', 'exit-intent-popup' ); ?></option>
								<option value="day"     <?php selected( $s['frequency'], 'day' ); ?>><?php esc_html_e( 'Once per day', 'exit-intent-popup' ); ?></option>
								<option value="week"    <?php selected( $s['frequency'], 'week' ); ?>><?php esc_html_e( 'Once per week', 'exit-intent-popup' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Appearance', 'exit-intent-popup' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Max width (px)', 'exit-intent-popup' ); ?></th>
						<td><input type="number" min="280" max="1200" name="<?php echo esc_attr( $opt ); ?>[max_width]" value="<?php echo esc_attr( $s['max_width'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Border radius (px)', 'exit-intent-popup' ); ?></th>
						<td><input type="number" min="0" max="40" name="<?php echo esc_attr( $opt ); ?>[border_radius]" value="<?php echo esc_attr( $s['border_radius'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Overlay darkness (%)', 'exit-intent-popup' ); ?></th>
						<td><input type="number" min="0" max="95" name="<?php echo esc_attr( $opt ); ?>[overlay_opacity]" value="<?php echo esc_attr( $s['overlay_opacity'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Accent colour', 'exit-intent-popup' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $opt ); ?>[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>" class="regular-text" placeholder="#111111">
							<p class="description"><?php esc_html_e( 'Used for the close button icon. Hex format (e.g. #eb008c).', 'exit-intent-popup' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Behaviour', 'exit-intent-popup' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Close on overlay click', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[close_on_overlay]" value="1" <?php checked( $s['close_on_overlay'], 1 ); ?>>
								<?php esc_html_e( 'Dismiss when the user clicks outside the popup', 'exit-intent-popup' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Close on ESC', 'exit-intent-popup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[close_on_esc]" value="1" <?php checked( $s['close_on_esc'], 1 ); ?>>
								<?php esc_html_e( 'Dismiss when the user presses ESC (recommended for accessibility)', 'exit-intent-popup' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Testing', 'exit-intent-popup' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: query argument example */
					esc_html__( 'Append %s to any front-end URL to force the popup to appear, ignoring the frequency cap. Handy for verifying your form embed works.', 'exit-intent-popup' ),
					'<code>?eip_preview=1</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	public function enqueue_public_assets() {
		if ( ! $this->should_render() ) {
			return;
		}

		$s = $this->get_settings();

		wp_enqueue_style( 'eip-popup', EIP_URL . 'public/popup.css', array(), EIP_VERSION );
		wp_enqueue_script( 'eip-popup', EIP_URL . 'public/popup.js', array(), EIP_VERSION, true );

		$preview = isset( $_GET['eip_preview'] ) ? (bool) $_GET['eip_preview'] : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'eip-popup',
			'EIP_CONFIG',
			array(
				'triggers'       => array(
					'desktop'            => (bool) $s['trigger_desktop'],
					'scrollUp'           => (bool) $s['trigger_scroll_up'],
					'backButton'         => (bool) $s['trigger_back_button'],
					'inactivity'         => (bool) $s['trigger_inactivity'],
					'inactivitySeconds'  => (int) $s['inactivity_seconds'],
					'timed'              => (bool) $s['trigger_timed'],
					'timedSeconds'       => (int) $s['timed_seconds'],
				),
				'frequency'      => $s['frequency'],
				'initialDelay'   => (int) $s['initial_delay'],
				'closeOnOverlay' => (bool) $s['close_on_overlay'],
				'closeOnEsc'     => (bool) $s['close_on_esc'],
				'preview'        => $preview,
			)
		);
	}

	public function render_popup() {
		if ( ! $this->should_render() ) {
			return;
		}

		$s = $this->get_settings();

		$style_vars = sprintf(
			'--eip-max-width:%dpx;--eip-radius:%dpx;--eip-overlay:rgba(0,0,0,%.2f);--eip-accent:%s;',
			(int) $s['max_width'],
			(int) $s['border_radius'],
			( (int) $s['overlay_opacity'] ) / 100,
			$s['accent_color']
		);
		?>
		<div id="eip-root" class="eip-root" aria-hidden="true" style="<?php echo esc_attr( $style_vars ); ?>">
			<div class="eip-overlay" data-eip-close></div>
			<div class="eip-modal" role="dialog" aria-modal="true"<?php echo ! empty( $s['title'] ) ? ' aria-labelledby="eip-title"' : ''; ?> tabindex="-1">
				<button class="eip-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'exit-intent-popup' ); ?>" data-eip-close>&times;</button>
				<?php if ( ! empty( $s['title'] ) ) : ?>
					<h2 id="eip-title" class="eip-title"><?php echo esc_html( $s['title'] ); ?></h2>
				<?php endif; ?>
				<div class="eip-content">
					<?php
					// Intentionally unescaped: admin-configured content may include
					// <script>/<iframe> embed codes from email providers. Access to
					// edit this setting is gated by manage_options + Settings API nonce.
					echo $s['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			</div>
		</div>
		<?php
	}
}

new Exit_Intent_Popup();
