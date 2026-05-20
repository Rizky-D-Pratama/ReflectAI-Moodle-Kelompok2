define(['jquery'], function($) {
    return {
        init: function() {
            $(document).ready(function() {
                var navbar = $('ul.more-nav.nav-tabs');
                var params = new URLSearchParams(window.location.search);
                var cmid = params.get('id') || params.get('cmid');

                if (!navbar.length || !cmid) {
                    return;
                }

                var reflectionUrl = '/local/ai_reflection/results.php?cmid=' + cmid;
                var isActive = window.location.href.indexOf('ai_reflection') !== -1;

                var moreBtn = navbar.find('li[data-region="morebutton"]');
                var tabHtml = '<li class="nav-item" role="none">' +
                    '<a role="menuitem" class="nav-link' + (isActive ? ' active' : '') + '" href="' + reflectionUrl + '">' +
                    'AI Reflection' +
                    '</a></li>';

                if (moreBtn.length) {
                    moreBtn.before(tabHtml);
                } else {
                    navbar.append(tabHtml);
                }
            });
        }
    };
});