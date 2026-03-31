/* Chat Nominomi – widget logic */
(function () {
	'use strict';

	var config   = (typeof chatConfig !== 'undefined') ? chatConfig : {};
	var API_URL  = config.apiUrl   || 'https://chat.nominomi.fr/chat';
	var CLIENT_ID = config.clientId || 'nominomi';

	/* ── State ── */
	var history  = [];   // [{ role: 'user'|'assistant', content: '' }]
	var isOpen   = false;
	var isBusy   = false;

	/* ── DOM refs ── */
	var bubble   = document.getElementById('cn-bubble');
	var win      = document.getElementById('cn-window');
	var messages = document.getElementById('cn-messages');
	var typing   = document.getElementById('cn-typing');
	var form     = document.getElementById('cn-form');
	var input    = document.getElementById('cn-input');
	var sendBtn  = document.getElementById('cn-send');
	var minimize = document.getElementById('cn-minimize');

	if (!bubble || !win) return;

	/* ── Open / Close ── */
	function openChat() {
		isOpen = true;
		win.classList.add('cn-visible');
		win.setAttribute('aria-hidden', 'false');
		bubble.classList.add('cn-open');
		bubble.setAttribute('aria-label', 'Fermer le chat');
		input.focus();

		if (messages.children.length === 0) {
			var welcome = (config.welcomeMessage || '').trim();
			if (welcome) appendMessage('bot', welcome);
		}
	}

	function closeChat() {
		isOpen = false;
		win.classList.remove('cn-visible');
		win.setAttribute('aria-hidden', 'true');
		bubble.classList.remove('cn-open');
		bubble.setAttribute('aria-label', 'Ouvrir le chat');
	}

	function toggleChat() {
		if (isOpen) closeChat(); else openChat();
	}

	bubble.addEventListener('click', toggleChat);
	bubble.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleChat(); }
	});
	minimize.addEventListener('click', closeChat);

	/* Keyboard: Escape closes */
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && isOpen) closeChat();
	});

	/* ── Message rendering ── */
	function appendMessage(role, text) {
		var div = document.createElement('div');
		div.className = 'cn-msg cn-msg-' + role;
		div.textContent = text;
		messages.appendChild(div);
		scrollBottom();
		return div;
	}

	function appendError(text) {
		var div = document.createElement('div');
		div.className = 'cn-msg cn-msg-error';
		div.textContent = text;
		messages.appendChild(div);
		scrollBottom();
	}

	function scrollBottom() {
		messages.scrollTop = messages.scrollHeight;
	}

	/* ── Typing indicator ── */
	function showTyping() {
		typing.classList.add('cn-show');
		scrollBottom();
	}

	function hideTyping() {
		typing.classList.remove('cn-show');
	}

	/* ── Send / Receive ── */
	function setInputState(busy) {
		isBusy = busy;
		input.disabled  = busy;
		sendBtn.disabled = busy;
	}

	async function sendMessage(text) {
		if (!text.trim() || isBusy) return;

		/* Push user message to history and UI */
		history.push({ role: 'user', content: text });
		appendMessage('user', text);

		setInputState(true);
		showTyping();

		try {
			var payload = {
				clientId: CLIENT_ID,
				messages: history.slice()   /* full conversation history */
			};

			payload._secret = config.secretKey || '';

			var response = await fetch(API_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			});

			hideTyping();

			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}

			var data = await response.json();

			/* Support both { reply } and { message } / { content } shapes */
			var reply = data.reply || data.message || data.content || '';

			if (!reply) throw new Error('Réponse vide du serveur.');

			history.push({ role: 'assistant', content: reply });
			appendMessage('bot', reply);

		} catch (err) {
			hideTyping();
			appendError('Désolé, une erreur est survenue. Veuillez réessayer.');
			console.error('[Chat Nominomi]', err);
			/* Remove last user message from history so it can be retried */
			if (history.length && history[history.length - 1].role === 'user') {
				history.pop();
			}
		} finally {
			setInputState(false);
			input.focus();
		}
	}

	/* ── Form submit ── */
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var text = input.value.trim();
		if (!text) return;
		input.value = '';
		sendMessage(text);
	});

	/* ── Send button – defensive preventDefault (certains thèmes déclenchent le submit natif) ── */
	sendBtn.addEventListener('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var text = input.value.trim();
		if (!text) return;
		input.value = '';
		sendMessage(text);
	});

	/* ── Enter key (no shift) ── */
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			e.stopPropagation();
			var text = input.value.trim();
			if (!text) return;
			input.value = '';
			sendMessage(text);
		}
	});

})();
