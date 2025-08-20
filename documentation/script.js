/**
 * Users Menu Manager Pro Documentation JavaScript
 * Handles interactive functionality and animations
 */

(function() {
    'use strict';

    // DOM Content Loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeDocumentation();
    });

    /**
     * Initialize all documentation functionality
     */
    function initializeDocumentation() {
        initializeSmoothScrolling();
        initializeScrollAnimations();
        initializeMobileNavigation();
        initializeAccordionEnhancements();
        initializeContactForm();
        initializeBackToTop();
    }

    /**
     * Initialize smooth scrolling for anchor links
     */
    function initializeSmoothScrolling() {
        const links = document.querySelectorAll('a[href^="#"]');
        
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    const offsetTop = targetElement.offsetTop - 80; // Account for fixed navbar
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                    
                    // Update active navigation link
                    updateActiveNavigation(targetId);
                }
            });
        });
    }

    /**
     * Update active navigation link based on scroll position
     */
    function updateActiveNavigation(currentSectionId) {
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === currentSectionId) {
                link.classList.add('active');
            }
        });
    }

    /**
     * Initialize scroll animations for elements
     */
    function initializeScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                }
            });
        }, observerOptions);

        // Observe elements for animation
        const animateElements = document.querySelectorAll('.feature-card, .step-card, .contact-card, .usage-image');
        animateElements.forEach(el => {
            el.classList.add('scroll-animate');
            observer.observe(el);
        });
    }

    /**
     * Initialize mobile navigation improvements
     */
    function initializeMobileNavigation() {
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            // Close mobile menu when clicking on a link
            const mobileNavLinks = navbarCollapse.querySelectorAll('.nav-link');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        navbarCollapse.classList.remove('show');
                    }
                });
            });

            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!navbarCollapse.contains(e.target) && !navbarToggler.contains(e.target)) {
                    navbarCollapse.classList.remove('show');
                }
            });
        }
    }

    /**
     * Initialize accordion enhancements
     */
    function initializeAccordionEnhancements() {
        const accordionButtons = document.querySelectorAll('.accordion-button');
        
        accordionButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Add smooth transition
                const accordionItem = this.closest('.accordion-item');
                const accordionBody = accordionItem.querySelector('.accordion-collapse');
                
                if (accordionBody) {
                    accordionBody.style.transition = 'all 0.3s ease';
                }
            });
        });
    }

    /**
     * Initialize contact form functionality
     */
    function initializeContactForm() {
        const contactButtons = document.querySelectorAll('a[href^="mailto:"]');
        
        contactButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Add click animation
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
    }

    /**
     * Initialize back to top functionality
     */
    function initializeBackToTop() {
        // Create back to top button
        const backToTopButton = document.createElement('button');
        backToTopButton.innerHTML = '<i class="bi bi-arrow-up"></i>';
        backToTopButton.className = 'btn btn-primary back-to-top';
        backToTopButton.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        `;
        
        document.body.appendChild(backToTopButton);

        // Show/hide back to top button based on scroll position
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'flex';
            } else {
                backToTopButton.style.display = 'none';
            }
        });

        // Scroll to top when clicked
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    /**
     * Add scroll-based navigation highlighting
     */
    function initializeScrollBasedNavigation() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link[href^="#"]');
        
        window.addEventListener('scroll', function() {
            let current = '';
            const scrollPosition = window.pageYOffset + 100;
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                
                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });
    }

    /**
     * Add loading animations for images - FIXED VERSION
     */
    function initializeImageLoading() {
        const images = document.querySelectorAll('img');
        
        images.forEach(img => {
            // Only apply animation if image is not already loaded
            if (!img.complete) {
                img.addEventListener('load', function() {
                    // Add a subtle entrance animation
                    this.style.transition = 'all 0.3s ease';
                    this.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 300);
                });
            }
        });
    }

    /**
     * Add keyboard navigation support
     */
    function initializeKeyboardNavigation() {
        document.addEventListener('keydown', function(e) {
            // Escape key to close mobile menu
            if (e.key === 'Escape') {
                const navbarCollapse = document.querySelector('.navbar-collapse.show');
                if (navbarCollapse) {
                    navbarCollapse.classList.remove('show');
                }
            }
            
            // Tab key navigation enhancement
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });
        
        // Remove keyboard navigation class on mouse use
        document.addEventListener('mousedown', function() {
            document.body.classList.remove('keyboard-navigation');
        });
    }

    /**
     * Add performance optimizations
     */
    function initializePerformanceOptimizations() {
        // Lazy load images if Intersection Observer is supported
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });
            
            const lazyImages = document.querySelectorAll('img[data-src]');
            lazyImages.forEach(img => imageObserver.observe(img));
        }
        
        // Debounce scroll events
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                // Perform scroll-based operations here
            }, 100);
        });
    }

    /**
     * Add accessibility enhancements
     */
    function initializeAccessibilityEnhancements() {
        // Add skip to content link
        const skipLink = document.createElement('a');
        skipLink.href = '#overview';
        skipLink.textContent = 'Skip to main content';
        skipLink.className = 'skip-link';
        skipLink.style.cssText = `
            position: absolute;
            top: -40px;
            left: 6px;
            background: #000;
            color: white;
            padding: 8px;
            text-decoration: none;
            z-index: 10000;
            transition: top 0.3s;
        `;
        
        skipLink.addEventListener('focus', function() {
            this.style.top = '6px';
        });
        
        skipLink.addEventListener('blur', function() {
            this.style.top = '-40px';
        });
        
        document.body.insertBefore(skipLink, document.body.firstChild);
        
        // Add ARIA labels and roles where needed
        const accordionButtons = document.querySelectorAll('.accordion-button');
        accordionButtons.forEach((button, index) => {
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-controls', `collapse-${index}`);
        });
    }

    /**
     * Initialize all additional features
     */
    function initializeAdditionalFeatures() {
        initializeScrollBasedNavigation();
        initializeImageLoading();
        initializeKeyboardNavigation();
        initializePerformanceOptimizations();
        initializeAccessibilityEnhancements();
    }

    // Initialize additional features after a short delay
    setTimeout(initializeAdditionalFeatures, 100);

    /**
     * Add CSS for active navigation state
     */
    function addActiveNavigationStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .navbar-nav .nav-link.active {
                color: #fff !important;
                font-weight: 600;
            }
            
            .navbar-nav .nav-link.active::after {
                width: 100% !important;
            }
            
            .keyboard-navigation *:focus {
                outline: 2px solid #007bff !important;
                outline-offset: 2px !important;
            }
            
            .skip-link:focus {
                top: 6px !important;
            }
        `;
        document.head.appendChild(style);
    }

    // Add active navigation styles
    addActiveNavigationStyles();

})();
