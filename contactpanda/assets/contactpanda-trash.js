// The lifecycle form also supports individual selection without JavaScript.
document.addEventListener('change', function (event) {
	if (!event.target.matches('.contactpanda-trash-select-all')) return;
	event.target.closest('form').querySelectorAll('input[name="ids[]"]').forEach(function (checkbox) { checkbox.checked = event.target.checked; });
});

document.addEventListener('click', function (event) {
	var button = event.target.closest('.contactpanda-trash-direct');
	if (!button) return;
	var operation = button.dataset.operation;
	var name = button.dataset.recordName || '';
	var templates = window.CONTACTPANDA_TRASH || {};
	var template = operation === 'purge' ? templates.purge : (operation === 'restore' ? templates.restore : templates.trash);
	var message = (template || 'Confirm this action for “%s”?').replace('%s', name);
	if (!window.confirm(message)) return;
	var form = button.closest('form');
	[['direct_id', button.dataset.recordId], ['direct_operation', operation], ['direct_confirmed', '1']].forEach(function (entry) {
		var input = document.createElement('input'); input.type = 'hidden'; input.name = entry[0]; input.value = entry[1]; form.appendChild(input);
	});
	form.submit();
});
