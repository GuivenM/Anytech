<?php
/**
 * download-integration-script.php
 * Génère et télécharge le script d'intégration personnalisé pour un site
 */

require_once 'auth-check.php';

$pdo = getDBConnection();

// Récupérer l'ID du site
$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;

if (!$site_id) {
    die('ID du site manquant');
}

// Vérifier que le site appartient au propriétaire connecté
$sql = "SELECT * FROM routeurs WHERE id = :id AND proprietaire_id = :proprietaire_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $site_id, ':proprietaire_id' => $proprietaire_id]);
$site = $stmt->fetch();

if (!$site) {
    die('Site non trouvé ou accès refusé');
}

// URL de l'API
$api_url = "https://" . $_SERVER['HTTP_HOST'] . "/api-register.php";
$code_routeur = $site['code_unique'];

// Contenu du script JavaScript
$js_content = <<<JAVASCRIPT
/**
 * ANYTECH Hotspot - Script d'Intégration
 * Site: {$site['nom_site']}
 * Code: {$code_routeur}
 * Généré le: " . date('d/m/Y H:i') . "
 */

(function() {
    'use strict';
    
    // ========================================
    // CONFIGURATION - Pré-configuré pour votre site
    // ========================================
    const CONFIG = {
        // URL de l'API (pré-configurée)
        apiUrl: '{$api_url}',
        
        // Code unique de votre routeur (pré-configuré)
        hotspotCode: '{$code_routeur}',
        
        // Sélecteur du formulaire de login
        formSelector: 'form[name=login]',
        
        // Sélecteur du bouton de soumission
        submitButtonSelector: 'button[type=submit], input[type=submit]',
        
        // ID de la div où insérer les champs
        containerDivId: 'anytech-fields',
        
        // Champs à afficher (true/false)
        fields: {
            nom: true,
            prenom: true,
            type_piece: true,
            cni: true,
            telephone: true
        },
        
        // Messages personnalisables
        messages: {
            loading: '⏳ Enregistrement en cours...',
            missingFields: '⚠️ Veuillez remplir tous les champs obligatoires',
            error: '⚠️ Erreur d\\'enregistrement, connexion quand même...'
        },
        
        // Options de style
        styling: {
            useDefaultStyles: true, // Utiliser les styles par défaut
            fieldsClass: 'anytech-field',
            containerClass: 'anytech-container'
        }
    };
    
    // ========================================
    // CODE D'INTÉGRATION - NE PAS MODIFIER
    // ========================================
    
    let isSubmitting = false;
    
    // Créer les styles par défaut
    if (CONFIG.styling.useDefaultStyles) {
        const style = document.createElement('style');
        style.textContent = `
            .\${CONFIG.styling.containerClass} {
                margin: 15px 0;
            }
            .\${CONFIG.styling.fieldsClass} {
                width: 100%;
                padding: 12px;
                margin: 8px 0;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 14px;
                font-family: inherit;
                transition: all 0.3s;
            }
            .\${CONFIG.styling.fieldsClass}:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            .anytech-loading {
                display: none;
                text-align: center;
                color: #667eea;
                font-weight: bold;
                padding: 10px;
                margin-top: 10px;
            }
            .anytech-loading.show {
                display: block;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Créer les champs HTML
    function createFields() {
        const container = document.createElement('div');
        container.className = CONFIG.styling.containerClass;
        container.id = CONFIG.containerDivId || 'anytech-fields';
        
        let html = '';
        
        if (CONFIG.fields.nom) {
            html += `<input class="\${CONFIG.styling.fieldsClass}" id="anytech-nom" type="text" placeholder="Nom" required />`;
        }
        if (CONFIG.fields.prenom) {
            html += `<input class="\${CONFIG.styling.fieldsClass}" id="anytech-prenom" type="text" placeholder="Prénom" required />`;
        }
        if (CONFIG.fields.type_piece) {
            html += `<select class="\${CONFIG.styling.fieldsClass}" id="anytech-type-piece">
                <option value="cni">Carte d'identité (CNI)</option>
                <option value="passeport">Passeport</option>
                <option value="cip">CIP</option>
            </select>`;
        }
        if (CONFIG.fields.cni) {
            html += `<input class="\${CONFIG.styling.fieldsClass}" id="anytech-cni" type="text" placeholder="Numéro de pièce" required />`;
        }
        if (CONFIG.fields.telephone) {
            html += `<input class="\${CONFIG.styling.fieldsClass}" id="anytech-telephone" type="tel" placeholder="Téléphone" required />`;
        }
        
        container.innerHTML = html;

        // Mettre à jour le placeholder du numéro selon le type sélectionné
        if (CONFIG.fields.type_piece && CONFIG.fields.cni) {
            const typeSelect = container.querySelector('#anytech-type-piece');
            const cniInput   = container.querySelector('#anytech-cni');
            const placeholders = { cni: 'Numéro CNI', passeport: 'Numéro de passeport', cip: 'Numéro CIP' };
            if (typeSelect && cniInput) {
                typeSelect.addEventListener('change', function() {
                    cniInput.placeholder = placeholders[this.value] || 'Numéro de pièce';
                });
            }
        }

        return container;
    }
    
    // Insérer les champs dans la page
    function insertFields() {
        const form = document.querySelector(CONFIG.formSelector);
        if (!form) {
            console.error('ANYTECH: Formulaire non trouvé');
            return false;
        }
        
        let container = document.getElementById(CONFIG.containerDivId);
        
        if (!container) {
            const usernameField = form.querySelector('[name=\"username\"], #username');
            if (usernameField) {
                container = createFields();
                usernameField.parentNode.insertBefore(container, usernameField);
            } else {
                console.error('ANYTECH: Impossible de trouver où insérer les champs');
                return false;
            }
        } else {
            container.appendChild(createFields());
        }
        
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'anytech-loading';
        loadingDiv.id = 'anytech-loading';
        loadingDiv.textContent = CONFIG.messages.loading;
        form.appendChild(loadingDiv);
        
        return true;
    }
    
    // Récupérer les données des champs
    function getFieldsData() {
        const data = {};
        
        if (CONFIG.fields.nom) {
            const nom = document.getElementById('anytech-nom');
            data.nom = nom ? nom.value.trim() : '';
        }
        if (CONFIG.fields.prenom) {
            const prenom = document.getElementById('anytech-prenom');
            data.prenom = prenom ? prenom.value.trim() : '';
        }
        if (CONFIG.fields.type_piece) {
            const typePiece = document.getElementById('anytech-type-piece');
            data.type_piece = typePiece ? typePiece.value : 'cni';
        } else {
            data.type_piece = 'cni'; // valeur par défaut si champ masqué
        }
        if (CONFIG.fields.cni) {
            const cni = document.getElementById('anytech-cni');
            data.cni = cni ? cni.value.trim() : '';
        }
        if (CONFIG.fields.telephone) {
            const tel = document.getElementById('anytech-telephone');
            data.telephone = tel ? tel.value.trim() : '';
        }
        
        return data;
    }
    
    // Valider les champs
    function validateFields(data) {
        for (let key in data) {
            if (!data[key] || data[key].length === 0) {
                return false;
            }
        }
        return true;
    }
    
    // Envoyer les données à l'API
    async function sendToAPI(userData) {
        try {
            console.log('ANYTECH: Envoi vers', CONFIG.apiUrl);
            console.log('ANYTECH: Données', userData);
            
            const response = await fetch(CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Hotspot-Code': CONFIG.hotspotCode
                },
                body: JSON.stringify(userData)
            });
            
            console.log('ANYTECH: Statut', response.status);
            
            if (!response.ok) {
                console.error('ANYTECH: Erreur HTTP', response.status);
                return false;
            }
            
            const result = await response.json();
            console.log('ANYTECH: Résultat', result);
            
            return result.success;
        } catch (error) {
            console.error('ANYTECH: Erreur', error);
            return false;
        }
    }
    
    // Intercepter la soumission du formulaire
    function interceptFormSubmit() {
        const form = document.querySelector(CONFIG.formSelector);
        if (!form) return;
        
        const submitButton = form.querySelector(CONFIG.submitButtonSelector);
        const loadingDiv = document.getElementById('anytech-loading');
        
        const originalOnSubmit = form.onsubmit;
        
        form.onsubmit = async function(event) {
            event.preventDefault();
            
            if (isSubmitting) {
                console.log('ANYTECH: Soumission déjà en cours');
                return false;
            }
            
            isSubmitting = true;
            
            const fieldsData = getFieldsData();
            
            if (!validateFields(fieldsData)) {
                alert(CONFIG.messages.missingFields);
                isSubmitting = false;
                return false;
            }
            
            if (loadingDiv) loadingDiv.classList.add('show');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.dataset.originalText = submitButton.innerHTML;
                submitButton.innerHTML = 'ENREGISTREMENT...';
            }
            
            const usernameField = form.querySelector('[name=\"username\"]');
            const macAddress = '\$(mac)';
            
            const userData = {
                ...fieldsData,
                code_voucher: usernameField ? usernameField.value : '',
                hotspot_code: CONFIG.hotspotCode,
                mac_address: macAddress,
                date: new Date().toISOString()
            };
            
            console.log('ANYTECH: Enregistrement...');
            const success = await sendToAPI(userData);
            
            if (loadingDiv) loadingDiv.classList.remove('show');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = submitButton.dataset.originalText;
            }
            
            if (success) {
                console.log('ANYTECH: ✅ Enregistrement réussi');
            } else {
                console.warn('ANYTECH: ⚠️ Enregistrement échoué');
            }
            
            isSubmitting = false;
            
            if (originalOnSubmit) {
                return originalOnSubmit.call(form, event);
            } else {
                form.submit();
                return false;
            }
        };
    }
    
    // Initialiser l'intégration
    function init() {
        console.log('ANYTECH: Initialisation du module d\\'intégration...');
        console.log('ANYTECH: Site: {$site['nom_site']}');
        console.log('ANYTECH: Code: {$code_routeur}');
        
        if (insertFields()) {
            console.log('ANYTECH: Champs insérés avec succès');
            interceptFormSubmit();
            console.log('ANYTECH: Formulaire intercepté avec succès');
            console.log('ANYTECH: ✅ Intégration terminée');
        } else {
            console.error('ANYTECH: ❌ Échec de l\\'intégration');
        }
    }
    
    // Attendre que le DOM soit chargé
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
JAVASCRIPT;

// Nom du fichier
$filename = 'anytech-integration-' . $code_routeur . '.js';

// Headers pour téléchargement
header('Content-Type: application/javascript; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($js_content));

echo $js_content;
exit();
?>
