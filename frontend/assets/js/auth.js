const auth = (() => {
	const Log_ON = false;
	let loggedIn = false;
	let isAdmin = false;
	let authReadyPromise;
	let resolveAuthReady;
	let app; // Pour stocker la référence à l'instance de l'application principale

	function init(appInstance) {
		app = appInstance; // Stocker la référence

		// Créer une promesse qui sera résolue lorsque l'authentification sera vérifiée
		authReadyPromise = new Promise((resolve) => {
			resolveAuthReady = resolve;
		});

		// Listeners pour les formulaires
		document
			.getElementById("login-form")
			.addEventListener("submit", handleLogin);
		document
			.getElementById("register-form")
			.addEventListener("submit", handleRegister);
		document
			.getElementById("logout-btn")
			.addEventListener("click", handleLogout);
		document
			.getElementById("request-reset-form")
			.addEventListener("submit", handleRequestReset);
		document
			.getElementById("confirm-reset-form")
			.addEventListener("submit", handleConfirmReset);

		// Vérifier si un état de connexion persiste (ex: via un appel à /me)
		checkInitialAuthStatus();
	}

	async function checkInitialAuthStatus() {
		try {
			// L'endpoint 'me' est parfait pour ça. S'il réussit, on est connecté.
			const data = await api.get("me");
			if (data && data.id) {
				loggedIn = true;
				isAdmin = data.is_admin || false;
				app.updateAuthState(true, isAdmin);
				// Pré-remplir le token CSRF pour les actions futures
				api.setCsrfToken(data.csrf_token);
			}
		} catch (error) {
			// L'erreur 401 est attendue si non connecté.
			if (error.status !== 401) {
				if (Log_ON) {
					console.error(
						"Erreur lors de la vérification du statut d'authentification",
						error
					);
				}
			}
			loggedIn = false;
			isAdmin = false;
			app.updateAuthState(false, false);
		} finally {
			// Résoudre la promesse pour indiquer que la vérification est terminée
			resolveAuthReady();
		}
	}

	async function handleLogin(e) {
		e.preventDefault();
		const email = document.getElementById("login-email").value;
		const password = document.getElementById("login-password").value;
		const errorDiv = document.getElementById("login-error");
		errorDiv.textContent = "";

		try {
			const data = await api.post("login", { email, password });
			loggedIn = true;
			isAdmin = data.user.is_admin || false;
			app.updateAuthState(true, isAdmin);
			// Mettre à jour le token CSRF reçu après le login
			api.setCsrfToken(data.csrf_token);
			window.location.hash = "profile";
		} catch (error) {
			errorDiv.textContent = error.error || "Une erreur est survenue. Err:2";
		}
	}

	async function handleRegister(e) {
		e.preventDefault();
		const email = document.getElementById("register-email").value;
		const pseudo = document.getElementById("register-pseudo").value;
		const password = document.getElementById("register-password").value;
		const errorDiv = document.getElementById("register-error");
		errorDiv.textContent = "";

		try {
			await api.post("register", { email, pseudo, password });
			// Rediriger vers la page de connexion avec un message de succès
			window.location.hash = "login";
			// On pourrait ajouter un message de succès ici
		} catch (error) {
			errorDiv.textContent = error.error || "Une erreur est survenue. Err:3";
		}
	}

	async function handleLogout(e) {
		e.preventDefault();
		try {
			await api.post("logout", {});
			loggedIn = false;
			isAdmin = false;
			api.setCsrfToken(null); // Invalider le token côté client
			app.updateAuthState(false, false);
			window.location.hash = "home";
		} catch (error) {
			if (Log_ON) {
				console.error("Erreur lors de la déconnexion:", error);
			}
			// Forcer la déconnexion côté client même si le serveur échoue
			loggedIn = false;
			isAdmin = false;
			app.updateAuthState(false, false);
			window.location.hash = "home";
		}
	}

	async function handleRequestReset(e) {
		e.preventDefault();
		const email = document.getElementById("reset-email").value;
		const errorDiv = document.getElementById("request-reset-error");
		errorDiv.textContent = "";

		try {
			const data = await api.post("request_reset", { email });
			// Afficher un message de succès générique
			e.target.innerHTML = `<p>${data.message}</p>`;
		} catch (error) {
			// Ne pas afficher d'erreur spécifique pour des raisons de sécurité
			errorDiv.textContent =
				"Une erreur est survenue. Veuillez réessayer. Err:4";
		}
	}

	async function handleConfirmReset(e) {
		e.preventDefault();
		const token = document.getElementById("reset-token-field").value;
		const password = document.getElementById("confirm-password").value;
		const errorDiv = document.getElementById("confirm-reset-error");
		errorDiv.textContent = "";

		if (!token) {
			errorDiv.textContent =
				"Token de réinitialisation manquant ou invalide.";
			return;
		}

		try {
			const data = await api.post("confirm_reset", { token, password });
			// Rediriger vers la page de connexion avec un message
			window.location.hash = "login";
			// On pourrait stocker le message dans sessionStorage pour l'afficher sur la page de login
			sessionStorage.setItem("loginMessage", data.message);
		} catch (error) {
			errorDiv.textContent = error.error || "Une erreur est survenue. Err:5";
		}
	}

	function isLoggedIn() {
		return loggedIn;
	}

	function isUserAdmin() {
		return isAdmin;
	}

	function waitForAuth() {
		return authReadyPromise;
	}

	return {
		init,
		isLoggedIn,
		isUserAdmin,
		waitForAuth,
	};
})();
