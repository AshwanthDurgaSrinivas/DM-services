document.addEventListener('DOMContentLoaded', () => {
    // Session Management
    const isLoggedIn = sessionStorage.getItem('isLoggedIn');
    const loginPage = document.getElementById('loginPage');
    const loginForm = document.getElementById('loginForm');
    const submitButton = loginForm ? loginForm.querySelector('.submit-login') : null;
    const buttonText = submitButton ? submitButton.querySelector('.button-text') : null;
    const loadingIcon = submitButton ? submitButton.querySelector('.loading-icon') : null;

    // Check if on login page
    if (loginPage && loginForm && submitButton && buttonText && loadingIcon) {
        // Show login page only
        loginPage.style.display = 'flex';

        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const rememberMe = document.getElementById('rememberMe').checked;

            if (username && password) {
                submitButton.classList.add('loading');
                submitButton.disabled = true;
                buttonText.style.display = 'none';
                loadingIcon.style.display = 'inline-block';

                // Simulate login with spinning animation
                setTimeout(() => {
                    loginPage.style.animation = 'fadeOut 0.5s ease-in-out forwards';
                    setTimeout(() => {
                        sessionStorage.setItem('isLoggedIn', 'true');
                       // window.location.href = 'dashboard.html';
                        submitButton.classList.remove('loading');
                        submitButton.disabled = false;
                        buttonText.style.display = 'inline-block';
                        loadingIcon.style.display = 'none';
                        if (rememberMe) {
                            localStorage.setItem('rememberMe', 'true');
                            localStorage.setItem('username', username);
                        } else {
                            localStorage.removeItem('rememberMe');
                            localStorage.removeItem('username');
                        }
                    }, 500);
                }, 1000);
            } else {
                alert('Please fill in both fields.');
            }
        });

        // Pre-fill username if "Remember Me" was checked
        if (localStorage.getItem('rememberMe') === 'true') {
            const savedUsername = localStorage.getItem('username');
            if (savedUsername) {
                document.getElementById('username').value = savedUsername;
                document.getElementById('rememberMe').checked = true;
            }
        }
    } else if (!isLoggedIn) {
        // Redirect to login if not logged in
      //  window.location.href = 'index.html';
    } else {
        // Ensure dashboard is visible
        const sidebar = document.querySelector('.sidebar');
        const main = document.querySelector('.main');
        if (sidebar && main) {
            sidebar.style.display = 'block';
            main.style.display = 'block';
        }
    }

    // Sidebar Toggle
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }

   // === LOGOUT ANIMATION + REDIRECT TO logout.php ===
    const states = {
        default: { '--figure-duration': '100', '--transform-figure': 'none', '--walking-duration': '100', '--transform-arm1': 'none', '--transform-wrist1': 'none', '--transform-arm2': 'none', '--transform-wrist2': 'none', '--transform-leg1': 'none', '--transform-calf1': 'none', '--transform-leg2': 'none', '--transform-calf2': 'none' },
        walking1: { '--figure-duration': '300', '--transform-figure': 'translateX(11px)', '--walking-duration': '300', '--transform-arm1': 'translateX(-4px) translateY(-2px) rotate(120deg)', '--transform-wrist1': 'rotate(-5deg)', '--transform-arm2': 'translateX(4px) rotate(-110deg)', '--transform-wrist2': 'rotate(-5deg)', '--transform-leg1': 'translateX(-3px) rotate(80deg)', '--transform-calf1': 'rotate(-30deg)', '--transform-leg2': 'translateX(4px) rotate(-60deg)', '--transform-calf2': 'rotate(20deg)' },
        walking2: { '--figure-duration': '400', '--transform-figure': 'translateX(17px)', '--walking-duration': '300', '--transform-arm1': 'rotate(60deg)', '--transform-wrist1': 'rotate(-15deg)', '--transform-arm2': 'rotate(-45deg)', '--transform-wrist2': 'rotate(6deg)', '--transform-leg1': 'rotate(-5deg)', '--transform-calf1': 'rotate(10deg)', '--transform-leg2': 'rotate(10deg)', '--transform-calf2': 'rotate(-20deg)' },
        falling1: { '--figure-duration': '1600', '--walking-duration': '400', '--transform-arm1': 'rotate(-60deg)', '--transform-wrist1': 'none', '--transform-arm2': 'rotate(30deg)', '--transform-wrist2': 'rotate(120deg)', '--transform-leg1': 'rotate(-30deg)', '--transform-calf1': 'rotate(-20deg)', '--transform-leg2': 'rotate(20deg)' },
        falling2: { '--walking-duration': '300', '--transform-arm1': 'rotate(-100deg)', '--transform-arm2': 'rotate(-60deg)', '--transform-wrist2': 'rotate(60deg)', '--transform-leg1': 'rotate(80deg)', '--transform-calf1': 'rotate(20deg)', '--transform-leg2': 'rotate(-60deg)' },
        falling3: { '--walking-duration': '500', '--transform-arm1': 'rotate(-30deg)', '--transform-wrist1': 'rotate(40deg)', '--transform-arm2': 'rotate(50deg)', '--transform-wrist2': 'none', '--transform-leg1': 'rotate(-30deg)', '--transform-leg2': 'rotate(20deg)', '--transform-calf2': 'none' }
    };

    const btn = document.getElementById('logoutBtn');
    let animating = false;

    const setState = (state) => {
        if (btn) {
            Object.entries(states[state] || {}).forEach(([k, v]) => btn.style.setProperty(k, v));
        }
    };

    if (btn) {
        btn.addEventListener('click', (e) => {
            e.preventDefault(); // Prevent any default action
            if (animating) return;

            animating = true;
            btn.classList.remove('clicked', 'door-slammed', 'falling');

            // === STEP 1: WALKING ===
            setState('walking1');
            btn.classList.add('clicked');

            setTimeout(() => {
                // === STEP 2: DOOR SLAM ===
                btn.classList.add('door-slammed');
                setState('walking2');

                setTimeout(() => {
                    // === STEP 3: FALLING ===
                    btn.classList.add('falling');
                    setState('falling1');
                    setTimeout(() => setState('falling2'), 400);
                    setTimeout(() => setState('falling3'), 700);

                    // === FINAL: AFTER ANIMATION → LOGOUT ===
                    setTimeout(() => {
                        // Clean up
                        btn.classList.remove('clicked', 'door-slammed', 'falling');
                        setState('default');
                        animating = false;

                        // Optional: Clear any local flags
                        sessionStorage.removeItem('isLoggedIn');

                        // REDIRECT TO logout.php
                        window.location.href = 'logout.php';
                    }, 1700); // Matches your animation duration

                }, 400);
            }, 300);
        });

        // Optional: Reset on hover
        btn.addEventListener('mouseenter', () => !animating && setState('default'));
        btn.addEventListener('mouseleave', () => !animating && setState('default'));

        // Initial state
        setState('default');
    }
});




  document.querySelectorAll('select').forEach(select => {
                    select.addEventListener('change', () => {
                        const card = select.closest('.task-card');
                        card.classList.remove('inprogress', 'completed', 'pending', 'problem');

                        const value = select.value;
                        if (value === 'In Progress') card.classList.add('inprogress');
                        else if (value === 'Completed') card.classList.add('completed');
                        else if (value === 'Pending') card.classList.add('pending');
                        else card.classList.add('problem');
                    });
                });