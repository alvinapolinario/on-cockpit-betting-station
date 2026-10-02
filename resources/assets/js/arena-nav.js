(function () {
  var desktop = 1200;

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function isMobile() {
    return window.innerWidth < desktop;
  }

  function setOpen(open) {
    document.documentElement.classList.toggle('layout-menu-expanded', open);
    document.body.style.overflow = open && isMobile() ? 'hidden' : '';
  }

  function closeMenu() {
    setOpen(false);
  }

  function toggleMenu(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    if (!isMobile()) {
      return;
    }
    setOpen(!document.documentElement.classList.contains('layout-menu-expanded'));
  }

  ready(function () {
    document.documentElement.classList.remove('layout-menu-collapsed');

    document.querySelectorAll('.arena-nav .side-link.has-sub').forEach(function (link) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        var item = link.closest('.side-item');
        if (item) {
          item.classList.toggle('is-open');
        }
      });
    });

    document.querySelectorAll('.arena-burger, .side-close').forEach(function (el) {
      el.addEventListener('click', toggleMenu);
    });
    var overlay = document.querySelector('.layout-overlay');
    if (overlay) {
      overlay.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeMenu();
      });
    }

    document.querySelectorAll('.arena-nav .side-link:not(.has-sub), .arena-nav .side-sub a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (isMobile()) {
          closeMenu();
        }
      });
    });

    window.addEventListener('resize', function () {
      if (!isMobile()) {
        closeMenu();
      }
    });

    document.addEventListener('keyup', function (event) {
      if (event.key === 'Escape') {
        closeMenu();
      }
    });
  });
})();
