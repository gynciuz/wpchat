/**
 * ChatAdmin → Diagnostics: copy the diagnostics blob to the clipboard and
 * POST a report to the support REST route. Config comes from
 * wp_localize_script as `chatadminDiagnostics`.
 */
(function () {
	var cfg = window.chatadminDiagnostics;
	var status = document.getElementById('chatadmin-diag-status');
	var copy = document.getElementById('chatadmin-diag-copy');
	var send = document.getElementById('chatadmin-diag-send');
	if (!cfg || !status || !copy || !send) {
		return;
	}

	copy.addEventListener('click', function () {
		navigator.clipboard
			.writeText(document.getElementById('chatadmin-diag-json').textContent)
			.then(function () {
				status.textContent = cfg.copied;
			});
	});

	send.addEventListener('click', function () {
		status.textContent = '…';
		fetch(cfg.rest, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			credentials: 'same-origin',
			body: JSON.stringify({
				note: document.getElementById('chatadmin-diag-note').value,
				error: 'admin-diagnostics'
			})
		})
			.then(function (r) {
				return r.json()
					.catch(function () { return {}; })
					.then(function (d) {
						status.textContent = r.ok && d.ok ? cfg.sentOk : cfg.sentFail;
					});
			})
			.catch(function () {
				status.textContent = cfg.sentFail;
			});
	});
})();
