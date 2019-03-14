(function() {
	if (window.eeposStaffListWidgetScriptInitialized) return;
	window.eeposStaffListWidgetScriptInitialized = true;

	console.log('init!');

	/**
	 * Finds the closest element matching the given class in the given element and its parents
	 * @param {Element} elem
	 * @param {string} theClass
	 * @returns {Element|undefined}
	 */
	function findClosestElem(elem, theClass) {
		if (!elem) return;

		var cursor = elem;
		do {
			if (cursor.classList && cursor.classList.contains(theClass)) break;
		} while (cursor = cursor.parentNode);

		return cursor;
	}

	function applyFilters(widgetRoot) {
		const filters = widgetRoot.querySelectorAll('.staff-member-filter-select');
		const staffMembers = widgetRoot.querySelectorAll('.staff-member');

		const filterValues = {};
		filters.forEach(function(filterSelect) {
			if (filterSelect.value !== '') {
				filterValues[filterSelect.getAttribute('data-field')] = filterSelect.value.toLowerCase();
			}
		});

		staffMembers.forEach(function(staffMemberElem) {
			let visible = true;
			for (const filter in filterValues) {
				const filterValue = filterValues[filter];
				const staffFieldValue = staffMemberElem.getAttribute('data-field-' + filter);
				visible = visible && (staffFieldValue.indexOf(filterValue) !== -1);
			}

			if (!visible) {
				staffMemberElem.classList.add('hidden');
			} else {
				staffMemberElem.classList.remove('hidden');
			}
		});

		console.log('filter applied!');
	}

	// Apply filters when the filter selects change
	document.addEventListener('change', function(ev) {
		console.log('sup', ev);
		if (! ev.target.classList.contains('staff-member-filter-select')) return;

		const widgetRoot = findClosestElem(ev.target, 'staff-member-list-widget');
		applyFilters(widgetRoot);
	});

	// Initial filters
	const widgets = document.querySelectorAll('staff-member-list-widget');
	widgets.forEach(applyFilters);
})();