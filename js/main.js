(function ($) {
  "use strict";

  // El preloader lo maneja js/preloader.js, sin jQuery, para que funcione
  // aunque alguna libreria no alcance a cargar.

  // Intro copy holds back until the branded shots at the head of the video have played,
  // and re-hides on every loop so the logo is never covered.
  var $intro = $('#intro');
  var introVideo = $intro.find('.intro-video').get(0);
  var introLogoEnd = 0.5; // seconds of branding footage at the start of hero.mp4

  function showIntroText() {
    $intro.addClass('intro-text-visible');
  }

  if (!introVideo) {
    showIntroText();
  } else {
    $(introVideo)
      .on('timeupdate', function () {
        $intro.toggleClass('intro-text-visible', introVideo.currentTime >= introLogoEnd);
      })
      .on('error', showIntroText);

    // Autoplay can be blocked and the file can be missing - never leave the copy hidden.
    setTimeout(function () {
      if (introVideo.paused || !introVideo.currentTime) {
        showIntroText();
      }
    }, introLogoEnd * 1000 + 1500);
  }

  // Back to top button
  $(window).scroll(function() {
    if ($(this).scrollTop() > 100) {
      $('.back-to-top').fadeIn('slow');
    } else {
      $('.back-to-top').fadeOut('slow');
    }
  });
  $('.back-to-top').click(function(){
    $('html, body').animate({scrollTop : 0},1500, 'easeInOutExpo');
    return false;
  });

  // Initiate the wowjs animation library
  new WOW().init();

  // Initiate superfish on nav menu
  $('.nav-menu').superfish({
    animation: {
      opacity: 'show'
    },
    speed: 400
  });

  // Mobile Navigation
  if ($('#nav-menu-container').length) {
    var $mobile_nav = $('#nav-menu-container').clone().prop({
      id: 'mobile-nav'
    });
    $mobile_nav.find('> ul').attr({
      'class': '',
      'id': ''
    });
    $('body').append($mobile_nav);
      $('body').prepend('<button type="button" id="mobile-nav-toggle" aria-label="Abrir men� de navegaci�n"><i class="fa fa-bars"></i></button>');
    $('body').append('<div id="mobile-body-overly"></div>');
    $('#mobile-nav').find('.menu-has-children').prepend('<i class="fa fa-chevron-down"></i>');

    $(document).on('click', '.menu-has-children i', function(e) {
      $(this).next().toggleClass('menu-item-active');
      $(this).nextAll('ul').eq(0).slideToggle();
      $(this).toggleClass("fa-chevron-up fa-chevron-down");
    });

    $(document).on('click', '#mobile-nav-toggle', function(e) {
      $('body').toggleClass('mobile-nav-active');
      $('#mobile-nav-toggle i').toggleClass('fa-times fa-bars');
      $('#mobile-body-overly').toggle();
    });

    $(document).click(function(e) {
      var container = $("#mobile-nav, #mobile-nav-toggle");
      if (!container.is(e.target) && container.has(e.target).length === 0) {
        if ($('body').hasClass('mobile-nav-active')) {
          $('body').removeClass('mobile-nav-active');
          $('#mobile-nav-toggle i').toggleClass('fa-times fa-bars');
          $('#mobile-body-overly').fadeOut();
        }
      }
    });
  } else if ($("#mobile-nav, #mobile-nav-toggle").length) {
    $("#mobile-nav, #mobile-nav-toggle").hide();
  }

  // Header scroll class
  $(window).scroll(function() {
    if ($(this).scrollTop() > 100) {
      $('#header').addClass('header-scrolled');
    } else {
      $('#header').removeClass('header-scrolled');
    }
  });

  if ($(window).scrollTop() > 100) {
    $('#header').addClass('header-scrolled');
  }

  // Smooth scroll for the menu and links with .scrollto classes
  $('.nav-menu a, #mobile-nav a, .scrollto').on('click', function() {
    if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
      var target = $(this.hash);
      if (target.length) {
        var top_space = 0;

        if ($('#header').length) {
          top_space = $('#header').outerHeight();

          if (! $('#header').hasClass('header-scrolled')) {
            top_space = top_space - 20;
          }
        }

        $('html, body').animate({
          scrollTop: target.offset().top - top_space
        }, 1500, 'easeInOutExpo');

        if ($(this).parents('.nav-menu').length) {
          $('.nav-menu .menu-active').removeClass('menu-active');
          $(this).closest('li').addClass('menu-active');
        }

        if ($('body').hasClass('mobile-nav-active')) {
          $('body').removeClass('mobile-nav-active');
          $('#mobile-nav-toggle i').toggleClass('fa-times fa-bars');
          $('#mobile-body-overly').fadeOut();
        }
        return false;
      }
    }
  });

  // Navigation active state on scroll
  var nav_sections = $('section');
  var main_nav = $('.nav-menu, #mobile-nav');
  var main_nav_height = $('#header').outerHeight();

  $(window).on('scroll', function () {
    var cur_pos = $(this).scrollTop();
  
    nav_sections.each(function() {
      var top = $(this).offset().top - main_nav_height,
          bottom = top + $(this).outerHeight();
  
      if (cur_pos >= top && cur_pos <= bottom) {
        main_nav.find('li').removeClass('menu-active menu-item-active');
        main_nav.find('a[href="#'+$(this).attr('id')+'"]').parent('li').addClass('menu-active menu-item-active');
      }
    });
  });

  // Skills section
  $('#skills').waypoint(function() {
    $('.progress .progress-bar').each(function() {
      $(this).css("width", $(this).attr("aria-valuenow") + '%');
    });
  }, { offset: '80%'} );

  // jQuery counterUp (used in Facts section)
  $('[data-toggle="counter-up"]').counterUp({
    delay: 10,
    time: 1000
  });

  // Portfolio 360 ring: the items sit on the face of a cylinder that spins on its axis
  var $ringViewport = $('.portfolio-ring-viewport');
  var $ring = $('.portfolio-ring');
  var $ringItems = $ring.find('.portfolio-ring-item');
  var $ringCounter = $('.portfolio-carousel-counter .current');
  var ringCount = $ringItems.length;

  if (ringCount) {
    var ringStep = 360 / ringCount;   // angle between two neighbouring items
    var ringRadius = 0;               // distance from the axis, derived from the item width
    var ringIndex = 0;                // item currently facing the visitor
    var ringTimer = null;

    // Edge-to-edge radius, widened so the cards sit apart instead of forming a solid wall.
    // A narrow screen gets a tighter ring, otherwise the neighbours fall off both edges
    // and the curve stops reading as 3D.
    function layoutRing() {
      var itemWidth = $ringItems.first().outerWidth();
      var spread = $(window).width() < 768 ? 1.1 : 1.3;
      ringRadius = Math.round((itemWidth / 2) / Math.tan(Math.PI / ringCount) * spread);

      $ringItems.each(function (i) {
        $(this).css('transform', 'rotateY(' + (i * ringStep) + 'deg) translateZ(' + ringRadius + 'px)');
      });

      spinRing();
    }

    // Spinning the whole ring backwards brings item `ringIndex` to the front.
    function spinRing() {
      $ring.css('transform', 'translateZ(-' + ringRadius + 'px) rotateY(' + (-ringIndex * ringStep) + 'deg)');

      var front = ((ringIndex % ringCount) + ringCount) % ringCount;

      $ringItems.each(function (i) {
        // shortest angular distance to the front, so item 0 and item 20 read as neighbours
        var offset = i - front;
        if (offset > ringCount / 2) { offset -= ringCount; }
        if (offset < -ringCount / 2) { offset += ringCount; }

        var steps = Math.abs(offset);
        $(this)
          .toggleClass('is-front', steps === 0)
          .css('opacity', steps > 3 ? 0 : Math.max(1 - steps * 0.3, 0));
      });

      $ringCounter.text(front + 1);
    }

    function ringGo(delta) {
      ringIndex += delta;
      spinRing();
    }

    function startRingAutoplay() {
      stopRingAutoplay();
      ringTimer = setInterval(function () { ringGo(1); }, 4000);
    }

    function stopRingAutoplay() {
      if (ringTimer) { clearInterval(ringTimer); ringTimer = null; }
    }

    $('.portfolio-ring-prev').on('click', function () { ringGo(-1); startRingAutoplay(); });
    $('.portfolio-ring-next').on('click', function () { ringGo(1); startRingAutoplay(); });

    $ringViewport.hover(stopRingAutoplay, startRingAutoplay);

    if ($.fn.swipe) {
      $ringViewport.swipe({
        swipeLeft: function () { ringGo(1); },
        swipeRight: function () { ringGo(-1); },
        allowPageScroll: 'vertical',
        threshold: 40
      });
    }

    layoutRing();
    startRingAutoplay();

    var ringResizeTimer = null;
    $(window).on('resize', function () {
      clearTimeout(ringResizeTimer);
      ringResizeTimer = setTimeout(layoutRing, 200);
    });
  }

  // Clients carousel (uses the Owl Carousel library)
  $(".clients-carousel").owlCarousel({
    autoplay: true,
    dots: true,
    loop: true,
    responsive: { 0: { items: 2 }, 768: { items: 4 }, 900: { items: 6 }
    }
  });

  // Testimonials carousel (uses the Owl Carousel library)
  $(".testimonials-carousel").owlCarousel({
    autoplay: true,
    dots: true,
    loop: true,
    items: 1,
    
  });

})(jQuery);

