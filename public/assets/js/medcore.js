/**
 * MedCore Global JavaScript
 */

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', function(e) {
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      e.preventDefault();
      const offset = 80;
      const top = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  });
});

// Navbar scroll effect
const navbar = document.getElementById('main-navbar');
if (navbar) {
  window.addEventListener('scroll', () => {
    if (window.scrollY > 20) {
      navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
    } else {
      navbar.style.boxShadow = 'none';
    }
  }, { passive: true });
}

// OTP input auto-advance
document.querySelectorAll('.otp-inputs').forEach(container => {
  const inputs = container.querySelectorAll('.otp-input');

  inputs.forEach((input, index) => {
    input.addEventListener('input', (e) => {
      const val = e.target.value;
      // Only allow digits
      e.target.value = val.replace(/\D/g, '').slice(-1);

      if (e.target.value && index < inputs.length - 1) {
        inputs[index + 1].focus();
      }

      // Auto-submit if all filled
      const allFilled = [...inputs].every(i => i.value);
      if (allFilled) {
        const form = container.closest('form');
        // Combine OTP digits into hidden field
        const hiddenOtp = container.parentElement.querySelector('[name="otp"]');
        if (hiddenOtp) {
          hiddenOtp.value = [...inputs].map(i => i.value).join('');
        }
        // Optional: auto-submit
        // form?.submit();
      }
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !e.target.value && index > 0) {
        inputs[index - 1].focus();
      }
      if (e.key === 'ArrowLeft' && index > 0) inputs[index - 1].focus();
      if (e.key === 'ArrowRight' && index < inputs.length - 1) inputs[index + 1].focus();
    });

    // Handle paste
    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, inputs.length);
      [...pasted].forEach((char, i) => {
        if (inputs[i]) inputs[i].value = char;
      });
      const lastFilled = Math.min(pasted.length, inputs.length) - 1;
      if (inputs[lastFilled]) inputs[lastFilled].focus();
    });
  });
});

// Auto-dismiss alerts
document.querySelectorAll('[data-auto-dismiss]').forEach(el => {
  const delay = parseInt(el.dataset.autoDismiss) || 5000;
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(-8px)';
    setTimeout(() => el.remove(), 300);
  }, delay);
});

// Form validation enhancement
document.querySelectorAll('form.needs-validation').forEach(form => {
  form.addEventListener('submit', e => {
    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    form.classList.add('was-validated');
  });
});

// Confirm dangerous actions
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', e => {
    const msg = btn.dataset.confirm || 'Are you sure?';
    if (!confirm(msg)) {
      e.preventDefault();
    }
  });
});

// Access session timer (used in doctor portal)
function startAccessTimer(elementId, secondsRemaining, onExpire) {
  const el = document.getElementById(elementId);
  if (!el) return;

  let secs = secondsRemaining;
  const update = () => {
    const m = Math.floor(secs / 60).toString().padStart(2, '0');
    const s = (secs % 60).toString().padStart(2, '0');
    el.textContent = m + ':' + s;

    if (secs <= 300) { // < 5 min = critical
      el.closest('.access-timer')?.classList.add('critical');
    }

    if (secs <= 0) {
      clearInterval(interval);
      if (typeof onExpire === 'function') onExpire();
    }
    secs--;
  };

  update();
  const interval = setInterval(update, 1000);
  return interval;
}

// Copy to clipboard
document.querySelectorAll('[data-copy]').forEach(btn => {
  btn.addEventListener('click', () => {
    const text = document.querySelector(btn.dataset.copy)?.textContent || btn.dataset.copy;
    navigator.clipboard.writeText(text).then(() => {
      const original = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
      setTimeout(() => btn.innerHTML = original, 2000);
    });
  });
});

// Reveal on scroll (animation)
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('revealed');
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
