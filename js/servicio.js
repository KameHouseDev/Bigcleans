/* Behaviour shared by the individual service pages.
   Same header, mobile nav and back-to-top as the home page, without the
   index-only pieces (hero video, portfolio ring, carousels, counters). */
(function ($) {
  "use strict";

  // Back to top button
  $(window).scroll(function () {
    if ($(this).scrollTop() > 100) {
      $('.back-to-top').fadeIn('slow');
    } else {
      $('.back-to-top').fadeOut('slow');
    }
  });

  $('.back-to-top').click(function () {
    $('html, body').animate({ scrollTop: 0 }, 1500, 'easeInOutExpo');
    return false;
  });

  // Initiate the wowjs animation library
  new WOW().init();

  // Initiate superfish on nav menu
  $('.nav-menu').superfish({
    animation: { opacity: 'show' },
    speed: 400
  });

  // Mobile Navigation
  if ($('#nav-menu-container').length) {
    var $mobile_nav = $('#nav-menu-container').clone().prop({ id: 'mobile-nav' });
    $mobile_nav.find('> ul').attr({ 'class': '', 'id': '' });
    $('body').append($mobile_nav);
    $('body').prepend('<button type="button" id="mobile-nav-toggle" aria-label="Abrir menu de navegacion"><i class="fa fa-bars"></i></button>');
    $('body').append('<div id="mobile-body-overly"></div>');

    $(document).on('click', '#mobile-nav-toggle', function () {
      $('body').toggleClass('mobile-nav-active');
      $('#mobile-nav-toggle i').toggleClass('fa-times fa-bars');
      $('#mobile-body-overly').toggle();
    });

    $(document).click(function (e) {
      var container = $("#mobile-nav, #mobile-nav-toggle");
      if (!container.is(e.target) && container.has(e.target).length === 0) {
        if ($('body').hasClass('mobile-nav-active')) {
          $('body').removeClass('mobile-nav-active');
          $('#mobile-nav-toggle i').toggleClass('fa-times fa-bars');
          $('#mobile-body-overly').fadeOut();
        }
      }
    });
  }

  // Header scroll class
  $(window).scroll(function () {
    if ($(this).scrollTop() > 100) {
      $('#header').addClass('header-scrolled');
    } else {
      $('#header').removeClass('header-scrolled');
    }
  });

  if ($(window).scrollTop() > 100) {
    $('#header').addClass('header-scrolled');
  }

  // Lightbox injects anchors without href - keep them valid for a11y/SEO
  var observer = new MutationObserver(function () {
    document.querySelectorAll('a.lb-cancel, a.lb-close').forEach(function (link) {
      if (!link.getAttribute('href')) link.setAttribute('href', '#');
      link.setAttribute('role', 'button');
      link.setAttribute('aria-label', link.classList.contains('lb-cancel') ? 'Cancelar galeria' : 'Cerrar galeria');
      link.setAttribute('rel', 'nofollow noopener');
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });

})(jQuery);
