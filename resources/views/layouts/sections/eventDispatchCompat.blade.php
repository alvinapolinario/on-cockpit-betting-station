<script>
  (function () {
    if (!window.EventTarget || EventTarget.prototype.__nativeDispatchEventPatched) return;

    const originalDispatchEvent = EventTarget.prototype.dispatchEvent;

    function createNativeEvent(eventLike) {
      const nativeEvent = document.createEvent('Event');
      nativeEvent.initEvent(
        eventLike && eventLike.type ? eventLike.type : '',
        Boolean(eventLike && eventLike.bubbles),
        Boolean(eventLike && eventLike.cancelable)
      );

      if (eventLike && 'relatedTarget' in eventLike) {
        Object.defineProperty(nativeEvent, 'relatedTarget', {
          configurable: true,
          value: eventLike.relatedTarget || null
        });
      }

      return nativeEvent;
    }

    EventTarget.prototype.dispatchEvent = function (event) {
      try {
        return originalDispatchEvent.call(this, event);
      } catch (error) {
        const message = error && error.message ? error.message : '';

        if (
          message.indexOf("parameter 1 is not of type 'Event'") !== -1 &&
          event &&
          typeof event.type === 'string' &&
          typeof document !== 'undefined' &&
          typeof document.createEvent === 'function'
        ) {
          return originalDispatchEvent.call(this, createNativeEvent(event));
        }

        throw error;
      }
    };

    EventTarget.prototype.__nativeDispatchEventPatched = true;
  })();
</script>
