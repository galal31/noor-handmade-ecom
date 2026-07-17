</div> </main> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const wrapper = document.querySelector(".page-wrapper");
    const toggle = document.getElementById("toggle-sidebar");
    const backdrop = document.querySelector(".sidebar-backdrop");
    const mobileQuery = window.matchMedia("(max-width: 991.98px)");

    if (!wrapper || !toggle) return;

    function syncSidebarState() {
        const mobileOpen = mobileQuery.matches && wrapper.classList.contains("toggled");
        document.body.classList.toggle("sidebar-open", mobileOpen);
        toggle.setAttribute("aria-expanded", mobileOpen ? "true" : "false");
        toggle.setAttribute("aria-label", mobileOpen ? "إغلاق القائمة الجانبية" : "فتح القائمة الجانبية");
    }

    function closeMobileSidebar() {
        if (!mobileQuery.matches) return;
        wrapper.classList.remove("toggled");
        syncSidebarState();
    }

    toggle.addEventListener("click", function() {
        wrapper.classList.toggle("toggled");
        syncSidebarState();
    });

    if (backdrop) {
        backdrop.addEventListener("click", closeMobileSidebar);
    }

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") closeMobileSidebar();
    });

    document.querySelectorAll(".sidebar-menu a").forEach(function(link) {
        link.addEventListener("click", closeMobileSidebar);
    });

    mobileQuery.addEventListener("change", function() {
        wrapper.classList.remove("toggled");
        syncSidebarState();
    });

    syncSidebarState();
});
</script>
</body>
</html>
