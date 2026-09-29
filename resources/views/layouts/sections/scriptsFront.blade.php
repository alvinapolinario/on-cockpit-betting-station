
<!-- BEGIN: Vendor JS-->
<script src="{{asset('assets/vendor/js/dropdown-hover.js')}}"></script>
<script src="{{asset('assets/vendor/js/mega-dropdown.js')}}"></script>
<script src="{{ asset(mix('assets/vendor/libs/popper/popper.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/js/bootstrap.js')) }}"></script>
@include('layouts/sections/bootstrapTooltipCompat')
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

@yield('vendor-script')
<!-- END: Page Vendor JS-->
<!-- BEGIN: Theme JS-->
<script src="{{ asset(mix('assets/js/front-main.js')) }}"></script>
<!-- END: Theme JS-->

<!-- BEGIN: Page JS-->
@yield('page-script')
<!-- END: Page JS-->
