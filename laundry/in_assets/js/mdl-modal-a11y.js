/**
 * Global a11y fix: blur focused descendants before aria-hidden="true".
 * Prevents console warning when closing MDL modals (.op-modal, .mdl-chat-modal, etc.).
 */
(function (global) {
  "use strict";

  function releaseFocusWithin(root) {
    if (!root || root.nodeType !== 1) return;
    var active = document.activeElement;
    if (!active || active === document.body || active === document.documentElement) return;
    if (root === active || root.contains(active)) {
      try {
        active.blur();
      } catch (e) {}
    }
  }

  var nativeSetAttribute = Element.prototype.setAttribute;
  Element.prototype.setAttribute = function (name, value) {
    if (name === "aria-hidden" && String(value) === "true") {
      releaseFocusWithin(this);
    }
    return nativeSetAttribute.call(this, name, value);
  };

  global.MdlModalA11y = {
    releaseFocusWithin: releaseFocusWithin,
    beforeHide: releaseFocusWithin,
  };
})(typeof window !== "undefined" ? window : this);
