define(['jquery'], function($) {
    return {
        init: function() {
            $(document).ready(function() {
                var panel = $('#local-ai-reflection-panel-wrapper');

                if (panel.length) {
                    var target = $('.submissionstatustable, .gradingsummary');

                    if (target.length) {
                        panel.insertAfter(target).removeClass('d-none');
                    } else {
                        $('#region-main').append(panel);
                        panel.removeClass('d-none');
                    }
                }

                // Polling hanya jalan kalau state processing aktif
                if ($('#ai-reflection-state-processing').length) {
                    var params = new URLSearchParams(window.location.search);
                    var cmid = params.get('id');

                    if (!cmid) {
                        return;
                    }

                    var pollInterval = setInterval(function() {
                        $.ajax({
                            url: '/local/ai_reflection/status.php',
                            data: { cmid: cmid },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'done' || response.status === 'error') {
                                    clearInterval(pollInterval);
                                    location.reload();
                                }
                            }
                        });
                    }, 10000); // cek tiap 10 detik
                }
            });
        }
    };
});