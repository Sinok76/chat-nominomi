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
	--cn-accent-rgb:          99, 102, 241;
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
	width: 60px;
	height: 60px;
	border-radius: var(--cn-radius-full);
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
	content: "×";
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
	width: 40px;
	height: 40px;
	border-radius: var(--cn-radius-full);
	background: linear-gradient(135deg, rgba(var(--cn-accent-rgb), 0.8), rgba(124, 58, 237, 0.8));
	color: var(--cn-text-primary);
	font-size: 16px;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
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
}

Chat_Nominomi::get_instance();
