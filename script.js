document.addEventListener("DOMContentLoaded", function () {
  // Auto-hide success messages after 5 seconds
  setTimeout(function () {
    var messages = document.querySelectorAll(
      ".message-success, .success-message"
    );
    messages.forEach(function (msg) {
      msg.style.transition = "opacity 0.5s";
      msg.style.opacity = "0";
      setTimeout(function () {
        msg.remove();
      }, 500);
    });
  }, 5000);
});

// Show register form
function showRegisterForm() {
  var login = document.getElementById("login-form");
  var register = document.getElementById("register-form");
  if (login && register) {
    login.classList.remove("active");
    register.classList.add("active");
  }
}

// Show login form
function showLoginForm() {
  var login = document.getElementById("login-form");
  var register = document.getElementById("register-form");
  if (login && register) {
    register.classList.remove("active");
    login.classList.add("active");
  }
}

// Show specific form (login or register)
function showForm(id) {
  var login = document.getElementById("login-form");
  var register = document.getElementById("register-form");

  if (!login || !register) return;

  if (id === "register-form") {
    register.classList.add("active");
    login.classList.remove("active");
  } else {
    login.classList.add("active");
    register.classList.remove("active");
  }
}

// Toggle password visibility
function togglePassword(inputId) {
  var input = document.getElementById(inputId);
  if (!input) return;

  if (input.type === "password") {
    input.type = "text";
  } else {
    input.type = "password";
  }
}

// Confirm dialog for delete actions
function confirmDelete(message) {
  return confirm(message || "Are you sure you want to delete this?");
}

// Image preview before upload
function previewImage(input, previewId) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();

    reader.onload = function (e) {
      var preview = document.getElementById(previewId);
      if (preview) {
        preview.src = e.target.result;
        preview.style.display = "block";
      }
    };

    reader.readAsDataURL(input.files[0]);
  }
}

// Show loading spinner
function showLoading(buttonElement) {
  if (buttonElement) {
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<span class="spinner"></span> Loading...';
  }
}

// Format date nicely
function formatDate(dateString) {
  var date = new Date(dateString);
  var options = { year: "numeric", month: "long", day: "numeric" };
  return date.toLocaleDateString("en-US", options);
}

// Debounce function for search
function debounce(func, wait) {
  var timeout;
  return function executedFunction() {
    var context = this;
    var args = arguments;
    var later = function () {
      timeout = null;
      func.apply(context, args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}
