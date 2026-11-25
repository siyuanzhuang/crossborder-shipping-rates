/**
 * 后台交互：增删表格行。
 *
 * 模板里的 __i__ 是行索引占位符，插入时替换成唯一值，
 * 这样子才敢用 name="zones[..][field]" 这种显式下标
 * （PHP 的 zones[][field] 写法每遇到一个字段就会新开一个元素，行会散架）。
 */
(function () {
	'use strict';

	var counter = 0;

	function addRow(button) {
		var targetSelector = button.getAttribute('data-cbsr-add-row');
		var templateSelector = button.getAttribute('data-cbsr-template');
		var target = document.querySelector(targetSelector);
		var template = document.querySelector(templateSelector);

		if (!target || !template) {
			return;
		}

		counter += 1;
		var index = 'r' + Date.now().toString(36) + counter;

		var holder = document.createElement('tbody');
		holder.innerHTML = template.innerHTML.replace(/__i__/g, index);

		var row = holder.firstElementChild;
		if (!row) {
			return;
		}

		target.appendChild(row);

		var firstInput = row.querySelector('input, select');
		if (firstInput) {
			firstInput.focus();
		}

		row.scrollIntoView({ block: 'nearest' });
	}

	document.addEventListener('click', function (event) {
		var addButton = event.target.closest('[data-cbsr-add-row]');
		if (addButton) {
			event.preventDefault();
			addRow(addButton);
			return;
		}

		var removeButton = event.target.closest('.cbsr-remove-row');
		if (removeButton) {
			event.preventDefault();
			var row = removeButton.closest('tr');
			if (row && row.parentNode) {
				row.parentNode.removeChild(row);
			}
		}
	});
})();
