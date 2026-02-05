// --- Multi-step navigation ---
const step1 = document.getElementById('step1');
const step2 = document.getElementById('step2');
const nextBtn = document.getElementById('nextBtn');
const prevBtn = document.getElementById('prevBtn');
const stepTabs = document.querySelectorAll('#stepTabs .nav-link');

nextBtn.addEventListener('click', () => {
    step1.classList.add('d-none');
    step2.classList.remove('d-none');
    stepTabs[0].classList.remove('active');
    stepTabs[1].classList.remove('disabled');
    stepTabs[1].classList.add('active');
});

prevBtn.addEventListener('click', () => {
    step2.classList.add('d-none');
    step1.classList.remove('d-none');
    stepTabs[1].classList.remove('active');
    stepTabs[1].classList.add('disabled');
    stepTabs[0].classList.add('active');
});

// --- Form submission ---
const clinicForm = document.getElementById('clinicForm');

clinicForm.addEventListener('submit', e => {
    e.preventDefault();

    const formData = new FormData(clinicForm);

    fetch('/THESIS/LYINGIN/auth/api/register-clinic.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.status === 'success') {
            clinicForm.reset();
            // Return to Step 1
            step2.classList.add('d-none');
            step1.classList.remove('d-none');
            stepTabs[1].classList.add('disabled');
            stepTabs[1].classList.remove('active');
            stepTabs[0].classList.add('active');
        }
    })
    .catch(err => {
        console.error('Registration Error:', err);
        alert('An error occurred during registration.');
    });
});
