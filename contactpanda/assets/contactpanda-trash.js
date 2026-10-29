// The lifecycle form also supports individual selection without JavaScript.
document.addEventListener('change', function (event) {
	if (event.target.matches('.contactpanda-trash-select-all')) event.target.closest('form').querySelectorAll('input[name="ids[]"]').forEach(function (checkbox) { checkbox.checked = event.target.checked; });
	if (event.target.matches('[name="operation"], [data-trash-confirm]')) updateBulkState(event.target.closest('form'));
});

function updateBulkState(form) {
	if (!form) return;
	var purge = form.querySelector('[name="operation"]') && form.querySelector('[name="operation"]').value === 'purge';
	var confirm = form.querySelector('[data-trash-confirm]'), apply = form.querySelector('[data-trash-apply]');
	if (confirm) confirm.required = purge;
	if (apply) apply.disabled = purge && (!confirm || !confirm.checked);
}

document.querySelectorAll('form').forEach(updateBulkState);

document.addEventListener('click', function (event) {
	var search = event.target.closest('[data-trash-search-submit]');
	if (!search) return;
	var form = search.closest('form'), input = form.querySelector('[data-trash-search]'), url = new URL(window.location.href);
	url.searchParams.set('s', input ? input.value : ''); url.searchParams.delete('paged'); window.location.href = url.toString();
});

document.addEventListener('click', function (event) {
	var button = event.target.closest('.contactpanda-trash-direct');
	if (!button) return;
	var operation = button.dataset.operation;
	var name = button.dataset.recordName || '';
	var templates = window.CONTACTPANDA_TRASH || {};
	var template = operation === 'purge' ? templates.purge : templates.trash;
	var message = (template || 'Confirm this action for “%s”?').replace('%s', name);
	if (operation !== 'restore' && !window.confirm(message)) return;
	var form = button.closest('form');
	if (button.dataset.entity && button.dataset.nonce) {
		form = document.createElement('form');
		form.method = 'post';
		form.action = window.ajaxurl ? window.ajaxurl.replace(/admin-ajax\.php.*$/, 'admin-post.php') : 'admin-post.php';
		[['action', 'contactpanda_record_action'], ['entity', button.dataset.entity], ['_wpnonce', button.dataset.nonce], ['confirmed', '1'], ['return_url', window.location.href]].forEach(function (entry) {
			var field = document.createElement('input'); field.type = 'hidden'; field.name = entry[0]; field.value = entry[1]; form.appendChild(field);
		});
		document.body.appendChild(form);
	}
	if (!form) return;
	[['direct_id', button.dataset.recordId], ['direct_operation', operation], ['direct_confirmed', '1']].forEach(function (entry) {
		var input = document.createElement('input'); input.type = 'hidden'; input.name = entry[0]; input.value = entry[1]; form.appendChild(input);
	});
	form.submit();
});
