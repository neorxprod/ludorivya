/* =============================================================
   LUDORIVYA — interactions
   - apparitions au scroll (IntersectionObserver)
   - compteurs animes
   - parallaxe legere du collage d'accueil
   - validation des formulaires (Bootstrap + groupes de checkbox)
   - confirmations avant suppression
   - auto-envoi des filtres du catalogue
   ============================================================= */

(() => {
    'use strict';

    // Le CSS garde tout visible si ce script ne se charge pas.
    document.documentElement.classList.remove('no-js');

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Header : ombre quand on scrolle ---------- */

    const header = document.querySelector('[data-site-header]');
    if (header) {
        const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ---------- Compteurs animes (section chiffres) ---------- */

    const animateCounter = (element) => {
        const target = Number(element.dataset.countTo || '0');
        if (reducedMotion || target <= 0) {
            element.textContent = target.toLocaleString('fr-FR');
            return;
        }

        const duration = 1300;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            // easeOutCubic : demarre vite, ralentit a la fin.
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = Math.round(target * eased).toLocaleString('fr-FR');
            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    };

    /* ---------- Apparitions au scroll ---------- */

    const revealElements = document.querySelectorAll('.reveal');

    const revealNow = (el) => {
        el.classList.add('revealed');
        el.querySelectorAll('[data-count-to]').forEach(animateCounter);
        if (el.matches('[data-count-to]')) {
            animateCounter(el);
        }
    };

    if (reducedMotion || !('IntersectionObserver' in window)) {
        revealElements.forEach(revealNow);
    } else {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }
                revealNow(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        revealElements.forEach((el) => {
            // Ce qui est deja a l'ecran apparait tout de suite,
            // l'observer ne gere que ce qui arrive en scrollant.
            if (el.getBoundingClientRect().top < window.innerHeight) {
                revealNow(el);
            } else {
                observer.observe(el);
            }
        });
    }

    /* ---------- Scene 3D du hero : scroll + souris ---------- */

    // Le CSS lit --scroll-p (avancement du scroll) et --mx/--my
    // (position de la souris) pour incliner les jaquettes en 3D.
    const heroStage = document.querySelector('[data-hero-stage]');
    if (heroStage && !reducedMotion) {
        window.addEventListener('scroll', () => {
            const progress = Math.min(window.scrollY / 600, 1);
            heroStage.style.setProperty('--scroll-p', progress.toFixed(3));
        }, { passive: true });

        window.addEventListener('mousemove', (event) => {
            const x = (event.clientX / window.innerWidth - 0.5) * 2;
            const y = (event.clientY / window.innerHeight - 0.5) * 2;
            heroStage.style.setProperty('--mx', x.toFixed(3));
            heroStage.style.setProperty('--my', y.toFixed(3));
        }, { passive: true });
    }

    // Meme principe pour la jaquette de la fiche jeu.
    const heroCover = document.querySelector('[data-cover-3d]');
    if (heroCover && !reducedMotion) {
        window.addEventListener('mousemove', (event) => {
            const x = (event.clientX / window.innerWidth - 0.5) * 2;
            const y = (event.clientY / window.innerHeight - 0.5) * 2;
            heroCover.style.setProperty('--mx', x.toFixed(3));
            heroCover.style.setProperty('--my', y.toFixed(3));
        }, { passive: true });
    }

    /* ---------- Scrollytelling : la carte qui tourne au scroll ---------- */

    // La section .showcase fait 320vh : on calcule l'avancement (0 a 1)
    // pendant qu'elle traverse l'ecran, on le donne au CSS (--scene-p,
    // rotation continue) et on en deduit l'etape affichee (data-step).
    const showcase = document.querySelector('[data-showcase]');
    if (showcase && !reducedMotion) {
        const updateShowcase = () => {
            const rect = showcase.getBoundingClientRect();
            const total = rect.height - window.innerHeight;
            if (total <= 0) {
                return;
            }
            const progress = Math.min(Math.max(-rect.top / total, 0), 1);
            showcase.style.setProperty('--scene-p', progress.toFixed(3));
            showcase.dataset.step = String(Math.min(2, Math.floor(progress * 3)));
        };
        updateShowcase();
        window.addEventListener('scroll', updateShowcase, { passive: true });
        window.addEventListener('resize', updateShowcase, { passive: true });
    }

    /* ---------- Cartes de jeux inclinables a la souris ---------- */

    if (!reducedMotion && window.matchMedia('(hover: hover)').matches) {
        document.querySelectorAll('.game-card').forEach((card) => {
            card.addEventListener('mousemove', (event) => {
                const rect = card.getBoundingClientRect();
                const x = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
                const y = ((event.clientY - rect.top) / rect.height - 0.5) * 2;
                card.classList.add('tilting');
                card.style.setProperty('--rx', x.toFixed(3));
                card.style.setProperty('--ry', y.toFixed(3));
            });
            card.addEventListener('mouseleave', () => {
                card.classList.remove('tilting');
                card.style.setProperty('--rx', '0');
                card.style.setProperty('--ry', '0');
            });
        });
    }

    /* ---------- Validation des formulaires ---------- */

    // Verifie qu'au moins une case est cochee dans chaque groupe obligatoire,
    // et affiche/masque le message d'erreur du groupe.
    const validateRequiredGroups = (form) => {
        let allValid = true;

        form.querySelectorAll('[data-required-group]').forEach((group) => {
            const name = group.dataset.requiredGroup;
            const boxes = group.querySelectorAll(`input[name="${CSS.escape(name)}"]`);
            const checked = Array.from(boxes).some((box) => box.checked);
            const feedback = group.parentElement.querySelector('.group-feedback');

            if (feedback) {
                feedback.hidden = checked;
            }

            if (!checked) {
                allValid = false;
            }
        });

        return allValid;
    };

    document.querySelectorAll('.needs-validation').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const groupsValid = validateRequiredGroups(form);

            if (!form.checkValidity() || !groupsValid) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add('was-validated');
        });

        // Le message du groupe disparait des qu'une case est cochee.
        form.querySelectorAll('[data-required-group] input[type="checkbox"]').forEach((box) => {
            box.addEventListener('change', () => validateRequiredGroups(form));
        });
    });

    /* ---------- Confirmation avant action destructive ---------- */

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    /* ---------- Filtres du catalogue : envoi automatique ---------- */

    document.querySelectorAll('[data-filter-form] [data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => select.closest('form').requestSubmit());
    });
})();
