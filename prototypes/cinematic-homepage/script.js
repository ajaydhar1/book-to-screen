/* ============================================================
   BOOK TO SCREEN — CINEMATIC HOMEPAGE PROTOTYPE
   Vanilla JS: scroll reveals, nav state, and theater focus management.
   ============================================================ */

(function () {
    "use strict";

    var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    /* ---------- Sticky nav: solid background after scroll ---------- */
    var nav = document.getElementById("siteNav");

    function updateNav() {
        if (window.scrollY > 24) {
            nav.classList.add("scrolled");
        } else {
            nav.classList.remove("scrolled");
        }
    }

    window.addEventListener("scroll", updateNav, { passive: true });
    updateNav();

    /* ---------- Scroll-triggered reveals ---------- */
    var revealTargets = document.querySelectorAll("[data-reveal]");

    // Assign stagger indices to children of stagger groups.
    document.querySelectorAll("[data-reveal-stagger]").forEach(function (group) {
        Array.prototype.forEach.call(group.children, function (child, i) {
            child.style.setProperty("--i", i);
        });
    });

    if (reducedMotion.matches || !("IntersectionObserver" in window)) {
        // Show everything immediately — no motion.
        revealTargets.forEach(function (el) {
            el.classList.add("is-visible");
        });
    } else {
        document.documentElement.classList.add("js-reveal");
        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-visible");
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0, rootMargin: "0px 0px -40px 0px" }
        );

        revealTargets.forEach(function (el) {
            observer.observe(el);
        });
    }

    /* If the user toggles reduced motion mid-session, reveal everything. */
    function handleMotionChange() {
        if (reducedMotion.matches) {
            revealTargets.forEach(function (el) {
                el.classList.add("is-visible");
            });
        }
    }

    if (typeof reducedMotion.addEventListener === "function") {
        reducedMotion.addEventListener("change", handleMotionChange);
    }

    var cards = document.querySelectorAll(".cinema-film, .cinema-story");
    if (!reducedMotion.matches && "IntersectionObserver" in window) {
        var cardObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.remove("card-reveal-pending");
                    cardObserver.unobserve(entry.target);
                }
            });
        }, { rootMargin: "0px 0px -24px 0px", threshold: 0 });

        var offscreenCards = Array.prototype.filter.call(cards, function (card) {
            return card.getBoundingClientRect().top >= window.innerHeight;
        });
        offscreenCards.forEach(function (card) {
            card.classList.add("card-reveal-pending");
            cardObserver.observe(card);
        });
        cards.forEach(function (card) {
            card.addEventListener("focusin", function () {
                card.classList.remove("card-reveal-pending");
                cardObserver.unobserve(card);
            }, { once: true });
        });

        if (typeof reducedMotion.addEventListener === "function") {
            reducedMotion.addEventListener("change", function () {
                if (reducedMotion.matches) {
                    cards.forEach(function (card) {
                        card.classList.remove("card-reveal-pending");
                    });
                    cardObserver.disconnect();
                }
            });
        }
    }

    var theater = document.getElementById("trailer-theater");
    var returnFocus = null;
    if (theater && window.MutationObserver) {
        new MutationObserver(function () {
            if (theater.classList.contains("is-open")) {
                returnFocus = document.activeElement;
                theater.querySelector(".trailer-theater-close").focus();
            } else if (returnFocus) {
                returnFocus.focus();
                returnFocus = null;
            }
        }).observe(theater, { attributes: true, attributeFilter: ["class"] });

        theater.addEventListener("keydown", function (event) {
            if (event.key !== "Tab") return;
            var controls = Array.prototype.filter.call(
                theater.querySelectorAll("button, iframe, a[href]"),
                function (element) { return !element.hidden && element.offsetParent !== null; }
            );
            if (!controls.length) return;
            var first = controls[0];
            var last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }
})();
