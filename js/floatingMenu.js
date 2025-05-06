document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleContacts');
    const options = document.querySelector('.floating-menu .contact-options');
    const icon = toggleBtn.querySelector('i');

    if (toggleBtn && options) {
        toggleBtn.addEventListener('click', () => {
            options.classList.toggle('show');
            icon.classList.toggle('fa-comments');
            icon.classList.toggle('fa-times');
        });

        // Ocultar el menú si se hace clic fuera de él
        document.addEventListener('click', function (e) {
            if (!toggleBtn.contains(e.target) && !options.contains(e.target)) {
                if (options.classList.contains('show')) {
                    options.classList.remove('show');
                    icon.classList.add('fa-comments');
                    icon.classList.remove('fa-times');
                }
            }
        });

        // También lo oculta cuando se hace clic en un ítem del menú
        options.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                options.classList.remove('show');
                icon.classList.add('fa-comments');
                icon.classList.remove('fa-times');
            });
        });
    }
});
