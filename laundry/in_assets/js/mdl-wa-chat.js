/**
 * Renderer riwayat chat WA — satu sumber untuk Delivery + Notifikasi Permintaan.
 * Markup: .mdl-wa-chat / .mdl-wa-chat__bubble--me|customer
 */
(function (window) {
  'use strict';

  var MEDIA_PROXY = 'https://api.nalju.com/CRM/Chat/media?id=';

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fmtTime(t) {
    if (!t) return '';
    var d = new Date(String(t).replace(' ', 'T'));
    if (isNaN(d.getTime())) {
      var s = String(t);
      return s.length >= 16 ? s.slice(5, 16).replace('-', '/') : s;
    }
    var dd = String(d.getDate()).padStart(2, '0');
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    var hh = String(d.getHours()).padStart(2, '0');
    var mi = String(d.getMinutes()).padStart(2, '0');
    return dd + '/' + mm + ' ' + hh + ':' + mi;
  }

  function mediaSrc(m) {
    if (m && m.media_url) return String(m.media_url);
    if (m && m.media_id) return MEDIA_PROXY + encodeURIComponent(String(m.media_id));
    return '';
  }

  /** WhatsApp formatting: *bold* _italic_ ~strike~ ```mono``` + links */
  function parseWhatsAppFormatting(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\s+\|\s+\|\s+/g, '\n')
      .replace(/```([^`]+)```/g, '<code>$1</code>')
      .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
      .replace(/_([^_]+)_/g, '<em>$1</em>')
      .replace(/~([^~]+)~/g, '<del>$1</del>')
      .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  }

  function sourceLabel(m) {
    var s = (m && m.source) ? String(m.source).toLowerCase() : '';
    if (s === 'fonnte') return 'Fonnte';
    if (s === 'ycloud') return 'yCloud';
    return '';
  }

  function renderBubble(m) {
    var isMe = m && m.sender === 'me';
    var who = isMe ? 'Laundry' : 'Pelanggan';
    var srcName = sourceLabel(m);
    if (srcName) {
      who = who + ' · ' + srcName;
    }
    var cls = isMe ? 'mdl-wa-chat__bubble--me' : 'mdl-wa-chat__bubble--customer';
    var caption = (m && m.text && String(m.text).trim()) ? String(m.text) : '';
    var src = mediaSrc(m);
    var type = (m && m.type) ? String(m.type) : 'text';
    var body = '';

    if (type === 'image' && src) {
      body = '<img class="mdl-wa-chat__img" src="' + escHtml(src) + '" alt="Gambar" loading="lazy"' +
        ' onclick="window.open(this.src,\'_blank\')">' +
        (caption ? '<div class="mdl-wa-chat__text">' + parseWhatsAppFormatting(caption) + '</div>' : '');
    } else if (type === 'sticker' && src) {
      body = '<img class="mdl-wa-chat__img" src="' + escHtml(src) + '" alt="Sticker" loading="lazy">';
    } else {
      body = '<div class="mdl-wa-chat__text">' +
        parseWhatsAppFormatting(caption || ('[' + type + ']')) +
        '</div>';
    }

    return '<div class="mdl-wa-chat__bubble ' + cls + '">' +
      '<div class="mdl-wa-chat__meta"><span>' + who + '</span><span>' + escHtml(fmtTime(m && m.time)) + '</span></div>' +
      body +
      '</div>';
  }

  /**
   * @param {object|array} data - {messages:[]} atau array messages
   * @param {object} [opts]
   * @param {string} [opts.emptyText]
   * @returns {string} HTML
   */
  function render(data, opts) {
    opts = opts || {};
    var msgs = Array.isArray(data) ? data : ((data && data.messages) || []);
    if (!msgs.length) {
      return '<div class="mdl-wa-chat__empty">' +
        escHtml(opts.emptyText || 'Tidak ada riwayat chat') +
        '</div>';
    }
    return '<div class="mdl-wa-chat">' + msgs.map(renderBubble).join('') + '</div>';
  }

  window.MdlWaChat = {
    render: render,
    fmtTime: fmtTime,
    parseWhatsAppFormatting: parseWhatsAppFormatting,
    mediaSrc: mediaSrc,
    escHtml: escHtml,
    MEDIA_PROXY: MEDIA_PROXY
  };
})(window);
