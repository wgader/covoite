/*
 * Covoite — autocompletion des lieux (adresses, magasins, gares, villes, codes postaux).
 *
 * Script classique charge dans le <head> de base.html.twig : il expose window.Places,
 * utilisable par n'importe quelle page (barre de recherche, assistant "Proposer un trajet"...).
 *
 *   Places.attach(input, list, {
 *       mode: 'tout',                 // 'tout' ou 'ville' (villes + codes postaux seulement)
 *       enterPicksFirst: false,       // Entree choisit la 1re suggestion meme sans fleche
 *       display: (lieu) => '...',     // texte mis dans le champ apres le choix
 *       onPick: (lieu) => {},         // lieu = {kind, label, detail, city, postcode, lat, lng, distance}
 *       onType: () => {},             // l'utilisateur retape : l'ancien choix n'est plus valable
 *       onError: () => {},            // le serveur ne repond pas
 *   });
 *
 * Le tri "du plus proche au plus loin" est fait par le serveur (/api/lieux), a partir de :
 *   - la position GPS du navigateur, si l'utilisateur l'a deja autorisee ;
 *   - sinon une position approximative d'apres l'adresse IP (aucune fenetre d'autorisation).
 * La fenetre "Autoriser la position ?" n'apparait QUE si on clique "Utiliser ma position".
 */
window.Places = (() => {
    const script = document.currentScript;
    const URLS = {
        search:   script.dataset.searchUrl,
        reverse:  script.dataset.reverseUrl,
        position: script.dataset.positionUrl,
    };
    const STORE = 'covoite.position';

    // ═══════════ POSITION DE L'UTILISATEUR ═══════════
    let position = null;
    try { position = JSON.parse(sessionStorage.getItem(STORE)); } catch (e) { /* stockage bloque */ }
    let ipLookup = null;

    const savePosition = (p) => {
        position = p;
        try { sessionStorage.setItem(STORE, JSON.stringify(p)); } catch (e) { /* stockage bloque */ }
    };

    // Position approximative d'apres l'IP (une seule requete par visite)
    const approximate = () => {
        if (position) return Promise.resolve(position);
        ipLookup ??= fetch(URLS.position)
            .then((r) => (r.ok ? r.json() : null))
            .then((p) => {
                if (p && typeof p.lat === 'number' && !position) savePosition({ lat: p.lat, lng: p.lng, precise: false });
                return position;
            })
            .catch(() => null);
        return ipLookup;
    };

    // Position GPS deja obtenue pendant cette session (moins d'1 h) : on la reutilise SANS rappeler
    // le navigateur. Certains (Safari notamment) reaffichent "Autoriser ?" a chaque appel.
    const FRESH = 60 * 60 * 1000;
    const hasFreshPrecise = () => !!(position && position.precise && Date.now() - (position.at || 0) < FRESH);

    // Position GPS precise (le navigateur demande l'autorisation la 1re fois)
    const precise = () => new Promise((resolve) => {
        if (hasFreshPrecise()) return resolve(position);
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
            (p) => { savePosition({ lat: p.coords.latitude, lng: p.coords.longitude, precise: true, at: Date.now() }); resolve(position); },
            () => resolve(null),
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 }
        );
    });

    // Au 1er clic dans un champ : position IP, et GPS seulement s'il est DEJA autorise
    const warmUp = () => {
        approximate();
        if (hasFreshPrecise()) return;
        if (navigator.permissions) {
            navigator.permissions.query({ name: 'geolocation' })
                .then((s) => { if (s.state === 'granted') precise(); })
                .catch(() => {});
        }
    };

    // ═══════════ AFFICHAGE ═══════════
    const ICONS = {
        city:    '<path d="M3 21h18M5 21V9l5-3v15M10 21V4l9 4v13"/><path d="M13 10h3M13 14h3M13 18h3"/>',
        address: '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        area:    '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        place:   '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><path d="m12 6.5.9 1.9 2.1.3-1.5 1.5.4 2.1-1.9-1-1.9 1 .4-2.1-1.5-1.5 2.1-.3z"/>',
        shop:    '<path d="M5 8h14l-1.2 12.1a1 1 0 0 1-1 .9H7.2a1 1 0 0 1-1-.9z"/><path d="M9 11V7a3 3 0 0 1 6 0v4"/>',
        station: '<rect x="6" y="3" width="12" height="13" rx="3"/><path d="M6 10h12M9.5 13h0M14.5 13h0M9 16l-2.5 4.5M15 16l2.5 4.5M8 19h8"/>',
        airport: '<path d="M2.5 16.5 21 9.5a1.8 1.8 0 0 0-1.4-3.2L14 8 8.5 3.5 6.8 4.3l3 5.1-4.2 1.6-2.2-1.6-1.4.6 1.5 3.5z"/><path d="M3 21h18"/>',
        parking: '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M10 16V8h2.5a2.5 2.5 0 0 1 0 5H10"/>',
        locate:  '<circle cx="12" cy="12" r="7.5"/><circle cx="12" cy="12" r="2.5" fill="currentColor"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22"/>',
    };
    const KIND_LABEL = { shop: 'Commerce', station: 'Gare', airport: 'Aeroport', parking: 'Parking', place: 'Lieu' };
    const MIN_CHARS  = 3; // en dessous, l'IGN ne repond pas (et les resultats seraient de toute facon trop vagues)

    const svg = (kind, cls) =>
        '<svg class="' + cls + '" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">' + (ICONS[kind] || ICONS.address) + '</svg>';

    // "Angoulême" -> "angouleme" (comparaison sans accents ni majuscules)
    const fold = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    // Met en surbrillance les mots tapes dans le nom du lieu (construit en DOM : aucun risque d'injection HTML)
    const highlight = (el, text, query) => {
        const chars  = [...text];
        const folded = chars.map((c) => fold(c));
        if (folded.some((c) => c.length !== 1)) { el.textContent = text; return; }
        const hay  = folded.join('');
        const mark = new Array(chars.length).fill(false);
        fold(query).split(/\s+/).filter((w) => w.length > 0).forEach((word) => {
            const at = hay.indexOf(word);
            if (at >= 0) for (let i = at; i < at + word.length; i++) mark[i] = true;
        });
        let i = 0;
        while (i < chars.length) {
            let j = i;
            while (j < chars.length && mark[j] === mark[i]) j++;
            const part = chars.slice(i, j).join('');
            if (mark[i]) {
                const m = document.createElement('mark');
                m.className = 'bg-lime-soft text-brand rounded-sm';
                m.textContent = part;
                el.appendChild(m);
            } else {
                el.appendChild(document.createTextNode(part));
            }
            i = j;
        }
    };

    const formatDistance = (m) => (m < 1000 ? (Math.round(m / 50) * 50 || 50) + ' m' : (m < 10000 ? (m / 1000).toFixed(1).replace('.', ',') : Math.round(m / 1000)) + ' km');

    /**
     * Texte complet d'un lieu, mis dans le champ apres le choix :
     *   ville    -> "Angouleme"
     *   magasin  -> "E.Leclerc, Impasse Diderot, 16000 Angouleme"
     *   adresse  -> "123 Avenue de la Republique, 16340 L'Isle-d'Espagnac"
     */
    const fullText = (p) => {
        if (p.kind === 'city') return p.label;
        const where = p.detail || [p.postcode, p.city].filter(Boolean).join(' ');
        return where ? p.label + ', ' + where : p.label;
    };

    // ═══════════ BRANCHEMENT SUR UN CHAMP ═══════════
    let uid = 0;

    function attach(input, list, opts = {}) {
        const o = Object.assign({ mode: 'tout', enterPicksFirst: false, display: fullText, onPick() {}, onType() {}, onError() {} }, opts);
        const listId = list.id || ('places-list-' + (++uid));
        list.id = listId;
        list.setAttribute('role', 'listbox');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', listId);
        input.setAttribute('aria-expanded', 'false');

        let options = [];   // ce qui est affiche : suggestions (+ "Utiliser ma position")
        let active  = -1;
        let source  = 'none';
        let status  = '';   // message affiche a la place des resultats ('', 'loading-locate', 'empty', 'short', 'error', 'denied')
        let timer   = null;
        let ctrl    = null;
        let lastQuery = '';

        const open  = (yes) => { list.hidden = !yes; input.setAttribute('aria-expanded', yes ? 'true' : 'false'); };
        const canLocate = () => !!navigator.geolocation && URLS.reverse;

        function render() {
            list.innerHTML = '';

            options.forEach((p, i) => {
                const li  = document.createElement('li');
                li.id = listId + '-' + i;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', i === active ? 'true' : 'false');

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.tabIndex = -1;
                btn.className = 'w-full text-left flex items-center gap-3 px-4 py-2.5 transition ' + (i === active ? 'bg-cream' : 'hover:bg-cream');

                const iconBox = document.createElement('span');
                iconBox.className = 'w-9 h-9 rounded-full flex items-center justify-center shrink-0 ' + (p.kind === 'locate' ? 'bg-lime-soft text-brand' : 'bg-cream text-brand/60');
                iconBox.innerHTML = svg(p.kind, 'w-[18px] h-[18px]');

                const text  = document.createElement('span');
                text.className = 'flex-1 min-w-0';
                const title = document.createElement('span');
                title.className = 'block text-sm font-semibold text-brand truncate';
                const sub   = document.createElement('span');
                sub.className = 'block text-xs text-brand/50 truncate';

                if (p.kind === 'locate') {
                    title.textContent = status === 'loading-locate' ? 'Localisation en cours...' : 'Utiliser ma position actuelle';
                    sub.textContent   = status === 'denied' ? 'Position refusee : autorisez-la dans votre navigateur' : 'Trouve l\'adresse ou vous etes';
                } else {
                    highlight(title, p.label, lastQuery);
                    sub.textContent = [KIND_LABEL[p.kind], p.detail].filter(Boolean).join(' · ');
                }
                text.append(title, sub);
                btn.append(iconBox, text);

                if (p.distance != null && position && position.precise) {
                    const d = document.createElement('span');
                    d.className = 'text-[11px] font-semibold text-brand/40 shrink-0';
                    d.textContent = formatDistance(p.distance);
                    btn.appendChild(d);
                }

                // mousedown (et pas click) : se declenche AVANT que le champ perde le focus
                btn.addEventListener('mousedown', (e) => { e.preventDefault(); choose(i); });
                li.appendChild(btn);
                list.appendChild(li);
            });

            const MESSAGES = {
                empty: 'Aucun lieu trouve. Verifiez l\'orthographe ou essayez une ville, une adresse, un code postal.',
                short: 'Tapez au moins ' + MIN_CHARS + ' caracteres.',
                error: 'Recherche indisponible pour le moment. Vous pouvez quand meme taper le nom de la ville.',
            };
            if (MESSAGES[status]) {
                const li = document.createElement('li');
                li.className = 'px-4 py-3 text-sm ' + (status === 'error' ? 'text-red-600' : 'text-brand/50');
                li.textContent = MESSAGES[status];
                list.appendChild(li);
            }

            // Attribution des donnees (obligatoire : licences OpenStreetMap / Geoapify)
            if (options.some((p) => p.kind !== 'locate')) {
                const li = document.createElement('li');
                li.className = 'px-4 pt-2 mt-1 border-t border-black/5 text-[10px] text-brand/35';
                li.innerHTML = source === 'geoapify'
                    ? 'Powered by <a href="https://www.geoapify.com/" target="_blank" rel="noopener" class="underline">Geoapify</a> · &copy; OpenStreetMap · IGN'
                    : 'Donnees &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="underline">OpenStreetMap</a> · IGN';
                list.appendChild(li);
            }

            input.setAttribute('aria-activedescendant', active >= 0 ? listId + '-' + active : '');
            open(list.children.length > 0);
        }

        // Champ vide : on propose "Utiliser ma position"
        function showLocate() {
            options = canLocate() ? [{ kind: 'locate' }] : [];
            active = -1; status = '';
            render();
        }

        async function fetchPlaces(q) {
            if (ctrl) ctrl.abort();
            ctrl = new AbortController();
            await approximate(); // ne bloque qu'au tout 1er appel (requete IP), ensuite c'est en memoire
            const params = new URLSearchParams({ q, mode: o.mode });
            if (position) { params.set('lat', position.lat); params.set('lng', position.lng); }
            try {
                const r = await fetch(URLS.search + '?' + params, { signal: ctrl.signal, headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                if (input.value.trim() !== q) return; // l'utilisateur a deja retape autre chose
                lastQuery = q;
                source  = data.source;
                options = data.results || [];
                active  = -1;
                status  = options.length ? '' : 'empty';
                render();
            } catch (err) {
                if (err.name === 'AbortError') return;
                options = []; status = 'error'; render();
                o.onError(err);
            }
        }

        async function choose(i) {
            const p = options[i];
            if (!p) return;

            if (p.kind === 'locate') {
                status = 'loading-locate'; render();
                const pos = await precise();
                if (!pos) { status = 'denied'; render(); return; }
                try {
                    const r = await fetch(URLS.reverse + '?' + new URLSearchParams({ lat: pos.lat, lng: pos.lng }));
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    const place = (await r.json()).result;
                    place.distance = 0;
                    pick(place);
                } catch (e) {
                    status = ''; options = []; render();
                    o.onError(e);
                }
                return;
            }
            pick(p);
        }

        function pick(p) {
            input.value = o.display(p);
            options = []; active = -1; status = '';
            open(false);
            o.onPick(p);
        }

        input.addEventListener('focus', () => {
            warmUp();
            if (!input.value.trim()) showLocate();
            else if (options.length) render();
        });

        input.addEventListener('input', () => {
            o.onType();
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < MIN_CHARS) {
                if (ctrl) ctrl.abort();
                if (q.length === 0) showLocate();
                else { options = []; status = 'short'; render(); }
                return;
            }
            timer = setTimeout(() => fetchPlaces(q), 180);
        });

        input.addEventListener('keydown', (e) => {
            if (list.hidden || !options.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % options.length; render(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + options.length) % options.length; render(); }
            else if (e.key === 'Enter') {
                const i = active >= 0 ? active : (o.enterPicksFirst ? options.findIndex((p) => p.kind !== 'locate') : -1);
                if (i < 0) return; // rien de selectionne : Entree garde son comportement normal (envoyer le formulaire)
                e.preventDefault();
                e.stopPropagation(); // ne pas passer a l'etape suivante de l'assistant
                choose(i);
            }
            else if (e.key === 'Escape') { open(false); }
        });

        input.addEventListener('blur', () => setTimeout(() => open(false), 150));
    }

    return { attach, fullText, position: () => position };
})();
