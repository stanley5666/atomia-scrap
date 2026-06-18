/* ==========================================================================
   AUTOKASSA — main.js
   - Sticky header efekt
   - Mobilné menu
   - Scroll reveal animácie
   - Orientačná kalkulačka záložnej sumy
   - Odoslanie formulára (AJAX, s fallbackom)
   - Rok v pätičke
   ========================================================================== */
(function () {
  "use strict";

  /* --- Sticky header --- */
  var header = document.getElementById("header");
  var onScroll = function () {
    if (window.scrollY > 20) header.classList.add("scrolled");
    else header.classList.remove("scrolled");
  };
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  /* --- Mobilné menu --- */
  var toggle = document.getElementById("navToggle");
  var links = document.getElementById("navLinks");
  if (toggle && links) {
    toggle.addEventListener("click", function () {
      var open = links.classList.toggle("open");
      document.body.classList.toggle("menu-open", open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.setAttribute("aria-label", open ? "Zatvoriť menu" : "Otvoriť menu");
    });
    // zatvoriť po kliknutí na odkaz
    links.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        links.classList.remove("open");
        document.body.classList.remove("menu-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* --- Scroll reveal --- */
  var revealEls = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && revealEls.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add("in");
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add("in"); });
  }

  /* --- Kalkulačka záložnej sumy ---
     Percentá podľa cenníka (osobné a úžitkové vozidlá). */
  var MIN_SUM = 1000;
  function rateByKm(km) {
    if (km <= 50000)  return 0.90;
    if (km <= 100000) return 0.85;
    if (km <= 150000) return 0.80;
    if (km <= 200000) return 0.70;
    if (km <= 225000) return 0.60;
    if (km <= 250000) return 0.50;
    return 0.40;
  }
  var fmt = new Intl.NumberFormat("sk-SK", { style: "currency", currency: "EUR", maximumFractionDigits: 0 });

  var valEl = document.getElementById("value");
  var kmEl = document.getElementById("km");
  var out = document.getElementById("estValue");
  var note = document.getElementById("estNote");

  function recalc() {
    if (!out) return;
    var value = parseFloat(valEl && valEl.value);
    var km = parseFloat(kmEl && kmEl.value);
    if (!value || value <= 0 || isNaN(km)) {
      out.textContent = "— €";
      note.textContent = "Zadajte hodnotu auta a najazdené km.";
      return;
    }
    var rate = rateByKm(km);
    var estimate = Math.round((value * rate) / 50) * 50; // zaokrúhlenie na 50 €
    if (estimate < MIN_SUM) estimate = MIN_SUM;
    out.textContent = fmt.format(estimate);
    note.textContent = "Orientačne až " + Math.round(rate * 100) + " % hodnoty · min. 1 000 €. Presná suma po obhliadke.";
  }
  if (valEl) valEl.addEventListener("input", recalc);
  if (kmEl) kmEl.addEventListener("input", recalc);

  /* --- Odoslanie formulára --- */
  var form = document.getElementById("quoteForm");
  var success = document.getElementById("formSuccess");
  if (form) {
    form.addEventListener("submit", function (ev) {
      // Ak nie je nakonfigurovaný reálny endpoint, zabránime odoslaniu a ukážeme potvrdenie.
      var action = form.getAttribute("action") || "";
      var configured = action && action.indexOf("your-id") === -1;

      if (!configured) {
        ev.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        showSuccess();
        return;
      }

      // Nakonfigurovaný endpoint → odoslať cez fetch (AJAX) pre plynulý UX
      ev.preventDefault();
      if (!form.checkValidity()) { form.reportValidity(); return; }
      var btn = form.querySelector('button[type="submit"]');
      var orig = btn.textContent;
      btn.disabled = true; btn.textContent = "Odosielam…";
      fetch(action, {
        method: "POST",
        body: new FormData(form),
        headers: { "Accept": "application/json" }
      }).then(function (r) {
        if (r.ok) { showSuccess(); }
        else { btn.disabled = false; btn.textContent = orig; alert("Odoslanie zlyhalo. Zavolajte nám prosím na 0908 589 181."); }
      }).catch(function () {
        btn.disabled = false; btn.textContent = orig;
        alert("Skontrolujte pripojenie alebo nám zavolajte na 0908 589 181.");
      });
    });
  }
  function showSuccess() {
    if (success) { form.style.display = "none"; success.classList.add("show"); success.scrollIntoView({ behavior: "smooth", block: "center" }); }
    // Tu môžete spustiť konverziu pre FB Pixel / GA4:
    // if (window.fbq) fbq('track', 'Lead');
    // if (window.gtag) gtag('event', 'generate_lead');
  }

  /* --- Rok v pätičke --- */
  var y = document.getElementById("year");
  if (y) y.textContent = new Date().getFullYear();
})();
