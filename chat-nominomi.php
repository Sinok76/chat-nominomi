<?php
/**
 * Plugin Name: Chat Nominomi
 * Plugin URI:  https://nominomi.fr
 * Description: Widget chat flottant alimenté par l'IA Stella.
 * Version:     1.0.0
 * Author:      Nominomi
 * License:     GPL-2.0-or-later
 * Text Domain: chat-nominomi
 */

defined( 'ABSPATH' ) || exit;

class Chat_Nominomi {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer',          [ $this, 'render_widget' ] );
	}

	public function enqueue_assets() {
		wp_enqueue_script(
			'chat-nominomi',
			plugin_dir_url( __FILE__ ) . 'chat.js',
			[],
			'1.0.0',
			true
		);

		wp_localize_script( 'chat-nominomi', 'chatConfig', [
			'apiUrl'   => 'https://chat.nominomi.fr/chat',
			'clientId' => 'nominomi',
		] );

		wp_register_style( 'chat-nominomi-base', false );
		wp_enqueue_style( 'chat-nominomi-base' );
		wp_add_inline_style( 'chat-nominomi-base', $this->get_css() );
	}

	public function render_widget() {
		?>
		<div id="cn-bubble" role="button" aria-label="Ouvrir le chat" tabindex="0">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26" aria-hidden="true">
				<path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Z"/>
			</svg>
		</div>

		<div id="cn-window" role="dialog" aria-label="Chat Stella" aria-hidden="true">
			<div id="cn-header">
				<div id="cn-header-info">
					<div id="cn-avatar" aria-hidden="true">S</div>
					<div>
						<div id="cn-bot-name">Stella</div>
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

			<div id="cn-typing" aria-live="polite" aria-label="Stella est en train d'écrire">
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

	private function get_css() {
		return '
/* ── Chat Nominomi – widget styles ── */
#cn-bubble,
#cn-window,
#cn-window * {
	box-sizing: border-box;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Floating bubble */
#cn-bubble {
	position: fixed;
	bottom: 28px;
	right: 28px;
	z-index: 99998;
	width: 58px;
	height: 58px;
	border-radius: 50%;
	background: #4f46e5;
	color: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	box-shadow: 0 8px 24px rgba(79,70,229,.55);
	transition: transform .2s ease, box-shadow .2s ease;
	user-select: none;
}
#cn-bubble:hover {
	transform: scale(1.08);
	box-shadow: 0 12px 32px rgba(79,70,229,.7);
}
#cn-bubble:focus-visible {
	outline: 3px solid #818cf8;
	outline-offset: 3px;
}
#cn-bubble.cn-open svg {
	display: none;
}
#cn-bubble.cn-open::after {
	content: "×";
	font-size: 28px;
	line-height: 1;
	font-weight: 300;
}

/* Chat window */
#cn-window {
	position: fixed;
	bottom: 100px;
	right: 28px;
	z-index: 99999;
	width: 360px;
	max-width: calc(100vw - 40px);
	height: 520px;
	max-height: calc(100vh - 120px);
	background: #0f1117;
	border-radius: 16px;
	display: flex;
	flex-direction: column;
	box-shadow: 0 20px 60px rgba(0,0,0,.6);
	overflow: hidden;
	transform: translateY(20px) scale(.96);
	opacity: 0;
	pointer-events: none;
	transition: transform .25s cubic-bezier(.34,1.56,.64,1), opacity .2s ease;
}
#cn-window.cn-visible {
	transform: translateY(0) scale(1);
	opacity: 1;
	pointer-events: all;
}

/* Header */
#cn-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14px 16px;
	background: linear-gradient(135deg, #1a1d2e 0%, #12151f 100%);
	border-bottom: 1px solid rgba(255,255,255,.07);
	flex-shrink: 0;
}
#cn-header-info {
	display: flex;
	align-items: center;
	gap: 12px;
}
#cn-avatar {
	width: 38px;
	height: 38px;
	border-radius: 50%;
	background: linear-gradient(135deg, #4f46e5, #7c3aed);
	color: #fff;
	font-size: 16px;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
#cn-bot-name {
	color: #f1f5f9;
	font-size: 15px;
	font-weight: 600;
	line-height: 1.2;
}
#cn-bot-status {
	display: flex;
	align-items: center;
	gap: 5px;
	color: #94a3b8;
	font-size: 11.5px;
	margin-top: 2px;
}
#cn-status-dot {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: #22c55e;
	flex-shrink: 0;
	animation: cn-pulse 2.5s infinite;
}
@keyframes cn-pulse {
	0%, 100% { opacity: 1; }
	50%       { opacity: .4; }
}
#cn-minimize {
	background: none;
	border: none;
	color: #64748b;
	cursor: pointer;
	padding: 6px;
	border-radius: 8px;
	line-height: 0;
	transition: background .15s, color .15s;
}
#cn-minimize:hover {
	background: rgba(255,255,255,.07);
	color: #cbd5e1;
}

/* Messages area */
#cn-messages {
	flex: 1;
	overflow-y: auto;
	padding: 16px 14px 8px;
	display: flex;
	flex-direction: column;
	gap: 10px;
	scroll-behavior: smooth;
}
#cn-messages::-webkit-scrollbar { width: 4px; }
#cn-messages::-webkit-scrollbar-track { background: transparent; }
#cn-messages::-webkit-scrollbar-thumb {
	background: rgba(255,255,255,.12);
	border-radius: 4px;
}

/* Message bubbles */
.cn-msg {
	max-width: 82%;
	padding: 10px 14px;
	border-radius: 14px;
	font-size: 14px;
	line-height: 1.55;
	word-break: break-word;
	white-space: pre-wrap;
}
.cn-msg-user {
	align-self: flex-end;
	background: #4f46e5;
	color: #fff;
	border-bottom-right-radius: 4px;
}
.cn-msg-bot {
	align-self: flex-start;
	background: #1e2535;
	color: #e2e8f0;
	border-bottom-left-radius: 4px;
}
.cn-msg-error {
	align-self: flex-start;
	background: #2d1b1b;
	color: #f87171;
	border-bottom-left-radius: 4px;
	font-size: 13px;
}

/* Typing indicator */
#cn-typing {
	padding: 4px 14px 6px;
	display: none;
	flex-shrink: 0;
}
#cn-typing.cn-show { display: block; }
.cn-typing-bubble {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	background: #1e2535;
	padding: 10px 14px;
	border-radius: 14px;
	border-bottom-left-radius: 4px;
}
.cn-typing-bubble span {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: #64748b;
	animation: cn-bounce .9s infinite ease-in-out;
}
.cn-typing-bubble span:nth-child(1) { animation-delay: 0s; }
.cn-typing-bubble span:nth-child(2) { animation-delay: .15s; }
.cn-typing-bubble span:nth-child(3) { animation-delay: .3s; }
@keyframes cn-bounce {
	0%, 60%, 100% { transform: translateY(0); }
	30%            { transform: translateY(-6px); }
}

/* Input bar */
#cn-form {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 12px 14px;
	background: #12151f;
	border-top: 1px solid rgba(255,255,255,.07);
	flex-shrink: 0;
}
#cn-input {
	flex: 1;
	background: #1e2535;
	border: 1px solid rgba(255,255,255,.08);
	border-radius: 10px;
	color: #e2e8f0;
	font-size: 14px;
	padding: 10px 14px;
	outline: none;
	transition: border-color .2s;
	min-width: 0;
}
#cn-input::placeholder { color: #475569; }
#cn-input:focus { border-color: #4f46e5; }
#cn-send {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	background: #4f46e5;
	border: none;
	color: #fff;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	transition: background .15s, transform .1s;
}
#cn-send:hover  { background: #4338ca; }
#cn-send:active { transform: scale(.93); }
#cn-send:disabled { background: #2d2f45; cursor: default; }

/* Mobile */
@media (max-width: 480px) {
	#cn-window {
		right: 0;
		bottom: 0;
		width: 100vw;
		max-width: 100vw;
		height: 100dvh;
		max-height: 100dvh;
		border-radius: 0;
	}
	#cn-bubble { bottom: 20px; right: 20px; }
}
		';
	}
}

Chat_Nominomi::get_instance();
