// Transform-based hero slider with arrows and dots
(function() {
    const INTERVAL = 6000;
    const container = document.querySelector('.carousel-container');
    const track = document.querySelector('.carousel-track');
    const slides = Array.from(document.querySelectorAll('.carousel-slide'));
    if (!container || !track || slides.length === 0) return;

    let idx = 0;
    let timer = null;

    function update() {
        track.style.transform = `translateX(-${idx * 100}%)`;
        // reset slide-up animations for the active slide
        slides.forEach((s, i) => {
            const elems = s.querySelectorAll('.animate-slide-up');
            if (i === idx) {
                elems.forEach(el => {
                    el.style.animation = 'none';
                    void el.offsetHeight;
                    el.style.animation = '';
                });
            }
        });
        // update dots
        const dots = document.querySelectorAll('.carousel-dots button');
        dots.forEach((d, i) => d.classList.toggle('active', i === idx));
    }

    function start() { timer = setInterval(() => { idx = (idx + 1) % slides.length; update(); }, INTERVAL); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }

    // create dots
    const dotsWrap = document.querySelector('.carousel-dots');
    slides.forEach((_, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.addEventListener('click', () => { idx = i; update(); });
        dotsWrap.appendChild(btn);
    });

    // arrows
    const left = document.querySelector('.carousel-arrow.left');
    const right = document.querySelector('.carousel-arrow.right');
    left && left.addEventListener('click', () => { idx = (idx - 1 + slides.length) % slides.length; update(); });
    right && right.addEventListener('click', () => { idx = (idx + 1) % slides.length; update(); });

    container.addEventListener('mouseenter', stop);
    container.addEventListener('mouseleave', start);

    update();
    start();
})();

