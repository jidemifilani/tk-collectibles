/* Admin panel behaviour. All changes go through /api/admin/* with the CSRF header. */
(function () {
  "use strict";

  function $(sel, el) { return (el || document).querySelector(sel); }
  function $all(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }
  var csrfMeta = $('meta[name="csrf-token"]');
  var CSRF = csrfMeta ? csrfMeta.content : "";
  var baseMeta = $('meta[name="admin-base"]');
  var BASE = baseMeta && baseMeta.content ? baseMeta.content : "/admin";

  function api(url, method, body, isForm) {
    var headers = { "X-CSRF-Token": CSRF };
    if (!isForm && body !== undefined) headers["Content-Type"] = "application/json";
    return fetch(url, { method: method, headers: headers, body: isForm ? body : body === undefined ? undefined : JSON.stringify(body) })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (d) {
          if (!r.ok) throw new Error(d.error || "Request failed (" + r.status + ")");
          return d;
        });
      });
  }
  function showError(el, msg) { if (!el) return; el.textContent = msg; el.classList.toggle("hidden", !msg); }
  function upload(file, type) {
    var fd = new FormData();
    fd.append("file", file);
    fd.append("type", type || "product");
    return api("/api/admin/upload", "POST", fd, true).then(function (d) { return d.url; });
  }

  // ---------- login ----------
  var loginForm = $("#login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var btn = $("button[type=submit]", loginForm);
      btn.disabled = true; btn.textContent = "Signing in…";
      fetch("/api/admin/login", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password: $("#password").value, code: $("#code") ? $("#code").value : "" })
      }).then(function (r) {
        return r.json().then(function (d) {
          if (d.needCode) { $("#code-row").classList.remove("hidden"); $("#code").focus(); }
          if (!r.ok) throw new Error(d.error || "Invalid password");
        });
      }).then(function () {
        var from = new URLSearchParams(location.search).get("from") || BASE;
        location.href = from.charAt(0) === "/" && from.charAt(1) !== "/" ? from : BASE;
      }).catch(function (ex) {
        showError($("#login-error"), ex.message);
        btn.disabled = false; btn.textContent = "Sign in";
      });
    });
  }

  // ---------- logout ----------
  var logout = $("#admin-logout");
  if (logout) logout.addEventListener("click", function () {
    api("/api/admin/logout", "POST").catch(function () {}).then(function () { location.href = BASE + "/login"; });
  });

  // ---------- single image fields ----------
  $all(".image-field").forEach(function (field) {
    var file = $("[data-file]", field), url = $("[data-url]", field), preview = $("[data-preview]", field);
    var label = $("[data-upload-label]", field), err = $("[data-error]", field);
    function setUrl(v) { url.value = v; preview.src = v; preview.classList.toggle("hidden", !v); }
    url.addEventListener("input", function () { setUrl(url.value.trim()); });
    file.addEventListener("change", function () {
      if (!file.files[0]) return;
      showError(err, ""); label.textContent = "Uploading…";
      upload(file.files[0], field.getAttribute("data-variant") === "logo" ? "logo" : "product")
        .then(setUrl).catch(function (ex) { showError(err, ex.message); })
        .then(function () { label.textContent = "Upload image"; file.value = ""; });
    });
    var clear = $("[data-clear]", field);
    if (clear) clear.addEventListener("click", function () { setUrl(""); });
  });

  // ---------- gallery ----------
  var galleryField = $("#gallery-field");
  function galleryUrls() {
    return galleryField ? $all("[data-gallery-item]", galleryField).map(function (el) { return el.getAttribute("data-gallery-item"); }) : [];
  }
  if (galleryField) {
    var list = $("[data-gallery]", galleryField), gFile = $("[data-gallery-file]", galleryField);
    var gLabel = $("[data-upload-label]", galleryField), gErr = $("[data-error]", galleryField);
    function addThumb(u) {
      var div = document.createElement("div");
      div.className = "relative";
      div.setAttribute("data-gallery-item", u);
      div.innerHTML = '<img alt="" class="h-24 w-20 rounded-lg bg-gray-100 object-cover"><button type="button" data-remove class="absolute -right-2 -top-2 rounded-full bg-white p-1 shadow ring-1 ring-gray-200" aria-label="Remove photo">×</button>';
      div.querySelector("img").src = u;
      list.appendChild(div);
    }
    list.addEventListener("click", function (e) { var b = e.target.closest("[data-remove]"); if (b) b.parentNode.remove(); });
    gFile.addEventListener("change", function () {
      var files = Array.prototype.slice.call(gFile.files, 0, Math.max(0, 6 - galleryUrls().length));
      if (!files.length) { showError(gErr, "Up to 6 extra photos"); return; }
      showError(gErr, ""); gLabel.textContent = "Uploading…";
      files.reduce(function (p, f) { return p.then(function () { return upload(f).then(addThumb); }); }, Promise.resolve())
        .catch(function (ex) { showError(gErr, ex.message); })
        .then(function () { gLabel.textContent = "Add photos"; gFile.value = ""; });
    });
  }

  // ---------- product form ----------
  var productForm = $("#product-form");
  if (productForm) {
    productForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var f = new FormData(productForm);
      var err = $("#form-error");
      if (!String(f.get("imageUrl") || "").trim()) { showError(err, "Please upload an image or paste an image URL"); return; }
      var btn = $("button[type=submit]", productForm), label = btn.textContent;
      btn.disabled = true; btn.textContent = "Saving…"; showError(err, "");
      var id = productForm.getAttribute("data-id");
      api(id ? "/api/admin/products/" + encodeURIComponent(id) : "/api/admin/products", id ? "PUT" : "POST", {
        name: f.get("name"), slug: f.get("slug"), description: f.get("description"),
        originalPriceNaira: Number(f.get("originalPriceNaira")), salePriceNaira: Number(f.get("salePriceNaira")),
        category: f.get("category"), size: f.get("size"), condition: f.get("condition"),
        imageUrl: String(f.get("imageUrl")).trim(), images: galleryUrls(),
        featured: f.get("featured") === "on", inStock: f.get("inStock") === "on",
        brand: f.get("brand"), color: f.get("color"), measurements: f.get("measurements"), flaws: f.get("flaws"),
        publishAt: f.get("publishAt"),
        fit: f.get("fit"), fabric: f.get("fabric"), modelInfo: f.get("modelInfo"), era: f.get("era"), costNaira: Number(f.get("costNaira") || 0),
        tags: ["rare", "vintage"].filter(function (t) { return f.get("tag_" + t) === "on"; }).join(",")
      }).then(function () { location.href = BASE + "/products"; })
        .catch(function (ex) { showError(err, ex.message); btn.disabled = false; btn.textContent = label; });
    });
  }

  // ---------- delete product ----------
  $all("[data-delete-product]").forEach(function (b) {
    b.addEventListener("click", function () {
      if (!confirm('Delete "' + b.getAttribute("data-name") + '"? This cannot be undone.')) return;
      api("/api/admin/products/" + encodeURIComponent(b.getAttribute("data-delete-product")), "DELETE")
        .then(function () { b.closest("tr").remove(); })
        .catch(function (ex) { alert(ex.message); });
    });
  });

  // ---------- settings ----------
  var settingsForm = $("#settings-form");
  if (settingsForm) {
    function rgb(hex) {
      var h = hex.replace("#", "");
      if (h.length === 3) h = h.split("").map(function (c) { return c + c; }).join("");
      if (!/^[0-9a-f]{6}$/i.test(h)) return null;
      var n = parseInt(h, 16);
      return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }
    function lum(c) {
      var v = c.map(function (x) { x /= 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); });
      return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2];
    }
    function ratio(a, b) {
      var A = rgb(a), B = rgb(b);
      if (!A || !B) return null;
      var la = lum(A), lb = lum(B);
      return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }
    function color(name) { return settingsForm.elements[name].value; }
    function renderContrast() {
      var checks = [
        ["Body text on background", color("colorCharcoal"), color("colorCream"), 4.5],
        ["Button text on primary color", "#ffffff", color("colorTerracotta"), 4.5],
        ["Price/link text on background", color("colorTerracotta"), color("colorCream"), 3],
        ["Muted text on background", color("colorWarmgray"), color("colorCream"), 3]
      ];
      $("#contrast-list").innerHTML = checks.map(function (c) {
        var r = ratio(c[1], c[2]), ok = r !== null && r >= c[3];
        return '<li class="flex items-center gap-2 text-sm ' + (ok ? "text-gray-700" : "text-amber-700") + '">'
          + (ok ? "✓" : "⚠") + " " + c[0] + " — " + (r !== null ? r.toFixed(2) + ":1" : "invalid color") + (ok ? "" : " (below recommended contrast)") + "</li>";
      }).join("");
    }
    $all("input[type=color]", settingsForm).forEach(function (inp) {
      inp.addEventListener("input", function () { $('[data-hex="' + inp.name + '"]').textContent = inp.value.toUpperCase(); renderContrast(); });
    });
    renderContrast();

    settingsForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var msg = $("#settings-message"), btn = $("button[type=submit]", settingsForm);
      var payload = {};
      new FormData(settingsForm).forEach(function (v, k) { payload[k] = String(v); });
      btn.disabled = true; btn.textContent = "Saving…";
      api("/api/admin/settings", "PUT", payload).then(function () {
        msg.className = "text-sm text-green-700"; msg.textContent = "Settings saved! The storefront now shows your changes.";
      }).catch(function (ex) {
        msg.className = "text-sm text-red-600"; msg.textContent = ex.message;
      }).then(function () { btn.disabled = false; btn.textContent = "Save all settings"; });
    });
  }

  // ---------- order actions ----------
  function orderAction(id, body, card) {
    var err = card ? $("[data-order-error]", card) : null;
    showError(err, "");
    return api("/api/admin/orders/" + encodeURIComponent(id), "PUT", body)
      .then(function () { location.reload(); })
      .catch(function (ex) { showError(err, ex.message); });
  }
  $all("[data-order-action]").forEach(function (b) {
    b.addEventListener("click", function () {
      var action = b.getAttribute("data-order-action");
      var id = b.getAttribute("data-order");
      var body = { action: action };
      if (action === "cancel") {
        if (!confirm("Cancel this order? Its items go back on sale.")) return;
        body.restock = true;
      }
      if (action === "paid" && !confirm("Mark this order as paid? Its items will be marked sold.")) return;
      b.disabled = true;
      orderAction(id, body, b.closest("[data-order-card]")).then(function () { b.disabled = false; });
    });
  });
  $all("[data-fulfillment]").forEach(function (sel) {
    sel.addEventListener("change", function () {
      orderAction(sel.getAttribute("data-fulfillment"), { action: "fulfillment", value: sel.value }, sel.closest("[data-order-card]"));
    });
  });

  // ---------- bulk upload ----------
  var bulk = $("#bulk");
  if (bulk) {
    var rows = $("[data-bulk-rows]", bulk), bFile = $("[data-bulk-file]", bulk), bLabel = $("[data-bulk-label]", bulk);
    var bErr = $("[data-bulk-error]", bulk), bSave = $("[data-bulk-save]", bulk);
    function def(name) { var el = $('[data-bulk-default="' + name + '"]', bulk); return el.type === "checkbox" ? el.checked : el.value; }
    function cell(html) { return '<td class="px-3 py-2">' + html + "</td>"; }
    var inp = 'class="w-full rounded border border-gray-300 px-2 py-1 text-sm"';
    function refresh() {
      var n = $all("tr[data-url]", rows).length;
      var empty = $("[data-empty]", rows);
      if (empty) empty.classList.toggle("hidden", n > 0);
      bSave.disabled = n === 0;
      bSave.textContent = n ? "Create " + n + " item" + (n === 1 ? "" : "s") : "Create items";
    }
    rows.addEventListener("click", function (e) { var b = e.target.closest("[data-row-remove]"); if (b) { b.closest("tr").remove(); refresh(); } });
    bFile.addEventListener("change", function () {
      var files = Array.prototype.slice.call(bFile.files, 0, Math.max(0, 30 - $all("tr[data-url]", rows).length));
      if (!files.length) return;
      showError(bErr, "");
      var done = 0;
      bLabel.textContent = "Uploading 0/" + files.length + "…";
      files.reduce(function (p, f) {
        return p.then(function () {
          return upload(f).then(function (url) {
            var name = f.name.replace(/\.[^.]+$/, "").replace(/[-_]+/g, " ").replace(/\s+/g, " ").trim();
            var tr = document.createElement("tr");
            tr.setAttribute("data-url", url);
            tr.innerHTML = cell('<img alt="" class="h-14 w-11 rounded bg-gray-100 object-cover">')
              + cell('<input data-f="name" ' + inp + ">") + cell('<input data-f="originalPriceNaira" type="number" min="1" ' + inp + ">")
              + cell('<input data-f="salePriceNaira" type="number" min="1" ' + inp + ">") + cell('<input data-f="size" ' + inp + ">")
              + cell('<input data-f="brand" ' + inp + ">") + cell('<button type="button" data-row-remove class="text-xs text-red-600">Remove</button>');
            tr.querySelector("img").src = url;
            tr.querySelector('[data-f="name"]').value = name.charAt(0).toUpperCase() + name.slice(1);
            tr.querySelector('[data-f="size"]').value = def("size");
            rows.appendChild(tr);
            done++; bLabel.textContent = "Uploading " + done + "/" + files.length + "…";
            refresh();
          });
        });
      }, Promise.resolve()).catch(function (ex) { showError(bErr, ex.message); })
        .then(function () { bLabel.textContent = "Add more photos"; bFile.value = ""; });
    });
    bSave.addEventListener("click", function () {
      var items = $all("tr[data-url]", rows).map(function (tr) {
        function v(f) { return tr.querySelector('[data-f="' + f + '"]').value; }
        return {
          name: v("name"), originalPriceNaira: Number(v("originalPriceNaira")), salePriceNaira: Number(v("salePriceNaira") || v("originalPriceNaira")),
          size: v("size"), brand: v("brand"), imageUrl: tr.getAttribute("data-url"), images: [],
          category: def("category"), condition: def("condition"), featured: def("featured"), inStock: true, publishAt: def("publishAt"), description: ""
        };
      });
      bSave.disabled = true; bSave.textContent = "Saving…"; showError(bErr, "");
      api("/api/admin/bulk-products", "POST", { items: items })
        .then(function () { location.href = BASE + "/products"; })
        .catch(function (ex) { showError(bErr, ex.message); refresh(); });
    });
  }

  // ---------- discount codes ----------
  var discountForm = $("#discount-form");
  if (discountForm) {
    discountForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var payload = {};
      new FormData(discountForm).forEach(function (v, k) { payload[k] = String(v); });
      api("/api/admin/discounts", "POST", payload).then(function () { location.reload(); })
        .catch(function (ex) { showError($("[data-error]", discountForm), ex.message); });
    });
  }
  $all("[data-delete-discount]").forEach(function (b) {
    b.addEventListener("click", function () {
      var code = b.getAttribute("data-delete-discount");
      if (!confirm("Delete code " + code + "?")) return;
      api("/api/admin/discounts/" + encodeURIComponent(code), "DELETE").then(function () { b.closest("tr").remove(); })
        .catch(function (ex) { alert(ex.message); });
    });
  });

  // ---------- reviews ----------
  $all("[data-review]").forEach(function (card) {
    var id = card.getAttribute("data-review");
    $all("[data-review-save]", card).forEach(function (b) {
      b.addEventListener("click", function () {
        api("/api/admin/reviews/" + encodeURIComponent(id), "PUT", {
          approved: b.getAttribute("data-review-save") === "1", photoUrl: $("[data-url]", card).value.trim()
        }).then(function () { location.reload(); }).catch(function (ex) { alert(ex.message); });
      });
    });
    $("[data-review-delete]", card).addEventListener("click", function () {
      if (!confirm("Delete this review?")) return;
      api("/api/admin/reviews/" + encodeURIComponent(id), "DELETE").then(function () { card.remove(); })
        .catch(function (ex) { alert(ex.message); });
    });
  });

  // ---------- 2-step login ----------
  var twofa = $("#twofa");
  if (twofa) {
    var tMsg = $("[data-2fa-msg]", twofa);
    function say(text, ok) { tMsg.textContent = text; tMsg.className = "mt-3 text-sm " + (ok ? "text-green-700" : "text-red-600"); }
    var start = $("[data-2fa-start]", twofa);
    if (start) start.addEventListener("click", function () {
      api("/api/admin/2fa/start", "POST", {}).then(function (d) {
        $("[data-2fa-secret]", twofa).textContent = d.secret.replace(/(.{4})/g, "$1 ").trim();
        $("[data-2fa-link]", twofa).href = d.uri;
        $("[data-2fa-setup]", twofa).classList.remove("hidden");
        start.classList.add("hidden");
      }).catch(function (ex) { say(ex.message); });
    });
    var enable = $("[data-2fa-enable]", twofa);
    if (enable) enable.addEventListener("click", function () {
      api("/api/admin/2fa/enable", "POST", { code: $("[data-2fa-code]", twofa).value }).then(function () {
        say("2-step login is on. You'll need your phone next time you sign in.", true);
        setTimeout(function () { location.reload(); }, 1500);
      }).catch(function (ex) { say(ex.message); });
    });
    var disable = $("[data-2fa-disable]", twofa);
    if (disable) disable.addEventListener("click", function () {
      api("/api/admin/2fa/disable", "POST", { code: $("[data-2fa-code]", twofa).value })
        .then(function () { location.reload(); }).catch(function (ex) { say(ex.message); });
    });
  }

  // ---------- offers ----------
  $all("[data-offer-id]").forEach(function (b) {
    b.addEventListener("click", function () {
      var accept = b.getAttribute("data-decision") === "accept";
      if (!confirm(accept ? "Accept this offer? The shopper gets a 48-hour code by email." : "Decline this offer?")) return;
      b.disabled = true;
      api("/api/admin/offers/" + encodeURIComponent(b.getAttribute("data-offer-id")), "PUT", { action: accept ? "accept" : "decline" })
        .then(function () { location.reload(); }).catch(function (ex) { alert(ex.message); b.disabled = false; });
    });
  });

  // ---------- sell requests ----------
  $all("[data-sell-status]").forEach(function (sel) {
    sel.addEventListener("change", function () {
      api("/api/admin/sell/" + encodeURIComponent(sel.getAttribute("data-sell-status")), "PUT", { status: sel.value }).catch(function (ex) { alert(ex.message); });
    });
  });

  // ---------- bulk price drop ----------
  var drop = $("#price-drop");
  if (drop) drop.addEventListener("submit", function (e) {
    e.preventDefault();
    var f = new FormData(drop);
    if (!confirm("Reduce prices by " + f.get("percent") + "% on matching in-stock items?")) return;
    api("/api/admin/price-drop", "POST", { percent: Number(f.get("percent")), olderThanDays: Number(f.get("olderThanDays")), category: f.get("category") })
      .then(function (d) { alert(d.updated + " items updated"); location.reload(); })
      .catch(function (ex) { showError($("[data-error]", drop), ex.message); });
  });
})();
