// Alpine is provided by Livewire. Motion stays local to its page/component lifecycle.
document.addEventListener('alpine:init', () => {
    Alpine.data('workspaceTabs', () => ({
        onKey(event) {
            const tabs = [...this.$el.querySelectorAll('[role="tab"]')];
            let index = tabs.indexOf(event.target);
            if (index < 0 || !['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
                : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[index].focus();
            tabs[index].click();
        },
    }));

    Alpine.data('heroDemo', () => ({
        elapsed:12000, playing:false, visible:false, started:false, timer:null, observer:null,
        reduced:window.matchMedia('(prefers-reduced-motion: reduce)'),
        get stage() {
            return this.elapsed < 640 ? 0 : this.elapsed < 3600 ? 1 : this.elapsed < 5200 ? 2
                : this.elapsed < 7520 ? 3 : this.elapsed < 10000 ? 4 : this.elapsed < 15120 ? 5 : 6;
        },
        get command() {
            return 'Periksa header target demo.'.slice(0, Math.max(0, Math.floor((this.elapsed - 640) / 68)));
        },
        init() {
            if (!('IntersectionObserver' in window)) return;
            this.onVisibility = () => document.hidden ? this.stop() : this.resumeIfAllowed();
            this.onMotion = () => {
                if (this.reduced.matches) { this.stop(); this.elapsed = 12000; }
                else this.resumeIfAllowed();
            };
            document.addEventListener('visibilitychange', this.onVisibility);
            this.reduced.addEventListener('change', this.onMotion);
            this.observer = new IntersectionObserver(entries => {
                this.visible = entries[0].isIntersecting && entries[0].intersectionRatio >= 0.35;
                this.visible ? this.resumeIfAllowed() : this.stop();
            }, { threshold:[0, 0.35] });
            this.observer.observe(this.$el);
        },
        resumeIfAllowed() {
            if (!this.visible || document.hidden || this.reduced.matches) return;
            if (!this.started) { this.elapsed = 0; this.started = true; }
            this.start();
        },
        start() {
            if (this.timer !== null || this.reduced.matches) return;
            this.playing = true;
            this.timer = setInterval(() => { this.elapsed = (this.elapsed + 80) % 16000; }, 80);
        },
        stop() {
            clearInterval(this.timer);
            this.timer = null;
            this.playing = false;
        },
        destroy() {
            this.stop();
            this.observer?.disconnect();
            document.removeEventListener('visibilitychange', this.onVisibility);
            this.reduced.removeEventListener('change', this.onMotion);
        },
    }));
});

let disposePageMotion = () => {};

function initializePageMotion() {
    disposePageMotion();
    const cleanups = [];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

    if ('IntersectionObserver' in window) {
        document.documentElement.classList.add('reveal-ready');
        const observer = new IntersectionObserver(entries => {
            for (const entry of entries) {
                if (entry.isIntersecting) entry.target.classList.add('visible');
                else if (!entry.target.contains(document.activeElement)) entry.target.classList.remove('visible');
            }
        }, { threshold:0.08 });
        document.querySelectorAll('[data-reveal]').forEach(element => observer.observe(element));
        cleanups.push(() => observer.disconnect());
    }

    document.querySelectorAll('[data-faq]').forEach(details => {
        const summary = details.querySelector('summary');
        const answer = details.querySelector('.faq-answer');
        let expansion = null;
        let fade = null;
        let expanded = details.open;

        const click = event => {
            if (reduced.matches || typeof details.animate !== 'function') return;
            event.preventDefault();
            const from = details.getBoundingClientRect().height;
            expanded = !expanded;
            expansion?.cancel();
            fade?.cancel();
            details.open = true;
            details.style.height = '';
            const to = expanded ? details.getBoundingClientRect().height : summary.getBoundingClientRect().height;
            details.style.overflow = 'hidden';
            expansion = details.animate([{height:`${from}px`}, {height:`${to}px`}], {
                duration:expanded ? 300 : 220, easing:'cubic-bezier(.2,.7,.25,1)',
            });
            fade = answer.animate(expanded
                ? [{opacity:0, transform:'translateY(-5px)'}, {opacity:1, transform:'translateY(0)'}]
                : [{opacity:1}, {opacity:0}], {duration:expanded ? 260 : 140, fill:'both'});
            expansion.onfinish = () => {
                details.open = expanded;
                details.style.overflow = '';
                fade?.cancel();
                expansion = null;
            };
        };
        const synchronizeOpenState = () => { if (!expansion) expanded = details.open; };
        summary.addEventListener('click', click);
        details.addEventListener('toggle', synchronizeOpenState);
        cleanups.push(() => {
            summary.removeEventListener('click', click);
            details.removeEventListener('toggle', synchronizeOpenState);
            expansion?.cancel();
            fade?.cancel();
            details.style.overflow = '';
        });
    });

    disposePageMotion = () => cleanups.forEach(cleanup => cleanup());
}

document.addEventListener('DOMContentLoaded', initializePageMotion);
document.addEventListener('livewire:navigated', initializePageMotion);
document.addEventListener('livewire:navigating', () => disposePageMotion());
