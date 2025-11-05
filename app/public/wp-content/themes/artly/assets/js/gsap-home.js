/**
 * Artly Cinematic Homepage Animations
 * GSAP + ScrollTrigger + Lottie
 */

document.addEventListener("DOMContentLoaded", () => {
  // Register plugins
  if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
    console.warn('GSAP or ScrollTrigger not loaded');
    return;
  }

  gsap.registerPlugin(ScrollTrigger, TextPlugin);

  // Responsive breakpoint
  const isMobile = window.innerWidth < 768;

  /* ==============================
     🎥  SCENE 1: HERO INTRO
     ============================== */
  const tlHero = gsap.timeline({
    defaults: { ease: "power3.out" },
  });

  // Eyebrow
  tlHero.from(".artly-eyebrow", {
    y: 30,
    opacity: 0,
    duration: 0.7
  })
  // Title
  .from(".artly-hero-title", {
    y: 80,
    opacity: 0,
    duration: 1.1
  }, "-=0.5")
  // Subtitle
  .from(".artly-hero-sub", {
    y: 60,
    opacity: 0,
    duration: 0.9
  }, "-=0.6")
  // Buttons
  .from(".artly-hero-actions .artly-btn", {
    y: 40,
    opacity: 0,
    duration: 0.8,
    stagger: 0.15
  }, "-=0.5")
  // Meta text
  .from(".artly-hero-meta", {
    opacity: 0,
    duration: 0.6
  }, "-=0.3")
  // Hero card
  .from(".artly-hero-card", {
    scale: 0.9,
    x: 50,
    opacity: 0,
    duration: 1.2
  }, "-=0.8");

  // Subtle background gradient animation
  gsap.to(".artly-hero", {
    backgroundPosition: "200% 200%",
    duration: 20,
    repeat: -1,
    yoyo: true,
    ease: "none"
  });

  // Floating animation for hero card (subtle)
  gsap.to(".artly-hero-card", {
    y: -12,
    duration: 4,
    repeat: -1,
    yoyo: true,
    ease: "power1.inOut",
    delay: 1
  });

  // Optional: Lottie animation in hero card background
  // Only loads if the JSON file exists
  const heroCardLottie = document.querySelector(".artly-hero-card");
  if (heroCardLottie && typeof lottie !== 'undefined') {
    try {
      const lottieContainer = document.createElement('div');
      lottieContainer.className = 'lottie-hero-bg';
      lottieContainer.style.cssText = 'position: absolute; inset: 0; z-index: -1; opacity: 0.3; pointer-events: none;';
      heroCardLottie.style.position = 'relative';
      heroCardLottie.appendChild(lottieContainer);

      // Get the correct path
      const lottiePath = get_template_directory_uri() + "/assets/js/lottie/flow-bg.json";
      
      // Load animation - loadAnimation returns an animation object, not a Promise
      const anim = lottie.loadAnimation({
        container: lottieContainer,
        renderer: "svg",
        loop: true,
        autoplay: true,
        path: lottiePath,
        // Handle errors via the renderer's data_ready event and error callback
        rendererSettings: {
          preserveAspectRatio: 'xMidYMid slice'
        }
      });

      // Listen for errors
      anim.addEventListener('data_failed', () => {
        console.log('Lottie animation file not found (optional feature)');
        lottieContainer.remove();
      });

      // If animation fails to load initially, remove container
      anim.addEventListener('DOMLoaded', () => {
        // Success - animation loaded
      });
      
    } catch (error) {
      console.log('Lottie animation failed to initialize (optional feature)');
    }
  }

  /* ==============================
     🎬 SCENE 2: HOW IT WORKS (Steps)
     ============================== */
  
  if (!isMobile) {
    // Desktop: Scroll-pinned cinematic timeline
    const tlSteps = gsap.timeline({
      scrollTrigger: {
        trigger: ".artly-section-steps",
        start: "top center",
        end: "+=2500",
        scrub: 1,
        pin: true,
        anticipatePin: 1
      },
    });

    tlSteps
      .from(".artly-step-card:nth-child(1)", {
        x: -200,
        opacity: 0,
        duration: 1
      })
      .to(".artly-step-card:nth-child(1)", {
        opacity: 0.3,
        duration: 0.3
      })
      .from(".artly-step-card:nth-child(2)", {
        y: 200,
        opacity: 0,
        duration: 1
      })
      .to(".artly-step-card:nth-child(2)", {
        opacity: 0.3,
        duration: 0.3
      })
      .from(".artly-step-card:nth-child(3)", {
        x: 200,
        opacity: 0,
        duration: 1
      });
  } else {
    // Mobile: Simple fade-up animation
    gsap.utils.toArray(".artly-step-card").forEach((card, i) => {
      gsap.from(card, {
        scrollTrigger: {
          trigger: card,
          start: "top 85%",
          toggleActions: "play none none none"
        },
        y: 60,
        opacity: 0,
        duration: 0.8,
        delay: i * 0.15,
        ease: "power3.out"
      });
    });
  }

  /* ==============================
     🎨 SCENE 3: PERSONAS
     ============================== */
  
  // Background color transition
  gsap.to(".artly-section-personas", {
    backgroundColor: "#030712",
    color: "#f9fafb",
    scrollTrigger: {
      trigger: ".artly-section-personas",
      start: "top 90%",
      end: "bottom 10%",
      scrub: true
    }
  });

  // Persona cards fade up
  gsap.utils.toArray(".artly-persona").forEach((card, i) => {
    gsap.from(card, {
      scrollTrigger: {
        trigger: card,
        start: "top 85%",
        toggleActions: "play none none none"
      },
      y: 100,
      opacity: 0,
      duration: 0.8,
      delay: i * 0.2,
      ease: "power3.out"
    });
  });

  // Parallax effect on personas visual card
  gsap.to(".artly-personas-visual", {
    yPercent: -25,
    ease: "none",
    scrollTrigger: {
      trigger: ".artly-section-personas",
      start: "top bottom",
      end: "bottom top",
      scrub: true
    }
  });

  /* ==============================
     💎 SCENE 4: BENEFITS
     ============================== */
  
  gsap.utils.toArray(".artly-benefit").forEach((el, i) => {
    gsap.from(el, {
      rotateY: 45,
      y: 80,
      opacity: 0,
      duration: 1,
      ease: "back.out(1.7)",
      scrollTrigger: {
        trigger: el,
        start: "top 85%",
        toggleActions: "play none none none"
      },
      delay: i * 0.15
    });
  });

  // Enhanced hover interactions
  gsap.utils.toArray(".artly-benefit").forEach((card) => {
    card.addEventListener("mouseenter", () => {
      gsap.to(card, {
        y: -8,
        scale: 1.02,
        duration: 0.3,
        ease: "power2.out"
      });
    });
    card.addEventListener("mouseleave", () => {
      gsap.to(card, {
        y: 0,
        scale: 1,
        duration: 0.3,
        ease: "power2.out"
      });
    });
  });

  /* ==============================
     💰 SCENE 5: PRICING STRIP
     ============================== */
  
  const tlPricing = gsap.timeline({
    scrollTrigger: {
      trigger: ".artly-section-pricing-strip",
      start: "top 90%",
      toggleActions: "play none none none"
    }
  });

  tlPricing
    .from(".artly-pricing-strip", {
      opacity: 0,
      y: 150,
      duration: 1.2,
      ease: "power3.out"
    })
    .from(".artly-pricing-strip h2", {
      x: -50,
      opacity: 0,
      duration: 0.6
    }, "-=0.5")
    .from(".artly-pricing-strip-cta", {
      scale: 0.85,
      opacity: 0,
      duration: 0.8
    }, "-=0.4");

  /* ==============================
     🪄 SCENE 6: STORY / BRAND OUTRO
     ============================== */
  
  // Stagger paragraph reveals
  gsap.utils.toArray(".artly-story-body p").forEach((p, i) => {
    gsap.from(p, {
      scrollTrigger: {
        trigger: ".artly-section-story",
        start: "top 85%",
        toggleActions: "play none none none"
      },
      y: 60,
      opacity: 0,
      duration: 0.8,
      stagger: 0.25,
      delay: i * 0.2,
      ease: "power3.out"
    });
  });

  // Background color transition
  gsap.to(".artly-section-story", {
    backgroundColor: "#fef3c7",
    scrollTrigger: {
      trigger: ".artly-section-story",
      start: "top 90%",
      end: "bottom 20%",
      scrub: true
    }
  });

  // Section headers animation
  gsap.utils.toArray(".artly-section-header").forEach((header) => {
    gsap.from(header, {
      scrollTrigger: {
        trigger: header,
        start: "top 85%",
        toggleActions: "play none none none"
      },
      y: 40,
      opacity: 0,
      duration: 0.8,
      ease: "power3.out"
    });
  });

  /* ==============================
     🎯 OPTIMIZATION: Handle Resize
     ============================== */
  
  let resizeTimer;
  window.addEventListener("resize", () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      ScrollTrigger.refresh();
    }, 250);
  });

  // Helper function to get template directory URI (WordPress)
  function get_template_directory_uri() {
    const scripts = document.querySelectorAll('script[src*="gsap-home.js"]');
    if (scripts.length > 0) {
      const src = scripts[0].src;
      return src.substring(0, src.lastIndexOf('/assets'));
    }
    return '/wp-content/themes/artly';
  }
});
