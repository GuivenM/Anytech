<?php
/**
 * profile.php
 * Page de paramètres du compte — profil, mot de passe, infos
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

// Fetch full profile from DB (session might be stale)
$stmt = $pdo->prepare("
    SELECT nom_complet, email, telephone, nom_entreprise, statut,
           solde_credits, date_inscription, derniere_connexion
    FROM proprietaires
    WHERE id = :id
");
$stmt->execute([':id' => $proprietaire_id]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: logout.php');
    exit();
}

// Stats for the sidebar summary
$stmt_stats = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM routeurs WHERE proprietaire_id = :pid1) as total_sites,
        (SELECT COUNT(*) FROM routeurs WHERE proprietaire_id = :pid2 AND actif = 1 AND date_expiration >= CURDATE()) as sites_actifs,
        (SELECT COUNT(*) FROM transactions_credits WHERE proprietaire_id = :pid3 AND statut_paiement = 'approuve') as total_transactions,
        (SELECT COALESCE(SUM(montant_fcfa),0) FROM transactions_credits WHERE proprietaire_id = :pid4 AND statut_paiement = 'approuve') as total_depense
");
$stmt_stats->execute([
    ':pid1' => $proprietaire_id,
    ':pid2' => $proprietaire_id,
    ':pid3' => $proprietaire_id,
    ':pid4' => $proprietaire_id,
]);
$account_stats = $stmt_stats->fetch();

renderHeader('profile', 'Mon Profil');
?>

<style>
    .profile-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 24px;
        align-items: start;
    }

    /* ---- SIDEBAR ---- */
    .profile-sidebar { display: flex; flex-direction: column; gap: 16px; }

    .profile-avatar-card {
        background: linear-gradient(135deg, var(--brand-from) 0%, var(--brand-to) 100%);
        border-radius: var(--radius-lg);
        padding: 32px 24px;
        text-align: center;
        color: white;
        box-shadow: var(--shadow-brand);
        position: relative;
        overflow: hidden;
    }

    .profile-avatar-card::before {
        content: '';
        position: absolute;
        top: -40px;
        right: -40px;
        width: 140px;
        height: 140px;
        background: rgba(255,255,255,0.08);
        border-radius: 50%;
    }

    .profile-avatar-card::after {
        content: '';
        position: absolute;
        bottom: -30px;
        left: -30px;
        width: 100px;
        height: 100px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
    }

    .profile-avatar-big {
        width: 80px;
        height: 80px;
        background: rgba(255,255,255,0.25);
        border: 3px solid rgba(255,255,255,0.4);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        font-weight: 700;
        margin: 0 auto 16px;
        position: relative;
        z-index: 1;
    }

    .profile-name {
        font-size: 17px;
        font-weight: 700;
        margin-bottom: 4px;
        position: relative;
        z-index: 1;
    }

    .profile-email {
        font-size: 12px;
        opacity: 0.75;
        position: relative;
        z-index: 1;
    }

    .profile-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(16,185,129,0.25);
        border: 1px solid rgba(16,185,129,0.4);
        color: #6ee7b7;
        padding: 4px 12px;
        border-radius: var(--radius-full);
        font-size: 11px;
        font-weight: 600;
        margin-top: 12px;
        position: relative;
        z-index: 1;
    }

    .account-stats-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .account-stat-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 18px;
        border-bottom: 1px solid var(--border);
    }

    .account-stat-row:last-child { border-bottom: none; }

    .stat-row-label {
        font-size: 13px;
        color: var(--text-secondary);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .stat-row-value {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
        font-family: 'DM Mono', monospace;
    }

    .member-since {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 16px 18px;
        box-shadow: var(--shadow-sm);
    }

    .member-since-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 6px;
    }

    .member-since-date {
        font-size: 15px;
        font-weight: 650;
        color: var(--text-primary);
    }

    .member-since-sub {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 2px;
    }

    /* ---- MAIN COLUMN ---- */
    .profile-main { display: flex; flex-direction: column; gap: 20px; }

    /* Tabs */
    .profile-tabs {
        display: flex;
        gap: 4px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 6px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 4px;
    }

    .tab-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 10px 16px;
        border-radius: var(--radius-sm);
        border: none;
        background: transparent;
        color: var(--text-secondary);
        font-size: 13.5px;
        font-weight: 600;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: var(--transition);
    }

    .tab-btn.active {
        background: linear-gradient(135deg, var(--brand-from), var(--brand-to));
        color: white;
        box-shadow: 0 2px 8px rgba(91,94,244,0.3);
    }

    .tab-btn:not(.active):hover {
        background: var(--surface-2);
        color: var(--text-primary);
    }

    /* Tab panels */
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* Form section titles */
    .section-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--text-muted);
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border);
    }

    /* Password strength bar */
    .strength-bar-wrap {
        height: 4px;
        background: var(--surface-3);
        border-radius: 4px;
        margin-top: 8px;
        overflow: hidden;
    }

    .strength-bar {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s, background 0.3s;
        width: 0%;
    }

    .strength-text {
        font-size: 11.5px;
        font-weight: 600;
        margin-top: 5px;
        color: var(--text-muted);
    }

    /* Password show/hide toggle */
    .input-with-toggle {
        position: relative;
    }

    .input-with-toggle .form-control {
        padding-right: 44px;
    }

    .toggle-pw {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-muted);
        font-size: 16px;
        transition: color 0.2s;
        line-height: 1;
        padding: 2px;
    }

    .toggle-pw:hover { color: var(--brand-from); }

    /* Submit row */
    .form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 8px;
        border-top: 1px solid var(--border);
        margin-top: 8px;
    }

    /* Info row (read-only) */
    .info-row {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border);
        gap: 12px;
    }

    .info-row:last-child { border-bottom: none; }

    .info-row-icon {
        width: 36px;
        height: 36px;
        background: var(--brand-light);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .info-row-label {
        font-size: 11.5px;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-row-value {
        font-size: 14.5px;
        font-weight: 600;
        color: var(--text-primary);
        margin-top: 1px;
    }

    @media (max-width: 900px) {
        .profile-layout { grid-template-columns: 1fr; }
        .profile-tabs { flex-wrap: wrap; }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">⚙️ Paramètres du compte</h1>
        <p class="page-subtitle">Gérez vos informations personnelles et la sécurité de votre compte</p>
    </div>
</div>

<div id="global-alert"></div>

<div class="profile-layout">

    <!-- SIDEBAR -->
    <aside class="profile-sidebar">
        <!-- Avatar card -->
        <div class="profile-avatar-card">
            <div class="profile-avatar-big" id="sidebar-initials">
                <?php
                $parts = explode(' ', $profile['nom_complet']);
                $initiales = strtoupper(substr($parts[0], 0, 1));
                if (count($parts) > 1) $initiales .= strtoupper(substr(end($parts), 0, 1));
                echo htmlspecialchars($initiales);
                ?>
            </div>
            <div class="profile-name" id="sidebar-name"><?php echo htmlspecialchars($profile['nom_complet']); ?></div>
            <div class="profile-email"><?php echo htmlspecialchars($profile['email']); ?></div>
            <div class="profile-status-badge">
                ● <?php echo $profile['statut'] === 'actif' ? 'Compte actif' : ucfirst($profile['statut']); ?>
            </div>
        </div>

        <!-- Account stats -->
        <div class="account-stats-card">
            <div class="account-stat-row">
                <span class="stat-row-label">💳 Crédits</span>
                <span class="stat-row-value"><?php echo number_format($profile['solde_credits']); ?></span>
            </div>
            <div class="account-stat-row">
                <span class="stat-row-label">📡 Sites actifs</span>
                <span class="stat-row-value"><?php echo $account_stats['sites_actifs']; ?> / <?php echo $account_stats['total_sites']; ?></span>
            </div>
            <div class="account-stat-row">
                <span class="stat-row-label">🧾 Transactions</span>
                <span class="stat-row-value"><?php echo $account_stats['total_transactions']; ?></span>
            </div>
            <div class="account-stat-row">
                <span class="stat-row-label">💰 Total dépensé</span>
                <span class="stat-row-value"><?php echo number_format($account_stats['total_depense']); ?> F</span>
            </div>
        </div>

        <!-- Member since -->
        <div class="member-since">
            <div class="member-since-label">📅 Membre depuis</div>
            <div class="member-since-date">
                <?php echo $profile['date_inscription'] ? date('d F Y', strtotime($profile['date_inscription'])) : '—'; ?>
            </div>
            <?php if ($profile['derniere_connexion']): ?>
                <div class="member-since-sub">
                    Dernière connexion : <?php echo date('d/m/Y à H:i', strtotime($profile['derniere_connexion'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="profile-main">

        <!-- Tabs -->
        <div class="profile-tabs">
            <button class="tab-btn active" onclick="switchTab('tab-profile', this)">👤 Mon profil</button>
            <button class="tab-btn" onclick="switchTab('tab-security', this)">🔒 Sécurité</button>
            <button class="tab-btn" onclick="switchTab('tab-info', this)">ℹ️ Informations</button>
        </div>

        <!-- TAB: PROFIL -->
        <div id="tab-profile" class="tab-panel active">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">👤 Informations personnelles</span>
                </div>
                <div class="card-body">
                    <div class="section-label">Informations de base</div>
                    <form id="form-profile">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nom complet *</label>
                                <input type="text" class="form-control" name="nom_complet"
                                    value="<?php echo htmlspecialchars($profile['nom_complet']); ?>"
                                    placeholder="Jean Dupont" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Téléphone *</label>
                                <input type="tel" class="form-control" name="telephone"
                                    value="<?php echo htmlspecialchars($profile['telephone']); ?>"
                                    placeholder="+229 97 XX XX XX" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nom de l'entreprise</label>
                            <input type="text" class="form-control" name="nom_entreprise"
                                value="<?php echo htmlspecialchars($profile['nom_entreprise'] ?? ''); ?>"
                                placeholder="Ma Société SARL (optionnel)">
                            <p class="form-hint">Apparaîtra sur vos reçus de paiement.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Adresse email</label>
                            <input type="email" class="form-control"
                                value="<?php echo htmlspecialchars($profile['email']); ?>"
                                disabled>
                            <p class="form-hint">L'email ne peut pas être modifié. Contactez le support si nécessaire.</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="btn-save-profile">
                                💾 Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: SÉCURITÉ -->
        <div id="tab-security" class="tab-panel">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">🔒 Changer le mot de passe</span>
                </div>
                <div class="card-body">
                    <div class="section-label">Modifier votre mot de passe</div>
                    <form id="form-password">
                        <div class="form-group">
                            <label class="form-label">Mot de passe actuel *</label>
                            <div class="input-with-toggle">
                                <input type="password" class="form-control" name="current_password"
                                    id="current_password" placeholder="••••••••" required>
                                <button type="button" class="toggle-pw" onclick="togglePw('current_password')">👁</button>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nouveau mot de passe *</label>
                                <div class="input-with-toggle">
                                    <input type="password" class="form-control" name="new_password"
                                        id="new_password" placeholder="••••••••" required
                                        oninput="checkStrength(this.value)">
                                    <button type="button" class="toggle-pw" onclick="togglePw('new_password')">👁</button>
                                </div>
                                <div class="strength-bar-wrap">
                                    <div class="strength-bar" id="strength-bar"></div>
                                </div>
                                <div class="strength-text" id="strength-text">Minimum 6 caractères</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirmer le nouveau mot de passe *</label>
                                <div class="input-with-toggle">
                                    <input type="password" class="form-control" name="confirm_password"
                                        id="confirm_password" placeholder="••••••••" required
                                        oninput="checkMatch()">
                                    <button type="button" class="toggle-pw" onclick="togglePw('confirm_password')">👁</button>
                                </div>
                                <div class="strength-text" id="match-text"></div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="btn-save-pw">
                                🔐 Changer le mot de passe
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security tips -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">💡 Conseils de sécurité</span>
                </div>
                <div class="card-body">
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <?php
                        $tips = [
                            ['🔑', 'Utilisez au moins 8 caractères avec lettres, chiffres et symboles'],
                            ['🚫', 'N\'utilisez pas le même mot de passe sur plusieurs sites'],
                            ['🔄', 'Changez votre mot de passe régulièrement (tous les 3 mois)'],
                            ['🔒', 'Ne partagez jamais votre mot de passe avec quelqu\'un d\'autre'],
                        ];
                        foreach ($tips as [$icon, $text]):
                        ?>
                        <div style="display:flex;align-items:center;gap:12px;font-size:14px;color:var(--text-secondary)">
                            <span style="font-size:18px"><?php echo $icon; ?></span>
                            <span><?php echo $text; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: INFORMATIONS -->
        <div id="tab-info" class="tab-panel">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">ℹ️ Informations du compte</span>
                </div>
                <div class="card-body">
                    <div class="section-label">Données de compte (lecture seule)</div>

                    <?php
                    $info_rows = [
                        ['icon' => '🪪', 'label' => 'ID compte',          'value' => '#' . str_pad($proprietaire_id, 5, '0', STR_PAD_LEFT)],
                        ['icon' => '📧', 'label' => 'Email',               'value' => $profile['email']],
                        ['icon' => '📊', 'label' => 'Statut',              'value' => ucfirst($profile['statut'])],
                        ['icon' => '💳', 'label' => 'Solde crédits',       'value' => number_format($profile['solde_credits']) . ' crédits'],
                        ['icon' => '📡', 'label' => 'Total sites créés',   'value' => $account_stats['total_sites'] . ' site(s)'],
                        ['icon' => '📅', 'label' => 'Inscription',         'value' => $profile['date_inscription'] ? date('d/m/Y', strtotime($profile['date_inscription'])) : '—'],
                        ['icon' => '🕐', 'label' => 'Dernière connexion',  'value' => $profile['derniere_connexion'] ? date('d/m/Y à H:i', strtotime($profile['derniere_connexion'])) : '—'],
                    ];
                    foreach ($info_rows as $row):
                    ?>
                    <div class="info-row">
                        <div class="info-row-icon"><?php echo $row['icon']; ?></div>
                        <div>
                            <div class="info-row-label"><?php echo $row['label']; ?></div>
                            <div class="info-row-value"><?php echo htmlspecialchars($row['value']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Support -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">📞 Support & Aide</span>
                </div>
                <div class="card-body">
                    <p style="font-size:14px;color:var(--text-secondary);margin-bottom:18px">
                        Pour toute question ou problème, contactez notre équipe support.
                    </p>
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <a href="tel:0194601012" class="btn btn-secondary">
                            📞 0194601012
                        </a>
                        <a href="mailto:support@anytech.bj" class="btn btn-secondary">
                            ✉️ support@anytech.bj
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- .profile-main -->
</div><!-- .profile-layout -->

<script>
/* ---- Tab switching ---- */
function switchTab(panelId, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(panelId).classList.add('active');
    btn.classList.add('active');
}

/* ---- Alert helper ---- */
function showAlert(msg, type = 'success') {
    const el = document.getElementById('global-alert');
    el.innerHTML = `<div class="alert alert-${type}" data-auto-hide style="animation:fadeIn 0.3s ease">${type === 'success' ? '✅' : '❌'} ${msg}</div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => {
        const a = el.querySelector('.alert');
        if (a) { a.style.opacity = '0'; a.style.transition = 'opacity 0.4s'; setTimeout(() => el.innerHTML = '', 400); }
    }, 5000);
}

/* ---- Profile form ---- */
document.getElementById('form-profile').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-profile');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Enregistrement...';

    const fd = new FormData(this);
    fd.append('action', 'update_profile');

    try {
        const res = await fetch('profile-process.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message, 'success');
            // Update sidebar
            const nom = fd.get('nom_complet');
            document.getElementById('sidebar-name').textContent = nom;
            const parts = nom.trim().split(' ');
            let init = parts[0].charAt(0).toUpperCase();
            if (parts.length > 1) init += parts[parts.length-1].charAt(0).toUpperCase();
            document.getElementById('sidebar-initials').textContent = init;
            // Update header avatar too
            document.querySelectorAll('.avatar').forEach(a => a.textContent = init);
        } else {
            showAlert(data.message, 'error');
        }
    } catch { showAlert('Erreur réseau. Veuillez réessayer.', 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '💾 Enregistrer les modifications';
    }
});

/* ---- Password form ---- */
document.getElementById('form-password').addEventListener('submit', async function(e) {
    e.preventDefault();
    const np = document.getElementById('new_password').value;
    const cp = document.getElementById('confirm_password').value;
    if (np !== cp) { showAlert('Les mots de passe ne correspondent pas.', 'error'); return; }

    const btn = document.getElementById('btn-save-pw');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Modification...';

    const fd = new FormData(this);
    fd.append('action', 'change_password');

    try {
        const res = await fetch('profile-process.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message, 'success');
            this.reset();
            resetStrength();
        } else {
            showAlert(data.message, 'error');
        }
    } catch { showAlert('Erreur réseau. Veuillez réessayer.', 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '🔐 Changer le mot de passe';
    }
});

/* ---- Password strength ---- */
function checkStrength(val) {
    const bar = document.getElementById('strength-bar');
    const txt = document.getElementById('strength-text');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '0%',   color: '',           label: 'Minimum 6 caractères', textColor: 'var(--text-muted)' },
        { pct: '25%',  color: '#ef4444',    label: 'Faible', textColor: '#ef4444' },
        { pct: '50%',  color: '#f59e0b',    label: 'Moyen', textColor: '#f59e0b' },
        { pct: '75%',  color: '#3b82f6',    label: 'Bon', textColor: '#3b82f6' },
        { pct: '90%',  color: '#10b981',    label: 'Fort', textColor: '#10b981' },
        { pct: '100%', color: '#059669',    label: '🔥 Très fort', textColor: '#059669' },
    ];

    const l = levels[Math.min(score, 5)];
    bar.style.width = l.pct;
    bar.style.background = l.color;
    txt.textContent = l.label;
    txt.style.color = l.textColor;
}

function resetStrength() {
    const bar = document.getElementById('strength-bar');
    const txt = document.getElementById('strength-text');
    bar.style.width = '0';
    txt.textContent = 'Minimum 6 caractères';
    txt.style.color = 'var(--text-muted)';
    document.getElementById('match-text').textContent = '';
}

function checkMatch() {
    const np = document.getElementById('new_password').value;
    const cp = document.getElementById('confirm_password').value;
    const el = document.getElementById('match-text');
    if (!cp) { el.textContent = ''; return; }
    if (np === cp) {
        el.textContent = '✓ Les mots de passe correspondent';
        el.style.color = 'var(--success)';
    } else {
        el.textContent = '✗ Ne correspondent pas';
        el.style.color = 'var(--danger)';
    }
}

/* ---- Show/hide password ---- */
function togglePw(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

/* ---- Fade in animation ---- */
const style = document.createElement('style');
style.textContent = '@keyframes fadeIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:none; } }';
document.head.appendChild(style);
</script>

<?php renderFooter(); ?>
