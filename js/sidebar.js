/* ============================================================
   KRYSTAL — Sidebar Toggle (Phase 4C)
   ============================================================ */
(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        var hamburger = document.getElementById('hamburger-btn');
        var overlay   = document.getElementById('sidebar-overlay');
        var body      = document.body;

        if (!hamburger || !overlay) return;

        function openSidebar()  { body.classList.add('sidebar-open'); }
        function closeSidebar() { body.classList.remove('sidebar-open'); }

        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (body.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        overlay.addEventListener('click', closeSidebar);

        // Close sidebar when a nav link is clicked (mobile UX)
        var links = document.querySelectorAll('.sidebar-link');
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener('click', function() {
                closeSidebar();
            });
        }
    });
})();
