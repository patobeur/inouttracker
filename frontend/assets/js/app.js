document.addEventListener("DOMContentLoaded", () => {
	const CONSOLE_ON = false;
	const pages = document.querySelectorAll(".page");
	const navLoggedIn = document.querySelectorAll(".nav-item-logged-in");
	const navLoggedOut = document.querySelectorAll(".nav-item-logged-out");
	const navAdmin = document.querySelectorAll(".nav-item-admin");

	const app = {
		// Affiche la page correspondante au hash et cache les autres
		showPage(pageId) {
			pages.forEach((page) => {
				page.classList.toggle("active", page.id === pageId);
			});
			// Pour les petits écrans, fermer le menu nav après un clic
			// (Implémentation future si un menu burger est ajouté)
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
		},
	};

	// Lancer l'application
	app.init();
});
