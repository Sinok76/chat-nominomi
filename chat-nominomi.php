<?php
/**
 * Plugin Name: Chat Nominomi
 * Plugin URI:  https://nominomi.fr
 * Description: Widget chat flottant alimenté par l'IA Stella.
 * Version:     1.1.0
 * Author:      Nominomi
 * License:     GPL-2.0-or-later
 * Text Domain: chat-nominomi
 */

defined( 'ABSPATH' ) || exit;

class Chat_Nominomi {

	private static $instance = null;

	const DEFAULT_BOT_NAME  = 'Stella';
	const DEFAULT_WELCOME   = 'Bonjour ! Je suis Stella, votre assistante IA. Comment puis-je vous aider ?';
	const DEFAULT_COLOR     = '#4f46e5';
	const DEFAULT_API_URL   = 'https://chat.nominomi.fr/chat';
	const DEFAULT_CLIENT_ID = 'nominomi';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer',             [ $this, 'render_widget' ] );
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_post_cn_save',    [ $this, 'save_settings' ] );
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private function opt( $key, $default = '' ) {
		return get_option( $key, $default );
	}

	/**
	 * Convert a #rrggbb hex color to "r, g, b" string for CSS rgba().
	 */
	private function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return implode( ', ', [
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		] );
	}

	// ── Front-end ─────────────────────────────────────────────────────────

	public function enqueue_assets() {
		if ( ! $this->opt( 'chat_wp_enabled', '1' ) ) return;

		$api_url   = esc_url( $this->opt( 'chat_wp_api_url',        self::DEFAULT_API_URL ) );
		$client_id  = sanitize_text_field( $this->opt( 'chat_wp_client_id',    self::DEFAULT_CLIENT_ID ) );
		$welcome    = sanitize_textarea_field( $this->opt( 'chat_wp_welcome_message', self::DEFAULT_WELCOME ) );
		$color      = sanitize_hex_color( $this->opt( 'chat_wp_primary_color', self::DEFAULT_COLOR ) ) ?: self::DEFAULT_COLOR;
		$secret_key = $this->opt( 'chat_wp_secret_key', '' );

		wp_enqueue_script(
			'chat-nominomi',
			plugin_dir_url( __FILE__ ) . 'chat.js',
			[],
			'1.1.0',
			true
		);

		wp_localize_script( 'chat-nominomi', 'chatConfig', [
			'apiUrl'         => $api_url,
			'clientId'       => $client_id,
			'welcomeMessage' => $welcome,
			'secretKey'      => $secret_key,
		] );

		wp_register_style( 'chat-nominomi-base', false );
		wp_enqueue_style( 'chat-nominomi-base' );
		wp_add_inline_style( 'chat-nominomi-base', $this->get_css( $color ) );
	}

	public function render_widget() {
		if ( ! $this->opt( 'chat_wp_enabled', '1' ) ) return;

		$bot_name = esc_html( $this->opt( 'chat_wp_bot_name', self::DEFAULT_BOT_NAME ) );
		$avatar   = esc_url( $this->opt( 'chat_wp_avatar', '' ) );
		$initial  = esc_html( mb_strtoupper( mb_substr( strip_tags( $bot_name ), 0, 1 ) ) );

		if ( $avatar ) {
			$avatar_html = '<img src="' . $avatar . '" alt="' . $bot_name . '" style="width:100%;height:100%;object-fit:cover;" />';
		} else {
			$avatar_html = $initial;
		}
		?>
		<div id="cn-bubble" role="button" aria-label="Ouvrir le chat" tabindex="0">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26" aria-hidden="true">
				<path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Z"/>
			</svg>
		</div>

		<div id="cn-window" role="dialog" aria-label="Chat <?php echo $bot_name; ?>" aria-hidden="true">
			<div id="cn-header">
				<div id="cn-header-info">
					<div id="cn-avatar" aria-hidden="true"><?php echo $avatar_html; ?></div>
					<div>
						<div id="cn-bot-name"><?php echo $bot_name; ?></div>
						<div id="cn-bot-status">
							<span id="cn-status-dot" aria-hidden="true"></span>
							En ligne &middot; IA active
						</div>
					</div>
				</div>
				<button id="cn-minimize" aria-label="Réduire le chat" title="Réduire">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" width="18" height="18" aria-hidden="true">
						<polyline points="18 15 12 9 6 15"/>
					</svg>
				</button>
			</div>

			<div id="cn-messages" role="log" aria-live="polite" aria-relevant="additions"></div>

			<div id="cn-typing" aria-live="polite" aria-label="<?php echo $bot_name; ?> est en train d'écrire">
				<div class="cn-typing-bubble">
					<span></span><span></span><span></span>
				</div>
			</div>

			<form id="cn-form" autocomplete="off" novalidate>
				<input
					id="cn-input"
					type="text"
					placeholder="Écrivez un message…"
					autocomplete="off"
					aria-label="Message à envoyer"
					maxlength="1000"
				/>
				<button type="submit" id="cn-send" aria-label="Envoyer">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true">
						<path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/>
					</svg>
				</button>
			</form>
		</div>
		<?php
	}

	// ── Admin ─────────────────────────────────────────────────────────────

	public function register_menu() {
		add_options_page(
			'Chat IA – Nominomi',
			'Chat IA',
			'manage_options',
			'chat-nominomi',
			[ $this, 'render_admin_page' ]
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_chat-nominomi' !== $hook ) return;

		wp_enqueue_media();

		wp_register_style( 'chat-nominomi-admin', false );
		wp_enqueue_style( 'chat-nominomi-admin' );
		wp_add_inline_style( 'chat-nominomi-admin', $this->get_admin_css() );

		// Attach admin JS to jquery (always present in WP admin)
		wp_add_inline_script( 'jquery', $this->get_admin_js() );
	}

	public function save_settings() {
		check_admin_referer( 'cn_save_settings', 'cn_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', 403 );
		}

		update_option( 'chat_wp_enabled',         isset( $_POST['chat_wp_enabled'] ) ? '1' : '0' );
		update_option( 'chat_wp_bot_name',         sanitize_text_field( wp_unslash( $_POST['chat_wp_bot_name'] ?? '' ) ) );
		update_option( 'chat_wp_welcome_message',  sanitize_textarea_field( wp_unslash( $_POST['chat_wp_welcome_message'] ?? '' ) ) );
		update_option( 'chat_wp_avatar',           esc_url_raw( wp_unslash( $_POST['chat_wp_avatar'] ?? '' ) ) );
		update_option( 'chat_wp_primary_color',    sanitize_hex_color( wp_unslash( $_POST['chat_wp_primary_color'] ?? '' ) ) ?: self::DEFAULT_COLOR );
		update_option( 'chat_wp_api_url',          esc_url_raw( wp_unslash( $_POST['chat_wp_api_url'] ?? '' ) ) );
		update_option( 'chat_wp_client_id',        sanitize_text_field( wp_unslash( $_POST['chat_wp_client_id'] ?? '' ) ) );
		update_option( 'chat_wp_secret_key',       sanitize_text_field( wp_unslash( $_POST['chat_wp_secret_key'] ?? '' ) ) );

		wp_safe_redirect( admin_url( 'options-general.php?page=chat-nominomi&updated=1' ) );
		exit;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$o = [
			'enabled'   => $this->opt( 'chat_wp_enabled', '1' ),
			'bot_name'  => $this->opt( 'chat_wp_bot_name',        self::DEFAULT_BOT_NAME ),
			'welcome'   => $this->opt( 'chat_wp_welcome_message', self::DEFAULT_WELCOME ),
			'avatar'    => $this->opt( 'chat_wp_avatar',          '' ),
			'color'     => sanitize_hex_color( $this->opt( 'chat_wp_primary_color', self::DEFAULT_COLOR ) ) ?: self::DEFAULT_COLOR,
			'api_url'   => $this->opt( 'chat_wp_api_url',         self::DEFAULT_API_URL ),
			'client_id' => $this->opt( 'chat_wp_client_id',       self::DEFAULT_CLIENT_ID ),
		];
		$health_url = esc_html( rtrim( $o['api_url'], '/' ) . '/health' );
		?>
		<div class="wrap">
			<h1>Chat IA – Nominomi</h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Réglages enregistrés avec succès.</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cn_save_settings', 'cn_nonce' ); ?>
				<input type="hidden" name="action" value="cn_save" />

				<div class="chatbot-admin-container">

					<!-- ── Main column ── -->
					<div class="chatbot-admin-main">

						<!-- Général -->
						<div class="chatbot-section">
							<h2>Général</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">Activer le widget</th>
									<td>
										<label class="chatbot-toggle">
											<input type="checkbox" name="chat_wp_enabled" value="1" <?php checked( $o['enabled'], '1' ); ?> />
											<span class="chatbot-toggle-slider"></span>
										</label>
										<p class="description" style="margin-top:8px;">Affiche ou masque le widget sur toutes les pages.</p>
									</td>
								</tr>
							</table>
						</div>

						<!-- Apparence -->
						<div class="chatbot-section">
							<h2>Apparence</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="chat_wp_bot_name">Nom du bot</label></th>
									<td>
										<input type="text" id="chat_wp_bot_name" name="chat_wp_bot_name"
											value="<?php echo esc_attr( $o['bot_name'] ); ?>"
											class="regular-text"
											placeholder="<?php echo esc_attr( self::DEFAULT_BOT_NAME ); ?>" />
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="chat_wp_welcome_message">Message de bienvenue</label></th>
									<td>
										<textarea id="chat_wp_welcome_message" name="chat_wp_welcome_message"
											rows="3" class="large-text"
											placeholder="<?php echo esc_attr( self::DEFAULT_WELCOME ); ?>"><?php echo esc_textarea( $o['welcome'] ); ?></textarea>
										<p class="description">Affiché automatiquement à l'ouverture du chat.</p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="chat_wp_avatar">Avatar du bot</label></th>
									<td>
										<div class="cn-avatar-row">
											<img id="cn-avatar-preview"
												src="<?php echo esc_url( $o['avatar'] ); ?>"
												alt="Aperçu avatar"
												<?php echo $o['avatar'] ? '' : 'style="display:none"'; ?> />
											<div class="cn-avatar-inputs">
												<input type="url" id="chat_wp_avatar" name="chat_wp_avatar"
													value="<?php echo esc_attr( $o['avatar'] ); ?>"
													class="regular-text"
													placeholder="https://…" />
												<button type="button" id="cn-avatar-upload" class="button">
													Choisir depuis la médiathèque
												</button>
												<button type="button" id="cn-avatar-remove" class="button">
													Supprimer
												</button>
											</div>
										</div>
										<p class="description">Laissez vide pour afficher l'initiale du nom du bot.</p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="chat_wp_primary_color">Couleur principale</label></th>
									<td>
										<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
											<input type="color" id="chat_wp_primary_color" name="chat_wp_primary_color"
												value="<?php echo esc_attr( $o['color'] ); ?>" />
											<span id="cn-color-swatch"
												style="display:inline-block;width:28px;height:28px;border-radius:6px;border:1px solid #ddd;background:<?php echo esc_attr( $o['color'] ); ?>;vertical-align:middle;"></span>
											<code id="cn-color-code"><?php echo esc_html( $o['color'] ); ?></code>
										</div>
										<p class="description">Couleur de la bulle flottante et des messages utilisateur.</p>
									</td>
								</tr>
							</table>
						</div>

						<!-- API -->
						<div class="chatbot-section">
							<h2>Connexion API</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="chat_wp_api_url">URL de l'API</label></th>
									<td>
										<input type="url" id="chat_wp_api_url" name="chat_wp_api_url"
											value="<?php echo esc_attr( $o['api_url'] ); ?>"
											class="large-text"
											placeholder="<?php echo esc_attr( self::DEFAULT_API_URL ); ?>" />
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="chat_wp_client_id">Client ID</label></th>
									<td>
										<input type="text" id="chat_wp_client_id" name="chat_wp_client_id"
											value="<?php echo esc_attr( $o['client_id'] ); ?>"
											class="regular-text"
											placeholder="<?php echo esc_attr( self::DEFAULT_CLIENT_ID ); ?>" />
										<p class="description">Identifiant envoyé dans chaque requête à l'API.</p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="chat_wp_secret_key">Clé secrète</label></th>
									<td>
										<input type="password" id="chat_wp_secret_key" name="chat_wp_secret_key"
											value="<?php echo esc_attr( $this->opt( 'chat_wp_secret_key', '' ) ); ?>"
											class="regular-text"
											autocomplete="new-password" />
										<p class="description">Envoyée dans chaque requête via le header <code>X-Chat-Secret</code>. Laissez vide pour désactiver.</p>
									</td>
								</tr>
							</table>
						</div>

						<?php submit_button( 'Enregistrer les réglages' ); ?>
					</div><!-- /.chatbot-admin-main -->

					<!-- ── Sidebar ── -->
					<div class="chatbot-admin-sidebar">

						<div class="chatbot-test-box">
							<h3>Tester la connexion</h3>
							<p style="font-size:13px;color:#6b7280;margin-top:0;">
								Envoie une requête GET vers le endpoint <code>/health</code> de l'API configurée.
							</p>
							<button type="button" id="test-connection" class="button button-secondary">
								Tester la connexion
							</button>
							<div id="test-result"></div>
						</div>

						<div class="chatbot-info-box">
							<h3>Informations</h3>

							<h4>Format de requête</h4>
							<ul>
								<li>Méthode : <code>POST</code></li>
								<li>Body : <code>{ clientId, messages }</code></li>
								<li>Réponse attendue : <code>{ reply }</code></li>
							</ul>

							<h4>Endpoint de santé</h4>
							<ul>
								<li style="word-break:break-all;"><code><?php echo $health_url; ?></code></li>
							</ul>

							<h4>Version du plugin</h4>
							<ul>
								<li>Chat Nominomi 1.1.0</li>
							</ul>
						</div>

					</div><!-- /.chatbot-admin-sidebar -->

				</div><!-- /.chatbot-admin-container -->
			</form>
		</div>
		<?php
	}

	// ── CSS – front-end ───────────────────────────────────────────────────

	private function get_css( $color = self::DEFAULT_COLOR ) {
		$rgb = $this->hex_to_rgb( $color );
		return '
/*
 * Chat Nominomi – Glassmorphism stylesheet
 * Adapté depuis Chatbot N8N v2.1.7
 */
:root {
	--cn-glass-bg-primary:    rgba(30, 30, 45, 0.72);
	--cn-glass-bg-secondary:  rgba(30, 30, 45, 0.5);
	--cn-glass-hover:         rgba(50, 50, 70, 0.8);
	--cn-glass-border:        rgba(255, 255, 255, 0.1);
	--cn-glass-border-strong: rgba(255, 255, 255, 0.2);
	--cn-shadow-lg:           0 12px 40px rgba(0, 0, 0, 0.3);
	--cn-shadow-inner:        inset 0 1px 0 rgba(255, 255, 255, 0.05);
	--cn-blur-lg:             blur(16px);
	--cn-accent-rgb:          ' . esc_attr( $rgb ) . ';
	--cn-text-primary:        rgba(255, 255, 255, 0.95);
	--cn-text-secondary:      rgba(255, 255, 255, 0.75);
	--cn-text-muted:          rgba(255, 255, 255, 0.55);
	--cn-space-lg:            16px;
	--cn-space-md:            12px;
	--cn-space-sm:            8px;
	--cn-radius-2xl:          24px;
	--cn-radius-xl:           20px;
	--cn-radius-lg:           16px;
	--cn-radius-md:           12px;
	--cn-radius-sm:           8px;
	--cn-radius-full:         50%;
	--cn-transition-bounce:   500ms cubic-bezier(0.68, -0.55, 0.265, 1.55);
	--cn-transition-smooth:   300ms cubic-bezier(0.4, 0, 0.2, 1);
	--cn-font:                -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* ── Reset ── */
#cn-bubble,
#cn-window,
#cn-window * { box-sizing: border-box; font-family: var(--cn-font); }

/* ── Floating bubble ── */
#cn-bubble {
	position: fixed;
	bottom: 24px;
	right: 24px;
	z-index: 99998;
	width: 60px !important;
	height: 60px !important;
	border-radius: 50% !important;
	flex-shrink: 0 !important;
	background: var(--cn-glass-bg-primary);
	backdrop-filter: var(--cn-blur-lg);
	-webkit-backdrop-filter: var(--cn-blur-lg);
	border: 1px solid var(--cn-glass-border);
	color: var(--cn-text-primary);
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	box-shadow: var(--cn-shadow-lg), var(--cn-shadow-inner);
	transition: all var(--cn-transition-smooth);
	user-select: none;
}
#cn-bubble:hover { transform: translateY(-2px); background: var(--cn-glass-hover); }
#cn-bubble:focus-visible { outline: 3px solid rgba(var(--cn-accent-rgb), 0.6); outline-offset: 3px; }
#cn-bubble svg {
	width: 28px; height: 28px;
	color: var(--cn-text-primary);
	transition: all var(--cn-transition-smooth);
	position: absolute;
}
#cn-bubble.cn-open svg { opacity: 0; transform: rotate(-90deg) scale(0.8); }
#cn-bubble.cn-open::after {
	content: "\00d7";
	font-size: 30px;
	line-height: 1;
	font-weight: 300;
	color: var(--cn-text-primary);
}

/* ── Chat window ── */
#cn-window {
	position: fixed;
	bottom: 100px;
	right: 24px;
	z-index: 99999;
	width: 380px;
	height: 580px;
	max-width: calc(100vw - 40px);
	max-height: calc(100vh - 120px);
	background: var(--cn-glass-bg-primary);
	backdrop-filter: var(--cn-blur-lg);
	-webkit-backdrop-filter: var(--cn-blur-lg);
	border: 1px solid var(--cn-glass-border);
	border-radius: var(--cn-radius-2xl);
	box-shadow: var(--cn-shadow-lg), var(--cn-shadow-inner);
	display: flex;
	flex-direction: column;
	overflow: hidden;
	opacity: 0;
	visibility: hidden;
	transform: translateY(20px) scale(0.95);
	pointer-events: none;
	transition: all var(--cn-transition-bounce);
}
#cn-window.cn-visible {
	opacity: 1;
	visibility: visible;
	transform: translateY(0) scale(1);
	pointer-events: all;
}

/* ── Header ── */
#cn-header {
	padding: var(--cn-space-lg);
	display: flex;
	align-items: center;
	gap: var(--cn-space-md);
	border-bottom: 1px solid var(--cn-glass-border);
	background: rgba(0, 0, 0, 0.1);
	flex-shrink: 0;
}
#cn-header-info { display: flex; align-items: center; gap: var(--cn-space-md); flex: 1; }
#cn-avatar {
	width: 40px !important;
	height: 40px !important;
	border-radius: 50% !important;
	flex-shrink: 0 !important;
	background: linear-gradient(135deg, rgba(var(--cn-accent-rgb), 0.8), rgba(124, 58, 237, 0.8));
	color: var(--cn-text-primary);
	font-size: 16px;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
}
#cn-bot-name { margin: 0; font-size: 16px; font-weight: 600; color: var(--cn-text-primary); line-height: 1.2; }
#cn-bot-status { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--cn-text-secondary); margin-top: 2px; }
#cn-status-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; flex-shrink: 0; animation: cn-pulse 2.5s infinite; }
@keyframes cn-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .4; } }

/* Minimize button */
#cn-header #cn-minimize {
	flex-shrink: 0;
	width: 36px;
	height: 36px;
	border-radius: var(--cn-radius-full);
	margin-left: auto;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	background: var(--cn-glass-bg-primary) !important;
	border: 1px solid var(--cn-glass-border) !important;
	color: var(--cn-text-secondary) !important;
	box-shadow: none !important;
	line-height: 0;
	transition: all var(--cn-transition-smooth);
}
#cn-header #cn-minimize:hover { background: var(--cn-glass-hover) !important; color: var(--cn-text-primary) !important; }

/* ── Messages area ── */
#cn-messages {
	flex: 1;
	padding: var(--cn-space-lg);
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 10px;
	scroll-behavior: smooth;
	scrollbar-width: thin;
	scrollbar-color: var(--cn-glass-border-strong) transparent;
}
#cn-messages::-webkit-scrollbar { width: 6px; }
#cn-messages::-webkit-scrollbar-track { background: transparent; margin-block: var(--cn-space-sm); }
#cn-messages::-webkit-scrollbar-thumb {
	background-color: var(--cn-glass-border-strong);
	border-radius: 10px;
	border: 1px solid var(--cn-glass-border);
}
#cn-messages::-webkit-scrollbar-thumb:hover { background-color: var(--cn-glass-hover); }

/* ── Message bubbles ── */
.cn-msg {
	max-width: 82%;
	padding: var(--cn-space-md);
	border-radius: var(--cn-radius-lg);
	background: var(--cn-glass-bg-secondary);
	color: var(--cn-text-primary);
	font-size: 14px;
	line-height: 1.5;
	word-break: break-word;
	white-space: pre-wrap;
	animation: cn-slideIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.cn-msg-bot  { align-self: flex-start; border-bottom-left-radius:  var(--cn-radius-sm); }
.cn-msg-user {
	align-self: flex-end;
	background: rgba(var(--cn-accent-rgb), 0.3);
	border: 1px solid rgba(var(--cn-accent-rgb), 0.4);
	border-bottom-right-radius: var(--cn-radius-sm);
}
.cn-msg-error {
	align-self: flex-start;
	background: rgba(239, 68, 68, 0.12);
	border: 1px solid rgba(239, 68, 68, 0.25);
	color: #f87171;
	border-bottom-left-radius: var(--cn-radius-sm);
	font-size: 13px;
}

/* ── Typing indicator ── */
#cn-typing {
	padding: 0 var(--cn-space-lg) var(--cn-space-md);
	display: none;
	align-items: center;
	flex-shrink: 0;
}
#cn-typing.cn-show { display: flex !important; }
.cn-typing-bubble {
	display: inline-flex;
	gap: 4px;
	padding: 10px 12px;
	background: var(--cn-glass-bg-secondary);
	border-radius: var(--cn-radius-lg);
	border-bottom-left-radius: var(--cn-radius-sm);
}
.cn-typing-bubble span {
	width: 6px;
	height: 6px;
	background-color: var(--cn-text-secondary);
	border-radius: 50%;
	animation: cn-bounce-subtle 1.4s infinite ease-in-out;
}
.cn-typing-bubble span:nth-child(1) { animation-delay: 0s; }
.cn-typing-bubble span:nth-child(2) { animation-delay: .2s; }
.cn-typing-bubble span:nth-child(3) { animation-delay: .4s; }

/* ── Input form ── */
#cn-form {
	padding: var(--cn-space-lg);
	background: rgba(0, 0, 0, 0.2);
	border-top: 1px solid var(--cn-glass-border);
	display: flex;
	align-items: center;
	gap: var(--cn-space-md);
	flex-shrink: 0;
}
#cn-form #cn-input {
	flex-grow: 1;
	width: auto;
	height: 44px;
	padding: 0 16px;
	font-size: 14px;
	color: var(--cn-text-primary);
	background: var(--cn-glass-bg-secondary);
	border: 1px solid var(--cn-glass-border);
	border-radius: var(--cn-radius-xl);
	font-family: var(--cn-font);
	outline: none;
	box-shadow: none !important;
	transition: border-color var(--cn-transition-smooth);
	min-width: 0;
}
#cn-form #cn-input::placeholder { color: var(--cn-text-muted); }
#cn-form #cn-input:focus { border-color: var(--cn-glass-border-strong) !important; outline: none !important; box-shadow: none !important; }

/* Send button */
#cn-form #cn-send {
	flex-shrink: 0;
	width: 44px;
	height: 44px;
	border-radius: var(--cn-radius-full);
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	background: var(--cn-glass-bg-primary) !important;
	border: 1px solid var(--cn-glass-border) !important;
	color: var(--cn-text-primary) !important;
	box-shadow: none !important;
	transition: background-color var(--cn-transition-smooth);
}
#cn-form #cn-send:hover  { background: var(--cn-glass-hover) !important; }
#cn-form #cn-send:active { transform: scale(.93); }
#cn-form #cn-send:disabled { opacity: 0.4; cursor: default; }

/* Ultra-specific SVG rule */
div#cn-window button#cn-send svg,
div#cn-window button#cn-minimize svg {
	width: 20px !important;
	height: 20px !important;
	flex-shrink: 0 !important;
	stroke: currentColor;
}

/* ── Animations ── */
@keyframes cn-slideIn    { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes cn-bounce-subtle { 0%, 80%, 100% { transform: scale(0.8); opacity: 0.4; } 40% { transform: scale(1); opacity: 1; } }

/* ── Mobile ── */
@media (max-width: 480px) {
	#cn-window {
		right: 0; bottom: 0;
		width: 100vw; max-width: 100vw;
		height: 100dvh; max-height: 100dvh;
		border-radius: 0;
	}
	#cn-bubble { bottom: 20px; right: 20px; }
}
		';
	}

	// ── CSS – admin ───────────────────────────────────────────────────────

	private function get_admin_css() {
		return '
/* Styles pour l\'interface d\'administration */
.chatbot-admin-container {
	display: grid;
	grid-template-columns: 1fr 300px;
	gap: 20px;
	margin-top: 20px;
}
.chatbot-admin-main {
	background: #fff;
	padding: 20px;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.chatbot-admin-sidebar {
	display: flex;
	flex-direction: column;
	gap: 20px;
}
.chatbot-section {
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid #e5e7eb;
}
.chatbot-section:last-child { border-bottom: none; margin-bottom: 0; }
.chatbot-section h2 { color: #1f2937; font-size: 20px; margin-bottom: 15px; font-weight: 600; }

/* Toggle Switch */
.chatbot-toggle { position: relative; display: inline-block; width: 50px; height: 24px; }
.chatbot-toggle input { opacity: 0; width: 0; height: 0; }
.chatbot-toggle-slider {
	position: absolute;
	cursor: pointer;
	top: 0; left: 0; right: 0; bottom: 0;
	background-color: #ccc;
	transition: 0.3s;
	border-radius: 24px;
}
.chatbot-toggle-slider:before {
	position: absolute;
	content: "";
	height: 18px;
	width: 18px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: 0.3s;
	border-radius: 50%;
}
.chatbot-toggle input:checked + .chatbot-toggle-slider { background-color: #3B82F6; }
.chatbot-toggle input:checked + .chatbot-toggle-slider:before { transform: translateX(26px); }

/* Info boxes dans la sidebar */
.chatbot-info-box,
.chatbot-test-box {
	background: #fff;
	padding: 20px;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.chatbot-info-box h3,
.chatbot-test-box h3 { margin-top: 0; color: #1f2937; font-size: 16px; font-weight: 600; }
.chatbot-info-box h4 { color: #374151; font-size: 14px; font-weight: 600; margin: 15px 0 8px 0; }
.chatbot-info-box ul { margin: 0; padding-left: 20px; }
.chatbot-info-box li { margin-bottom: 5px; color: #6b7280; font-size: 14px; }

/* Avatar row */
.cn-avatar-row { display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; }
.cn-avatar-row #cn-avatar-preview {
	width: 56px;
	height: 56px;
	border-radius: 50%;
	object-fit: cover;
	border: 2px solid #e5e7eb;
	flex-shrink: 0;
}
.cn-avatar-inputs { display: flex; flex-direction: column; gap: 6px; }
.cn-avatar-inputs input { width: 320px; max-width: 100%; }

/* Test de connexion */
#test-connection { width: 100%; margin-bottom: 15px; }
#test-result { padding: 10px; border-radius: 6px; font-size: 14px; display: none; margin-top: 10px; }
#test-result.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
#test-result.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

/* Responsive pour l\'admin */
@media (max-width: 782px) {
	.chatbot-admin-container { grid-template-columns: 1fr; }
}

/* Amélioration des champs de formulaire */
.form-table input[type="text"],
.form-table input[type="url"],
.form-table textarea {
	border: 1px solid #e5e7eb;
	border-radius: 6px;
	padding: 8px 12px;
	transition: border-color 0.2s ease;
}
.form-table input[type="text"]:focus,
.form-table input[type="url"]:focus,
.form-table textarea:focus {
	border-color: #3B82F6;
	box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
	outline: none;
}
		';
	}

	// ── JS – admin ────────────────────────────────────────────────────────

	private function get_admin_js() {
		return '
(function($) {
	$(function() {

		/* ── Media uploader (avatar) ── */
		var mediaFrame;
		$("#cn-avatar-upload").on("click", function(e) {
			e.preventDefault();
			if (mediaFrame) { mediaFrame.open(); return; }
			mediaFrame = wp.media({
				title:    "Choisir un avatar",
				button:   { text: "Utiliser cette image" },
				multiple: false,
				library:  { type: "image" }
			});
			mediaFrame.on("select", function() {
				var attachment = mediaFrame.state().get("selection").first().toJSON();
				$("#chat_wp_avatar").val(attachment.url);
				$("#cn-avatar-preview").attr("src", attachment.url).show();
			});
			mediaFrame.open();
		});

		$("#cn-avatar-remove").on("click", function(e) {
			e.preventDefault();
			$("#chat_wp_avatar").val("");
			$("#cn-avatar-preview").attr("src", "").hide();
		});

		/* ── Color preview ── */
		$("#chat_wp_primary_color").on("input", function() {
			var color = $(this).val();
			$("#cn-color-swatch").css("background", color);
			$("#cn-color-code").text(color);
		});

		/* ── Test connection ── */
		$("#test-connection").on("click", function() {
			var apiUrl = $("#chat_wp_api_url").val().trim();
			var $result = $("#test-result");

			if (!apiUrl) {
				$result.removeClass("success").addClass("error")
					.text("Veuillez d\'abord saisir une URL d\'API.")
					.show();
				return;
			}

			var parsed    = new URL(apiUrl);
			var healthUrl = parsed.origin + "/health";
			$result.removeClass("success error").text("Test en cours…").show();

			fetch(healthUrl, { method: "GET" })
				.then(function(r) {
					if (r.ok) {
						$result.removeClass("error").addClass("success")
							.text("Connexion réussie ! L\'API répond correctement (HTTP " + r.status + ").");
					} else {
						$result.removeClass("success").addClass("error")
							.text("L\'API a répondu avec le code HTTP " + r.status + ".");
					}
				})
				.catch(function() {
					$result.removeClass("success").addClass("error")
						.text("Impossible de joindre l\'API. Vérifiez l\'URL et les autorisations CORS.");
				});
		});

	});
})(jQuery);
		';
	}
}

Chat_Nominomi::get_instance();
