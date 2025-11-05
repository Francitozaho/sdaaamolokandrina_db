// Effet de survol 3D pour les cartes
function applyCardHoverEffect() {
    const cards = document.querySelectorAll('.card');
    const strength = 15; // Force de l'effet de rotation

    cards.forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left; // Position X de la souris relative à la carte
            const y = e.clientY - rect.top;  // Position Y de la souris relative à la carte
            
            // Calculer les angles de rotation basés sur la position de la souris
            const xRotation = ((y - rect.height / 2) / rect.height) * strength;
            const yRotation = ((x - rect.width / 2) / rect.width) * -strength;
            
            // Appliquer la transformation avec un effet de profondeur
            card.style.transform = `
                scale(1.02)
                rotateX(${xRotation}deg)
                rotateY(${yRotation}deg)
            `;
            card.style.boxShadow = `
                0 5px 15px rgba(0,0,0,0.15),
                ${-yRotation}px ${-xRotation}px 15px rgba(0,0,0,0.1)
            `;
        });

        // Réinitialiser la transformation quand la souris quitte la carte
        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
            card.style.boxShadow = '';
        });

        // Effet de transition douce
        card.style.transition = 'transform 0.2s ease-out, box-shadow 0.2s ease-out';
        card.style.transformStyle = 'preserve-3d';
    });
}

// Lightweight parallax for bubbles
(function() {
    var root = document;
    var bubbles = root.querySelectorAll('.parallax .bubble');
    if (bubbles.length) {
        root.addEventListener('mousemove', function(e) {
            var w = window.innerWidth, h = window.innerHeight;
            var rx = (e.clientX / w) - 0.5;
            var ry = (e.clientY / h) - 0.5;
            bubbles.forEach(function(b, i) {
                var depth = (i + 1) * 8;
                b.style.transform = 'translate(' + (-rx * depth) + 'px,' + (-ry * depth) + 'px)';
            });
        });
    }

    // Scroll parallax for elements with data-parallax-y (value is factor e.g. 0.1)
    var parElems = [].slice.call(root.querySelectorAll('[data-parallax-y]'));
    if (parElems.length) {
        var onScroll = function() {
            var sy = window.pageYOffset || document.documentElement.scrollTop || 0;
            parElems.forEach(function(el) {
                var factor = parseFloat(el.getAttribute('data-parallax-y') || '0.1');
                el.style.transform = 'translateY(' + (sy * factor) + 'px)';
            });
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
})();


