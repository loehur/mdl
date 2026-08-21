(function (window) {
  'use strict';

  if (!window.OpModal) {
    window.OpModal = (function () {
      function el(id) {
        if (!id) return null;
        if (id.charAt(0) === '#') id = id.slice(1);
        return document.getElementById(id);
      }

      function syncLock() {
        var n = document.querySelectorAll('.op-modal.is-open').length;
        document.body.classList.toggle('op-modal-open', n > 0);
      }

      function open(id, opts) {
        opts = opts || {};
        var modal = el(id);
        if (!modal) return null;
        var isStatic = opts.static === true || modal.getAttribute('data-op-static') === '1';
        modal.classList.toggle('is-static', isStatic);
        if (isStatic) {
          modal.setAttribute('data-op-static', '1');
        }
        if (!modal.classList.contains('is-open')) {
          modal.classList.add('is-open');
          modal.setAttribute('aria-hidden', 'false');
          syncLock();
          try {
            modal.dispatchEvent(new CustomEvent('op-modal:open', { bubbles: true }));
          } catch (e) {}
        }
        return modal;
      }

      function close(id) {
        var modal = typeof id === 'string' ? el(id) : id;
        if (!modal || !modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        syncLock();
        try {
          modal.dispatchEvent(new CustomEvent('op-modal:close', { bubbles: true }));
        } catch (e) {}
      }

      function closeAll() {
        document.querySelectorAll('.op-modal.is-open').forEach(function (m) {
          m.classList.remove('is-open');
          m.setAttribute('aria-hidden', 'true');
          try {
            m.dispatchEvent(new CustomEvent('op-modal:close', { bubbles: true }));
          } catch (e) {}
        });
        syncLock();
      }

      if (!window.__opModalDelegatesBound) {
        window.__opModalDelegatesBound = true;
        document.addEventListener('click', function (e) {
          var closeBtn = e.target.closest('[data-op-close]');
          if (closeBtn) {
            var modalClose = closeBtn.closest('.op-modal');
            if (!modalClose) return;
            if (closeBtn.classList.contains('op-modal__backdrop') && modalClose.classList.contains('is-static')) {
              return;
            }
            e.preventDefault();
            window.OpModal.close(modalClose);
            return;
          }
          var trigger = e.target.closest('[data-op-target]');
          if (!trigger) return;
          var target = trigger.getAttribute('data-op-target');
          if (!target) return;
          e.preventDefault();
          window.OpModal.open(target.replace(/^#/, ''));
        });
        document.addEventListener('keydown', function (e) {
          if (e.key !== 'Escape') return;
          var openModals = document.querySelectorAll('.op-modal.is-open:not(.is-static)');
          if (!openModals.length) return;
          window.OpModal.close(openModals[openModals.length - 1]);
        });
      }

      return { open: open, close: close, closeAll: closeAll, syncLock: syncLock };
    })();
  }
})(window);
