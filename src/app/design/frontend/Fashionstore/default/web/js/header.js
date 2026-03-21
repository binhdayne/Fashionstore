document.addEventListener('DOMContentLoaded', function() {
    var lastScroll = 0;
    var header = document.querySelector('.page-header');
    var nav = document.querySelector('.nav-sections');
    var isCmsHome = document.body.classList.contains('cms-home');

    window.addEventListener('scroll', function() {
        var currentScroll = window.pageYOffset;

        // Ẩn/hiện header khi scroll
        if (currentScroll > lastScroll && currentScroll > 100) {
            document.body.classList.add('header-hidden');
        } else {
            document.body.classList.remove('header-hidden');
        }

        // Thêm class scrolled cho trang home
        if (isCmsHome) {
            if (currentScroll > 50) {
                header && header.classList.add('scrolled');
                nav && nav.classList.add('scrolled');
            } else {
                header && header.classList.remove('scrolled');
                nav && nav.classList.remove('scrolled');
            }
        }

        lastScroll = currentScroll;
    });
});
