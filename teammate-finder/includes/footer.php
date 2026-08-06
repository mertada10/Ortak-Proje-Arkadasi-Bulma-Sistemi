<script src="/teammate-finder/assets/script.js?v=<?= time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('filterToggle');
        const panel = document.querySelector('.filter-panel');

        if (toggle && panel) {
            toggle.addEventListener('click', function () {
                const isExpanded = panel.getAttribute('aria-expanded') === 'true';
                panel.setAttribute('aria-expanded', String(!isExpanded));
                toggle.setAttribute('aria-expanded', String(!isExpanded));
            });
        }
    });
</script>

</body>
</html>