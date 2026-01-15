(function ($) {
    const leadTimeMs = (AWSettings.leadTimeMinutes || 10) * 60 * 1000;
    const slotIntervalMinutes = Math.max(1, AWSettings.slotIntervalMinutes || 15);
    const daysAhead = Math.max(1, AWSettings.registrationDaysAhead || 7);
    const serverNowMs = (AWSettings.serverNow || Math.floor(Date.now() / 1000)) * 1000;
    const serverOffsetMs = serverNowMs - Date.now();

    const getNow = () => new Date(Date.now() + serverOffsetMs);

    const formatDate = (date) => date.toLocaleDateString('pl-PL', { weekday: 'long', day: '2-digit', month: '2-digit' });
    const formatTime = (date) => date.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });

    const buildDayOptions = () => {
        const daySelect = $('#aw-day-select');
        daySelect.empty();
        const now = getNow();
        for (let i = 0; i < daysAhead; i += 1) {
            const day = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i, 0, 0, 0);
            const option = $('<option />').val(day.toISOString()).text(formatDate(day));
            daySelect.append(option);
        }
    };

    const buildTimeOptions = () => {
        const timeSelect = $('#aw-time-select');
        const dayValue = $('#aw-day-select').val();
        if (!dayValue) {
            return;
        }
        const day = new Date(dayValue);
        timeSelect.empty();
        const now = getNow();
        for (let slot = 0; slot < 24 * 60; slot += slotIntervalMinutes) {
            const slotDate = new Date(day.getFullYear(), day.getMonth(), day.getDate(), 0, slot, 0);
            if (slotDate.getTime() < now.getTime() + leadTimeMs) {
                continue;
            }
            const option = $('<option />')
                .val(slotDate.getTime().toString())
                .text(formatTime(slotDate));
            timeSelect.append(option);
        }

        if (timeSelect.children().length === 0) {
            timeSelect.append($('<option />').val('').text('Brak dostępnych godzin'));
        }
    };

    const updateSlotTimestamp = () => {
        const value = $('#aw-time-select').val();
        $('#aw-slot-timestamp').val(value ? Math.floor(parseInt(value, 10) / 1000) : '');
    };

    if ($('#aw-registration-form').length) {
        buildDayOptions();
        buildTimeOptions();
        updateSlotTimestamp();

        $('#aw-day-select').on('change', () => {
            buildTimeOptions();
            updateSlotTimestamp();
        });
        $('#aw-time-select').on('change', updateSlotTimestamp);

        $('#aw-registration-form').on('submit', function (event) {
            event.preventDefault();
            const payload = {
                action: 'aw_register',
                nonce: AWSettings.nonce,
                name: $('#aw-name').val(),
                email: $('#aw-email').val(),
                slot_timestamp: $('#aw-slot-timestamp').val(),
            };

            $.post(AWSettings.ajaxUrl, payload)
                .done((response) => {
                    $('#aw-form-message').text(response.data.message || 'Zapisano.');
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                })
                .fail((xhr) => {
                    const message = xhr.responseJSON?.data?.message || 'Błąd zapisu.';
                    $('#aw-form-message').text(message);
                });
        });
    }

    const room = $('.aw-room');
    if (room.length) {
        const slot = parseInt(room.data('slot'), 10) * 1000;
        const videoSeconds = parseInt(room.data('video-seconds'), 10);
        const endAction = room.data('end-action');
        const endRedirect = room.data('end-redirect');
        const chatBefore = room.data('chat-before') || 'show';
        const chatDuring = room.data('chat-during') || 'show';
        const chatAfter = room.data('chat-after') || 'hide';
        const videoBefore = room.data('video-before') || 'hide';
        const videoDuring = room.data('video-during') || 'show';
        const videoAfter = room.data('video-after') || 'hide';
        const videoEmbed = $('.aw-video-embed');

        const formatCountdown = (diffMs) => {
            const totalSeconds = Math.max(0, Math.floor(diffMs / 1000));
            const days = Math.floor(totalSeconds / 86400);
            const hours = Math.floor((totalSeconds % 86400) / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            const parts = [];
            if (days > 0) {
                parts.push(`${days} d`);
            }
            if (hours > 0 || days > 0) {
                parts.push(`${hours} h`);
            }
            parts.push(`${minutes} min`);
            parts.push(`${seconds} s`);
            return parts.join(' ');
        };

        const buildVideoEmbed = () => {
            if (!videoEmbed.length) {
                return;
            }
            if (videoEmbed.children().length) {
                return;
            }
            const provider = videoEmbed.data('provider');
            if (provider === 'custom') {
                const encoded = videoEmbed.data('embed') || '';
                try {
                    const decoded = decodeURIComponent(escape(atob(encoded)));
                    videoEmbed.html(decoded);
                } catch (e) {
                    videoEmbed.html('');
                }
                return;
            }
            if (provider === 'iframe') {
                const src = videoEmbed.data('src');
                if (!src) {
                    return;
                }
                const url = new URL(src, window.location.origin);
                if (!url.searchParams.has('autoplay')) {
                    url.searchParams.set('autoplay', '0');
                }
                if (url.hostname.includes('wistia')) {
                    url.searchParams.set('autoPlay', '0');
                }
                const iframe = $('<iframe />', {
                    src: url.toString(),
                    allow: 'autoplay; fullscreen',
                    allowfullscreen: true,
                });
                videoEmbed.append(iframe);
                return;
            }
            if (provider === 'self') {
                const src = videoEmbed.data('src');
                if (!src) {
                    return;
                }
                const video = $('<video />', {
                    controls: true,
                });
                video.attr('src', src);
                videoEmbed.append(video);
            }
        };

        const clearVideoEmbed = () => {
            if (videoEmbed.length) {
                videoEmbed.empty();
            }
        };

        const updateRoomState = () => {
            const now = getNow().getTime();
            const start = slot;
            const end = slot + videoSeconds * 1000;
            const countdownEl = $('#aw-countdown');
            const statusEl = $('#aw-room-status');
            const changeEl = $('#aw-change-slot');
            const chatSection = $('#aw-qa-section');
            const videoSection = $('#aw-video');

            if (now < start) {
                const diff = start - now;
                statusEl.text('Oczekiwanie na start webinaru');
                countdownEl.text(`Start za ${formatCountdown(diff)}`);
                if (videoBefore === 'show') {
                    buildVideoEmbed();
                } else {
                    clearVideoEmbed();
                }
                videoSection.toggle(videoBefore === 'show');
                $('#aw-end-cta').hide();
                chatSection.toggle(chatBefore === 'show');
                if (diff <= 60000) {
                    changeEl.hide();
                } else {
                    changeEl.show();
                }
            } else if (now >= start && now <= end) {
                statusEl.text('Webinar trwa');
                countdownEl.text('');
                if (videoDuring === 'show') {
                    buildVideoEmbed();
                } else {
                    clearVideoEmbed();
                }
                videoSection.toggle(videoDuring === 'show');
                $('#aw-end-cta').hide();
                chatSection.toggle(chatDuring === 'show');
                changeEl.hide();
            } else {
                statusEl.text('Webinar zakończony');
                countdownEl.text('');
                if (videoAfter === 'show') {
                    buildVideoEmbed();
                } else {
                    clearVideoEmbed();
                }
                videoSection.toggle(videoAfter === 'show');
                changeEl.hide();
                chatSection.toggle(chatAfter === 'show');
                if (endAction === 'redirect' && endRedirect) {
                    window.location.href = endRedirect;
                } else {
                    $('#aw-end-cta').show();
                }
            }
        };

        updateRoomState();
        setInterval(updateRoomState, 1000);

        const token = $('#aw-room-token').val();
        const fetchQuestions = () => {
            $.post(AWSettings.ajaxUrl, {
                action: 'aw_fetch_questions',
                nonce: AWSettings.nonce,
                token: token,
            }).done((response) => {
                const list = $('#aw-qa-list');
                list.empty();
                response.data.questions.forEach((item) => {
                    const entry = $('<div class="aw-qa-item" />');
                    entry.append(`<div class="aw-qa-question"><strong>Pytanie:</strong> ${item.question}</div>`);
                    if (item.status === 'answered' && item.answer) {
                        entry.append(`<div class="aw-qa-answer"><strong>Odpowiedź:</strong> ${item.answer}</div>`);
                    } else {
                        entry.append('<div class="aw-qa-answer">Oczekuje na odpowiedź.</div>');
                    }
                    list.append(entry);
                });
            });
        };

        fetchQuestions();
        setInterval(fetchQuestions, 10000);

        $('#aw-qa-form').on('submit', function (event) {
            event.preventDefault();
            const question = $('#aw-question').val();
            if (!question) {
                return;
            }
            $.post(AWSettings.ajaxUrl, {
                action: 'aw_submit_question',
                nonce: AWSettings.nonce,
                token: token,
                question: question,
            }).done(() => {
                $('#aw-question').val('');
                fetchQuestions();
            });
        });
    }
})(jQuery);
