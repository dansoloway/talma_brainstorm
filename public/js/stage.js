/*
 * Participant stage page: selection + autosave.
 *
 * Rules (see .cursor/rules/autosave.mdc):
 *  - save ~2s after typing stops, only if the text changed
 *  - save immediately on blur, navigation, tab hidden and page close
 *  - keep a localStorage copy on every keystroke; clear it only after the server confirms that exact text
 *  - on failure keep the text and retry with backoff, forever; refresh the CSRF token on 419
 */
(function () {
  'use strict';

  var config = JSON.parse(document.getElementById('stage-config').textContent);
  var T = config.text;
  var DEBOUNCE_MS = 2000;
  var PING_MS = 5 * 60 * 1000;
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var counterEl = document.getElementById('counter');
  var toastEl = document.getElementById('toast');
  var navigating = false;
  var signedOut = false;

  // ---------- localStorage (never let it break the page) ----------
  var storePrefix = 'pwa:' + config.participantId + ':';
  function storeGet(promptId) {
    try { return JSON.parse(localStorage.getItem(storePrefix + promptId)); } catch (e) { return null; }
  }
  function storeSet(promptId, text) {
    try { localStorage.setItem(storePrefix + promptId, JSON.stringify({ text: text, t: Date.now() })); } catch (e) {}
  }
  function storeClearIf(promptId, text) {
    try {
      var cur = storeGet(promptId);
      if (cur && cur.text === text) localStorage.removeItem(storePrefix + promptId);
    } catch (e) {}
  }
  function storeKeys() {
    var ids = [];
    try {
      for (var i = 0; i < localStorage.length; i++) {
        var k = localStorage.key(i);
        if (k && k.indexOf(storePrefix) === 0) ids.push(k.slice(storePrefix.length));
      }
    } catch (e) {}
    return ids;
  }

  // ---------- HTTP ----------
  function url(name, id) { return config.urls[name].replace('__ID__', id); }

  function request(method, href, body, opts) {
    opts = opts || {};
    return fetch(href, {
      method: method,
      credentials: 'same-origin',
      keepalive: !!opts.keepalive,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body ? JSON.stringify(body) : undefined
    });
  }

  function refreshCsrf() {
    return fetch(config.urls.ping, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (r.status === 401) { signedOut = true; throw new Error('signed out'); }
        return r.json();
      })
      .then(function (d) { if (d && d.csrf) csrf = d.csrf; });
  }

  function toast(msg) {
    toastEl.textContent = msg;
    toastEl.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toastEl.hidden = true; }, 6000);
  }

  // ---------- one Box per prompt ----------
  var boxes = [];

  function Box(li) {
    this.li = li;
    this.id = li.getAttribute('data-prompt-id');
    this.checkbox = li.querySelector('.pick-box');
    this.writer = li.querySelector('.writer');
    this.textarea = li.querySelector('.answer');
    this.statusEl = li.querySelector('.status');
    this.keptNote = li.querySelector('.kept-note');
    this.serverText = this.textarea.value;     // last text the server confirmed
    this.savedAt = parseInt(li.getAttribute('data-saved-at'), 10) || 0;
    this.timer = null;
    this.inflight = null;                      // Promise while a save is running
    this.saveSeq = 0;                          // monotonic; server ignores older seqs
    this.retryDelay = 2000;
    this.retryTimer = null;
    this.failed = false;
    this.userEdited = false;                   // true once an input event happened on this page
  }

  Box.prototype.selected = function () { return this.li.getAttribute('data-selected') === '1'; };
  Box.prototype.dirty = function () { return this.textarea.value !== this.serverText; };

  Box.prototype.setStatus = function (key) {
    this.statusEl.textContent = key ? T[key] : '';
    this.statusEl.setAttribute('data-state', key || '');
  };

  Box.prototype.onInput = function () {
    var self = this;
    self.userEdited = true;
    if (config.preview) { self.setStatus('previewNote'); return; }
    storeSet(self.id, self.textarea.value);
    if (!self.failed) self.setStatus(null);
    clearTimeout(self.timer);
    self.timer = setTimeout(function () { self.save(); }, DEBOUNCE_MS);
  };

  /** Save now. Resolves true when the server holds the current text. */
  Box.prototype.save = function (opts) {
    var self = this;
    opts = opts || {};
    clearTimeout(self.timer);
    if (config.preview || signedOut) return Promise.resolve(!self.dirty());
    if (!self.dirty()) {
      if (self.serverText !== '' || self.savedAt) self.setStatus('saved');
      return Promise.resolve(true);
    }
    if (self.inflight && !opts.keepalive) {
      // Wait for the running save, then save again if the text moved on.
      return self.inflight.then(function () { return self.save(); });
    }
    // Keepalive may overlap an in-flight save (tab hide / page close). client_seq
    // on the server ensures an older response never overwrites newer text.

    var text = self.textarea.value;
    var seq = ++self.saveSeq;
    clearTimeout(self.retryTimer);
    self.setStatus('saving');

    var p = request('PUT', url('save', self.id), {
      body: text,
      clear: text === '' && self.userEdited,
      client_seq: seq
    }, opts)
      .then(function (r) {
        if (r.ok) return r.json();
        var err = new Error('HTTP ' + r.status);
        err.status = r.status;
        throw err;
      })
      .then(function (data) {
        // A newer save started after this one — ignore this response for local state.
        if (seq !== self.saveSeq) {
          if (self.inflight === p) self.inflight = null;
          return self.dirty() ? self.save() : true;
        }

        // Server rejected an empty wipe (or a stale seq): sync to what it actually holds.
        if (data && data.applied === false && typeof data.body === 'string') {
          self.serverText = data.body;
        } else {
          self.serverText = text;
        }
        self.savedAt = Date.now();
        self.failed = false;
        self.retryDelay = 2000;
        storeClearIf(self.id, self.serverText);
        self.inflight = null;
        if (self.dirty()) return self.save();
        self.setStatus('saved');
        return true;
      })
      .catch(function (err) {
        if (self.inflight === p) self.inflight = null;
        // Newer save owns the slot; don't start retry storms for a superseded request.
        if (seq !== self.saveSeq) return self.dirty() ? self.save() : false;
        self.failed = true;
        if (err && err.status === 401) {
          signedOut = true;
          self.setStatus('signedOut');
          return false;
        }
        self.setStatus('retrying');
        var delay = self.retryDelay;
        self.retryDelay = Math.min(self.retryDelay * 2, 30000);
        var retry = (err && err.status === 419)
          ? function () { refreshCsrf().then(function () { self.save(); }, function () { self.scheduleRetry(); }); }
          : function () { self.save(); };
        self.retryTimer = setTimeout(retry, delay);
        return false;
      });

    self.inflight = p;
    return p;
  };

  Box.prototype.scheduleRetry = function () {
    var self = this;
    if (signedOut) { self.setStatus('signedOut'); return; }
    self.retryTimer = setTimeout(function () { self.save(); }, self.retryDelay);
  };

  /** Restore a newer local copy (e.g. after a failed save or a crash). */
  Box.prototype.restoreLocal = function () {
    var local = storeGet(this.id);
    if (!local || typeof local.text !== 'string') return;
    if (local.text === this.serverText) { storeClearIf(this.id, local.text); return; }
    if (local.t > this.savedAt) {
      this.textarea.value = local.text;
      if (local.text !== '') this.userEdited = true;
      this.save();
    }
  };

  Box.prototype.showWriter = function (show) {
    this.writer.hidden = !show;
    this.li.classList.toggle('is-selected', show);
    this.li.setAttribute('data-selected', show ? '1' : '0');
    this.keptNote.hidden = show || this.textarea.value.trim() === '';
  };

  Box.prototype.toggle = function () {
    var self = this;
    var want = self.checkbox.checked;

    if (config.preview) {
      if (want && selectedCount() >= config.max) { self.checkbox.checked = false; toast(T.atMax); return; }
      self.showWriter(want);
      updateCounter();
      return;
    }

    self.checkbox.disabled = true;
    var before = want ? Promise.resolve() : self.save();   // flush text before dropping

    before.then(function () {
      return request('POST', url(want ? 'select' : 'deselect', self.id));
    }).then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, status: r.status, data: d }; });
    }).then(function (res) {
      if (res.status === 419) { return refreshCsrf().then(function () { self.checkbox.checked = !want; self.checkbox.disabled = false; toast(T.network); }); }
      if (!res.ok) {
        self.checkbox.checked = !want;
        var msg = res.data && res.data.errors && res.data.errors.prompt ? res.data.errors.prompt[0] : T.network;
        toast(msg);
        return;
      }
      self.showWriter(want);
      if (want) self.textarea.focus();
      updateCounter(res.data.selected_count);
    }).catch(function () {
      self.checkbox.checked = !want;
      toast(T.network);
    }).then(function () {
      self.checkbox.disabled = false;
      updateCounter();
    });
  };

  // ---------- page-level helpers ----------
  function selectedCount() {
    return boxes.filter(function (b) { return b.selected(); }).length;
  }

  function updateCounter() {
    var n = selectedCount();
    counterEl.textContent = n === 0 ? T.counterNone : T.counter.replace(':n', n).replace(':max', config.max);
    counterEl.classList.toggle('is-warning', n === 0);
    counterEl.classList.toggle('is-full', n >= config.max);
    boxes.forEach(function (b) {
      if (!b.selected()) {
        // Preview / view-as keep inputs disabled; never unlock them here.
        b.checkbox.disabled = config.preview || n >= config.max;
        b.li.classList.toggle('is-locked', !config.preview && n >= config.max);
        b.checkbox.title = (!config.preview && n >= config.max) ? T.atMax : '';
      }
    });
  }

  function anyUnsaved() {
    return boxes.some(function (b) { return b.dirty() || b.inflight; });
  }

  /** Save everything now; resolves true if all text reached the server. */
  function flushAll(timeoutMs) {
    var all = Promise.all(boxes.map(function (b) { return b.save(); }))
      .then(function (r) { return r.every(Boolean); });
    var timeout = new Promise(function (res) { setTimeout(function () { res(false); }, timeoutMs || 8000); });
    return Promise.race([all, timeout]);
  }

  /** Fire-and-forget saves that survive the page closing. */
  function beaconAll() {
    boxes.forEach(function (b) { if (b.dirty()) b.save({ keepalive: true }); });
  }

  /** Text saved locally for prompts on OTHER stages (e.g. a failed save before leaving). */
  function syncOtherLocalCopies() {
    var here = {};
    boxes.forEach(function (b) { here[b.id] = true; });
    storeKeys().forEach(function (id) {
      if (here[id]) return;
      var local = storeGet(id);
      if (!local || typeof local.text !== 'string' || local.text === '') return;
      request('PUT', url('save', id), { body: local.text, clear: false, client_seq: Date.now() })
        .then(function (r) { if (r.ok) storeClearIf(id, local.text); })
        .catch(function () {});
    });
  }

  // ---------- wire up ----------
  document.querySelectorAll('.prompt[data-prompt-id]').forEach(function (li) {
    var b = new Box(li);
    boxes.push(b);
    b.textarea.addEventListener('input', function () { b.onInput(); autoGrow(b.textarea); });
    b.textarea.addEventListener('blur', function () { b.save(); });
    b.checkbox.addEventListener('change', function () { b.toggle(); });
    autoGrow(b.textarea);
    if (!config.preview) b.restoreLocal();
    if (b.serverText !== '' && !b.dirty()) b.setStatus('saved');
  });
  updateCounter();

  if (!config.preview) {
    syncOtherLocalCopies();

    document.querySelectorAll('a[data-nav]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var href = a.href;
        flushAll().then(function (ok) {
          if (ok || window.confirm(T.leaveUnsaved)) {
            navigating = true;
            window.location.href = href;
          }
        });
      });
    });

    var doneForm = document.querySelector('form[data-done]');
    if (doneForm) {
      doneForm.addEventListener('submit', function (e) {
        e.preventDefault();
        flushAll().then(function (ok) {
          if (ok || window.confirm(T.leaveUnsaved)) {
            navigating = true;
            doneForm.querySelector('input[name="_token"]').value = csrf;
            doneForm.submit();
          }
        });
      });
    }

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') beaconAll();
    });
    window.addEventListener('pagehide', beaconAll);
    window.addEventListener('beforeunload', function (e) {
      if (!navigating && anyUnsaved()) { e.preventDefault(); e.returnValue = ''; }
    });
    window.addEventListener('online', function () { boxes.forEach(function (b) { if (b.dirty()) b.save(); }); });

    setInterval(function () { refreshCsrf().catch(function () {}); }, PING_MS);
  }

  function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = Math.max(el.scrollHeight + 2, 120) + 'px';
  }
})();
