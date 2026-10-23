(function () {
	'use strict';
	var config = window.CONTACTPANDA_CAMPAIGNS || {};
	setupStatusPolling();
	var form = document.getElementById('contactpanda-campaign-form');
	if (!form) return;
	var editorModal = document.getElementById('contactpanda-mailing-editor-modal');
	if (editorModal && !editorModal.hidden) document.body.classList.add('contactpanda-modal-open');
	document.querySelectorAll('[data-contactpanda-mailing-open]').forEach(function (button) { button.addEventListener('click', function () { editorModal.hidden = false; document.body.classList.add('contactpanda-modal-open'); }); });
	document.querySelectorAll('.contactpanda-close-mailing-modal').forEach(function (button) { button.addEventListener('click', function () { editorModal.hidden = true; document.body.classList.remove('contactpanda-modal-open'); }); });
	var scheduled = form.querySelector('[name="scheduled_at"]');
	form.querySelectorAll('.contactpanda-searchable-select').forEach(function (wrapper) {
		var search = wrapper.querySelector('.contactpanda-select-search'), select = wrapper.querySelector('select');
		if (!search || !select) return;
		wrapper.classList.add('is-enhanced');
		var toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'contactpanda-searchable-toggle'; toggle.setAttribute('aria-expanded', 'false');
		var toggleText = document.createElement('span'); var toggleIcon = document.createElement('span'); toggleIcon.className = 'dashicons dashicons-arrow-down-alt2'; toggle.appendChild(toggleText); toggle.appendChild(toggleIcon);
		var panel = document.createElement('div'); panel.className = 'contactpanda-searchable-panel'; panel.hidden = true;
		var options = document.createElement('div'); options.className = 'contactpanda-searchable-options'; panel.appendChild(search); panel.appendChild(options); select.before(toggle); select.before(panel);
		var selected = select.options[select.selectedIndex] || select.options[0];
		function updateToggle() { toggleText.textContent = selected ? selected.text : '—'; }
		function renderOptions() {
			var query = search.value.trim().toLocaleLowerCase(), found = 0; options.replaceChildren();
			Array.prototype.forEach.call(select.options, function (option) {
				if (query && option.text.toLocaleLowerCase().indexOf(query) === -1 && option.value !== select.value) return;
				var button = document.createElement('button'); button.type = 'button'; button.className = 'contactpanda-searchable-option' + (option.value === select.value ? ' is-selected' : ''); button.textContent = option.text; button.dataset.value = option.value;
				button.addEventListener('click', function () { select.value = button.dataset.value; selected = option; updateToggle(); search.value = ''; panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }); options.appendChild(button); found += 1;
			});
			if (!found) { var empty = document.createElement('span'); empty.className = 'contactpanda-option-picker-empty'; empty.textContent = config.noOptions || 'No matching entries'; options.appendChild(empty); }
		}
		updateToggle();
		toggle.addEventListener('click', function () { panel.hidden = !panel.hidden; toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true'); if (!panel.hidden) { search.value = ''; renderOptions(); search.focus(); } });
		search.addEventListener('input', renderOptions);
		search.addEventListener('keydown', function (event) { if (event.key === 'Escape') { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); } });
	});
	document.addEventListener('click', function (event) { form.querySelectorAll('.contactpanda-searchable-select.is-enhanced').forEach(function (wrapper) { if (wrapper.contains(event.target)) return; var panel = wrapper.querySelector('.contactpanda-searchable-panel'), toggle = wrapper.querySelector('.contactpanda-searchable-toggle'); panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }); });
	form.querySelectorAll('[name="send_timing"]').forEach(function (radio) {
		radio.addEventListener('change', function () { var later = radio.value === 'later' && radio.checked; if (later) { scheduled.hidden = false; scheduled.disabled = false; scheduled.required = true; } else if (radio.checked) { scheduled.hidden = true; scheduled.disabled = true; scheduled.required = false; } });
	});
	var customerPanel = form.querySelector('.contactpanda-customer-recipient-search');
	var consentOverride = form.querySelector('.contactpanda-consent-override');
	var customerSearch = customerPanel ? customerPanel.querySelector('[name="recipient_search"]') : null;
	var customerResults = customerPanel ? customerPanel.querySelector('.contactpanda-customer-search-results') : null;
	var customerTimer = null;
	function updateRecipientSource() {
		var selected = form.querySelector('[name="audience_source"]:checked');
		if (!customerPanel) return;
		customerPanel.hidden = !selected || selected.value !== 'registered_customers';
		if (consentOverride) { consentOverride.hidden = customerPanel.hidden; consentOverride.querySelectorAll('input,select').forEach(function (field) { field.disabled = customerPanel.hidden; }); }
		if (!customerPanel.hidden) searchCustomers();
	}
	function searchCustomers() {
		if (!customerPanel || customerPanel.hidden || !customerResults) return;
		customerResults.textContent = config.customerSearchLoading || 'Loading…';
		var data = new FormData(); data.set('action', 'contactpanda_campaign_customer_search'); data.set('nonce', config.nonce || ''); data.set('query', customerSearch ? customerSearch.value : '');
		fetch(config.ajaxUrl, {method:'POST', credentials:'same-origin', body:data}).then(function (response) { return response.json(); }).then(function (result) {
			if (!result.success) throw new Error(result.data && result.data.message || 'Search failed');
			customerResults.replaceChildren();
			var summary = document.createElement('p'); summary.className = 'contactpanda-customer-search-summary'; summary.textContent = (config.customerSearchSummary || '%1$d registered customers found; %2$d without documented marketing consent.').replace('%1$d', result.data.total).replace('%2$d', result.data.withoutConsent); customerResults.appendChild(summary);
			if (!result.data.items.length) { var empty = document.createElement('p'); empty.textContent = config.noCustomers || 'No matching registered customers found.'; customerResults.appendChild(empty); return; }
			var list = document.createElement('ul'); list.className = 'contactpanda-customer-recipient-list'; result.data.items.forEach(function (item) { var row = document.createElement('li'); var identity = document.createElement('span'); identity.textContent = item.name + ' · ' + item.email; var state = document.createElement('span'); state.className = item.marketable ? 'is-marketable' : 'is-without-consent'; state.textContent = item.marketable ? (config.marketingConsent || 'Marketing consent') : (config.withoutMarketingConsent || 'No documented marketing consent'); row.appendChild(identity); row.appendChild(state); list.appendChild(row); }); customerResults.appendChild(list);
			if (result.data.truncated) { var truncated = document.createElement('p'); truncated.className = 'description'; truncated.textContent = config.customerSearchTruncated || 'Only the first 200 matches are shown.'; customerResults.appendChild(truncated); }
		}).catch(function (error) { customerResults.textContent = error.message; });
	}
	form.querySelectorAll('[name="audience_source"]').forEach(function (radio) { radio.addEventListener('change', updateRecipientSource); });
	if (customerSearch) customerSearch.addEventListener('input', function () { window.clearTimeout(customerTimer); customerTimer = window.setTimeout(searchCustomers, 250); });
	updateRecipientSource();
	function request(action, email) {
		var data = new FormData(); data.set('action', action); data.set('nonce', config.nonce || ''); data.set('template_id', form.elements.template_id.value); data.set('subject', form.elements.subject.value); data.set('from_name', form.elements.from_name.value); data.set('from_email', form.elements.from_email.value); data.set('reply_to', form.elements.reply_to.value); if (email) data.set('email', email);
		return fetch(config.ajaxUrl, {method:'POST', credentials:'same-origin', body:data}).then(function (response) { return response.json(); });
	}
	var modal = document.getElementById('contactpanda-campaign-preview-modal'); var frame = document.getElementById('contactpanda-campaign-preview-frame'); var message = document.getElementById('contactpanda-campaign-message');
	document.getElementById('contactpanda-campaign-preview').addEventListener('click', function () { request('contactpanda_campaign_preview').then(function (result) { if (!result.success) throw new Error(result.data && result.data.message || 'Preview failed'); frame.srcdoc = result.data.html; modal.hidden = false; }).catch(function (error) { message.textContent = error.message; }); });
	document.getElementById('contactpanda-close-campaign-preview').addEventListener('click', function () { modal.hidden = true; });
	document.querySelectorAll('[data-campaign-preview-width]').forEach(function (button) { button.addEventListener('click', function () { document.querySelectorAll('[data-campaign-preview-width]').forEach(function (item) { item.classList.remove('is-active'); }); button.classList.add('is-active'); frame.style.width = button.dataset.campaignPreviewWidth; }); });
	document.getElementById('contactpanda-campaign-test').addEventListener('click', function () { message.textContent = ''; request('contactpanda_campaign_test', document.getElementById('contactpanda-campaign-test-email').value).then(function (result) { if (!result.success) throw new Error(result.data && result.data.message || 'Test failed'); message.textContent = result.data.message; }).catch(function (error) { message.textContent = error.message; }); });

	function setupStatusPolling() {
		var delay = Math.max(10000, Number(config.pollInterval) || 12000);
		function activeRows() {
			return Array.prototype.filter.call(document.querySelectorAll('[data-contactpanda-campaign-id]'), function (row) {
				return row.dataset.contactpandaCampaignStatus === 'queued' || row.dataset.contactpandaCampaignStatus === 'sending';
			});
		}
		function renderStatus(row, item) {
			row.dataset.contactpandaCampaignStatus = item.status;
			var summary = row.querySelector('.contactpanda-recipient-summary');
			var delivered = row.querySelector('[data-contactpanda-mailing-delivered]');
			var opened = row.querySelector('[data-contactpanda-mailing-opened]');
			var clicked = row.querySelector('[data-contactpanda-mailing-clicked]');
			var progress = row.querySelector('.contactpanda-mailing-progress');
			if (summary) summary.textContent = item.recipientSummary;
			if (delivered) delivered.textContent = item.delivered;
			if (opened) opened.textContent = item.opened;
			if (clicked) clicked.textContent = item.clicked;
			if (progress) {
				progress.setAttribute('aria-label', item.progressLabel);
				var bar = progress.querySelector('i'); var label = progress.querySelector('small');
				if (bar) bar.style.width = item.progress + '%';
				if (label) label.textContent = item.progress + '%';
			}
			if (item.terminal) {
				var actions = row.querySelector('.contactpanda-mailing-actions');
				if (!actions) return;
				var badge = document.createElement('span');
				badge.className = 'button contactpanda-mailing-state ' + (item.status === 'completed' ? 'contactpanda-mailing-completed' : 'contactpanda-mailing-cancelled');
				badge.textContent = item.statusLabel;
				actions.replaceChildren(badge);
			}
		}
		function poll() {
			var rows = activeRows();
			if (!rows.length) return;
			var data = new FormData();
			data.set('action', 'contactpanda_campaign_statuses');
			data.set('nonce', config.nonce || '');
			rows.forEach(function (row) { data.append('ids[]', row.dataset.contactpandaCampaignId); });
			fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function (response) { return response.json(); })
				.then(function (result) {
					if (!result.success || !result.data || !result.data.statuses) throw new Error('Status update failed');
					rows.forEach(function (row) { var item = result.data.statuses[row.dataset.contactpandaCampaignId]; if (item) renderStatus(row, item); });
					if (activeRows().length) window.setTimeout(poll, delay);
				})
				.catch(function () { window.setTimeout(poll, Math.max(delay * 2, 30000)); });
		}
		if (activeRows().length) window.setTimeout(poll, delay);
	}
}());
