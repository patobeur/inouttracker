document.addEventListener("DOMContentLoaded", () => {
	const CONSOLE_ON = false;
	const pages = document.querySelectorAll(".page");
	const navLoggedIn = document.querySelectorAll(".nav-item-logged-in");
	const navLoggedOut = document.querySelectorAll(".nav-item-logged-out");
	const navAdmin = document.querySelectorAll(".nav-item-admin");
	const navToggle = document.querySelector(".nav-toggle");
	const navLinksList = document.getElementById("nav-links-list");

	if (navToggle && navLinksList) {
		// Toggle menu on hamburger click
		navToggle.addEventListener("click", (e) => {
			e.stopPropagation();
			navLinksList.classList.toggle("active");
		});

		// Close menu when a link is clicked inside it
		navLinksList.addEventListener("click", () => {
			if (navLinksList.classList.contains("active")) {
				navLinksList.classList.remove("active");
			}
		});
	}

	const app = {
		// Affiche la page correspondante au hash et cache les autres
		showPage(pageId) {
			pages.forEach((page) => {
				page.classList.toggle("active", page.id === pageId);
			});
		},

		// Met à jour l'UI en fonction de l'état de connexion
		updateAuthState(isLoggedIn, isAdmin = false) {
			navLoggedIn.forEach(
				(item) => (item.style.display = isLoggedIn ? "list-item" : "none")
			);
			navLoggedOut.forEach(
				(item) => (item.style.display = isLoggedIn ? "none" : "list-item")
			);
			navAdmin.forEach(
				(item) =>
					(item.style.display =
						isLoggedIn && isAdmin ? "list-item" : "none")
			);
		},

		// Logique de routage simple basée sur le hash
		async handleRoute() {
			let hash = window.location.hash.substring(1) || "home";

			// Gérer les cas spéciaux comme le reset de mot de passe avec token
			if (hash.startsWith("reset=")) {
				const token = hash.split("=")[1];
				document.getElementById("reset-token-field").value = token;
				hash = "confirm-reset-page";
			}

			const pageId = `${hash}-page`;

			// *** Sécurité : Vérification d'accès pour la page admin ***
			if (hash === "admin") {
				// Attendre que l'authentification soit vérifiée
				await auth.waitForAuth();
				if (!auth.isUserAdmin()) {
					if (CONSOLE_ON) {
						console.warn(
							"Tentative d'accès non autorisé à la page admin."
						);
					}
					window.location.hash = "home"; // Redirection
					return; // Arrêter le traitement pour cette route
				}
			}

			if (document.getElementById(pageId)) {
				app.showPage(pageId);
			} else {
				app.showPage("home-page");
			}
		},

		// Initialisation de l'application
		init() {
			// Gérer la navigation
			window.addEventListener("hashchange", this.handleRoute);

			// Afficher la page initiale
			this.handleRoute();

			// Vérifier l'état de connexion initial (par exemple, en appelant l'endpoint /me)
			// Pour l'instant, on suppose déconnecté
			this.updateAuthState(auth.isLoggedIn(), auth.isUserAdmin());

			// Initialiser les autres modules en leur passant l'instance de l'app
			auth.init(this);
			profile.init(this);
			admin.init(this);

		// Gestionnaire pour le bouton d'installation
		document.getElementById("install-btn").addEventListener("click", this.handleInstall.bind(this));
	},

	// Gère l'appel API pour l'installation
	async handleInstall() {
		const errorDiv = document.getElementById("install-error");
		const notificationDiv = document.getElementById("system-notification");
		errorDiv.textContent = "";

		try {
			// On utilise api.post mais sans body, car l'action elle-même est la requête
			const data = await api.post("install", {});

			if (data.success) {
				// Afficher le message de succès
				notificationDiv.textContent = data.message;
				notificationDiv.className = "notification success";
				notificationDiv.style.display = "block";

				// Cacher la page d'installation
				document.getElementById("install-page").style.display = "none";

				// Optionnel: recharger la page après un court délai pour que l'utilisateur voie le message
				setTimeout(() => window.location.reload(), 4000);
			} else {
				// Cette condition ne devrait pas être atteinte car api.post lève une exception en cas d'erreur
				errorDiv.textContent = data.message || "Une erreur inattendue est survenue.";
			}
		} catch (error) {
			errorDiv.textContent = error.error || "Une erreur critique est survenue lors de l'installation.";
		}
		},
	};

	// Lancer l'application
	app.init();
});
