/*
 * Participant stage page: selection + autosave.
 *
 * Rules (see .cursor/rules/autosave.mdc):
 *  - save ~2s after typing stops, only if the text changed
 *  - save immediately on blur, navigation, tab hidden and page close
 *  - keep a localStorage copy on every keystroke; clear it only after the server confirms that exact text
 *  - on failure keep the text and retry with backoff, forever; refresh the CSRF token on 419
 *  - each selected prompt can hold several answer boxes ("Add Another Answer")
 */
(function () {
  'use strict';

  var config = JSON.parse(document.getElementById('stage-config').textContent);
  var T = config.text;
  var DEBOUNCE_MS = 2000;
  var PING_MS = 5 * 60 * 1000;
  var MAX_ANSWERS = config.maxAnswers || 5;
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var counterEl = document.getElementById('counter');
  var toastEl = document.getElementById('toast');
  var navigating = false;
  var signedOut = false;

  // ---------- localStorage (never let it break the page) ----------
  var storePrefix = 'pwa:' + config.participantId + ':';
  function storeKey(promptId, index) { return storePrefix + promptId + ':' + index; }
  function storeGet(promptId, index) {
    try { return JSON.parse(localStorage.getItem(storeKey(promptId, index))); } catch (e) { return null; }
  }
  function storeSet(promptId, index, text) {
    try { localStorage.setItem(storeKey(promptId, index), JSON.stringify({ text: text, t: Date.now() })); } catch (e) {}
  }
  function storeClearIf(promptId, index, text) {
    try {
      var cur = storeGet(promptId, index);
      if (cur && cur.text === text) localStorage.removeItem(storeKey(promptId, index));
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

  function answerLabel(n) {
    return (T.answerLabel || 'Answer :n').replace(':n', String(n));
  }

  // ---------- one answer slot ----------
  function Answer(box, block) {
    this.box = box;
    this.block = block;
    this.index = parseInt(block.getAttribute('data-answer-index'), 10) || 0;
    this.textarea = block.querySelector('.answer');
    this.statusEl = block.querySelector('.status');
    this.serverText = this.textarea.value;
    this.savedAt = box.savedAt;
    this.timer = null;
    this.inflight = null;
    this.saveSeq = 0;
    this.retryDelay = 2000;
    this.retryTimer = null;
    this.failed = false;
    this.userEdited = false;
  }

  Answer.prototype.dirty = function () { return this.textarea.value !== this.serverText; };

  Answer.prototype.setStatus = function (key) {
    this.statusEl.textContent = key ? T[key] : '';
    this.statusEl.setAttribute('data-state', key || '');
  };

  Answer.prototype.onInput = function () {
    var self = this;
    self.userEdited = true;
    if (config.preview) { self.setStatus('previewNote'); return; }
    storeSet(self.box.id, self.index, self.textarea.value);
    if (!self.failed) self.setStatus(null);
    clearTimeout(self.timer);
    self.timer = setTimeout(function () { self.save(); }, DEBOUNCE_MS);
  };

  Answer.prototype.save = function (opts) {
    var self = this;
    opts = opts || {};
    clearTimeout(self.timer);
    if (config.preview || signedOut) return Promise.resolve(!self.dirty());
    if (!self.dirty()) {
      if (self.serverText !== '' || self.savedAt) self.setStatus('saved');
      return Promise.resolve(true);
    }
    if (self.inflight && !opts.keepalive) {
      return self.inflight.then(function () { return self.save(); });
    }

    var text = self.textarea.value;
    var seq = ++self.saveSeq;
    clearTimeout(self.retryTimer);
    self.setStatus('saving');

    var p = request('PUT', url('save', self.box.id), {
      body: text,
      clear: text === '' && self.userEdited,
      client_seq: seq,
      answer_index: self.index
    }, opts)
      .then(function (r) {
        if (r.ok) return r.json();
        var err = new Error('HTTP ' + r.status);
        err.status = r.status;
        throw err;
      })
      .then(function (data) {
        if (seq !== self.saveSeq) {
          if (self.inflight === p) self.inflight = null;
          return self.dirty() ? self.save() : true;
        }

        if (data && data.applied === false && typeof data.body === 'string') {
          self.serverText = data.body;
        } else {
          self.serverText = text;
        }
        self.savedAt = Date.now();
        self.failed = false;
        self.retryDelay = 2000;
        storeClearIf(self.box.id, self.index, self.serverText);
        self.inflight = null;
        if (self.dirty()) return self.save();
        self.setStatus('saved');
        return true;
      })
      .catch(function (err) {
        if (self.inflight === p) self.inflight = null;
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

  Answer.prototype.scheduleRetry = function () {
    var self = this;
    if (signedOut) { self.setStatus('signedOut'); return; }
    self.retryTimer = setTimeout(function () { self.save(); }, self.retryDelay);
  };

  Answer.prototype.restoreLocal = function () {
    var local = storeGet(this.box.id, this.index);
    if (!local || typeof local.text !== 'string') return;
    if (local.text === this.serverText) { storeClearIf(this.box.id, this.index, local.text); return; }
    if (local.t > this.savedAt) {
      this.textarea.value = local.text;
      if (local.text !== '') this.userEdited = true;
      this.save();
    }
  };

  // ---------- one Box per prompt ----------
  var boxes = [];

  function Box(li) {
    this.li = li;
    this.id = li.getAttribute('data-prompt-id');
    this.checkbox = li.querySelector('.pick-box');
    this.writer = li.querySelector('.writer');
    this.answersEl = li.querySelector('.answers');
    this.addBtn = li.querySelector('.add-answer');
    this.keptNote = li.querySelector('.kept-note');
    this.savedAt = parseInt(li.getAttribute('data-saved-at'), 10) || 0;
    this.answers = [];
    this.bindExistingAnswers();
    if (this.addBtn) {
      this.addBtn.addEventListener('click', function () { this.addAnswer(); }.bind(this));
    }
    this.refreshAddButton();
  }

  Box.prototype.bindExistingAnswers = function () {
    var self = this;
    self.answers = [];
    self.answersEl.querySelectorAll('.answer-block').forEach(function (block) {
      self.answers.push(self.wireAnswer(block));
    });
    self.refreshAnswerLabels();
  };

  Box.prototype.wireAnswer = function (block) {
    var answer = new Answer(this, block);
    answer.textarea.addEventListener('input', function () { answer.onInput(); autoGrow(answer.textarea); });
    answer.textarea.addEventListener('blur', function () { answer.save(); });
    autoGrow(answer.textarea);
    if (!config.preview) answer.restoreLocal();
    if (answer.serverText !== '' && !answer.dirty()) answer.setStatus('saved');
    return answer;
  };

  Box.prototype.selected = function () { return this.li.getAttribute('data-selected') === '1'; };

  Box.prototype.hasText = function () {
    return this.answers.some(function (a) { return a.textarea.value.trim() !== ''; });
  };

  Box.prototype.dirty = function () {
    return this.answers.some(function (a) { return a.dirty() || a.inflight; });
  };

  Box.prototype.save = function (opts) {
    return Promise.all(this.answers.map(function (a) { return a.save(opts); }))
      .then(function (r) { return r.every(Boolean); });
  };

  Box.prototype.refreshAnswerLabels = function () {
    var many = this.answers.length > 1;
    this.answers.forEach(function (a, i) {
      var label = a.block.querySelector('.answer-label');
      if (!many) {
        if (label) label.remove();
        return;
      }
      if (!label) {
        label = document.createElement('p');
        label.className = 'answer-label';
        a.block.insertBefore(label, a.textarea);
      }
      label.textContent = answerLabel(i + 1);
    });
  };

  Box.prototype.refreshAddButton = function () {
    if (!this.addBtn) return;
    this.addBtn.disabled = config.preview || this.answers.length >= MAX_ANSWERS;
  };

  Box.prototype.addAnswer = function (text) {
    if (this.answers.length >= MAX_ANSWERS) {
      toast(T.answersAtMax || T.atMax);
      return null;
    }
    var index = this.answers.length;
    var block = document.createElement('div');
    block.className = 'answer-block';
    block.setAttribute('data-answer-index', String(index));
    block.innerHTML =
      '<p class="answer-label"></p>' +
      '<textarea class="answer" rows="5" aria-label="' + (T.answerLabel || 'Answer') + '"></textarea>' +
      '<span class="status" aria-live="polite"></span>';
    var textarea = block.querySelector('.answer');
    textarea.dir = this.li.getAttribute('dir') || 'auto';
    textarea.value = typeof text === 'string' ? text : '';
    if (config.preview) textarea.readOnly = true;
    this.answersEl.appendChild(block);
    var answer = this.wireAnswer(block);
    this.answers.push(answer);
    this.refreshAnswerLabels();
    this.refreshAddButton();
    if (!config.preview) answer.textarea.focus();
    return answer;
  };

  Box.prototype.setAnswers = function (answers) {
    var list = Array.isArray(answers) && answers.length ? answers : [''];
    this.answersEl.innerHTML = '';
    this.answers = [];
    var self = this;
    list.forEach(function (text, index) {
      var block = document.createElement('div');
      block.className = 'answer-block';
      block.setAttribute('data-answer-index', String(index));
      block.innerHTML =
        '<textarea class="answer" rows="5" aria-label="' + (T.answerLabel || 'Answer') + '"></textarea>' +
        '<span class="status" aria-live="polite"></span>';
      var textarea = block.querySelector('.answer');
      textarea.dir = self.li.getAttribute('dir') || 'auto';
      textarea.value = typeof text === 'string' ? text : '';
      if (config.preview) textarea.readOnly = true;
      self.answersEl.appendChild(block);
      self.answers.push(self.wireAnswer(block));
    });
    this.refreshAnswerLabels();
    this.refreshAddButton();
  };

  Box.prototype.showWriter = function (show) {
    this.writer.hidden = !show;
    this.li.classList.toggle('is-selected', show);
    this.li.setAttribute('data-selected', show ? '1' : '0');
    this.keptNote.hidden = show || !this.hasText();
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
    var before = want ? Promise.resolve() : self.save();

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
      if (want && res.data && Array.isArray(res.data.answers)) {
        self.setAnswers(res.data.answers);
      }
      self.showWriter(want);
      if (want && self.answers[0]) self.answers[0].textarea.focus();
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
        b.checkbox.disabled = config.preview || n >= config.max;
        b.li.classList.toggle('is-locked', !config.preview && n >= config.max);
        b.checkbox.title = (!config.preview && n >= config.max) ? T.atMax : '';
      }
    });
  }

  function anyUnsaved() {
    return boxes.some(function (b) { return b.dirty(); });
  }

  function flushAll(timeoutMs) {
    var all = Promise.all(boxes.map(function (b) { return b.save(); }))
      .then(function (r) { return r.every(Boolean); });
    var timeout = new Promise(function (res) { setTimeout(function () { res(false); }, timeoutMs || 8000); });
    return Promise.race([all, timeout]);
  }

  function beaconAll() {
    boxes.forEach(function (b) {
      b.answers.forEach(function (a) { if (a.dirty()) a.save({ keepalive: true }); });
    });
  }

  function syncOtherLocalCopies() {
    var here = {};
    boxes.forEach(function (b) { here[b.id] = true; });
    storeKeys().forEach(function (key) {
      var parts = key.split(':');
      var id = parts[0];
      var index = parseInt(parts[1] || '0', 10) || 0;
      if (here[id]) return;
      var local = storeGet(id, index);
      if (!local || typeof local.text !== 'string' || local.text === '') return;
      request('PUT', url('save', id), {
        body: local.text,
        clear: false,
        client_seq: Date.now(),
        answer_index: index
      })
        .then(function (r) { if (r.ok) storeClearIf(id, index, local.text); })
        .catch(function () {});
    });
  }

  // ---------- wire up ----------
  document.querySelectorAll('.prompt[data-prompt-id]').forEach(function (li) {
    var b = new Box(li);
    boxes.push(b);
    b.checkbox.addEventListener('change', function () { b.toggle(); });
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
    window.addEventListener('online', function () {
      boxes.forEach(function (b) {
        b.answers.forEach(function (a) { if (a.dirty()) a.save(); });
      });
    });

    setInterval(function () { refreshCsrf().catch(function () {}); }, PING_MS);
  }

  function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = Math.max(el.scrollHeight + 2, 120) + 'px';
  }
})();
