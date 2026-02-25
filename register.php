<?php
/**
 * register.php
 * Vue : formulaire d'inscription.
 * La logique de traitement est dans register-process.php (appelé en fetch/POST).
 */

session_start();
require_once 'auth-layout.php';

if(isset($_SESSION['logged_in'])) {
    header('Location: dashboard.php');
    exit();
}

renderAuthHeader('Créer un compte');
?>

<div id="message"></div>

<form id="registerForm" novalidate>

    <div class="form-group">
        <label for="nom_complet">Nom complet *</label>
        <input type="text" id="nom_complet" name="nom_complet"
               required placeholder="Jean DOSSOU" autofocus>
    </div>

    <div class="form-group">
        <label for="email">Adresse email *</label>
        <input type="email" id="email" name="email"
               required placeholder="jean@email.com">
    </div>

    <div class="form-group">
        <label for="telephone">Téléphone *</label>
        <input type="tel" id="telephone" name="telephone"
               required placeholder="0194601012">
    </div>

    <div class="form-group">
        <label for="nom_entreprise">Nom de l'entreprise <span style="color:var(--text-muted);font-weight:400">(optionnel)</span></label>
        <input type="text" id="nom_entreprise" name="nom_entreprise"
               placeholder="Restaurant La Palmeraie">
    </div>

    <div class="form-group">
        <label for="mot_de_passe">Mot de passe *</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe"
               required placeholder="••••••••" minlength="6">
    </div>

    <div class="form-group">
        <label for="mot_de_passe_confirm">Confirmer le mot de passe *</label>
        <input type="password" id="mot_de_passe_confirm" name="mot_de_passe_confirm"
               required placeholder="••••••••" minlength="6">
    </div>

    <!-- Info box -->
    <div class="auth-info-box">
        💡 <strong>Comment ça marche ?</strong>
        <ul>
            <li>1 crédit = <strong>2 000 FCFA</strong> = 1 site pour 1 mois</li>
            <li>Connexions <strong>illimitées</strong> par site</li>
            <li>Packages avec bonus disponibles (ex : 6 crédits pour 10 000 FCFA)</li>
        </ul>
    </div>

    <button type="submit" class="btn-auth" id="submitBtn">
        Créer mon compte
    </button>

</form>

<div class="auth-footer-link">
    Déjà un compte ? <a href="login.php">Se connecter</a>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const messageDiv = document.getElementById('message');
    const submitBtn  = document.getElementById('submitBtn');
    const formData   = new FormData(e.target);

    // Validation côté client : mots de passe identiques
    if (formData.get('mot_de_passe') !== formData.get('mot_de_passe_confirm')) {
        messageDiv.innerHTML = '<div class="auth-alert error">❌ Les mots de passe ne correspondent pas.</div>';
        return;
    }

    // On n'envoie pas la confirmation au serveur
    formData.delete('mot_de_passe_confirm');

    // Loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="btn-spinner"></span>Inscription en cours…';
    messageDiv.innerHTML = '';

    try {
        const response = await fetch('register-process.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            messageDiv.innerHTML = '<div class="auth-alert success">✅ ' + result.message + '</div>';
            setTimeout(() => { window.location.href = 'login.php'; }, 1800);
        } else {
            messageDiv.innerHTML = '<div class="auth-alert error">❌ ' + result.message + '</div>';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Créer mon compte';
        }
    } catch (err) {
        messageDiv.innerHTML = '<div class="auth-alert error">❌ Erreur réseau. Veuillez réessayer.</div>';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Créer mon compte';
    }
});
</script>

<?php renderAuthFooter(); ?>