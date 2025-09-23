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

			let pageId = `${hash}-page`;

			// *** Sécurité : Vérification d'accès pour la page admin et ses sous-routes ***
			if (hash.startsWith("admin")) {
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
				// Si c'est une route admin, on doit afficher le conteneur principal 'admin-page'
				pageId = "admin-page";
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
		const installPrompt = document.getElementById("install-prompt");
		const installProgress = document.getElementById("install-progress");
		const stepTables = document.getElementById("step-tables");
		const stepSeed = document.getElementById("step-seed");
		const stepVerify = document.getElementById("step-verify");

		errorDiv.textContent = "";
		installPrompt.style.display = "none";
		installProgress.style.display = "block";

		try {
			// Simuler une progression même si l'appel est unique
			stepTables.classList.add("success");
			await new Promise(resolve => setTimeout(resolve, 300)); // Courte pause pour l'effet visuel
			stepSeed.classList.add("success");
			await new Promise(resolve => setTimeout(resolve, 300));

			const data = await api.post("install", {});

			stepVerify.classList.add("success");

			if (data.success) {
				notificationDiv.textContent = data.message;
				notificationDiv.className = "notification success";
				notificationDiv.style.display = "block";
				installProgress.style.display = "none";

				setTimeout(() => window.location.reload(), 4000);
			}
		} catch (error) {
			stepVerify.classList.add("error");
			errorDiv.textContent = error.error || "Une erreur critique est survenue lors de l'installation.";
			// Ré-afficher le bouton pour une nouvelle tentative
			installPrompt.style.display = "block";
			installProgress.style.display = "none";
		}
	},
	};

	// Lancer l'application
	app.init();
});
