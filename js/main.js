/**
 * Care Connect SL - Main JavaScript
 * Handles preloader, navigation, forms, animations, and interactions
 */

'use strict';

// ============================================
// OPTIMIZED PRELOADER
// ============================================
(function initPreloader() {
    const preloader = document.getElementById('preloader');
    if (!preloader) return;

    function hidePreloader() {
        if (preloader.classList.contains('hidden')) return;
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 400);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(hidePreloader, 300);
        });
    } else {
        setTimeout(hidePreloader, 200);
    }

    window.addEventListener('load', function() {
        setTimeout(hidePreloader, 100);
    });

    setTimeout(hidePreloader, 2000);
})();

// ============================================
// MOBILE NAVIGATION TOGGLE - FIXED
// ============================================
(function initMobileNav() {
    const toggleBtn = document.querySelector('.mobile-menu-toggle');
    if (!toggleBtn) return;

    // Create mobile menu if it doesn't exist
    let mobileMenu = document.querySelector('.mobile-menu');
    if (!mobileMenu) {
        mobileMenu = document.createElement('div');
        mobileMenu.className = 'mobile-menu';
        mobileMenu.setAttribute('role', 'navigation');
        mobileMenu.setAttribute('aria-label', 'Mobile navigation');

        const navLinks = document.querySelector('.nav-links');
        if (navLinks) {
            const clone = navLinks.cloneNode(true);
            mobileMenu.appendChild(clone);
        }

        const navActions = document.querySelector('.nav-actions');
        if (navActions) {
            const clone = navActions.cloneNode(true);
            mobileMenu.appendChild(clone);
        }

        document.body.appendChild(mobileMenu);
    }

    // Toggle function
    function toggleMobileMenu() {
        const isOpen = mobileMenu.classList.toggle('open');
        toggleBtn.setAttribute('aria-expanded', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    toggleBtn.addEventListener('click', toggleMobileMenu);

    // Close on link click
    mobileMenu.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
            mobileMenu.classList.remove('open');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenu.classList.contains('open')) {
            mobileMenu.classList.remove('open');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
    });

    // Close on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && mobileMenu.classList.contains('open')) {
            mobileMenu.classList.remove('open');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
    });
})();

// ============================================
// HERO SEARCH FUNCTIONALITY
// ============================================
(function initHeroSearch() {
    const heroSearch = document.getElementById('heroSearch');
    if (!heroSearch) return;

    heroSearch.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            performSearch(this.value);
        }
    });

    const searchBtn = document.querySelector('.btn-search');
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            const searchInput = document.getElementById('heroSearch');
            if (searchInput) {
                performSearch(searchInput.value);
            }
        });
    }

    function performSearch(query) {
        if (query && query.trim().length > 0) {
            sessionStorage.setItem('careConnectSearch', query.trim());
            window.location.href = 'pages/doctors.php?search=' + encodeURIComponent(query.trim());
        } else {
            window.location.href = 'pages/doctors.php';
        }
    }
})();

// ============================================
// FORM VALIDATION
// ============================================
(function initFormValidation() {
    const forms = document.querySelectorAll('form[novalidate]');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
            let isValid = true;
            let firstInvalid = null;

            inputs.forEach(function(input) {
                input.classList.remove('error');
                const errorEl = input.parentElement.querySelector('.field-error');
                if (errorEl) errorEl.remove();

                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                    if (!firstInvalid) firstInvalid = input;
                    showFieldError(input, 'This field is required.');
                } else if (input.type === 'email' && !isValidEmail(input.value)) {
                    isValid = false;
                    input.classList.add('error');
                    if (!firstInvalid) firstInvalid = input;
                    showFieldError(input, 'Please enter a valid email address.');
                } else if (input.type === 'tel' && input.value && !isValidPhone(input.value)) {
                    isValid = false;
                    input.classList.add('error');
                    if (!firstInvalid) firstInvalid = input;
                    showFieldError(input, 'Please enter a valid phone number.');
                } else if (input.type === 'password' && input.value.length < 8) {
                    isValid = false;
                    input.classList.add('error');
                    if (!firstInvalid) firstInvalid = input;
                    showFieldError(input, 'Password must be at least 8 characters.');
                } else if (input.pattern && input.value && !new RegExp(input.pattern).test(input.value)) {
                    isValid = false;
                    input.classList.add('error');
                    if (!firstInvalid) firstInvalid = input;
                    showFieldError(input, 'Please enter a valid value.');
                }
            });

            if (!isValid) {
                event.preventDefault();
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        form.querySelectorAll('input, textarea, select').forEach(function(input) {
            input.addEventListener('input', function() {
                this.classList.remove('error');
                const errorEl = this.parentElement.querySelector('.field-error');
                if (errorEl) errorEl.remove();
            });
        });
    });

    function showFieldError(input, message) {
        const error = document.createElement('span');
        error.className = 'field-error';
        error.textContent = message;
        error.style.cssText = 'display:block;color:#EF4444;font-size:0.85rem;margin-top:4px;';
        input.parentElement.appendChild(error);
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidPhone(phone) {
        return /^[0-9+\-\s()]+$/.test(phone) && phone.replace(/[\s\-()]/g, '').length >= 8;
    }
})();

// ============================================
// STAT COUNTER ANIMATION
// ============================================
(function initStatCounters() {
    const stats = document.querySelectorAll('.stat-number[data-target]');
    if (!stats.length) return;

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    stats.forEach(function(stat) {
        observer.observe(stat);
    });

    function animateCounter(element) {
        const target = parseInt(element.getAttribute('data-target')) || 0;
        const duration = 2000;
        const startTime = performance.now();

        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(eased * target);
            
            element.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent = target.toLocaleString();
            }
        }

        requestAnimationFrame(updateCounter);
    }
})();

// ============================================
// SMOOTH SCROLL FOR ANCHOR LINKS
// ============================================
(function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                const headerOffset = 80;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
})();

// ============================================
// PASSWORD TOGGLE VISIBILITY
// ============================================
(function initPasswordToggle() {
    document.querySelectorAll('.password-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input[type="password"], input[type="text"]');
            if (!input) return;
            
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            this.textContent = isPassword ? '🙈' : '👁️';
            this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });
})();

// ============================================
// CURRENT YEAR IN FOOTER
// ============================================
(function updateFooterYear() {
    const yearElements = document.querySelectorAll('.footer-note, .copyright-year');
    const currentYear = new Date().getFullYear();
    yearElements.forEach(function(el) {
        el.textContent = el.textContent.replace('2026', currentYear);
    });
})();

// ============================================
// BACK TO TOP BUTTON
// ============================================
(function initBackToTop() {
    const btn = document.createElement('button');
    btn.className = 'back-to-top';
    btn.innerHTML = '↑';
    btn.setAttribute('aria-label', 'Back to top');
    btn.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: linear-gradient(135deg, #1EB53A, #0000CD);
        color: white;
        border: none;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        font-size: 1.5rem;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(30,181,58,0.3);
        z-index: 100;
    `;
    document.body.appendChild(btn);

    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        if (currentScroll > 300) {
            btn.style.opacity = '1';
            btn.style.visibility = 'visible';
        } else {
            btn.style.opacity = '0';
            btn.style.visibility = 'hidden';
        }
    });

    btn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
})();

// ============================================
// AI FLOATING BUTTON
// ============================================
(function initAIFloatingButton() {
    const isAIChatPage = window.location.pathname.includes('ai-chat.php');
    if (isAIChatPage) return;

    const btn = document.createElement('button');
    btn.className = 'ai-float-btn';
    btn.setAttribute('aria-label', 'Chat with AI Assistant');
    btn.innerHTML = `
        <span class="pulse"></span>
        💬
    `;
    document.body.appendChild(btn);

    btn.addEventListener('click', function() {
        window.location.href = 'ai-chat.php';
    });

    setTimeout(function() {
        btn.style.opacity = '1';
        btn.style.transform = 'scale(1)';
    }, 3000);
})();

// ============================================
// CONSOLE WELCOME MESSAGE
// ============================================
console.log('%c❤️ Care Connect SL', 'font-size:20px; font-weight:bold; color:#1EB53A;');
console.log('%cConnecting communities to quality healthcare across Sierra Leone.', 'font-size:14px; color:#64748B;');
console.log('%c📧 hello@careconnect.sl', 'font-size:12px; color:#94A3B8;');
console.log('%c🇸🇱 Made with ❤️ in Sierra Leone', 'font-size:12px; color:#94A3B8;');