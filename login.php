<?php
/**
 * login.php
 * Vue : formulaire de connexion.
 * La logique de traitement est dans login-process.php (appelé en fetch/POST).
 */
session_start();
require_once 'auth-layout.php';

if(isset($_SESSION['logged_in'])) {
    header('Location: dashboard.php');
    exit();
}

renderAuthHeader('Connexion à votre espace');
?>

<div id="message"></div>

<form id="loginForm" novalidate>

    <div class="form-group">
        <label for="email">Adresse email</label>
        <input type="email" id="email" name="email"
               required placeholder="jean@email.com" autofocus>
    </div>

    <div class="form-group">
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe"
               required placeholder="••••••••">
        <a class="forgot-link"
           href="forgot-password.php">
            Mot de passe oublié ?
        </a>
    </div>

    <button type="submit" class="btn-auth" id="submitBtn">
        Se connecter
    </button>

</form>

<div class="auth-footer-link">
    Pas encore de compte ? <a href="register.php">S'inscrire</a>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const messageDiv = document.getElementById('message');
    const submitBtn  = document.getElementById('submitBtn');

    // Loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="btn-spinner"></span>Connexion en cours…';
    messageDiv.innerHTML = '';

    try {
        const response = await fetch('login-process.php', {
            method: 'POST',
            body: new FormData(e.target)
        });

        const result = await response.json();

        if (result.success) {
            messageDiv.innerHTML = '<div class="auth-alert success">✅ ' + result.message + '</div>';
            setTimeout(() => { window.location.href = 'dashboard.php'; }, 900);
        } else {
            messageDiv.innerHTML = '<div class="auth-alert error">❌ ' + result.message + '</div>';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Se connecter';
        }
    } catch (err) {
        messageDiv.innerHTML = '<div class="auth-alert error">❌ Erreur réseau. Veuillez réessayer.</div>';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Se connecter';
    }
});
</script>

<?php renderAuthFooter(); ?>