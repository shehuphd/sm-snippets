(function () {
	document.addEventListener('click', function (event) {
		var target = event.target;

		if (!target || !target.getAttribute) {
			return;
		}

		var message = target.getAttribute('data-sm-confirm');

		if (message && !window.confirm(message)) {
			event.preventDefault();
		}
	});
})();
