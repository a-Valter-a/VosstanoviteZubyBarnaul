(function () {
  "use strict";

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  /* ——— Tilda artboard: screens 320,1200 ——— */
  const ARTBOARD = 1200;

  const syncArtboard = () => {
    document.documentElement.classList.toggle(
      "is-artboard-mobile",
      window.innerWidth < ARTBOARD
    );
  };

  syncArtboard();
  window.addEventListener("resize", syncArtboard, { passive: true });

  /* ——— Fixed header: actual height → --header-h ——— */
  const header = $(".header");

  const syncHeaderHeight = () => {
    if (!header) return;
    document.documentElement.style.setProperty(
      "--header-h",
      `${Math.ceil(header.getBoundingClientRect().height)}px`
    );
  };

  if (header) {
    syncHeaderHeight();
    window.addEventListener("resize", syncHeaderHeight, { passive: true });

    if (document.fonts?.ready) {
      document.fonts.ready.then(syncHeaderHeight);
    }

    const headerLogo = $(".header__brand-mark", header);
    if (headerLogo) {
      if (headerLogo.complete) {
        syncHeaderHeight();
      } else {
        headerLogo.addEventListener("load", syncHeaderHeight, { once: true });
      }
    }

    if (typeof ResizeObserver !== "undefined") {
      new ResizeObserver(syncHeaderHeight).observe(header);
    }
  }

  /* ——— Scroll to section ——— */
  $$("[data-scroll-to]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const target = document.getElementById(btn.dataset.scrollTo);
      target?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });

  /* ——— Cookie banner ——— */
  const cookie = $("#cookie");
  const COOKIE_KEY = "cookie_ok_barnaul";

  if (cookie && localStorage.getItem(COOKIE_KEY)) {
    cookie.classList.add("is-hidden");
  }

  $("[data-cookie-ok]", cookie)?.addEventListener("click", () => {
    localStorage.setItem(COOKIE_KEY, "1");
    cookie.classList.add("is-hidden");
  });

  /* ——— Scroll to top ——— */
  const scrollBtn = $("#scroll-top");
  if (scrollBtn) {
    const toggleScrollBtn = () => {
      scrollBtn.classList.toggle("is-visible", window.scrollY > 400);
    };
    window.addEventListener("scroll", toggleScrollBtn, { passive: true });
    toggleScrollBtn();
    scrollBtn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  /* ——— Phone mask: +7 (XXX) XXX-XX-XX ——— */
  const PHONE_EMPTY = "+7";

  const extractPhoneDigits = (value) => {
    let digits = value.replace(/\D/g, "");
    if (!digits) return "";
    if (digits.startsWith("8")) digits = "7" + digits.slice(1);
    else if (!digits.startsWith("7")) digits = "7" + digits;
    return digits.slice(0, 11);
  };

  const formatPhone = (value) => {
    const digits = extractPhoneDigits(value);
    if (!digits) return "";
    if (digits.length === 1) return PHONE_EMPTY;

    let out = "+7 (" + digits.slice(1, 4);
    if (digits.length < 4) return out;

    out += ") " + digits.slice(4, 7);
    if (digits.length < 7) return out;

    out += "-" + digits.slice(7, 9);
    if (digits.length < 9) return out;

    out += "-" + digits.slice(9, 11);
    return out;
  };

  const countDigitsBefore = (value, pos) =>
    value.slice(0, pos).replace(/\D/g, "").length;

  const cursorAfterDigits = (formatted, digitCount) => {
    if (!formatted) return 0;
    if (digitCount <= 0) return formatted.startsWith("+7") ? 2 : 0;

    let seen = 0;
    for (let i = 0; i < formatted.length; i++) {
      if (/\d/.test(formatted[i])) {
        seen += 1;
        if (seen >= digitCount) return i + 1;
      }
    }
    return formatted.length;
  };

  const applyPhoneMask = (input) => {
    const start = input.selectionStart ?? input.value.length;
    const end = input.selectionEnd ?? start;
    const digitsBefore = countDigitsBefore(input.value, start);
    const formatted = formatPhone(input.value);

    input.value = formatted;

    const nextPos = cursorAfterDigits(formatted, digitsBefore);
    input.setSelectionRange(nextPos, nextPos);
  };

  $$('input[type="tel"]').forEach((input) => {
    input.addEventListener("input", () => applyPhoneMask(input));

    input.addEventListener("focus", () => {
      if (!extractPhoneDigits(input.value)) {
        input.value = PHONE_EMPTY;
        input.setSelectionRange(input.value.length, input.value.length);
      }
    });

    input.addEventListener("blur", () => {
      if (input.value === PHONE_EMPTY) input.value = "";
    });

    input.addEventListener("paste", (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData("text");
      const start = input.selectionStart ?? input.value.length;
      const end = input.selectionEnd ?? start;
      const digitsBefore = countDigitsBefore(input.value, start);
      const pastedDigits = pasted.replace(/\D/g, "").length;
      const nextValue = input.value.slice(0, start) + pasted + input.value.slice(end);

      input.value = formatPhone(nextValue);
      const nextPos = cursorAfterDigits(
        input.value,
        digitsBefore + pastedDigits
      );
      input.setSelectionRange(nextPos, nextPos);
    });
  });

  /* ——— Quiz ——— */
  const quizRoot = $("#quiz-form");
  if (quizRoot) {
    const form = $("form", quizRoot);
    const steps = $$(".quiz__step", quizRoot);
    const tabs = $$("[data-quiz-tab]", quizRoot);
    const btnPrev = $("[data-quiz-prev]", quizRoot);
    const btnSubmit = $("[data-quiz-submit]", quizRoot);
    const submitGift = $("[data-quiz-submit-gift]", quizRoot);
    const nav = $(".quiz__nav", quizRoot);
    const success = $(".quiz__success", quizRoot);
    const lastTab = $(".quiz__tab--last", quizRoot);
    const question2Tab = tabs[1];
    let step = 0;
    const total = steps.length;

    const updateTabs = () => {
      tabs.forEach((tab, i) => {
        const isActive = i === step;
        const isDone = i < step;
        tab.classList.toggle("is-active", isActive);
        tab.classList.toggle("is-done", isDone);
        tab.setAttribute("aria-selected", isActive ? "true" : "false");
      });

      if (lastTab) {
        lastTab.hidden = step < 2;
      }

      if (question2Tab) {
        question2Tab.hidden = step === total - 1;
      }
    };

    const updateQuiz = () => {
      steps.forEach((s, i) => s.classList.toggle("is-active", i === step));
      if (btnPrev) btnPrev.hidden = step === 0 || step === total - 1;
      if (btnSubmit) btnSubmit.hidden = step !== total - 1;
      if (submitGift) submitGift.hidden = step !== total - 1;
      if (nav) nav.hidden = step === 0 || step === total - 1;
      updateTabs();
    };

    const validateStep = () => {
      const current = steps[step];
      const radios = $$('input[type="radio"]', current);
      if (radios.length) {
        return radios.some((r) => r.checked);
      }
      const phone = $('input[type="tel"]', current);
      if (phone) {
        const digits = phone.value.replace(/\D/g, "");
        if (digits.length < 11) {
          phone.focus();
          return false;
        }
      }
      const name = $('input[type="text"]', current);
      if (name && !name.value.trim()) {
        name.focus();
        return false;
      }
      return true;
    };

    btnPrev?.addEventListener("click", () => {
      if (step > 0) {
        step--;
        updateQuiz();
      }
    });

    steps.forEach((quizStep, stepIndex) => {
      $$('input[type="radio"]', quizStep).forEach((radio) => {
        radio.addEventListener("change", () => {
          if (step !== stepIndex || !radio.checked) return;
          if (step < total - 1) {
            step++;
            updateQuiz();
          }
        });
      });
    });

    form?.addEventListener("submit", (e) => {
      e.preventDefault();
      if (!validateStep()) return;
      form.hidden = true;
      $(".quiz__nav", quizRoot).hidden = true;
      $(".quiz__tabs", quizRoot).hidden = true;
      const formWrap = $(".quiz__form-wrap", quizRoot);
      if (formWrap) formWrap.classList.add("is-success");
      success.hidden = false;
    });

    updateQuiz();
  }
})();
