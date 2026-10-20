<!-- BEGIN: Vendor JS-->
<script src="{{ asset(mix('assets/vendor/libs/jquery/jquery.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/popper/popper.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/js/bootstrap.js')) }}"></script>
<script>
  window.getBootstrapModal = function (target) {
    const element = target && target.jquery ? target[0] : typeof target === 'string' ? document.querySelector(target) : target;
    return element ? window.bootstrap.Modal.getOrCreateInstance(element) : null;
  };

  window.showBootstrapModal = function (target) {
    const modal = window.getBootstrapModal(target);
    if (modal) modal.show();
  };

  window.hideBootstrapModal = function (target) {
    const modal = window.getBootstrapModal(target);
    if (modal) modal.hide();
  };

  window.toggleBootstrapModal = function (target) {
    const modal = window.getBootstrapModal(target);
    if (modal) modal.toggle();
  };
</script>
<script src="{{ asset(mix('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/hammer/hammer.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/typeahead-js/typeahead.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/js/menu.js')) }}"></script>
@yield('vendor-script')
<!-- END: Page Vendor JS-->
<!-- BEGIN: Theme JS-->
<script src="{{ asset(mix('assets/js/main.js')) }}"></script>

<!-- END: Theme JS-->

<!-- BEGIN: Page JS-->
@yield('page-script')
<script src="{{ asset('assets/js/arena-nav.js') }}?v=6"></script>
<!-- END: Page JS-->
