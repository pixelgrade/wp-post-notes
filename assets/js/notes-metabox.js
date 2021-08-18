/*global pixelgrade_wppostnotes_metabox */
jQuery(function ($) {

	/**
	 * Post Notes Metabox
	 */
	var pixelgrade_post_notes_metabox = {
		init: function () {
			$('#pixelgrade_wppostnotes-metabox')
				.on('click', 'button.add_note', this.add_post_note)
				.on('click', 'a.delete_note', this.delete_post_note)
				.on('init_tooltips', function () {

					$('.pixelgrade_wppostnotes-help-tip').tipTip({
						'attribute': 'data-tip',
						'fadeIn': 50,
						'fadeOut': 50,
						'delay': 200,
						'keepAlive': true
					})
				})

			$('#pixelgrade_wppostnotes-metabox').trigger('init_tooltips')
		},

		add_post_note: function () {
			if (!$('textarea#post_note_content').val()) {
				return
			}

			$('#pixelgrade_wppostnotes-metabox').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			})

			var data = {
				action: 'pixelgrade_wppostnotes_add_post_note',
				post_id: pixelgrade_wppostnotes_metabox.post_id,
				note: $('textarea#post_note_content').val(),
				note_type: $('select#post_note_type').val(),
				security: pixelgrade_wppostnotes_metabox.add_post_note_nonce
			}

			$.post(pixelgrade_wppostnotes_metabox.ajax_url, data, function (response) {
				$('ul.post_notes .no-items').remove()
				$('ul.post_notes').prepend(response)
				$('#pixelgrade_wppostnotes-metabox').unblock()
				$('#post_note_content').val('')
			})

			return false
		},

		delete_post_note: function () {
			if (window.confirm(pixelgrade_wppostnotes_metabox.i18n_delete_note_confirm)) {
				var note = $(this).closest('li.note')
				var $note = $(note)

				$note.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				})

				var data = {
					action: 'pixelgrade_wppostnotes_delete_post_note',
					note_id: $note.attr('rel'),
					security: pixelgrade_wppostnotes_metabox.delete_post_note_nonce
				}

				$.post(pixelgrade_wppostnotes_metabox.ajax_url, data)
					.done(function () {
						$note.slideUp(400, function(){ $note.remove(); })
					})
					.fail(function () {
						$note.unblock().effect('shake', { distance: 7 });
					})
			}

			return false
		}
	}

	pixelgrade_post_notes_metabox.init()
})
