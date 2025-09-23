# Documentation de l'API

Cette documentation décrit les points d'accès (endpoints) de l'API du projet.

**URL de base** : `/api/index.php`

## Endpoints Publics

---

### 1. Inscription

- **Action**: `register`
- **Méthode**: `POST`
- **Description**: Crée un nouveau compte utilisateur.
- **Paramètres**:
    - `email` (string, requis)
    - `pseudo` (string, requis)
    - `password` (string, requis)

**Exemple `curl`**:
```bash
curl -X POST -d "email=test@example.com&pseudo=testeur&password=password123" /api/index.php?action=register
```

**Réponse Succès (201)**:
```json
{
  "message": "Inscription réussie."
}
```

**Réponse Erreur (400)**:
```json
{
  "error": "L'email ou le pseudo est déjà utilisé."
}
```

---

### 2. Connexion

- **Action**: `login`
- **Méthode**: `POST`
- **Description**: Démarre une session pour un utilisateur.
- **Paramètres**:
    - `email` (string, requis)
    - `password` (string, requis)

**Exemple `curl`**:
```bash
curl -X POST -d "email=test@example.com&password=password123" -c cookies.txt /api/index.php?action=login
```

**Réponse Succès (200)**:
```json
{
  "message": "Connexion réussie.",
  "user": {
    "pseudo": "testeur"
  },
  "csrf_token": "..."
}
```

**Réponse Erreur (401)**:
```json
{
  "error": "Email ou mot de passe incorrect."
}
```

---

### 3. Demande de réinitialisation de mot de passe

- **Action**: `request_reset`
- **Méthode**: `POST`
- **Description**: Envoie un lien de réinitialisation (simulé).
- **Paramètres**:
    - `email` (string, requis)

**Exemple `curl`**:
```bash
curl -X POST -d "email=test@example.com" /api/index.php?action=request_reset
```

**Réponse Succès (200)** (toujours la même pour des raisons de sécurité):
```json
{
  "message": "Si un compte avec cet email existe, un lien de réinitialisation a été envoyé."
}
```

---

### 4. Confirmation de réinitialisation de mot de passe

- **Action**: `confirm_reset`
- **Méthode**: `POST`
- **Description**: Met à jour le mot de passe avec un token valide.
- **Paramètres**:
    - `token` (string, requis)
    - `password` (string, requis)

**Exemple `curl`**:
```bash
curl -X POST -d "token=...&password=newpassword456" /api/index.php?action=confirm_reset
```

**Réponse Succès (200)**:
```json
{
  "message": "Mot de passe réinitialisé avec succès."
}
```

**Réponse Erreur (400)**:
```json
{
  "error": "Le token a expiré."
}
```

---

## Endpoints Authentifiés

Tous les endpoints suivants nécessitent d'être authentifié (session valide) et de fournir un token CSRF pour les requêtes `POST`.

---

### 5. Déconnexion

- **Action**: `logout`
- **Méthode**: `POST`
- **Description**: Termine la session de l'utilisateur.
- **Paramètres**:
    - `csrf_token` (string, requis)

**Exemple `curl`**:
```bash
curl -X POST -d "csrf_token=..." -b cookies.txt /api/index.php?action=logout
```

**Réponse Succès (200)**:
```json
{
  "message": "Déconnexion réussie."
}
```

---

### 6. Obtenir les informations de l'utilisateur

- **Action**: `me`
- **Méthode**: `GET`
- **Description**: Récupère les informations du profil de l'utilisateur connecté.

**Exemple `curl`**:
```bash
curl -X GET -b cookies.txt /api/index.php?action=me
```

**Réponse Succès (200)**:
```json
{
  "id": 1,
  "email": "test@example.com",
  "pseudo": "testeur",
  "first_name": "Jean",
  "last_name": "Dupont",
  "created_at": "...",
  "csrf_token": "..."
}
```

---

### 7. Mettre à jour le profil

- **Action**: `profile_update`
- **Méthode**: `POST`
- **Description**: Met à jour le profil de l'utilisateur.
- **Paramètres**:
    - `pseudo` (string, requis)
    - `first_name` (string)
    - `last_name` (string)
    - `csrf_token` (string, requis)

**Exemple `curl`**:
```bash
curl -X POST -d "pseudo=testeur_modifie&first_name=John&csrf_token=..." -b cookies.txt /api/index.php?action=profile_update
```

**Réponse Succès (200)**:
```json
{
  "success": true,
  "message": "Profil mis à jour avec succès."
}
```

---

### 8. Obtenir les badges de l'utilisateur

- **Action**: `badges`
- **Méthode**: `GET`
- **Description**: Récupère la liste des badges de l'utilisateur (statique en Phase 1).

**Exemple `curl`**:
```bash
curl -X GET -b cookies.txt /api/index.php?action=badges
```

**Réponse Succès (200)**:
```json
[
  {
    "slug": "pionnier",
    "label": "Pionnier",
    "color": "#4a90e2",
    "level": 1,
    "awarded_at": "..."
  }
]
```
