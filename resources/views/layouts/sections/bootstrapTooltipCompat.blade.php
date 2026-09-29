<script>
  (function () {
    window.disableBootstrapTooltips = true;

    if (!window.bootstrap || !window.bootstrap.Tooltip || window.bootstrap.Tooltip.__safeTooltipPatched) return;

    const NativeTooltip = window.bootstrap.Tooltip;
    const noopTooltip = {
      show: function () {},
      hide: function () {},
      toggle: function () {},
      dispose: function () {},
      update: function () {},
      enable: function () {},
      disable: function () {},
      toggleEnabled: function () {},
      setContent: function () {}
    };

    function isValidReferenceElement(element) {
      return element instanceof Element && typeof element.nodeName === 'string';
    }

    function SafeTooltip(element, config) {
      if (!isValidReferenceElement(element)) {
        return noopTooltip;
      }

      try {
        return new NativeTooltip(element, config);
      } catch (error) {
        return noopTooltip;
      }
    }

    Object.keys(NativeTooltip).forEach(function (key) {
      SafeTooltip[key] = NativeTooltip[key];
    });

    SafeTooltip.getInstance = function (element) {
      return isValidReferenceElement(element) ? NativeTooltip.getInstance(element) : null;
    };

    SafeTooltip.getOrCreateInstance = function (element, config) {
      return isValidReferenceElement(element) ? NativeTooltip.getOrCreateInstance(element, config) : noopTooltip;
    };

    SafeTooltip.__safeTooltipPatched = true;
    window.bootstrap.Tooltip = SafeTooltip;
  })();
</script>
