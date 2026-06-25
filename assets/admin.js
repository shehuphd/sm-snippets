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

	function initCodeEditor() {
		if (!window.wp || !wp.codeEditor || !window.smSnippetsEditor) {
			return;
		}

		var textarea = document.querySelector('textarea[name="code"]');
		var typeSelect = document.querySelector('select[name="type"]');

		if (!textarea || textarea.dataset.smCodeEditorReady) {
			return;
		}

		var settings = Object.assign({}, smSnippetsEditor.settings || {});
		settings.codemirror = Object.assign({}, settings.codemirror || {}, {
			indentUnit: 2,
			lineNumbers: true,
			lineWrapping: true,
			matchBrackets: true,
			mode: getMode(typeSelect ? typeSelect.value : 'html')
		});

		var editor = wp.codeEditor.initialize(textarea, settings);
		textarea.dataset.smCodeEditorReady = '1';

		if (typeSelect && editor && editor.codemirror) {
			typeSelect.addEventListener('change', function () {
				editor.codemirror.setOption('mode', getMode(typeSelect.value));
			});
		}
	}

	function getMode(type) {
		return (smSnippetsEditor.modes && smSnippetsEditor.modes[type]) || 'htmlmixed';
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCodeEditor);
	} else {
		initCodeEditor();
	}
})();
