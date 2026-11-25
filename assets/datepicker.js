/*
 * Covoite — calendrier maison (remplace le <input type="date"> du navigateur).
 *
 * Script classique charge dans le <head> de base.html.twig : il expose window.DatePicker.
 *
 *   // Calendrier affiche en permanence (assistant "Proposer un trajet")
 *   const cal = DatePicker.calendar(div, { value: '2026-11-26', min: '2026-11-25', onSelect: (v) => {} });
 *   cal.setValue('2026-11-27');
 *
 *   // Calendrier dans une bulle qui s'ouvre au clic (barre de recherche)
 *   DatePicker.popover(box, { min: '...', allowClear: true, onChange: (v) => {} });
 *       box contient : [data-date-trigger] (bouton), [data-date-label] (texte affiche),
 *                      [data-date-value] (champ cache envoye avec le formulaire), [data-date-pop] (la bulle)
 *
 * Les dates circulent au format "AAAA-MM-JJ", comme un vrai champ date HTML :
 * le controleur Symfony n'a rien a changer.
 */
window.DatePicker = (() => {
    const MONTHS   = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];
    const MONTHS_S = ['janv.', 'fevr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'aout', 'sept.', 'oct.', 'nov.', 'dec.'];
    const DAYS_S   = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    const DAYS_L   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    const HEAD     = ['L', 'M', 'M', 'J', 'V', 'S', 'D']; // semaine qui commence le lundi, comme en France

    const pad   = (n) => String(n).padStart(2, '0');
    const iso   = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const parse = (s) => (/^\d{4}-\d{2}-\d{2}$/.test(s || '') ? new Date(s + 'T12:00:00') : null); // midi : pas de piege d'heure d'ete
    const today = () => iso(new Date());
    const addDays = (s, n) => { const d = parse(s); d.setDate(d.getDate() + n); return iso(d); };

    /** "2026-11-26" -> "Demain", "Aujourd'hui", "ven. 28 nov." (style 'short') ou "vendredi 28 novembre" ('long') */
    function format(value, style = 'short') {
        const d = parse(value);
        if (!d) return '';
        if (value === today()) return 'Aujourd\'hui';
        if (value === addDays(today(), 1)) return 'Demain';
        return style === 'long'
            ? DAYS_L[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()]
            : DAYS_S[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS_S[d.getMonth()];
    }

    const svgArrow = (dir) =>
        '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' +
        (dir < 0 ? 'M15 18l-6-6 6-6' : 'M9 18l6-6-6-6') + '"/></svg>';

    // ═══════════ CALENDRIER (grille d'un mois) ═══════════
    function calendar(root, opts = {}) {
        const o = Object.assign({ value: '', min: today(), max: '', onSelect() {} }, opts);
        let selected = o.value;
        let view  = parse(selected || o.min || today()); // mois affiche
        view.setDate(1);
        let focus = selected || (o.min > today() ? o.min : today()); // jour qui a le focus clavier

        const disabled = (v) => (o.min && v < o.min) || (o.max && v > o.max);

        function render(keepFocus = false) {
            const y = view.getFullYear(), m = view.getMonth();
            const first  = new Date(y, m, 1, 12);
            const blanks = (first.getDay() + 6) % 7; // lundi = 0
            const count  = new Date(y, m + 1, 0).getDate();
            const minD   = parse(o.min);
            const canPrev = !minD || (y > minD.getFullYear() || (y === minD.getFullYear() && m > minD.getMonth()));

            root.innerHTML = '';
            root.classList.add('select-none');

            // En-tete : mois + fleches
            const head = document.createElement('div');
            head.className = 'flex items-center justify-between mb-3';
            head.innerHTML =
                '<button type="button" data-cal-nav="-1" aria-label="Mois precedent" class="w-9 h-9 rounded-full flex items-center justify-center text-brand hover:bg-cream transition disabled:opacity-25 disabled:hover:bg-transparent"' + (canPrev ? '' : ' disabled') + '>' + svgArrow(-1) + '</button>' +
                '<p class="text-sm font-bold text-brand capitalize" aria-live="polite"></p>' +
                '<button type="button" data-cal-nav="1" aria-label="Mois suivant" class="w-9 h-9 rounded-full flex items-center justify-center text-brand hover:bg-cream transition">' + svgArrow(1) + '</button>';
            head.querySelector('p').textContent = MONTHS[m] + ' ' + y;
            root.appendChild(head);

            // Grille
            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-7 gap-y-1 text-center';
            grid.setAttribute('role', 'grid');
            HEAD.forEach((h) => {
                const c = document.createElement('span');
                c.className = 'text-[11px] font-semibold text-brand/35 pb-1';
                c.textContent = h;
                grid.appendChild(c);
            });
            for (let i = 0; i < blanks; i++) grid.appendChild(document.createElement('span'));

            for (let day = 1; day <= count; day++) {
                const v   = y + '-' + pad(m + 1) + '-' + pad(day);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.calDay = v;
                btn.textContent = day;
                btn.tabIndex = v === focus ? 0 : -1;
                btn.setAttribute('aria-label', format(v, 'long'));
                const isSel = v === selected, isToday = v === today(), off = disabled(v);
                btn.disabled = off;
                if (isSel) btn.setAttribute('aria-pressed', 'true');
                btn.className = 'relative mx-auto w-10 h-10 rounded-full text-sm font-semibold transition outline-none focus-visible:ring-4 focus-visible:ring-lime/40 ' +
                    (isSel ? 'bg-brand text-cream shadow-card'
                        : off ? 'text-brand/20 cursor-not-allowed'
                        : 'text-brand hover:bg-lime-soft');
                if (isToday && !isSel) {
                    const dot = document.createElement('span');
                    dot.className = 'absolute bottom-1.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-lime';
                    btn.appendChild(dot);
                }
                grid.appendChild(btn);
            }
            root.appendChild(grid);

            if (keepFocus) {
                const f = root.querySelector('[data-cal-day="' + focus + '"]');
                if (f) f.focus();
            }
        }

        function go(delta) {
            view.setMonth(view.getMonth() + delta);
            const d = parse(focus);
            if (d.getMonth() !== view.getMonth() || d.getFullYear() !== view.getFullYear()) {
                focus = iso(new Date(view.getFullYear(), view.getMonth(), 1, 12));
            }
            render();
        }

        root.addEventListener('click', (e) => {
            const nav = e.target.closest('[data-cal-nav]');
            if (nav) { go(+nav.dataset.calNav); return; }
            const b = e.target.closest('[data-cal-day]');
            if (!b || b.disabled) return;
            selected = focus = b.dataset.calDay;
            render(true);
            o.onSelect(selected);
        });

        // Clavier : fleches = jour, Page haut/bas = mois, Debut/Fin = semaine
        root.addEventListener('keydown', (e) => {
            if (!e.target.matches('[data-cal-day]')) return;
            const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
            let next = null;
            if (moves[e.key]) next = addDays(focus, moves[e.key]);
            else if (e.key === 'PageUp' || e.key === 'PageDown') {
                const d = parse(focus); d.setMonth(d.getMonth() + (e.key === 'PageUp' ? -1 : 1)); next = iso(d);
            } else if (e.key === 'Home') next = addDays(focus, -((parse(focus).getDay() + 6) % 7));
            else if (e.key === 'End') next = addDays(focus, 6 - ((parse(focus).getDay() + 6) % 7));
            if (!next) return;
            e.preventDefault();
            if (disabled(next)) return;
            focus = next;
            const d = parse(next);
            view = new Date(d.getFullYear(), d.getMonth(), 1, 12);
            render(true);
        });

        render();

        return {
            setValue(v) {
                selected = v;
                if (v && parse(v)) { focus = v; const d = parse(v); view = new Date(d.getFullYear(), d.getMonth(), 1, 12); }
                render();
            },
            focus() { render(true); },
        };
    }

    // ═══════════ BULLE (champ de la barre de recherche) ═══════════
    function popover(box, opts = {}) {
        const o = Object.assign({ min: today(), allowClear: false, placeholder: 'Quand ?', onChange() {} }, opts);
        const trigger = box.querySelector('[data-date-trigger]');
        const label   = box.querySelector('[data-date-label]');
        const input   = box.querySelector('[data-date-value]');
        const pop     = box.querySelector('[data-date-pop]');

        // Raccourcis + calendrier + (optionnel) "Toutes les dates"
        pop.innerHTML =
            '<div class="flex flex-wrap gap-2 mb-4" data-date-quick></div>' +
            '<div data-date-cal></div>' +
            (o.allowClear ? '<div class="mt-3 pt-3 border-t border-black/5 flex justify-between items-center">' +
                '<button type="button" data-date-clear class="text-xs font-semibold text-brand/50 hover:text-brand transition">Toutes les dates</button>' +
                '<button type="button" data-date-close class="text-xs font-bold text-brand bg-cream hover:bg-lime-soft px-3 py-1.5 rounded-full transition">Fermer</button></div>' : '');

        const quick = [
            ['Aujourd\'hui', () => today()],
            ['Demain', () => addDays(today(), 1)],
            ['Ce week-end', () => { const d = new Date(); const toSat = (6 - d.getDay() + 7) % 7; return addDays(today(), toSat); }],
        ];
        const quickBox = pop.querySelector('[data-date-quick]');
        quick.forEach(([text, fn]) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'text-xs font-semibold px-3.5 py-2 rounded-full border border-black/10 text-brand hover:border-brand/40 transition aria-pressed:bg-brand aria-pressed:text-cream aria-pressed:border-brand';
            b.textContent = text;
            b.addEventListener('click', () => choose(fn()));
            b.dataset.quick = text;
            quickBox.appendChild(b);
        });

        const cal = calendar(pop.querySelector('[data-date-cal]'), { value: input.value, min: o.min, onSelect: (v) => choose(v) });

        const paint = () => {
            label.textContent = input.value ? format(input.value) : o.placeholder;
            label.classList.toggle('text-brand/35', !input.value);
            label.classList.toggle('font-normal', !input.value);
            quickBox.querySelectorAll('button').forEach((b, i) => b.setAttribute('aria-pressed', String(!!input.value && quick[i][1]() === input.value)));
        };

        const open = (yes) => {
            pop.hidden = !yes;
            trigger.setAttribute('aria-expanded', String(yes));
            if (yes) { cal.setValue(input.value); setTimeout(() => cal.focus(), 30); }
        };

        function choose(v) {
            input.value = v;
            paint();
            open(false);
            trigger.focus();
            o.onChange(v);
        }

        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.addEventListener('click', () => open(pop.hidden));
        pop.querySelector('[data-date-clear]')?.addEventListener('click', () => choose(''));
        pop.querySelector('[data-date-close]')?.addEventListener('click', () => open(false));

        // Fermeture : clic en dehors ou Echap
        document.addEventListener('mousedown', (e) => { if (!pop.hidden && !box.contains(e.target)) open(false); });
        box.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !pop.hidden) { e.stopPropagation(); open(false); trigger.focus(); } });

        paint();
        return { open, setValue: (v) => { input.value = v; paint(); } };
    }

    return { calendar, popover, format, iso, parse, today, addDays };
})();
