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

  /* --- Online kalkulačka pôžičky (ocenenie z autobazar.eu) ---
     UI zadá značku, model, ročník a km. Backend (api/ocenenie.php) scrapne
     autobazar.eu a vráti priemernú cenu podobných áut. Tu z nej vypočítame:
       reálna hodnota = priemer * MARKET_ADJ (90 % – inzerátne ceny bývajú vyššie)
       možná pôžička  = reálna hodnota * sadzba podľa km  (min. 1 000 €)
     ----------------------------------------------------------------------
     NASTAVENIE: API_BASE = pôvod, kde beží api/ocenenie.php.
       - prázdne ""   = rovnaký pôvod ako web (funguje na PHP hostingu autokassa.sk)
       - alebo napr.  "https://www.autokassa.sk"  (ak je web inde a PHP na doméne)
     Na GitHub Pages (statický náhľad) PHP nebeží – kalkulačka vtedy
     používateľa nasmeruje na odoslanie žiadosti. */
  var API_BASE = "";           // napr. "https://www.autokassa.sk"
  var API_PATH = "/api/ocenenie.php";
  var MARKET_ADJ = 0.90;       // reálna hodnota = 90 % priemernej inzerátnej ceny
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
  function round50(n) { return Math.round(n / 50) * 50; }

  var calcBtn = document.getElementById("calcBtn");
  var estAvg = document.getElementById("estAvg");
  var estMarket = document.getElementById("estMarket");
  var estLoan = document.getElementById("estLoan");
  var estNote = document.getElementById("estNote");
  var estBox = estNote ? estNote.closest(".est") : null;
  var hidAvg = document.getElementById("hidAvg");
  var hidLoan = document.getElementById("hidLoan");

  function setEst(avg, loan, market) {
    if (estAvg) estAvg.textContent = avg != null ? fmt.format(avg) : "—";
    if (estMarket) estMarket.textContent = market != null ? fmt.format(market) : "—";
    if (estLoan) estLoan.textContent = loan != null ? fmt.format(loan) : "—";
    if (hidAvg) hidAvg.value = avg != null ? Math.round(avg) : "";
    if (hidLoan) hidLoan.value = loan != null ? Math.round(loan) : "";
  }

  function computeAndShow(avg, km, count) {
    var market = avg * MARKET_ADJ;
    var rate = rateByKm(km);
    var loan = Math.max(MIN_SUM, round50(market * rate));
    setEst(avg, loan, market);
    if (estNote) estNote.textContent =
      "Z " + count + " podobných áut na autobazar.eu · sadzba " + Math.round(rate * 100) +
      " % podľa km. Orientačné, presnú sumu určíme po obhliadke.";
  }

  if (calcBtn) {
    calcBtn.addEventListener("click", function () {
      var brand = (document.getElementById("brand").value || "").trim();
      var model = (document.getElementById("model").value || "").trim();
      var year = parseInt(document.getElementById("year").value, 10);
      var km = parseFloat(document.getElementById("km").value);

      if (!brand || !model || !year || isNaN(km)) {
        if (estNote) estNote.textContent = "Vyplňte prosím značku, model, rok výroby aj najazdené km.";
        return;
      }

      // Bez nakonfigurovaného backendu (napr. na GitHub Pages) sa nedá scrapovať.
      if (!API_BASE && location.protocol === "https:" && /github\.io$/.test(location.hostname)) {
        setEst(null, null, null);
        if (estNote) estNote.textContent =
          "Online ocenenie beží na ostrej doméne (autokassa.sk). Tu v náhľade vyplňte kontakt nižšie a my vám obratom pošleme presný prepočet.";
        return;
      }

      var url = API_BASE + API_PATH +
        "?znacka=" + encodeURIComponent(brand) +
        "&model=" + encodeURIComponent(model) +
        "&rok=" + encodeURIComponent(year) +
        "&km=" + encodeURIComponent(km);

      calcBtn.disabled = true;
      var orig = calcBtn.textContent;
      calcBtn.textContent = "Hľadám podobné autá…";
      if (estBox) estBox.classList.add("loading");
      if (estNote) estNote.textContent = "Porovnávam podobné " + brand + " " + model + " na autobazar.eu…";

      fetch(url, { headers: { "Accept": "application/json" } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.ok && data.avg > 0 && data.count > 0) {
            computeAndShow(data.avg, km, data.count);
          } else {
            setEst(null, null, null);
            if (estNote) estNote.textContent =
              (data && data.message) ||
              "Nenašli sme dosť podobných áut. Nechajte nám kontakt nižšie a oceníme auto ručne.";
          }
        })
        .catch(function () {
          setEst(null, null, null);
          if (estNote) estNote.textContent =
            "Ocenenie sa teraz nepodarilo. Nechajte nám kontakt nižšie alebo zavolajte na 0908 589 181.";
        })
        .finally(function () {
          calcBtn.disabled = false;
          calcBtn.textContent = orig;
          if (estBox) estBox.classList.remove("loading");
        });
    });
  }

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
