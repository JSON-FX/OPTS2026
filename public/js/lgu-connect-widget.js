/* LGU Connect host loader. The host owns its authenticated launch callback. */
(function (global) {
  "use strict";
  var types = {
    ready: "lgu-connect.widget.ready.v1",
    launchRequest: "lgu-connect.widget.launch-request.v1",
    launchReady: "lgu-connect.widget.launch-ready.v1",
    launchFailed: "lgu-connect.widget.launch-failed.v1",
    visibility: "lgu-connect.widget.visibility.v1",
    close: "lgu-connect.widget.close.v1",
    badge: "lgu-connect.widget.badge.v1",
  };
  var reference = /^[A-Za-z0-9_-]{43}$/;
  var requestId = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

  function originOf(value) {
    try {
      var url = new URL(value);
      if (url.protocol !== "https:" || url.origin !== value || url.username || url.password
        || url.pathname !== "/" || url.search || url.hash) return null;
      return url.origin;
    } catch { return null; }
  }

  function validReference(value) {
    if (typeof value !== "string" || !reference.test(value)) return false;
    try {
      var plain = value.replace(/-/g, "+").replace(/_/g, "/");
      return global.btoa(global.atob(plain + "=")).replace(/\+/g, "-").replace(/\//g, "_").replace(/=/g, "") === value;
    } catch { return false; }
  }

  function mount(options) {
    var origin = originOf(options && options.connectOrigin);
    if (!origin || typeof options.launch !== "function") throw new Error("Invalid LGU Connect widget configuration");
    var host = options.root || global.document.body;
    if (!host || typeof host.appendChild !== "function") throw new Error("Widget root is unavailable");
    var document = global.document;
    var wrapper = document.createElement("div");
    wrapper.setAttribute("data-lgu-connect-widget", "");
    wrapper.style.cssText = "position:fixed;right:20px;bottom:20px;z-index:2147483000;font:14px system-ui,sans-serif";
    var button = document.createElement("button");
    button.type = "button";
    button.setAttribute("aria-label", "Open LGU Connect");
    button.setAttribute("aria-expanded", "false");
    button.style.cssText = "display:flex;align-items:center;gap:9px;border:0;border-radius:999px;padding:9px 16px 9px 9px;background:#10263e;color:white;cursor:pointer;box-shadow:0 4px 18px #0003";
    var seal = document.createElement("img");
    seal.src = origin + "/lgu-seal.png";
    seal.alt = "";
    seal.width = 32;
    seal.height = 32;
    seal.style.cssText = "display:block;width:32px;height:32px;object-fit:contain";
    var label = document.createElement("span");
    label.textContent = "Connect";
    button.appendChild(seal);
    button.appendChild(label);
    var panel = document.createElement("div");
    panel.hidden = true;
    panel.setAttribute("role", "dialog");
    panel.setAttribute("aria-label", "LGU Connect conversations");
    panel.style.cssText = "position:absolute;right:0;bottom:64px;width:min(390px,calc(100vw - 24px));height:min(630px,calc(100dvh - 96px));background:white;border:1px solid #cad4df;border-radius:14px;overflow:hidden;box-shadow:0 12px 40px #0004";
    var iframe = null;
    var ready = false;
    var destroyed = false;
    var pending = null;
    var timer = null;
    var launchAbort = null;
    var launchTimer = null;
    var opener = null;
    var badge = 0;

    function status(message) {
      panel.textContent = "";
      var text = document.createElement("p");
      text.setAttribute("role", "status");
      text.style.cssText = "padding:20px;line-height:1.5;color:#243b53";
      text.textContent = message;
      panel.appendChild(text);
      if (message !== "Opening LGU Connect…") {
        var retry = document.createElement("button");
        retry.type = "button";
        retry.textContent = "Retry Connect";
        retry.style.cssText = "margin:0 20px;padding:8px 12px;border:1px solid #10263e;border-radius:6px;background:white;color:#10263e;cursor:pointer";
        retry.addEventListener("click", function () { iframe = null; open(); });
        panel.appendChild(retry);
      }
    }
    function showVisibility(value) {
      if (ready && iframe && iframe.contentWindow) {
        iframe.contentWindow.postMessage({ type: types.visibility, visible: value }, origin);
      }
    }
    function open() {
      if (destroyed) return;
      opener = document.activeElement instanceof global.HTMLElement ? document.activeElement : button;
      panel.hidden = false;
      button.setAttribute("aria-expanded", "true");
      button.setAttribute("aria-label", "Minimize LGU Connect");
      if (ready && iframe) { showVisibility(true); iframe.focus(); return; }
      if (iframe) return;
      status("Opening LGU Connect…");
      iframe = document.createElement("iframe");
      iframe.title = "LGU Connect conversations";
      iframe.setAttribute("referrerpolicy", "strict-origin-when-cross-origin");
      iframe.setAttribute("allow", "clipboard-write");
      iframe.style.cssText = "display:block;width:100%;height:100%;border:0";
      iframe.src = origin + "/embed";
      panel.textContent = "";
      panel.appendChild(iframe);
      timer = global.setTimeout(function () {
        if (ready || destroyed) return;
        iframe.remove(); iframe = null;
        status("Connect did not open. Your application remains available.");
      }, 10000);
    }
    function close() {
      if (destroyed) return;
      panel.hidden = true;
      button.setAttribute("aria-expanded", "false");
      button.setAttribute("aria-label", badge ? "Open LGU Connect, " + badge + " unread messages" : "Open LGU Connect");
      showVisibility(false);
      if (opener && opener.isConnected) opener.focus(); else button.focus();
    }
    function onMessage(event) {
      if (destroyed || !iframe || event.origin !== origin || event.source !== iframe.contentWindow
        || !event.data || typeof event.data !== "object" || Array.isArray(event.data)) return;
      var data = event.data;
      if (data.type === types.ready && Object.keys(data).length === 1) {
        ready = true;
        global.clearTimeout(timer);
        showVisibility(!panel.hidden);
        if (!panel.hidden) iframe.focus();
        return;
      }
      if (data.type === types.close && Object.keys(data).length === 1) { close(); return; }
      if (data.type === types.badge && Object.keys(data).length === 2 && Number.isSafeInteger(data.count)
        && data.count >= 0 && data.count <= 99) {
        badge = data.count;
        if (typeof options.onBadge === "function") options.onBadge(badge);
        if (panel.hidden) button.setAttribute("aria-label", badge
          ? "Open LGU Connect, " + badge + " unread messages" : "Open LGU Connect");
        return;
      }
      if (data.type !== types.launchRequest || Object.keys(data).length !== 2
        || !requestId.test(data.request_id) || pending) return;
      pending = data.request_id;
      var controller = new AbortController();
      launchAbort = controller;
      var deadline = new Promise(function (_, reject) {
        launchTimer = global.setTimeout(function () { controller.abort(); reject(new Error("Host launch timed out")); }, 9000);
      });
      Promise.race([Promise.resolve().then(function () { return options.launch({ signal: controller.signal }); }), deadline])
        .then(function (result) {
          if (destroyed || !iframe || pending !== data.request_id || controller.signal.aborted) return;
          if (!result || !validReference(result.launch_ref)) throw new Error("Invalid host launch");
          iframe.contentWindow.postMessage({ type: types.launchReady,
            request_id: data.request_id, launch_ref: result.launch_ref }, origin);
        }).catch(function () {
          if (!destroyed && iframe && pending === data.request_id) {
            iframe.contentWindow.postMessage({ type: types.launchFailed, request_id: data.request_id }, origin);
          }
        }).finally(function () { global.clearTimeout(launchTimer); launchAbort = null; launchTimer = null; pending = null; });
    }
    button.addEventListener("click", function () { if (panel.hidden) open(); else close(); });
    wrapper.appendChild(button);
    wrapper.appendChild(panel);
    host.appendChild(wrapper);
    global.addEventListener("message", onMessage);
    return {
      open: open, close: close,
      destroy: function () { destroyed = true; global.clearTimeout(timer); global.clearTimeout(launchTimer);
        launchAbort?.abort(); global.removeEventListener("message", onMessage);
        wrapper.remove(); iframe = null; },
    };
  }

  global.LGUConnectWidget = Object.freeze({ mount: mount });
})(window);
