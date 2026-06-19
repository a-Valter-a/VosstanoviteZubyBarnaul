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
    if (form && !$('input[name="_hp"]', form)) {
      const hp = document.createElement("input");
      hp.type = "text";
      hp.name = "_hp";
      hp.setAttribute("aria-hidden", "true");
      hp.setAttribute("tabindex", "-1");
      hp.setAttribute("autocomplete", "off");
      hp.style.cssText =
        "position:fixed;top:-9999px;left:-9999px;width:0;height:0;opacity:0;pointer-events:none;border:0;padding:0;margin:0";
      form.appendChild(hp);
    }
    const steps = $$(".quiz__step", quizRoot);
    const tabs = $$("[data-quiz-tab]", quizRoot);
    const btnPrev = $("[data-quiz-prev]", quizRoot);
    const btnSubmit = $("[data-quiz-submit]", quizRoot);
    const submitGift = $("[data-quiz-submit-gift]", quizRoot);
    const nav = $(".quiz__nav", quizRoot);
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

    const showSuccess = () => {
      const thankYouUrl =
        window.APP_CONFIG?.thankYouUrl || "spasibo.html";
      window.location.href = thankYouUrl;
    };

    const hideFormError = () => {
      const errorEl = $("#quiz-form-error", quizRoot);
      if (errorEl) errorEl.hidden = true;
    };

    const showFormError = (message) => {
      const errorEl = $("#quiz-form-error", quizRoot);
      if (!errorEl) return;
      errorEl.textContent = message;
      errorEl.hidden = false;
    };

    const getUtmString = () => {
      const params = new URLSearchParams(window.location.search);
      const keys = [
        "utm_source",
        "utm_medium",
        "utm_campaign",
        "utm_content",
        "utm_term",
      ];
      const parts = keys
        .map((key) => (params.get(key) ? `${key}=${params.get(key)}` : ""))
        .filter(Boolean);
      return parts.join("; ");
    };

    const submitLead = async () => {
      const payload = {
        name: $('input[name="name"]', form)?.value.trim() || "",
        phone: $('input[name="phone"]', form)?.value.trim() || "",
        utm: getUtmString(),
        _hp: $('input[name="_hp"]', form)?.value || "",
      };

      const apiEndpoint =
        window.APP_CONFIG?.leadEndpoint || "api/send-lead.php";
      const albatoUrl = window.APP_CONFIG?.albatoWebhook || "";

      const sendJson = (url, body) =>
        fetch(url, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify(body),
        });

      try {
        const response = await sendJson(apiEndpoint, payload);
        let result = null;

        try {
          result = await response.json();
        } catch (_) {
          result = null;
        }

        if (response.ok && result?.ok) {
          return;
        }

        if (response.status === 503) {
          throw new Error(
            "На сервере нет api/config.php — скопируйте из api/config.example.php"
          );
        }

        if (response.status === 429) {
          throw new Error(
            result?.error || "Слишком много заявок. Попробуйте позже."
          );
        }

        if (response.status === 422 && result?.error) {
          throw new Error(result.error);
        }
      } catch (error) {
        if (
          error instanceof Error &&
          (error.message.includes("config.php") ||
            error.message.includes("заявок") ||
            error.message.includes("имя") ||
            error.message.includes("телефон"))
        ) {
          throw error;
        }
      }

      if (!albatoUrl) {
        throw new Error("Не удалось отправить заявку. Попробуйте ещё раз.");
      }

      await sendJson(albatoUrl, {
        name: payload.name,
        phone: payload.phone,
        utm: payload.utm,
      });
    };

    form?.addEventListener("submit", async (e) => {
      e.preventDefault();
      hideFormError();
      if (!validateStep()) return;

      if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.classList.add("is-loading");
      }

      try {
        await submitLead();
        showSuccess();
      } catch (error) {
        showFormError(
          error instanceof Error
            ? error.message
            : "Не удалось отправить заявку. Попробуйте ещё раз."
        );
      } finally {
        if (btnSubmit) {
          btnSubmit.disabled = false;
          btnSubmit.classList.remove("is-loading");
        }
      }
    });

    updateQuiz();
  }

  /* ——— Thanks page: slider hints ——— */
  const initThanksSliders = () => {
    $$("[data-thanks-slider-track]").forEach((track) => {
      const root = track.closest(".thanks-slider");
      const dotsEl = root?.querySelector("[data-thanks-slider-dots]");
      if (!root || !dotsEl) return;

      const getSlides = () =>
        [...track.children].filter((el) => getComputedStyle(el).display !== "none");

      const updateState = () => {
        const slides = getSlides();
        const dots = [...dotsEl.querySelectorAll(".thanks-slider__dot")];
        if (!slides.length) return;

        const { scrollLeft, clientWidth, scrollWidth } = track;
        let active = 0;

        slides.forEach((slide, i) => {
          if (slide.offsetLeft <= scrollLeft + clientWidth * 0.35) active = i;
        });

        dots.forEach((dot, i) => {
          dot.classList.toggle("is-active", i === active);
          dot.setAttribute("aria-selected", i === active ? "true" : "false");
        });

        const scrollable = slides.length > 1 && scrollWidth > clientWidth + 1;
        root.classList.toggle("is-scrollable", scrollable);
        root.classList.toggle("is-at-end", scrollLeft + clientWidth >= scrollWidth - 8);
        dotsEl.hidden = !scrollable;
        dotsEl.setAttribute("aria-hidden", scrollable ? "false" : "true");
      };

      const renderDots = () => {
        dotsEl.innerHTML = "";
        const slides = getSlides();

        slides.forEach((_, i) => {
          const dot = document.createElement("button");
          dot.type = "button";
          dot.className = "thanks-slider__dot";
          dot.setAttribute("role", "tab");
          dot.setAttribute("aria-label", `Карточка ${i + 1} из ${slides.length}`);
          dot.addEventListener("click", () => {
            const slide = getSlides()[i];
            if (slide) {
              track.scrollTo({ left: slide.offsetLeft, behavior: "smooth" });
            }
          });
          dotsEl.appendChild(dot);
        });

        updateState();
      };

      renderDots();
      track.addEventListener("scroll", updateState, { passive: true });
      window.addEventListener("resize", renderDots, { passive: true });
    });
  };

  if (document.body.classList.contains("page-thanks")) {
    initThanksSliders();
  }
})();
