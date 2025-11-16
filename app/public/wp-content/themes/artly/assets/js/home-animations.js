document.addEventListener('DOMContentLoaded', function () {
  if (typeof gsap === 'undefined') return;

  if (typeof ScrollTrigger !== 'undefined') {
    gsap.registerPlugin(ScrollTrigger);
  }

  gsap.from('.artly-hero-left', {
    opacity: 0,
    y: 40,
    duration: 0.9,
    ease: 'power3.out'
  });

  gsap.from('.artly-hero-right', {
    opacity: 0,
    y: 40,
    duration: 0.9,
    delay: 0.15,
    ease: 'power3.out'
  });

  var consoleEl = document.querySelector('.artly-hero-console');
  if (consoleEl) {
    gsap.to(consoleEl, {
      y: -8,
      duration: 3,
      yoyo: true,
      repeat: -1,
      ease: 'sine.inOut'
    });
  }

  if (typeof ScrollTrigger !== 'undefined') {
    gsap.utils.toArray('.artly-section').forEach(function (section) {
      gsap.from(section, {
        opacity: 0,
        y: 32,
        duration: 0.8,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: section,
          start: 'top 80%',
          toggleActions: 'play none none reverse'
        }
      });
    });
  }

  var featureCards = document.querySelectorAll('.artly-feature-card');
  if (featureCards.length && typeof ScrollTrigger !== 'undefined') {
    gsap.from(featureCards, {
      opacity: 0,
      y: 26,
      duration: 0.75,
      ease: 'power2.out',
      stagger: 0.08,
      scrollTrigger: {
        trigger: '.artly-features',
        start: 'top 80%',
        toggleActions: 'play none none reverse'
      }
    });
  }
});
