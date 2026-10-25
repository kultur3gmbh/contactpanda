(function () {
	'use strict';

	var config = window.CONTACTPANDA_ADMIN_UI || {};
	var i18n = config.i18n || {};
	var actionIcons = [
		[/^(view|preview|details|show|anzeigen|ansehen|vorschau|details anzeigen|view \/ edit|ansehen \/ bearbeiten)$/i, 'visibility'],
		[/^(edit|bearbeiten|ändern)$/i, 'edit'],
		[/^(delete|remove|trash|löschen|entfernen|in den papierkorb|in papierkorb verschieben)$/i, 'trash'],
		[/^(duplicate|copy|duplizieren|kopieren)$/i, 'admin-page'],
		[/^(download|herunterladen)$/i, 'download'],
		[/^(restore|wiederherstellen)$/i, 'undo'],
		[/^(view records|datensätze anzeigen|show records)$/i, 'list-view'],
		[/^(rollback import|import zurückrollen|import rückgängig machen)$/i, 'undo'],
		[/^(allow address again|adresse wieder zulassen)$/i, 'unlock'],
		[/^(open review|prüfung öffnen|kontrolle öffnen)$/i, 'search'],
		[/^(activate|approve|aktivieren|genehmigen)$/i, 'yes-alt'],
		[/^(deactivate|disable|deaktivieren|sperren)$/i, 'hidden'],
		[/^(send|resend|versenden|erneut senden)$/i, 'email-alt'],
		[/^(archive|archivieren)$/i, 'archive'],
		[/^(review|inspect|prüfen|kontrollieren)$/i, 'search'],
		[/^(open|öffnen)$/i, 'external']
	];

	function findAll(root, selector) {
		root = root || document;
		var matches = [];
		if (root.nodeType === 1 && root.matches(selector)) matches.push(root);
		return matches.concat(Array.prototype.slice.call(root.querySelectorAll(selector)));
	}

	function uniqueColors(values) {
		var found = [];
		(values || []).forEach(function (value) {
			value = String(value || '').trim();
			if (/^#[0-9a-f]{6}$/i.test(value) && found.indexOf(value.toLowerCase()) < 0) found.push(value.toLowerCase());
		});
		return found;
	}

	function colorPalette(input, field) {
		var colors = (field.dataset.colors || '').split(',').concat(config.themeColors || []);
		document.querySelectorAll('.contactpanda-theme-color-palette [data-color]').forEach(function (swatch) { colors.push(swatch.dataset.color); });
		colors = uniqueColors(colors);
		if (!colors.length) return;
		var palette = field.querySelector('.contactpanda-theme-color-palette');
		if (!palette) {
			palette = document.createElement('span');
			palette.className = 'contactpanda-theme-color-palette';
			field.appendChild(palette);
		}
		var existing = Array.prototype.map.call(palette.querySelectorAll('[data-color]'), function (node) { return String(node.dataset.color || '').toLowerCase(); });
		colors.forEach(function (color) {
			if (existing.indexOf(color) >= 0) return;
			var swatch = document.createElement('button');
			swatch.type = 'button';
			swatch.className = 'contactpanda-settings-color-swatch';
			swatch.dataset.color = color;
			swatch.title = color;
			swatch.setAttribute('aria-label', color);
			swatch.style.backgroundColor = color;
			swatch.addEventListener('click', function () {
				input.value = color;
				input.dispatchEvent(new Event('input', {bubbles: true}));
				input.dispatchEvent(new Event('change', {bubbles: true}));
			});
			palette.appendChild(swatch);
		});
	}

	function enhanceColorPicker(input) {
		if (!input || input.dataset.contactpandaColorReady === '1') return;
		input.dataset.contactpandaColorReady = '1';
		var field = input.closest('.contactpanda-settings-color-field, .contactpanda-color-field');
		if (!field) {
			if (input.parentElement && input.parentElement.tagName === 'LABEL') {
				field = input.parentElement;
				field.classList.add('contactpanda-settings-color-field', 'contactpanda-color-field');
			} else {
				field = document.createElement('span');
				field.className = 'contactpanda-settings-color-field contactpanda-color-field';
				input.parentNode.insertBefore(field, input);
				field.appendChild(input);
			}
		}
		var control = input.closest('.contactpanda-settings-color-control');
		if (!control) {
			control = document.createElement('span');
			control.className = 'contactpanda-settings-color-control';
			input.parentNode.insertBefore(control, input);
			control.appendChild(input);
		}
		var value = control.querySelector('.contactpanda-settings-color-value');
		if (!value) {
			value = document.createElement('code');
			value.className = 'contactpanda-settings-color-value';
			control.appendChild(value);
		}
		function update() { value.textContent = input.value; }
		input.addEventListener('input', update);
		input.addEventListener('change', update);
		update();
		colorPalette(input, field);
	}

	function normaliseImage(item) {
		return {id: Number(item.id || item.attachment_id || 0), url: item.url || item.source_url || '', alt: item.alt || item.alt_text || item.title || ''};
	}

	function createImagePicker(options) {
		options = options || {};
		var root = document.createElement('div');
		root.className = 'contactpanda-image-picker';
		var drop = document.createElement('button');
		drop.type = 'button';
		drop.className = 'contactpanda-image-picker-drop';
		drop.innerHTML = '<span class="dashicons dashicons-format-image" aria-hidden="true"></span><strong></strong><span class="contactpanda-image-picker-preview"></span><small class="contactpanda-image-picker-status" aria-live="polite"></small>';
		drop.querySelector('strong').textContent = options.dropLabel || i18n.dropImage || 'Click or drop an image here';
		root.appendChild(drop);
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'contactpanda-image-picker-remove contactpanda-list-action';
		remove.title = options.removeLabel || i18n.removeImage || 'Remove image';
		remove.setAttribute('aria-label', remove.title);
		remove.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
		root.appendChild(remove);
		var items = (options.items || (options.url ? [{id: options.attachmentId, url: options.url, alt: options.alt}] : [])).map(normaliseImage).filter(function (item) { return item.url; });

		function render(notify) {
			var preview = root.querySelector('.contactpanda-image-picker-preview');
			preview.replaceChildren();
			items.forEach(function (item) { var image = document.createElement('img'); image.src = item.url; image.alt = item.alt || ''; preview.appendChild(image); });
			root.classList.toggle('has-image', items.length > 0);
			remove.hidden = !items.length;
			if (notify && typeof options.onChange === 'function') options.onChange(options.multiple ? items.slice() : (items[0] || null));
		}
		function select(selected) {
			selected = selected.map(normaliseImage).filter(function (item) { return item.url; });
			items = options.multiple ? selected.slice(0, options.max || 20) : selected.slice(0, 1);
			render(true);
		}
		function openMedia() {
			if (!window.wp || !wp.media) return;
			var frame = wp.media({title: options.title || i18n.chooseImage || 'Select image', button: {text: options.buttonLabel || i18n.useImage || 'Use image'}, multiple: options.multiple ? 'add' : false, library: {type: 'image'}});
			frame.on('select', function () { select(frame.state().get('selection').toJSON()); });
			frame.open();
		}
		function upload(files) {
			var images = Array.prototype.filter.call(files || [], function (file) { return /^image\//.test(file.type); }).slice(0, options.multiple ? (options.max || 20) : 1);
			if (!images.length || !window.wp || !wp.apiFetch) return;
			root.classList.add('is-uploading');
			root.querySelector('.contactpanda-image-picker-status').textContent = i18n.uploading || 'Uploading image…';
			Promise.all(images.map(function (file) { var data = new FormData(); data.append('file', file, file.name); return wp.apiFetch({path: '/wp/v2/media', method: 'POST', body: data}); }))
				.then(function (uploaded) { select(uploaded); root.querySelector('.contactpanda-image-picker-status').textContent = ''; }, function () { root.querySelector('.contactpanda-image-picker-status').textContent = i18n.uploadFailed || 'The image could not be uploaded.'; })
				.then(function () { root.classList.remove('is-uploading'); });
		}
		drop.addEventListener('click', openMedia);
		drop.addEventListener('dragover', function (event) { event.preventDefault(); root.classList.add('is-dragging'); });
		drop.addEventListener('dragleave', function () { root.classList.remove('is-dragging'); });
		drop.addEventListener('drop', function (event) { event.preventDefault(); root.classList.remove('is-dragging'); upload(event.dataTransfer.files); });
		remove.addEventListener('click', function () { items = []; render(true); });
		root.contactPandaSetImages = select;
		window.setTimeout(function () { render(false); }, 0);
		return root;
	}

	function enhanceStaticImagePicker(root) {
		if (!root || root.dataset.contactpandaImageReady === '1') return;
		root.dataset.contactpandaImageReady = '1';
		var url = root.querySelector('[data-contactpanda-image-url], input[type="url"]');
		var id = root.querySelector('[data-contactpanda-image-id], input[type="hidden"]');
		var legacyButton = root.querySelector('.contactpanda-newsletter-media-button');
		var legacyPreview = root.querySelector('.contactpanda-newsletter-logo-preview');
		var picker = createImagePicker({
			url: url ? url.value : '', attachmentId: id ? id.value : 0,
			onChange: function (image) {
				if (url) url.value = image ? image.url : '';
				if (id) id.value = image ? image.id : 0;
			}
		});
		root.insertBefore(picker, root.firstChild);
		if (url) url.addEventListener('change', function () { picker.contactPandaSetImages(url.value ? [{id: id ? id.value : 0, url: url.value, alt: ''}] : []); });
		if (legacyButton) legacyButton.hidden = true;
		if (legacyPreview) legacyPreview.hidden = true;
	}

	function actionIcon(control) {
		if (!control || control.dataset.contactpandaActionReady === '1') return;
		if (control.querySelector('.dashicons')) {
			control.dataset.contactpandaActionReady = '1';
			control.classList.add('contactpanda-list-action');
			return;
		}
		var label = (control.getAttribute('aria-label') || control.getAttribute('title') || control.textContent || '').trim().replace(/\s+/g, ' ');
		var icon = '';
		actionIcons.some(function (entry) { if (entry[0].test(label)) { icon = entry[1]; return true; } return false; });
		if (!icon) return;
		control.dataset.contactpandaActionReady = '1';
		control.classList.add('contactpanda-list-action');
		control.title = label;
		control.setAttribute('aria-label', label);
		control.replaceChildren();
		var glyph = document.createElement('span'); glyph.className = 'dashicons dashicons-' + icon; glyph.setAttribute('aria-hidden', 'true'); control.appendChild(glyph);
		var text = document.createElement('span'); text.className = 'screen-reader-text'; text.textContent = label; control.appendChild(text);
	}

	function enhanceListActions(root) {
		findAll(root, 'table').forEach(function (table) {
			var actionColumns = [];
			table.querySelectorAll('thead th').forEach(function (heading, index) {
				if (/^(actions|aktionen)$/i.test(heading.textContent.trim()) || /(column-actions|actions?-column)/.test(heading.className)) actionColumns.push(index);
			});
			table.querySelectorAll('tbody tr').forEach(function (row) {
				actionColumns.forEach(function (index) { var cell = row.children[index]; if (cell) cell.querySelectorAll('a,button').forEach(actionIcon); });
			});
		});
		findAll(root, '.contactpanda-form-actions a,.contactpanda-form-actions button,.contactpanda-order-actions a,.contactpanda-order-actions button,.cpb-actions a,.cpb-actions button,.cpb-card-actions a,.cpb-card-actions button').forEach(actionIcon);
	}

	function enhance(root) {
		findAll(root, '.contactpanda-wrap input[type="color"],.contactpanda-business-wrap input[type="color"],.contactpanda-pro-wrap input[type="color"]').forEach(enhanceColorPicker);
		findAll(root, '.contactpanda-image-picker-source,.contactpanda-newsletter-logo-setting').forEach(enhanceStaticImagePicker);
		enhanceListActions(root || document);
	}

	window.ContactPandaAdminUI = {enhance: enhance, enhanceColorPicker: enhanceColorPicker, createImagePicker: createImagePicker, enhanceListActions: enhanceListActions};
	document.addEventListener('DOMContentLoaded', function () {
		enhance(document);
		var observer = new MutationObserver(function (records) { records.forEach(function (record) { record.addedNodes.forEach(function (node) { if (node.nodeType === 1) enhance(node); }); }); });
		observer.observe(document.body, {childList: true, subtree: true});
	});
}());
