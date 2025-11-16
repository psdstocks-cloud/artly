/**
 * Language Switcher for Bilingual Homepage
 * Handles language toggle, cookie management, and page reload
 */

(function() {
    'use strict';

    // Check if artlyLang object is available
    if (typeof artlyLang === 'undefined') {
        console.warn('artlyLang object not found. Language switcher may not work correctly.');
        return;
    }

    const currentLang = artlyLang.current || 'en';
    const otherLang = currentLang === 'en' ? 'ar' : 'en';
    const siteUrl = artlyLang.siteUrl || window.location.origin;

    /**
     * Set language cookie
     */
    function setLanguageCookie(lang) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days
        document.cookie = `artly_lang=${lang}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
    }

    /**
     * Switch language
     */
    function switchLanguage(lang) {
        if (!['en', 'ar'].includes(lang)) {
            console.error('Invalid language code:', lang);
            return;
        }

        // Set cookie
        setLanguageCookie(lang);

        // Get current URL
        const currentUrl = new URL(window.location.href);
        
        // Update or add lang parameter
        currentUrl.searchParams.set('lang', lang);

        // Reload page with new language
        window.location.href = currentUrl.toString();
    }

    /**
     * Initialize language switcher
     */
    function initLanguageSwitcher() {
        const langToggle = document.getElementById('artly-lang-toggle');
        
        if (!langToggle) {
            return;
        }

        // Add click handler
        langToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Switch to the other language
            switchLanguage(otherLang);
        });

        // Update HTML attributes based on current language
        const direction = currentLang === 'ar' ? 'rtl' : 'ltr';
        document.documentElement.setAttribute('dir', direction);
        document.documentElement.setAttribute('lang', currentLang);
    }

    /**
     * Update page direction and language attributes
     */
    function updatePageAttributes() {
        const direction = currentLang === 'ar' ? 'rtl' : 'ltr';
        document.documentElement.setAttribute('dir', direction);
        document.documentElement.setAttribute('lang', currentLang);
        
        // Update body class if needed
        document.body.classList.toggle('rtl-mode', currentLang === 'ar');
        document.body.classList.toggle('ltr-mode', currentLang === 'en');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initLanguageSwitcher();
            updatePageAttributes();
        });
    } else {
        initLanguageSwitcher();
        updatePageAttributes();
    }

    // Expose switchLanguage function globally for other scripts
    window.artlySwitchLanguage = switchLanguage;

})();

