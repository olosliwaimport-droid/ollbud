(function ($) {
    const encodeBase64 = (value) => {
        if (!value) {
            return '';
        }
        return btoa(unescape(encodeURIComponent(value)));
    };

    $('#aw-toggle-token').on('click', function () {
        const field = $('#mailerlite_token');
        const isPassword = field.attr('type') === 'password';
        field.attr('type', isPassword ? 'text' : 'password');
        $(this).text(isPassword ? 'Ukryj' : 'Pokaż');
    });

    $('#aw-settings-form').on('submit', function (event) {
        event.preventDefault();
        const form = $(this);
        const data = form.serializeArray();
        const payload = {
            action: 'aw_save_settings',
            nonce: AWAdminSettings.nonce,
        };

        data.forEach((item) => {
            payload[item.name] = item.value;
        });

        payload.mailerlite_token = encodeBase64(payload.mailerlite_token || '');
        payload.video_iframe_embed = encodeBase64(payload.video_iframe_embed || '');

        $.post(AWAdminSettings.ajaxUrl, payload)
            .done((response) => {
                alert(response.data.message || 'Zapisano.');
            })
            .fail((xhr) => {
                const message = xhr.responseJSON?.data?.message || 'Błąd zapisu.';
                alert(message);
            });
    });

    $('.aw-answer-submit').on('click', function (event) {
        event.preventDefault();
        const id = $(this).data('question-id');
        const answer = $(`.aw-answer[data-question-id="${id}"]`).val();

        $.post(AWAdminSettings.ajaxUrl, {
            action: 'aw_admin_answer',
            nonce: AWAdminSettings.nonce,
            question_id: id,
            answer: answer,
        })
            .done((response) => {
                alert(response.data.message || 'Zapisano.');
            })
            .fail((xhr) => {
                const message = xhr.responseJSON?.data?.message || 'Błąd zapisu.';
                alert(message);
            });
    });
})(jQuery);
