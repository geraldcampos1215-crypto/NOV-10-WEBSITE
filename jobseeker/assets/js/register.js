
document.addEventListener('DOMContentLoaded', function () {

    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm');

    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    const matchMessage = document.getElementById('matchMessage');

    if (!password || !confirmPassword) return;

    // Show and hide password
    document.querySelectorAll('.password-toggle').forEach(function (button) {

        button.addEventListener('click', function () {

            const input = document.getElementById(
                this.dataset.target
            );

            const show = input.type === 'password';

            input.type = show ? 'text' : 'password';

            this.setAttribute(
                'aria-label',
                show ? 'Hide password' : 'Show password'
            );

            this.innerHTML = show
                ? `<svg viewBox="0 0 24 24" width="20" height="20"
                    fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3l18 18"/>
                    <path d="M10.6 10.6a2 2 0 002.8 2.8"/>
                    <path d="M9.9 5.2A10.5 10.5 0 0122 12"/>
                    <path d="M6.6 6.6A12 12 0 002 12s3.5 7 10 7"/>
                   </svg>`
                : `<svg viewBox="0 0 24 24" width="20" height="20"
                    fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                    <circle cx="12" cy="12" r="3"/>
                   </svg>`;
        });
    });

    // Password requirements
    const requirements = {
        reqLength: {
            test: value => value.length >= 8,
            label: 'At least 8 characters'
        },
        reqUpper: {
            test: value => /[A-Z]/.test(value),
            label: 'One uppercase letter'
        },
        reqLower: {
            test: value => /[a-z]/.test(value),
            label: 'One lowercase letter'
        },
        reqNumber: {
            test: value => /[0-9]/.test(value),
            label: 'One number'
        },
        reqSpecial: {
            test: value => /[^A-Za-z0-9]/.test(value),
            label: 'One special character'
        }
    };

    function updateStrength() {

        const value = password.value;
        let score = 0;

        Object.keys(requirements).forEach(function (id) {

            const requirement = requirements[id];
            const element = document.getElementById(id);

            if (!element) return;

            const passed = requirement.test(value);

            if (passed) score++;

            element.classList.toggle('valid', passed);

            element.classList.toggle(
                'invalid',
                value.length > 0 && !passed
            );

            element.textContent =
                (passed ? '✓ ' : value.length ? '✗ ' : '○ ') +
                requirement.label;
        });

        const levels = [
            { text: 'Weak', color: '#dc3545', width: '20%' },
            { text: 'Weak', color: '#dc3545', width: '40%' },
            { text: 'Medium', color: '#ffc107', width: '60%' },
            { text: 'Medium', color: '#ffc107', width: '80%' },
            { text: 'Strong', color: '#198754', width: '100%' }
        ];

        if (!value) {

            strengthFill.style.width = '0%';
            strengthText.textContent = 'Not entered';
            strengthText.style.color = '#6c757d';

        } else {

            const level = levels[score - 1] || {
                text: 'Weak',
                color: '#dc3545',
                width: '0%'
            };

            strengthFill.style.width = level.width;
            strengthFill.style.backgroundColor = level.color;
            strengthText.textContent = level.text;
            strengthText.style.color = level.color;
        }

        updateMatch();
    }

    // Confirm password matching
    function updateMatch() {

        if (!confirmPassword.value) {
            matchMessage.textContent = '';
            return;
        }

        if (password.value === confirmPassword.value) {

            matchMessage.textContent = '✓ Passwords match';
            matchMessage.style.color = '#198754';

        } else {

            matchMessage.textContent = '✗ Passwords do not match';
            matchMessage.style.color = '#dc3545';
        }
    }

    password.addEventListener('input', updateStrength);

    confirmPassword.addEventListener('input', updateMatch);

    updateStrength();

});
