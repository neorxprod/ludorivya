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

    /* ---------- Titres reveles mot par mot (blur-text) ---------- */

    document.querySelectorAll('[data-blur-text]').forEach((el) => {
        const words = el.textContent.trim().split(/\s+/);
        el.textContent = '';
        words.forEach((word, i) => {
            const span = document.createElement('span');
            span.className = 'blur-word';
            span.style.setProperty('--word-delay', `${i * 90}ms`);
            span.textContent = word;
            el.appendChild(span);
        });
        el.classList.add('blur-ready', 'reveal');
        // S'il est deja a l'ecran, on le revele tout de suite.
        if (el.getBoundingClientRect().top < window.innerHeight) {
            el.classList.add('revealed');
        } else if ('IntersectionObserver' in window && !reducedMotion) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });
            io.observe(el);
        } else {
            el.classList.add('revealed');
        }
    });

    /* ---------- Particules flottantes (canvas) ---------- */

    document.querySelectorAll('[data-particles]').forEach((canvas) => {
        if (reducedMotion) {
            return;
        }
        const context = canvas.getContext('2d');
        const colors = ['rgba(139,92,246,', 'rgba(196,132,252,', 'rgba(34,211,238,', 'rgba(255,255,255,'];
        let particles = [];

        const resize = () => {
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            particles = Array.from({ length: Math.min(80, Math.floor(canvas.width / 16)) }, () => ({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                radius: 0.6 + Math.random() * 1.8,
                speed: 0.15 + Math.random() * 0.45,
                drift: (Math.random() - 0.5) * 0.2,
                color: colors[Math.floor(Math.random() * colors.length)],
                alpha: 0.2 + Math.random() * 0.5,
            }));
        };

        const tick = () => {
            context.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach((p) => {
                p.y -= p.speed;
                p.x += p.drift;
                if (p.y < -4) { p.y = canvas.height + 4; p.x = Math.random() * canvas.width; }
                context.beginPath();
                context.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                context.fillStyle = p.color + p.alpha + ')';
                context.fill();
            });
            requestAnimationFrame(tick);
        };

        resize();
        window.addEventListener('resize', resize, { passive: true });
        requestAnimationFrame(tick);
    });

    /* ---------- Selecteur d'etoiles (avis) ---------- */

    document.querySelectorAll('[data-star-picker]').forEach((picker) => {
        const input = picker.querySelector('input[name="stars"]');
        const buttons = Array.from(picker.querySelectorAll('.star-btn'));
        const valueLabel = picker.querySelector('[data-star-value]');

        const paint = (count) => {
            buttons.forEach((btn, i) => btn.classList.toggle('lit', i < count));
        };

        const current = () => parseInt(input.value, 10) || 0;
        paint(current());

        buttons.forEach((btn) => {
            btn.addEventListener('mouseenter', () => paint(parseInt(btn.dataset.star, 10)));
            btn.addEventListener('click', () => {
                input.value = btn.dataset.star;
                paint(current());
                if (valueLabel) { valueLabel.textContent = btn.dataset.star + '/10'; }
            });
        });
        picker.addEventListener('mouseleave', () => paint(current()));
    });

    /* ---------- Mode versus ---------- */

    const arena = document.querySelector('[data-versus]');
    if (arena) {
        const stage = arena.querySelector('[data-versus-stage]');
        const cards = Array.from(arena.querySelectorAll('[data-versus-card]'));
        const isLogged = arena.dataset.logged === '1';
        const csrf = arena.dataset.csrf || '';
        let busy = false;

        const setHud = (selector, value) => {
            const el = arena.querySelector(selector);
            if (el && value !== undefined && value !== null) {
                el.textContent = String(value);
                const block = el.closest('.hud-block');
                if (block) {
                    block.classList.remove('pop');
                    void block.offsetWidth;
                    block.classList.add('pop');
                }
            }
        };

        // Injecte la nouvelle paire renvoyee par le serveur et rejoue le slam.
        const loadPair = (pair) => {
            cards.forEach((card, i) => {
                const game = pair[i];
                card.dataset.gameId = String(game.id);
                card.dataset.gameTitle = game.title;
                card.querySelector('[data-versus-img]').src = game.cover_url;
                card.querySelector('[data-versus-img]').alt = 'Jaquette de ' + game.title;
                card.querySelector('[data-versus-title]').textContent = game.title;
                card.querySelector('[data-versus-meta]').textContent = game.studio_name + (game.genre_names ? ' · ' + game.genre_names : '');
                card.querySelector('[data-versus-elo]').textContent = String(game.elo);
                const delta = card.querySelector('[data-versus-delta]');
                delta.className = 'versus-delta';
                delta.textContent = '';
                card.classList.remove('winner', 'loser');
                card.disabled = !isLogged;
                // Rejoue l'animation d'entree.
                const slam = card.classList.contains('versus-left') ? 'versus-left' : 'versus-right';
                card.classList.remove(slam);
                void card.offsetWidth;
                card.classList.add(slam);
            });
            busy = false;
        };

        // Compteur Elo qui "roule" de l'ancienne valeur a la nouvelle.
        const rollElo = (el, target) => {
            const start = parseInt(el.textContent.replace(/\s/g, ''), 10) || 0;
            const t0 = performance.now();
            const tick = (now) => {
                const p = Math.min((now - t0) / 600, 1);
                el.textContent = String(Math.round(start + (target - start) * (1 - Math.pow(1 - p, 3))));
                if (p < 1) { requestAnimationFrame(tick); }
            };
            requestAnimationFrame(tick);
        };

        // Pluie de confettis projetee depuis la carte gagnante.
        const burstConfetti = (card) => {
            if (reducedMotion) { return; }
            const palette = ['#8b5cf6', '#c084fc', '#22d3ee', '#fbbf24', '#fb7185', '#ffffff'];
            const rect = card.getBoundingClientRect();
            const stageRect = stage.getBoundingClientRect();
            for (let i = 0; i < 26; i++) {
                const piece = document.createElement('span');
                piece.className = 'confetti';
                piece.style.left = (rect.left - stageRect.left + rect.width / 2) + 'px';
                piece.style.top = (rect.top - stageRect.top + rect.height / 3) + 'px';
                piece.style.background = palette[i % palette.length];
                const angle = Math.random() * Math.PI * 2;
                const distance = 90 + Math.random() * 160;
                piece.style.setProperty('--cx', Math.cos(angle) * distance + 'px');
                piece.style.setProperty('--cy', (Math.sin(angle) * distance - 70) + 'px');
                stage.appendChild(piece);
                setTimeout(() => piece.remove(), 1200);
            }
        };

        const vote = async (winnerCard) => {
            if (busy || !isLogged) {
                return;
            }
            busy = true;
            const loserCard = cards.find((c) => c !== winnerCard);

            // Feedback immediat : flash + secousse + impact VS + confettis.
            stage.classList.remove('flash', 'shake');
            void stage.offsetWidth;
            stage.classList.add('flash', 'shake');
            const vs = arena.querySelector('.versus-vs');
            if (vs) {
                vs.classList.remove('hit');
                void vs.offsetWidth;
                vs.classList.add('hit');
            }
            winnerCard.classList.add('winner');
            loserCard.classList.add('loser');
            burstConfetti(winnerCard);
            cards.forEach((c) => { c.disabled = true; });

            const body = new FormData();
            body.append('ajax', '1');
            body.append('csrf_token', csrf);
            body.append('winner_id', winnerCard.dataset.gameId);
            body.append('loser_id', loserCard.dataset.gameId);

            try {
                const response = await fetch('duel_store.php', { method: 'POST', body });
                const data = await response.json();

                if (!data.ok) {
                    if (data.login) {
                        window.location.href = 'login.php?redirect=versus.php';
                        return;
                    }
                    busy = false;
                    winnerCard.classList.remove('winner');
                    loserCard.classList.remove('loser');
                    cards.forEach((c) => { c.disabled = false; });
                    return;
                }

                // Deltas Elo flottants + mise a jour des compteurs.
                const winDelta = winnerCard.querySelector('[data-versus-delta]');
                winDelta.textContent = '+' + data.delta;
                winDelta.classList.add('show-gain');
                rollElo(winnerCard.querySelector('[data-versus-elo]'), data.winner.elo);

                const loseDelta = loserCard.querySelector('[data-versus-delta]');
                loseDelta.textContent = '-' + data.delta;
                loseDelta.classList.add('show-loss');
                rollElo(loserCard.querySelector('[data-versus-elo]'), data.loser.elo);

                const duelsEl = arena.querySelector('[data-hud-duels]');
                if (duelsEl) {
                    const current = parseInt(duelsEl.textContent.replace(/\s/g, ''), 10) || 0;
                    setHud('[data-hud-duels]', (current + 1).toLocaleString('fr-FR'));
                }
                setHud('[data-hud-streak]', data.streak);
                setHud('[data-hud-xp]', data.xp);
                setHud('[data-hud-level]', data.level);

                // Duel suivant apres l'animation de victoire.
                setTimeout(() => loadPair(data.next), 1000);
            } catch (error) {
                busy = false;
                cards.forEach((c) => { c.disabled = false; });
            }
        };

        cards.forEach((card) => card.addEventListener('click', () => vote(card)));

        // "Je ne connais pas" : nouvelle paire, sans vote ni Elo (serie remise a zero).
        const skipButton = arena.querySelector('[data-versus-skip]');
        if (skipButton) {
            skipButton.addEventListener('click', async () => {
                if (busy) { return; }
                busy = true;
                const body = new FormData();
                body.append('ajax', '1');
                body.append('csrf_token', csrf);
                body.append('action', 'skip');
                try {
                    const response = await fetch('duel_store.php', { method: 'POST', body });
                    const data = await response.json();
                    if (data.ok && data.next) {
                        setHud('[data-hud-streak]', 0);
                        loadPair(data.next);
                    } else {
                        busy = false;
                    }
                } catch (error) {
                    busy = false;
                }
            });
        }

        // Vote au clavier : fleche gauche / fleche droite.
        document.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') { vote(cards[0]); }
            if (event.key === 'ArrowRight') { vote(cards[1]); }
        });
    }
})();
