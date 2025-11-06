document.addEventListener('DOMContentLoaded', () => {
    // Time Tracker
    const prog = document.querySelector('.circle-wrap .progress');
    if (prog) {
        const r = prog.r.baseVal.value;
        const circ = 2 * Math.PI * r;
        prog.style.strokeDasharray = circ;
        const hrs = 6.5;
        const pct = (hrs / 8) * 100;
        prog.style.strokeDashoffset = circ - (pct / 100) * circ;
        document.getElementById('time-display').textContent =
            Math.floor(hrs) + 'h ' + Math.floor((hrs % 1) * 60) + 'm';
    }

    // Sidebar Toggle
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (
                window.innerWidth <= 992 &&
                sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                !toggleBtn.contains(e.target)
            ) {
                sidebar.classList.remove('active');
            }
        });
    }

    // Logout Animation
    const states = {
        default: {
            '--figure-duration': '100',
            '--transform-figure': 'none',
            '--walking-duration': '100',
            '--transform-arm1': 'none',
            '--transform-wrist1': 'none',
            '--transform-arm2': 'none',
            '--transform-wrist2': 'none',
            '--transform-leg1': 'none',
            '--transform-calf1': 'none',
            '--transform-leg2': 'none',
            '--transform-calf2': 'none'
        },
        walking1: {
            '--figure-duration': '300',
            '--transform-figure': 'translateX(11px)',
            '--walking-duration': '300',
            '--transform-arm1':
                'translateX(-4px) translateY(-2px) rotate(120deg)',
            '--transform-wrist1': 'rotate(-5deg)',
            '--transform-arm2': 'translateX(4px) rotate(-110deg)',
            '--transform-wrist2': 'rotate(-5deg)',
            '--transform-leg1': 'translateX(-3px) rotate(80deg)',
            '--transform-calf1': 'rotate(-30deg)',
            '--transform-leg2': 'translateX(4px) rotate(-60deg)',
            '--transform-calf2': 'rotate(20deg)'
        },
        walking2: {
            '--figure-duration': '400',
            '--transform-figure': 'translateX(17px)',
            '--walking-duration': '300',
            '--transform-arm1': 'rotate(60deg)',
            '--transform-wrist1': 'rotate(-15deg)',
            '--transform-arm2': 'rotate(-45deg)',
            '--transform-wrist2': 'rotate(6deg)',
            '--transform-leg1': 'rotate(-5deg)',
            '--transform-calf1': 'rotate(10deg)',
            '--transform-leg2': 'rotate(10deg)',
            '--transform-calf2': 'rotate(-20deg)'
        },
        falling1: {
            '--figure-duration': '1600',
            '--walking-duration': '400',
            '--transform-arm1': 'rotate(-60deg)',
            '--transform-wrist1': 'none',
            '--transform-arm2': 'rotate(30deg)',
            '--transform-wrist2': 'rotate(120deg)',
            '--transform-leg1': 'rotate(-30deg)',
            '--transform-calf1': 'rotate(-20deg)',
            '--transform-leg2': 'rotate(20deg)'
        },
        falling2: {
            '--walking-duration': '300',
            '--transform-arm1': 'rotate(-100deg)',
            '--transform-arm2': 'rotate(-60deg)',
            '--transform-wrist2': 'rotate(60deg)',
            '--transform-leg1': 'rotate(80deg)',
            '--transform-calf1': 'rotate(20deg)',
            '--transform-leg2': 'rotate(-60deg)'
        },
        falling3: {
            '--walking-duration': '500',
            '--transform-arm1': 'rotate(-30deg)',
            '--transform-wrist1': 'rotate(40deg)',
            '--transform-arm2': 'rotate(50deg)',
            '--transform-wrist2': 'none',
            '--transform-leg1': 'rotate(-30deg)',
            '--transform-leg2': 'rotate(20deg)',
            '--transform-calf2': 'none'
        }
    };

    const btn = document.getElementById('logoutBtn');
    let animating = false;

    const setState = (state) => {
        if (btn) {
            Object.entries(states[state] || {}).forEach(([k, v]) =>
                btn.style.setProperty(k, v)
            );
        }
    };

    if (btn) {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if (animating) return;
            animating = true;
            btn.classList.remove('clicked', 'door-slammed', 'falling');

            setState('walking1');
            btn.classList.add('clicked');

            setTimeout(() => {
                btn.classList.add('door-slammed');
                setState('walking2');
                setTimeout(() => {
                    btn.classList.add('falling');
                    setState('falling1');
                    setTimeout(() => setState('falling2'), 400);
                    setTimeout(() => setState('falling3'), 700);
                    setTimeout(() => {
                        btn.classList.remove(
                            'clicked',
                            'door-slammed',
                            'falling'
                        );
                        setState('default');
                        animating = false;
                        // Redirect to logout.php after animation
                        window.location.href = 'logout.php';
                    }, 1700);
                }, 400);
            }, 300);
        });

        btn.addEventListener('mouseenter', () => !animating && setState('default'));
        btn.addEventListener('mouseleave', () => !animating && setState('default'));
        setState('default');
    }

    // Project Modal Handling
    const projectDetails = {
        'website-redesign': {
            title: 'Website Redesign',
            description:
                'Complete overhaul of the landing page with modern UI/UX principles and new branding elements.',
            status: '80% Completed',
            team: [
                'Alice Johnson (Designer)',
                'Bob Smith (Developer)',
                'Clara Lee (PM)'
            ],
            timeline: 'Start: 2025-08-01 | End: 2025-11-15',
            tasks: [
                'UI Design',
                'Frontend Development',
                'Content Integration',
                'Testing'
            ],
            notes: 'Focus on accessibility and mobile responsiveness.'
        },
        'seo-optimization': {
            title: 'SEO Optimization',
            description:
                'Comprehensive SEO strategy including meta tags, keyword optimization, and sitemap restructuring.',
            status: '100% Completed',
            team: ['David Brown (SEO Specialist)', 'Emma Wilson (Content Writer)'],
            timeline: 'Start: 2025-07-01 | End: 2025-09-30',
            tasks: ['Keyword Research', 'On-page SEO', 'Sitemap Update', 'Analytics Setup'],
            notes: 'Achieved 20% increase in organic traffic.'
        },
        'app-deployment': {
            title: 'App Deployment',
            description:
                'Deployment of mobile application on Play Store and App Store with CI/CD pipeline.',
            status: '45% Completed',
            team: ['Frank Davis (DevOps)', 'Grace Kim (Mobile Dev)', 'Henry Patel (QA)'],
            timeline: 'Start: 2025-09-01 | End: 2025-12-31',
            tasks: ['Build Pipeline Setup', 'Store Submission', 'Beta Testing', 'Release'],
            notes: 'Currently in beta testing phase.'
        },
        'marketing-campaign': {
            title: 'Marketing Campaign',
            description:
                'Cross-platform marketing campaign with social media ads and analytics integration.',
            status: '60% Completed',
            team: ['Isabella Martinez (Marketer)', 'James Lee (Analyst)'],
            timeline: 'Start: 2025-08-15 | End: 2025-11-30',
            tasks: [
                'Ad Creative Design',
                'Platform Setup',
                'Analytics Integration',
                'Campaign Launch'
            ],
            notes: 'Targeting 30% increase in user engagement.'
        }
    };

    const modal = document.getElementById('projectModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const closeButtons = document.querySelectorAll('.btn-secondary, .close-btn');

    if (modal && modalTitle && modalBody) {
        document.querySelectorAll('.project-card').forEach((card) => {
            const openModal = () => {
                const projectId = card.dataset.project;
                const project = projectDetails[projectId];

                if (project) {
                    modalTitle.textContent = project.title;
                    modalBody.innerHTML = `
                        <div class="modal-section"><h3>Description</h3><p>${project.description}</p></div>
                        <div class="modal-section"><h3>Status</h3><p>${project.status}</p></div>
                        <div class="modal-section"><h3>Team</h3><ul>${project.team
                            .map(
                                (member) =>
                                    `<li><i class="bi bi-person-fill"></i>${member}</li>`
                            )
                            .join('')}</ul></div>
                        <div class="modal-section"><h3>Timeline</h3><p>${project.timeline}</p></div>
                        <div class="modal-section"><h3>Tasks</h3><ul>${project.tasks
                            .map(
                                (task) =>
                                    `<li><i class="bi bi-check-circle-fill"></i>${task}</li>`
                            )
                            .join('')}</ul></div>
                        <div class="modal-section"><h3>Notes</h3><p>${project.notes}</p></div>
                    `;
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                    gsap.fromTo(
                        '.modal-content',
                        { scale: 0.8, opacity: 0 },
                        { scale: 1, opacity: 1, duration: 0.3, ease: 'power2.out' }
                    );
                    modalBody.focus();
                }
            };

            card.addEventListener('click', openModal);
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openModal();
                }
            });
        });

        closeButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                gsap.to('.modal-content', {
                    scale: 0.8,
                    opacity: 0,
                    duration: 0.3,
                    ease: 'power2.in',
                    onComplete: () => {
                        modal.style.display = 'none';
                        modal.setAttribute('aria-hidden', 'true');
                    }
                });
            });
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                gsap.to('.modal-content', {
                    scale: 0.8,
                    opacity: 0,
                    duration: 0.3,
                    ease: 'power2.in',
                    onComplete: () => {
                        modal.style.display = 'none';
                        modal.setAttribute('aria-hidden', 'true');
                    }
                });
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                gsap.to('.modal-content', {
                    scale: 0.8,
                    opacity: 0,
                    duration: 0.3,
                    ease: 'power2.in',
                    onComplete: () => {
                        modal.style.display = 'none';
                        modal.setAttribute('aria-hidden', 'true');
                    }
                });
            }
        });
    }
});
