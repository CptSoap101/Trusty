/* Connects the Trusty Ofisina app to the Hostinger server.
   The app was built for Claude's window.claude.use("db" | "assets" | "downloads") interface;
   this file provides the same interface on top of api.php, so app.html runs unchanged. */
(function () {
  "use strict";
  var CFG = window.TRUSTY || {};
  var API = CFG.api || "api.php";
  var POLL_MS = 5000;

  var store = {};        // "collection/id" -> record
  var listeners = [];    // { kind: "col" | "doc", path, next }
  var since = 0, ready = false, syncing = null;

  function err(code, message) { var e = new Error(message || code); e.code = code; return e; }

  function call(action, body, isForm) {
    var opts = { method: body ? "POST" : "GET", credentials: "same-origin", headers: { "X-CSRF": CFG.csrf || "" } };
    if (body) {
      if (isForm) opts.body = body;
      else { opts.headers["Content-Type"] = "application/json"; opts.body = JSON.stringify(body); }
    }
    return fetch(API + "?action=" + action, opts).then(function (r) {
      if (r.status === 401) { location.href = "login.php"; throw err("unauthenticated", "Please log in again."); }
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) throw err(j.code || "unavailable", j.error || "The server couldn't save that. Try again.");
        return j;
      });
    }, function () { throw err("unavailable", "Can't reach the server. Check your internet connection."); });
  }

  function clone(v) { return v == null ? v : JSON.parse(JSON.stringify(v)); }
  function split(path) { var i = path.indexOf("/"); return { col: path.slice(0, i), id: path.slice(i + 1) }; }
  function meta() { return { fromCache: false, hasPendingWrites: false }; }
  function snapDoc(path) {
    var d = store[path];
    return { id: split(path).id, exists: d !== undefined, data: function () { return d; }, metadata: meta() };
  }
  function fire(l) {
    if (l.kind === "col") {
      var pre = l.path + "/";
      var docs = Object.keys(store).filter(function (k) { return k.indexOf(pre) === 0 && k.slice(pre.length).indexOf("/") < 0; }).map(snapDoc);
      l.next({ docs: docs, size: docs.length, empty: !docs.length, docChanges: function () { return []; }, metadata: meta() });
    } else {
      l.next(snapDoc(l.path));
    }
  }
  function emit() { listeners.slice().forEach(function (l) { try { fire(l); } catch (e) { console.error(e); } }); }

  function sync() {
    if (syncing) return syncing;
    syncing = call("sync&since=" + since).then(function (j) {
      var changed = false;
      (j.docs || []).forEach(function (d) {
        var k = d.col + "/" + d.id;
        if (d.deleted) { if (k in store) { delete store[k]; changed = true; } }
        else if (JSON.stringify(store[k]) !== JSON.stringify(d.data)) { store[k] = d.data; changed = true; }
      });
      since = j.now || since;
      if (changed || !ready) { ready = true; emit(); }
    }).catch(function (e) { console.warn("Sync failed", e); })
      .then(function () { syncing = null; });
    return syncing;
  }

  function write(action, path, data, local) {
    var p = split(path);
    local();
    emit();
    return call(action, { col: p.col, id: p.id, data: data }).then(function () { return undefined; }, function (e) {
      since = 0; store = {}; sync(); // put the screen back to what the server has
      throw e;
    });
  }

  function randomId() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 10); }

  function docRef(path) {
    return {
      id: split(path).id, path: path,
      get: function () { return Promise.resolve(snapDoc(path)); },
      set: function (data) { var c = clone(data); return write("set", path, c, function () { store[path] = c; }); },
      update: function (data) {
        if (store[path] === undefined) return Promise.reject(err("invalid_argument", "That record no longer exists."));
        var c = clone(data);
        return write("update", path, c, function () { var n = Object.assign({}, store[path]); Object.keys(c).forEach(function (k) { n[k] = c[k]; }); store[path] = n; });
      },
      delete: function () { return write("delete", path, null, function () { delete store[path]; }); },
      onSnapshot: function (next) { var l = { kind: "doc", path: path, next: next }; listeners.push(l); if (ready) fire(l); else sync(); return function () { listeners = listeners.filter(function (x) { return x !== l; }); }; },
      collection: function (sub) { return colRef(path + "/" + sub); }
    };
  }
  function colRef(path) {
    return {
      path: path,
      doc: function (id) { return docRef(path + "/" + (id || randomId())); },
      add: function (data) { var r = docRef(path + "/" + randomId()); return r.set(data).then(function () { return r; }); },
      onSnapshot: function (next) { var l = { kind: "col", path: path, next: next }; listeners.push(l); if (ready) fire(l); else sync(); return function () { listeners = listeners.filter(function (x) { return x !== l; }); }; },
      get: function () { return sync().then(function () { var out; fire({ kind: "col", path: path, next: function (s) { out = s; } }); return out; }); }
    };
  }

  var db = { doc: docRef, collection: colRef };

  var assets = {
    upload: function (blob) {
      var fd = new FormData();
      fd.append("file", blob, (blob && blob.name) || "upload");
      return call("upload", fd, true);
    },
    delete: function (id) { return call("file_delete", { id: id }); },
    list: function () { return Promise.resolve({ assets: [], usage: {} }); }
  };

  var downloads = {
    save: function (req) {
      var data = req.data instanceof Blob ? req.data : new Blob([req.data]);
      var url = URL.createObjectURL(data);
      var a = document.createElement("a");
      a.href = url; a.download = req.filename || "download";
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 5000);
      return Promise.resolve({ status: "saved" });
    }
  };

  window.claude = {
    use: function (name) {
      return Promise.resolve(name === "db" ? db : name === "assets" ? assets : name === "downloads" ? downloads : null);
    }
  };

  setInterval(function () { if (!document.hidden) sync(); }, POLL_MS);
  document.addEventListener("visibilitychange", function () { if (!document.hidden) sync(); });
})();
