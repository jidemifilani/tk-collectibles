/* Storefront behaviour: cart, checkout, order confirmation, chat, gallery, alerts. */
(function () {
  "use strict";

  var root = document.getElementById("store");
  var STORE = {};
  try { STORE = JSON.parse(root.getAttribute("data-store") || "{}"); } catch (e) {}

  // ---------- helpers ----------
  function $(sel, el) { return (el || document).querySelector(sel); }
  function $all(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function naira(kobo) { return "₦" + Math.round(kobo / 100).toLocaleString("en-NG"); }
  function store(key, value) {
    try {
      if (value === undefined) return localStorage.getItem(key);
      if (value === null) localStorage.removeItem(key); else localStorage.setItem(key, value);
    } catch (e) { return null; }
  }
  var ICON = {
    minus: '<path d="M5 12h14"/>',
    plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
    trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    checkCircle: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    xCircle: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
    loader: '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>',
    bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    bellOff: '<path d="M8.7 3A6 6 0 0 1 18 8a21.3 21.3 0 0 0 .6 5"/><path d="M17 17H3s3-2 3-9a4.67 4.67 0 0 1 .3-1.7"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="m2 2 20 20"/>',
    bellRing: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="M4 2C2.8 3.7 2 5.7 2 8"/><path d="M22 8c0-2.3-.8-4.3-2-6"/>'
  };
  function icon(name, cls) {
    return '<svg xmlns="http://www.w3.org/2000/svg" class="' + (cls || "h-4 w-4") + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICON[name] + "</svg>";
  }
  var BTN = "inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 ";
  var BTN_PRIMARY = BTN + "bg-terracotta text-white hover:bg-rust focus-visible:ring-terracotta";
  var BTN_OUTLINE = BTN + "border-2 border-charcoal text-charcoal hover:bg-charcoal hover:text-cream focus-visible:ring-charcoal";

  // ---------- cart (kept in this browser) ----------
  var CART_KEY = "tk-cart";
  var Cart = {
    items: function () {
      try { var v = JSON.parse(store(CART_KEY) || "[]"); return Array.isArray(v) ? v : []; } catch (e) { return []; }
    },
    save: function (items) { store(CART_KEY, JSON.stringify(items)); updateCartCount(); },
    add: function (item) {
      var items = Cart.items();
      var found = items.filter(function (i) { return i.productId === item.productId && i.size === item.size; })[0];
      if (found) found.quantity += 1; else { item.quantity = 1; items.push(item); }
      Cart.save(items);
    },
    setQty: function (productId, size, qty) {
      var items = Cart.items();
      if (qty < 1) items = items.filter(function (i) { return !(i.productId === productId && i.size === size); });
      else items.forEach(function (i) { if (i.productId === productId && i.size === size) i.quantity = qty; });
      Cart.save(items);
    },
    remove: function (productId, size) { Cart.setQty(productId, size, 0); },
    clear: function () { Cart.save([]); },
    count: function () { return Cart.items().reduce(function (s, i) { return s + i.quantity; }, 0); },
    subtotal: function () { return Cart.items().reduce(function (s, i) { return s + i.salePrice * i.quantity; }, 0); }
  };

  function updateCartCount() {
    var n = Cart.count();
    $all("[data-cart-count]").forEach(function (el) {
      el.textContent = n > 9 ? "9+" : String(n);
      el.classList.toggle("hidden", n === 0);
      el.classList.toggle("flex", n > 0);
    });
  }
  updateCartCount();

  // ---------- header menu ----------
  var menuBtn = $("#menu-toggle");
  if (menuBtn) {
    menuBtn.addEventListener("click", function () {
      var menu = $("#mobile-menu");
      var open = menu.classList.toggle("hidden") === false;
      menuBtn.setAttribute("aria-expanded", String(open));
      menuBtn.setAttribute("aria-label", open ? "Close menu" : "Open menu");
      $("[data-icon-open]", menuBtn).classList.toggle("hidden", open);
      $("[data-icon-close]", menuBtn).classList.toggle("hidden", !open);
    });
  }

  // ---------- new-arrival bar ----------
  var bar = $("#new-arrival-bar");
  if (bar) {
    var drop = bar.getAttribute("data-drop");
    if (store("tk-new-arrival-dismissed") !== drop) bar.classList.remove("hidden");
    $all("[data-dismiss-bar]", bar).forEach(function (el) {
      el.addEventListener("click", function () { bar.classList.add("hidden"); store("tk-new-arrival-dismissed", drop); });
    });
  }

  // ---------- add to cart ----------
  $all("[data-add-to-cart]").forEach(function (btn) {
    var label = btn.innerHTML;
    btn.addEventListener("click", function () {
      Cart.add(JSON.parse(btn.getAttribute("data-add-to-cart")));
      btn.innerHTML = icon("check") + " Added to cart";
      btn.classList.replace("bg-terracotta", "bg-olive");
      setTimeout(function () { btn.innerHTML = label; btn.classList.replace("bg-olive", "bg-terracotta"); }, 2000);
    });
  });

  // ---------- product gallery ----------
  var gallery = $("#gallery");
  if (gallery) {
    var images = JSON.parse(gallery.getAttribute("data-images") || "[]");
    var active = 0;
    var lightbox = $("[data-lightbox]", gallery);
    function closeLightbox() { lightbox.classList.add("hidden"); lightbox.classList.remove("flex"); }
    $all("[data-thumb]", gallery).forEach(function (t) {
      t.addEventListener("click", function () {
        active = Number(t.getAttribute("data-thumb"));
        $("[data-main]", gallery).src = images[active];
        $all("[data-thumb]", gallery).forEach(function (o) {
          var on = o === t;
          o.classList.toggle("ring-terracotta", on);
          o.classList.toggle("ring-transparent", !on);
        });
      });
    });
    $("[data-zoom]", gallery).addEventListener("click", function () {
      $("[data-big]", gallery).src = images[active];
      lightbox.classList.remove("hidden"); lightbox.classList.add("flex");
    });
    lightbox.addEventListener("click", function (e) { if (e.target === lightbox || e.target.closest("[data-close]")) closeLightbox(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeLightbox(); });
  }

  // ---------- cart page ----------
  var cartPage = $("#cart-page");
  function renderCart() {
    var items = Cart.items();
    if (!items.length) {
      cartPage.innerHTML = '<div class="mx-auto max-w-lg py-10 text-center"><h1 class="font-display text-2xl font-semibold">Your cart is empty</h1>'
        + '<p class="mt-2 text-warmgray">Find something you love from this week&apos;s drop.</p>'
        + '<a href="/shop" class="mt-8 ' + BTN_PRIMARY + '">Browse shop</a></div>';
      return;
    }
    var rows = items.map(function (i) {
      var price = '<span class="text-sm font-semibold text-terracotta">' + naira(i.salePrice) + "</span>"
        + (i.salePrice < i.originalPrice ? ' <span class="text-xs text-warmgray line-through">' + naira(i.originalPrice) + "</span>" : "");
      var key = ' data-id="' + esc(i.productId) + '" data-size="' + esc(i.size) + '"';
      return '<li class="flex gap-4 rounded-2xl bg-white p-4 ring-1 ring-sand/60">'
        + '<div class="relative h-28 w-24 shrink-0 overflow-hidden rounded-xl bg-sand"><img src="' + esc(i.imageUrl) + '" alt="' + esc(i.name) + '" class="absolute inset-0 h-full w-full object-cover"></div>'
        + '<div class="flex flex-1 flex-col"><a href="/product/' + esc(i.slug) + '" class="font-medium hover:text-terracotta">' + esc(i.name) + "</a>"
        + '<p class="text-sm text-warmgray">Size ' + esc(i.size) + '</p><div class="mt-1">' + price + "</div>"
        + '<div class="mt-auto flex items-center justify-between pt-3"><div class="flex items-center gap-2 rounded-full border border-sand px-1">'
        + '<button type="button" data-qty="-1"' + key + ' class="rounded-full p-2 hover:bg-sand/50" aria-label="Decrease quantity">' + icon("minus") + "</button>"
        + '<span class="w-8 text-center text-sm font-medium">' + i.quantity + "</span>"
        + '<button type="button" data-qty="1"' + key + ' class="rounded-full p-2 hover:bg-sand/50" aria-label="Increase quantity">' + icon("plus") + "</button></div>"
        + '<button type="button" data-remove' + key + ' class="rounded-full p-2 text-warmgray hover:text-rust" aria-label="Remove item">' + icon("trash") + "</button>"
        + "</div></div></li>";
    }).join("");
    cartPage.innerHTML = '<h1 class="font-display text-3xl font-semibold">Cart (' + Cart.count() + ")</h1>"
      + '<div class="mt-10 grid gap-10 lg:grid-cols-3"><ul class="space-y-4 lg:col-span-2">' + rows + "</ul>"
      + '<div class="h-fit rounded-2xl bg-white p-6 ring-1 ring-sand/60"><h2 class="font-display text-lg font-semibold">Order summary</h2>'
      + '<div class="mt-4 flex justify-between text-sm"><span class="text-warmgray">Subtotal</span><span class="font-semibold">' + naira(Cart.subtotal()) + "</span></div>"
      + '<p class="mt-2 text-xs text-warmgray">Shipping calculated at checkout. Delivery across Nigeria.</p>'
      + '<a href="/checkout" class="mt-6 ' + BTN_PRIMARY + ' w-full">Proceed to checkout</a>'
      + '<a href="/shop" class="mt-3 block text-center text-sm text-terracotta hover:text-rust">Continue shopping</a></div></div>';
  }
  if (cartPage) {
    renderCart();
    cartPage.addEventListener("click", function (e) {
      var b = e.target.closest("button[data-id]");
      if (!b) return;
      var id = b.getAttribute("data-id"), size = b.getAttribute("data-size");
      if (b.hasAttribute("data-remove")) Cart.remove(id, size);
      else {
        var item = Cart.items().filter(function (i) { return i.productId === id && i.size === size; })[0];
        if (item) Cart.setQty(id, size, item.quantity + Number(b.getAttribute("data-qty")));
      }
      renderCart();
    });
  }

  // ---------- checkout ----------
  var checkoutForm = $("#checkout-form");
  if (checkoutForm) {
    var unavailable = [];
    var checking = true;
    var submitting = false;
    var quote = null;
    var appliedCode = "";
    var idemKey = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random();
    var submit = $("#checkout-submit");

    function method() { var m = checkoutForm.querySelector('input[name="paymentMethod"]:checked'); return m ? m.value : "paystack"; }
    function cartLines() { return Cart.items().map(function (i) { return { productId: i.productId, quantity: i.quantity, size: i.size }; }); }

    function renderCheckout() {
      var items = Cart.items();
      if (!items.length) { $("#checkout-main").classList.add("hidden"); $("#checkout-empty").classList.remove("hidden"); return; }
      $("#checkout-items").innerHTML = items.map(function (i) {
        var gone = unavailable.indexOf(i.productId) !== -1;
        return '<li class="flex items-start justify-between gap-3 text-sm"><span class="' + (gone ? "text-warmgray line-through" : "") + '">'
          + esc(i.name) + " × " + i.quantity + '<span class="text-warmgray"> · ' + esc(i.size) + "</span>"
          + (gone ? '<span class="mt-0.5 block text-xs font-medium text-rust">No longer available</span>' : "") + "</span>"
          + (gone ? '<button type="button" data-remove-id="' + esc(i.productId) + '" data-size="' + esc(i.size) + '" class="shrink-0 text-xs font-medium text-rust underline underline-offset-2">Remove</button>'
                  : "<span>" + naira(i.salePrice * i.quantity) + "</span>") + "</li>";
      }).join("");
      var subtotal = quote ? quote.subtotal : Cart.subtotal();
      $("#checkout-subtotal").textContent = naira(subtotal);
      var del = $("#checkout-delivery");
      if (quote && quote.delivery !== null) { del.textContent = quote.delivery === 0 ? "Free" : naira(quote.delivery); del.classList.remove("text-warmgray"); }
      else { del.textContent = "Select your state"; del.classList.add("text-warmgray"); }
      var discount = quote ? quote.discount : 0;
      var dRow = $("#checkout-discount-row");
      dRow.classList.toggle("hidden", !discount); dRow.classList.toggle("flex", !!discount);
      $("#checkout-discount").textContent = "−" + naira(discount);
      $("#checkout-total").textContent = naira(quote ? quote.total : subtotal);
      var warn = $("#checkout-unavailable");
      warn.classList.toggle("hidden", unavailable.length === 0);
      warn.classList.toggle("flex", unavailable.length > 0);
      submit.disabled = submitting || checking || unavailable.length > 0;
      var m = method();
      submit.textContent = submitting ? (m === "paystack" ? "Redirecting to Paystack…" : "Placing order…")
        : checking ? "Checking availability…" : m === "paystack" ? "Pay with Paystack" : "Place order";
    }

    function refreshQuote() {
      return fetch("/api/checkout/quote", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: cartLines(), state: checkoutForm.elements.state.value, discountCode: appliedCode })
      }).then(function (r) { return r.json(); }).then(function (d) {
        quote = d;
        var msg = $("#discount-msg");
        if (appliedCode) {
          msg.classList.remove("hidden");
          msg.className = "mt-2 text-xs " + (d.codeError ? "text-rust" : "text-olive");
          msg.textContent = d.codeError || ("Code " + d.code + " applied — you save " + naira(d.discount));
          if (d.codeError) appliedCode = "";
        }
        renderCheckout();
      }).catch(function () {});
    }

    $("#checkout-items").addEventListener("click", function (e) {
      var b = e.target.closest("[data-remove-id]");
      if (!b) return;
      var id = b.getAttribute("data-remove-id");
      Cart.remove(id, b.getAttribute("data-size"));
      unavailable = unavailable.filter(function (x) { return x !== id; });
      refreshQuote();
    });
    checkoutForm.elements.state.addEventListener("change", refreshQuote);
    $all('input[name="paymentMethod"]', checkoutForm).forEach(function (r) { r.addEventListener("change", renderCheckout); });
    $("#discount-apply").addEventListener("click", function () {
      appliedCode = $("#discount").value.trim().toUpperCase();
      if (!appliedCode) return;
      refreshQuote();
    });
    $("#discount").addEventListener("keydown", function (e) { if (e.key === "Enter") { e.preventDefault(); $("#discount-apply").click(); } });

    var cartItems = Cart.items();
    if (cartItems.length) {
      fetch("/api/cart/check", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ productIds: cartItems.map(function (i) { return i.productId; }) })
      }).then(function (r) { return r.json(); }).then(function (d) {
        unavailable = (d.items || []).filter(function (i) { return !i.available; }).map(function (i) { return i.id; });
      }).catch(function () { /* the server re-checks at payment time */ })
        .then(function () { checking = false; return refreshQuote(); })
        .then(renderCheckout);
    } else { checking = false; }
    renderCheckout();

    checkoutForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var err = $("#checkout-error");
      err.classList.add("hidden");
      submitting = true; renderCheckout();
      var f = new FormData(checkoutForm);
      var m = method();
      fetch(m === "paystack" ? "/api/paystack/initialize" : "/api/order/place", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Idempotency-Key": idemKey },
        body: JSON.stringify({
          customerName: f.get("name"), customerEmail: f.get("email"), customerPhone: f.get("phone"),
          shippingAddress: f.get("address"), city: f.get("city"), state: f.get("state"),
          discountCode: appliedCode, paymentMethod: m, items: cartLines()
        })
      }).then(function (r) {
        return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || "Checkout failed"); return d; });
      }).then(function (d) {
        if (d.redirect) Cart.clear();
        window.location.href = d.authorization_url || d.redirect;
      }).catch(function (ex) {
        err.textContent = ex.message || "Something went wrong";
        err.classList.remove("hidden");
        submitting = false; renderCheckout();
      });
    });
  }

  // ---------- order success ----------
  var success = $("#order-success");
  if (success) {
    var ref = new URLSearchParams(location.search).get("reference");
    var showError = function (msg) {
      success.className = "mx-auto max-w-lg px-4 py-20 text-center";
      success.innerHTML = icon("xCircle", "mx-auto h-14 w-14 text-rust")
        + '<h1 class="mt-4 font-display text-2xl font-semibold">Payment issue</h1>'
        + '<p class="mt-2 text-warmgray">' + esc(msg) + "</p>"
        + (ref ? '<p class="mt-2 text-sm text-warmgray">If you were charged, message us on WhatsApp with reference: <strong>' + esc(ref) + "</strong></p>" : "")
        + '<a href="/shop" class="mt-8 ' + BTN_PRIMARY + '">Back to shop</a>';
    };
    if (!ref) showError("No payment reference found");
    else {
      fetch("/api/paystack/verify?reference=" + encodeURIComponent(ref)).then(function (r) {
        return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || "Verification failed"); return d; });
      }).then(function (d) {
        var o = d.order;
        var paid = o.status === "paid";
        if (paid || o.status === "awaiting") Cart.clear();
        var lines = o.items.map(function (i) {
          return '<li class="flex justify-between"><span>' + esc(i.product.name) + " × " + i.quantity + " (" + esc(i.size) + ")</span><span>" + naira(i.price * i.quantity) + "</span></li>";
        }).join("");
        if (o.deliveryFee) lines += '<li class="flex justify-between text-warmgray"><span>Delivery</span><span>' + naira(o.deliveryFee) + "</span></li>";
        if (o.discountAmount) lines += '<li class="flex justify-between text-olive"><span>Discount (' + esc(o.discountCode) + ")</span><span>−" + naira(o.discountAmount) + "</span></li>";
        var intro = paid
          ? "Hi " + esc(o.customerName) + ", your payment was successful. We&apos;ll pack your thrift finds and send delivery updates by email and WhatsApp."
          : o.paymentMethod === "transfer"
            ? "Hi " + esc(o.customerName) + ", your items are held for 48 hours. Please transfer the total below and use your order reference as the narration."
            : "Hi " + esc(o.customerName) + ", your order is confirmed. You&apos;ll pay when it&apos;s delivered — we&apos;ll call you to arrange delivery.";
        success.className = "mx-auto max-w-lg px-4 py-16 text-center sm:px-6";
        success.innerHTML = icon("checkCircle", "mx-auto h-16 w-16 text-olive")
          + '<h1 class="mt-6 font-display text-3xl font-semibold">Thank you!</h1>'
          + '<p class="mt-2 text-warmgray">' + intro + "</p>"
          + (d.bankDetails ? '<div class="mt-6 whitespace-pre-line rounded-2xl bg-sand/60 p-5 text-left text-sm font-medium">' + esc(d.bankDetails) + "</div>" : "")
          + '<div class="mt-8 rounded-2xl bg-white p-6 text-left ring-1 ring-sand/60"><p class="text-xs uppercase tracking-wide text-warmgray">Order reference</p>'
          + '<p class="mt-1 font-mono text-sm font-medium">' + esc(o.reference) + "</p>"
          + '<ul class="mt-4 space-y-2 border-t border-sand pt-4 text-sm">' + lines + "</ul>"
          + '<div class="mt-4 flex justify-between border-t border-sand pt-4 font-semibold"><span>' + (paid ? "Total paid" : "Total to pay") + '</span><span class="text-terracotta">' + naira(o.totalAmount) + "</span></div></div>"
          + '<div class="mt-8 flex flex-wrap justify-center gap-3"><a href="/track?ref=' + encodeURIComponent(o.reference) + '" class="' + BTN_PRIMARY + '">Track order</a>'
          + '<a href="/shop" class="' + BTN_OUTLINE + '">Continue shopping</a></div>';
      }).catch(function (ex) { showError(ex.message || "Could not verify payment"); });
    }
  }

  // ---------- order tracking ----------
  var trackForm = $("#track-form");
  if (trackForm) {
    var STEPS = [["new", "Order received"], ["packed", "Packed"], ["shipped", "Shipped"], ["delivered", "Delivered"]];
    trackForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var out = $("#track-result");
      out.innerHTML = '<p class="text-warmgray">Looking up your order…</p>';
      fetch("/api/track?ref=" + encodeURIComponent(trackForm.elements.ref.value.trim()) + "&contact=" + encodeURIComponent(trackForm.elements.contact.value.trim()))
        .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || "Not found"); return d.order; }); })
        .then(function (o) {
          var cancelled = o.status === "cancelled" || o.status === "failed";
          var at = STEPS.map(function (s) { return s[0]; }).indexOf(o.fulfillment);
          var pay = o.status === "paid" ? "Paid" : o.status === "awaiting" ? (o.paymentMethod === "pod" ? "Pay on delivery" : "Awaiting bank transfer") : o.status === "pending" ? "Payment not completed" : "Cancelled";
          out.innerHTML = '<div class="rounded-2xl bg-white p-6 ring-1 ring-sand/60"><div class="flex flex-wrap justify-between gap-2"><p class="font-mono text-sm font-medium">' + esc(o.reference) + "</p>"
            + '<span class="rounded-full bg-sand px-3 py-1 text-xs font-semibold">' + esc(pay) + "</span></div>"
            + (cancelled ? '<p class="mt-4 text-rust">This order was cancelled. Contact us if you have questions.</p>'
              : '<ol class="mt-6 space-y-4">' + STEPS.map(function (s, i) {
                  var done = i <= at;
                  return '<li class="flex items-center gap-3"><span class="flex h-7 w-7 items-center justify-center rounded-full ' + (done ? "bg-olive text-white" : "bg-sand text-warmgray") + '">' + (done ? icon("check") : i + 1) + "</span>"
                    + '<span class="' + (done ? "font-semibold" : "text-warmgray") + '">' + s[1] + "</span></li>";
                }).join("") + "</ol>")
            + '<ul class="mt-6 space-y-1 border-t border-sand pt-4 text-sm">' + o.items.map(function (i) { return "<li>" + esc(i.name) + " × " + i.quantity + "</li>"; }).join("") + "</ul>"
            + '<p class="mt-3 flex justify-between font-semibold"><span>Total</span><span class="text-terracotta">' + naira(o.totalAmount) + "</span></p>"
            + '<p class="mt-2 text-xs text-warmgray">Delivering to ' + esc(o.city) + ", " + esc(o.state) + " · Last update " + new Date(o.updatedAt).toLocaleString("en-NG") + "</p></div>";
        }).catch(function (ex) { out.innerHTML = '<p class="rounded-lg bg-rust/10 px-3 py-2 text-sm text-rust">' + esc(ex.message) + "</p>"; });
    });
    if (trackForm.elements.ref.value) trackForm.elements.contact.focus();
  }

  // ---------- wishlist + recently viewed (saved in this browser) ----------
  function idList(key) { try { var v = JSON.parse(store(key) || "[]"); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
  function setIdList(key, v) { store(key, JSON.stringify(v)); }
  var WISH = "tk-wishlist", RECENT = "tk-recent";
  function paintWish() {
    var list = idList(WISH);
    $all("[data-wish]").forEach(function (b) {
      var on = list.indexOf(b.getAttribute("data-wish")) !== -1;
      b.setAttribute("aria-pressed", String(on));
      b.setAttribute("aria-label", on ? "Remove from wishlist" : "Save to wishlist");
      b.classList.toggle("text-terracotta", on);
      var svg = b.querySelector("svg");
      if (svg) svg.setAttribute("fill", on ? "currentColor" : "none");
    });
    $all("[data-wish-count]").forEach(function (el) {
      el.textContent = list.length > 9 ? "9+" : String(list.length);
      el.classList.toggle("hidden", !list.length); el.classList.toggle("flex", !!list.length);
    });
  }
  document.addEventListener("click", function (e) {
    var b = e.target.closest("[data-wish]");
    if (!b) return;
    e.preventDefault();
    var id = b.getAttribute("data-wish"), list = idList(WISH), at = list.indexOf(id);
    if (at === -1) list.unshift(id); else list.splice(at, 1);
    setIdList(WISH, list.slice(0, 50));
    paintWish();
    if (wishPage && at !== -1) renderIdGrid(wishPage, idList(WISH), true);
  });

  function miniCard(p) {
    var sold = p.availability !== "available";
    return '<div class="relative"><a href="/product/' + esc(p.slug) + '" class="flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-sand/60' + (sold ? " opacity-75" : "") + '">'
      + '<div class="relative aspect-[3/4] bg-sand"><img src="' + esc(p.imageUrl) + '" alt="' + esc(p.name) + '" loading="lazy" class="absolute inset-0 h-full w-full object-cover' + (sold ? " grayscale" : "") + '">'
      + (sold ? '<span class="absolute left-3 top-3 rounded-full bg-charcoal px-2.5 py-1 text-[10px] font-semibold uppercase text-cream">' + (p.availability === "reserved" ? "Reserved" : "Sold") + "</span>" : "") + "</div>"
      + '<div class="p-4"><h3 class="font-display text-lg leading-tight">' + esc(p.name) + '</h3><p class="mt-2 text-sm font-semibold text-terracotta">' + naira(p.salePrice) + ' <span class="text-xs font-normal text-warmgray">· Size ' + esc(p.size) + "</span></p></div></a>"
      + '<button type="button" data-wish="' + esc(p.id) + '" aria-pressed="false" aria-label="Save to wishlist" class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-charcoal shadow-sm hover:text-terracotta">' + icon("heart") + "</button></div>";
  }
  function renderIdGrid(el, ids, isWishlist) {
    if (!ids.length) {
      el.innerHTML = isWishlist ? '<p class="text-warmgray">Nothing saved yet. Tap the ♡ on any piece to save it here.</p><a href="/shop" class="mt-6 ' + BTN_PRIMARY + '">Browse shop</a>' : "";
      return Promise.resolve([]);
    }
    return fetch("/api/products?ids=" + encodeURIComponent(ids.join(","))).then(function (r) { return r.json(); }).then(function (d) {
      var byId = {};
      (d.products || []).forEach(function (p) { byId[p.id] = p; });
      var list = ids.map(function (id) { return byId[id]; }).filter(Boolean);
      el.innerHTML = list.length ? (isWishlist ? '<div class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">' : "") + list.map(miniCard).join("") + (isWishlist ? "</div>" : "")
        : (isWishlist ? '<p class="text-warmgray">Your saved pieces are no longer listed.</p>' : "");
      paintWish();
      return list;
    }).catch(function () { return []; });
  }
  ICON.heart = '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>';
  var wishPage = $("#wishlist-page");
  if (wishPage) renderIdGrid(wishPage, idList(WISH), true);
  paintWish();

  var viewed = $("[data-recent]");
  var recent = idList(RECENT);
  if (viewed) {
    var vid = viewed.getAttribute("data-recent");
    setIdList(RECENT, [vid].concat(recent.filter(function (x) { return x !== vid; })).slice(0, 12));
  }
  var recentSection = $("#recently-viewed");
  if (recentSection && recent.length) {
    renderIdGrid($("[data-recent-grid]", recentSection), recent.slice(0, 4), false).then(function (list) {
      if (list.length) recentSection.classList.remove("hidden");
    });
  }

  // ---------- email signup (new drops / similar items) ----------
  $all("[data-subscribe]").forEach(function (form) {
    var msg = form.nextElementSibling;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var btn = form.querySelector("button");
      btn.disabled = true;
      fetch("/api/subscribe", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: form.querySelector("input").value, category: form.getAttribute("data-category") || "" })
      }).then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || "Could not subscribe"); }); })
        .then(function () { form.classList.add("hidden"); msg.textContent = "You're on the list! We'll email you when new pieces drop."; })
        .catch(function (ex) { msg.textContent = ex.message; })
        .then(function () { btn.disabled = false; msg.classList.remove("hidden"); });
    });
  });

  // ---------- reviews ----------
  var reviewForm = $("#review-form");
  if (reviewForm) {
    var rating = 0;
    $("[data-review-toggle]").addEventListener("click", function () { reviewForm.classList.toggle("hidden"); });
    $all("[data-star]", reviewForm).forEach(function (b) {
      b.addEventListener("click", function () {
        rating = Number(b.getAttribute("data-star"));
        $all("[data-star]", reviewForm).forEach(function (o) {
          var on = Number(o.getAttribute("data-star")) <= rating;
          o.classList.toggle("text-terracotta", on);
          o.setAttribute("aria-checked", String(Number(o.getAttribute("data-star")) === rating));
        });
      });
    });
    reviewForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var msg = $("[data-review-msg]", reviewForm);
      msg.classList.remove("hidden");
      if (!rating) { msg.className = "text-sm text-rust"; msg.textContent = "Tap the stars to choose a rating."; return; }
      fetch("/api/reviews", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ rating: rating, name: reviewForm.elements.name.value, body: reviewForm.elements.body.value })
      }).then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || "Could not send review"); return d; }); })
        .then(function (d) { reviewForm.reset(); msg.className = "text-sm text-olive"; msg.textContent = d.message; })
        .catch(function (ex) { msg.className = "text-sm text-rust"; msg.textContent = ex.message; });
    });
  }

  // ---------- next-drop countdown ----------
  var countdown = $("[data-countdown]");
  if (countdown) {
    var target = Number(countdown.getAttribute("data-countdown"));
    var text = $("[data-countdown-text]", countdown);
    var tick = function () {
      var left = Math.max(0, Math.floor((target - Date.now()) / 1000));
      if (!left) { text.textContent = "Live now — refresh!"; clearInterval(timer); setTimeout(function () { location.reload(); }, 3000); return; }
      var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
      function pad(n) { return n < 10 ? "0" + n : String(n); }
      text.textContent = (d ? d + "d " : "") + pad(h) + "h " + pad(m) + "m " + pad(s) + "s";
    };
    var timer = setInterval(tick, 1000);
    tick();
  }

  // ---------- shop filters: apply on change ----------
  $all("form[data-autosubmit] select").forEach(function (sel) { sel.addEventListener("change", function () { sel.form.submit(); }); });

  // ---------- chat ----------
  var chatToggle = $("#chat-toggle");
  if (chatToggle) {
    var panel = $("#chat-panel"), log = $("#chat-log"), input = $("#chat-input"), quick = $("#chat-quick");
    var greeting = { role: "assistant", content: "Hi! 👋 I'm the " + STORE.storeName + " assistant. Ask me about any item, prices, sizes, delivery, or how to order." };
    var messages = [];
    var loading = false;
    try { messages = JSON.parse(sessionStorage.getItem("tk-chat") || "[]"); } catch (e) { messages = []; }

    function bodyHtml(text) {
      return esc(text).replace(/(\/product\/[a-z0-9-]+)/gi, '<a href="$1" class="font-medium text-terracotta underline underline-offset-2">view item</a>');
    }
    function renderChat() {
      var all = [greeting].concat(messages);
      log.innerHTML = all.map(function (m) {
        var mine = m.role === "user";
        return '<div class="flex ' + (mine ? "justify-end" : "justify-start") + '"><div class="max-w-[85%] whitespace-pre-line rounded-2xl px-3 py-2 text-sm '
          + (mine ? "rounded-br-sm bg-terracotta text-white" : "rounded-bl-sm bg-white text-charcoal ring-1 ring-sand") + '">' + bodyHtml(m.content) + "</div></div>";
      }).join("") + (loading ? '<div class="flex justify-start"><div class="rounded-2xl rounded-bl-sm bg-white px-3 py-2 ring-1 ring-sand">' + icon("loader", "h-4 w-4 animate-spin text-warmgray") + "</div></div>" : "");
      quick.classList.toggle("hidden", messages.length > 0 || loading);
      quick.classList.toggle("flex", messages.length === 0 && !loading);
      log.scrollTop = log.scrollHeight;
      try { sessionStorage.setItem("tk-chat", JSON.stringify(messages.slice(-30))); } catch (e) {}
    }
    function send(text) {
      text = (text || "").trim();
      if (!text || loading) return;
      messages.push({ role: "user", content: text });
      input.value = ""; loading = true; renderChat();
      fetch("/api/chat", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ messages: messages }) })
        .then(function (r) { return r.json(); })
        .then(function (d) { messages.push({ role: "assistant", content: d.reply || d.error || "Sorry, something went wrong. Please try again." }); })
        .catch(function () { messages.push({ role: "assistant", content: "I'm having trouble connecting. Please call us on " + STORE.phone + "." }); })
        .then(function () { loading = false; renderChat(); });
    }
    chatToggle.addEventListener("click", function () {
      var open = panel.classList.toggle("hidden") === false;
      panel.classList.toggle("flex", open);
      chatToggle.setAttribute("aria-label", open ? "Close chat" : "Chat with us");
      $("[data-icon-open]", chatToggle).classList.toggle("hidden", open);
      $("[data-icon-close]", chatToggle).classList.toggle("hidden", !open);
      if (open) { renderChat(); input.focus(); }
    });
    $("#chat-form").addEventListener("submit", function (e) { e.preventDefault(); send(input.value); });
    $all("[data-quick]", quick).forEach(function (b) { b.addEventListener("click", function () { send(b.getAttribute("data-quick")); }); });
  }

  // ---------- new-arrival phone notifications ----------
  var slots = $all(".notify-slot");
  if (slots.length) {
    var vapidKey = STORE.vapidKey || "";
    var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var installed = window.matchMedia && matchMedia("(display-mode: standalone)").matches;
    var supported = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window && vapidKey;
    var reg = null;

    function keyBytes(b64) {
      var padded = (b64 + "===".slice((b64.length + 3) % 4)).replace(/-/g, "+").replace(/_/g, "/");
      var raw = atob(padded), out = new Uint8Array(raw.length);
      for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
      return out;
    }
    function render(status) {
      slots.forEach(function (slot) {
        var dark = slot.getAttribute("data-variant") === "dark";
        var hint = dark ? "text-sand/80" : "text-warmgray";
        if (status === "ios-install") {
          slot.innerHTML = '<p class="text-sm ' + hint + '">' + icon("bell", "mr-1 inline h-4 w-4") + "On iPhone? Tap <strong>Share → Add to Home Screen</strong>, open the app, then turn on new-arrival alerts.</p>";
        } else if (status === "blocked") {
          slot.innerHTML = '<p class="text-sm ' + hint + '">' + icon("bellOff", "mr-1 inline h-4 w-4") + "Notifications are blocked. Allow them in your browser settings to get new-arrival alerts.</p>";
        } else if (status === "unsupported") {
          slot.innerHTML = "";
        } else {
          var on = status === "on";
          var cls = on ? (dark ? "bg-white/10 text-cream hover:bg-white/20" : "bg-sand text-charcoal hover:bg-sand/70") : "bg-terracotta text-cream hover:bg-rust";
          slot.innerHTML = '<button type="button" data-notify class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-60 ' + cls + '"'
            + (status === "busy" ? " disabled" : "") + ">" + icon(on ? "bellRing" : "bell")
            + (status === "busy" ? "Please wait…" : on ? "Alerts on · Turn off" : "Notify me of new arrivals") + "</button>";
          slot.querySelector("[data-notify]").onclick = on ? unsubscribe : subscribe;
        }
      });
    }
    function subscribe() {
      render("busy");
      Notification.requestPermission().then(function (perm) {
        if (perm !== "granted") { render(perm === "denied" ? "blocked" : "off"); return; }
        return navigator.serviceWorker.ready.then(function (r) {
          return r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(vapidKey) });
        }).then(function (sub) {
          return fetch("/api/push/subscribe", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(sub.toJSON()) });
        }).then(function (res) { if (!res.ok) throw new Error(); render("on"); });
      }).catch(function () { render("off"); });
    }
    function unsubscribe() {
      render("busy");
      navigator.serviceWorker.ready.then(function (r) { return r.pushManager.getSubscription(); }).then(function (sub) {
        if (!sub) return;
        return fetch("/api/push/subscribe", { method: "DELETE", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ endpoint: sub.endpoint }) })
          .then(function () { return sub.unsubscribe(); });
      }).catch(function () {}).then(function () { render("off"); });
    }

    if (!supported) render(isIos && !installed ? "ios-install" : "unsupported");
    else if (Notification.permission === "denied") render("blocked");
    else {
      navigator.serviceWorker.register("/sw.js").then(function (r) { reg = r; return r.pushManager.getSubscription(); })
        .then(function (sub) { render(sub ? "on" : "off"); })
        .catch(function () { render("unsupported"); });
    }
  }
})();
