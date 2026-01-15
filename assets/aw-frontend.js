(function ($) {
    const leadTimeMs = (AWSettings.leadTimeMinutes || 10) * 60 * 1000;
    const slotIntervalMinutes = Math.max(1, AWSettings.slotIntervalMinutes || 15);
    const daysAhead = Math.max(1, AWSettings.registrationDaysAhead || 7);
    const serverNowMs = (AWSettings.serverNow || Math.floor(Date.now() / 1000)) * 1000;
    const serverOffsetMs = serverNowMs - Date.now();
    const jitEnabled = !!AWSettings.jitEnabled;
    const jitMinutes = Math.max(1, AWSettings.jitMinutes || 15);
    const timeZoneMode = AWSettings.timezoneMode || 'auto';
    const timeZoneDefault = AWSettings.timezoneDefault || '';

    const getNow = () => new Date(Date.now() + serverOffsetMs);

    const getSavedTimeZone = () => localStorage.getItem('aw_timezone') || '';

    const setSavedTimeZone = (tz) => {
        if (tz) {
            localStorage.setItem('aw_timezone', tz);
        }
    };

    const detectTimeZone = () => {
        if (Intl?.DateTimeFormat) {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        }
        return '';
    };

    const resolveTimeZone = () => {
        const saved = getSavedTimeZone();
        if (saved) {
            return saved;
        }
        const detected = detectTimeZone();
        return detected || timeZoneDefault;
    };

    let selectedTimeZone = resolveTimeZone();

    const formatDate = (date) => new Intl.DateTimeFormat('pl-PL', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        timeZone: selectedTimeZone || undefined,
    }).format(date);

    const formatTime = (date) => new Intl.DateTimeFormat('pl-PL', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: selectedTimeZone || undefined,
    }).format(date);

    const formatDayValue = (date) => {
        const formatter = new Intl.DateTimeFormat('en-CA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            timeZone: selectedTimeZone || 'UTC',
        });
        return formatter.format(date);
    };

    const getTimeZoneOffsetMinutes = (date, tz) => {
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: tz,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        });
        const parts = formatter.formatToParts(date).reduce((acc, part) => {
            if (part.type !== 'literal') {
                acc[part.type] = part.value;
            }
            return acc;
        }, {});
        const iso = `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}Z`;
        const utcTime = Date.parse(iso);
        return (utcTime - date.getTime()) / 60000;
    };

    const toEpochMs = (dayValue, minutesFromMidnight) => {
        const [year, month, day] = dayValue.split('-').map((value) => parseInt(value, 10));
        const hours = Math.floor(minutesFromMidnight / 60);
        const minutes = minutesFromMidnight % 60;
        if (!selectedTimeZone) {
            return new Date(year, month - 1, day, hours, minutes, 0).getTime();
        }
        const utcMs = Date.UTC(year, month - 1, day, hours, minutes, 0);
        const offsetMinutes = getTimeZoneOffsetMinutes(new Date(utcMs), selectedTimeZone);
        return utcMs - offsetMinutes * 60000;
    };

    const buildDayOptions = () => {
        const daySelect = $('#aw-day-select');
        daySelect.empty();
        const now = getNow();
        for (let i = 0; i < daysAhead; i += 1) {
            const day = new Date(now.getTime() + i * 86400000);
            const option = $('<option />').val(formatDayValue(day)).text(formatDate(day));
            daySelect.append(option);
        }
    };

    const buildTimeOptions = (showAll = false) => {
        const timeSelect = $('#aw-time-select');
        const dayValue = $('#aw-day-select').val();
        if (!dayValue) {
            return;
        }
        timeSelect.empty();
        const now = getNow();
        let added = 0;
        for (let slot = 0; slot < 24 * 60; slot += slotIntervalMinutes) {
            const slotMs = toEpochMs(dayValue, slot);
            if (slotMs < now.getTime() + leadTimeMs) {
                continue;
            }
            const option = $('<option />')
                .val(slotMs.toString())
                .text(formatTime(new Date(slotMs)));
            timeSelect.append(option);
            added += 1;
            if (jitEnabled && !showAll && added >= 1) {
                break;
            }
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
        const timezoneField = $('#aw-timezone-field');
        const timezoneSelect = $('#aw-timezone');
        const showAllButton = $('#aw-show-all-slots');

        if (timeZoneMode === 'select' || timeZoneMode === 'auto_select') {
            if (Intl?.supportedValuesOf) {
                const zones = Intl.supportedValuesOf('timeZone');
                zones.forEach((tz) => {
                    timezoneSelect.append($('<option />').val(tz).text(tz));
                });
            }
            timezoneSelect.val(selectedTimeZone || timeZoneDefault);
            timezoneField.show();
        }

        buildDayOptions();
        buildTimeOptions();
        updateSlotTimestamp();

        $('#aw-day-select').on('change', () => {
            buildTimeOptions();
            updateSlotTimestamp();
        });
        $('#aw-time-select').on('change', updateSlotTimestamp);
        if (jitEnabled) {
            showAllButton.show();
            showAllButton.on('click', () => {
                buildTimeOptions(true);
                updateSlotTimestamp();
                showAllButton.hide();
            });
        }

        timezoneSelect.on('change', () => {
            selectedTimeZone = timezoneSelect.val();
            setSavedTimeZone(selectedTimeZone);
            buildDayOptions();
            buildTimeOptions();
            updateSlotTimestamp();
        });

        const existingToken = localStorage.getItem('aw_token') || '';
        if (existingToken) {
            $.post(AWSettings.ajaxUrl, {
                action: 'aw_get_lock',
                nonce: AWSettings.nonce,
                token: existingToken,
            }).done((response) => {
                $('#aw-form-message').text('Masz już przypisany termin. Przekierowuję do pokoju.');
                if (response.data?.room_url) {
                    window.location.href = response.data.room_url;
                }
            });
        }

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
                    if (response.data.token) {
                        localStorage.setItem('aw_token', response.data.token);
                        document.cookie = `aw_token=${response.data.token}; path=/; max-age=2592000`;
                    }
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
        const deadlineEnabled = !!AWSettings.deadlineEnabled;
        const deadlineMinutes = Math.max(1, AWSettings.deadlineMinutes || 30);
        const deadlineTrigger = AWSettings.deadlineTrigger || 'after_start';
        const deadlineWatchPercent = Math.min(100, Math.max(1, AWSettings.deadlineWatchPercent || 50));
        const deadlineEl = $('#aw-deadline');
        const roomToken = $('#aw-room-token').val() || 'global';
        const deadlineKey = `aw_deadline_${roomToken}`;

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

        const getDeadlineEnd = () => {
            const stored = localStorage.getItem(deadlineKey);
            if (stored) {
                return parseInt(stored, 10);
            }
            return 0;
        };

        const setDeadlineEnd = (startMs) => {
            const deadlineEnd = startMs + deadlineMinutes * 60 * 1000;
            localStorage.setItem(deadlineKey, deadlineEnd.toString());
            return deadlineEnd;
        };

        const ensureDeadline = (startMs) => {
            const existing = getDeadlineEnd();
            if (existing > 0) {
                return existing;
            }
            return setDeadlineEnd(startMs);
        };

        const updateDeadlineCountdown = () => {
            if (!deadlineEnabled) {
                deadlineEl.hide();
                return;
            }
            const end = getDeadlineEnd();
            if (!end) {
                deadlineEl.hide();
                return;
            }
            const now = getNow().getTime();
            const diff = end - now;
            if (diff <= 0) {
                deadlineEl.text('Oferta wygasła.');
                return;
            }
            deadlineEl.text(`Oferta ważna jeszcze ${formatCountdown(diff)}`);
            deadlineEl.show();
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
                    if (deadlineEnabled && deadlineTrigger === 'after_start') {
                        ensureDeadline(now);
                    }
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

            updateDeadlineCountdown();
        };

        updateRoomState();
        setInterval(updateRoomState, 1000);

        if (deadlineEnabled && deadlineTrigger === 'after_watch') {
            const observeVideo = () => {
                const video = videoEmbed.find('video').get(0);
                if (!video) {
                    return;
                }
                video.addEventListener('timeupdate', () => {
                    if (!video.duration) {
                        return;
                    }
                    const watchedPercent = (video.currentTime / video.duration) * 100;
                    if (watchedPercent >= deadlineWatchPercent) {
                        ensureDeadline(getNow().getTime());
                    }
                });
            };
            observeVideo();
        }

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
