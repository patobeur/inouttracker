const profile = (() => {
	const CONSOLE_ON = false;
	function init() {
		// Event listeners pour les boutons du profil
		document
			.getElementById("edit-profile-btn")
			.addEventListener("click", showEditForm);
		document
			.getElementById("cancel-edit-profile-btn")
			.addEventListener("click", hideEditForm);
		document
			.getElementById("profile-edit-form")
			.addEventListener("submit", handleUpdateProfile);

		// Observer les changements de hash pour charger les données si la page de profil est affichée
		window.addEventListener("hashchange", () => {
			if (
				window.location.hash === "#profile" ||
				window.location.hash === "#badges"
			) {
				loadProfileAndBadgesData();
			}
		});

		// Charger les données si on est déjà sur la page au chargement initial
		if (
			window.location.hash === "#profile" ||
			window.location.hash === "#badges"
		) {
			loadProfileAndBadgesData();
		}
	}

	async function loadProfileAndBadgesData() {
		// Attendre que la vérification d'authentification soit terminée
		await auth.waitForAuth();

		// Ne rien faire si on n'est pas connecté
		if (!auth.isLoggedIn()) return;

		try {
			// Charger les données du profil
			const profileData = await api.get("me");
			renderProfile(profileData);

			// Charger les badges
			const badgesData = await api.get("badges");
			renderBadges(badgesData);
		} catch (error) {
			if (CONSOLE_ON) {
				console.error(
					"Erreur lors du chargement des données du profil/badges",
					error
				);
			}
			// Rediriger vers login si le token est invalide/expiré
			if (error.status === 401) {
				window.location.hash = "login";
			}
		}
	}

	function renderProfile(data) {
		document.getElementById("profile-pseudo").textContent = data.pseudo || "";
		document.getElementById("profile-email").textContent = data.email || "";
		document.getElementById("profile-firstname").textContent =
			data.first_name || "Non renseigné";
		document.getElementById("profile-lastname").textContent =
			data.last_name || "Non renseigné";

		// Pré-remplir le formulaire d'édition
		document.getElementById("profile-edit-pseudo").value = data.pseudo || "";
		document.getElementById("profile-edit-firstname").value =
			data.first_name || "";
		document.getElementById("profile-edit-lastname").value =
			data.last_name || "";
	}

	function renderBadges(badges) {
		const container = document.getElementById("badges-list");
		container.innerHTML = ""; // Vider le conteneur
		if (badges.length === 0) {
			container.innerHTML = "<p>Vous n'avez encore aucun badge.</p>";
			return;
		}
		badges.forEach((badge) => {
			const badgeEl = document.createElement("div");
			badgeEl.className = "badge";
			badgeEl.style.backgroundColor = badge.color;
			badgeEl.textContent = badge.label;
			badgeEl.title = badge.description || "";
			container.appendChild(badgeEl);
		});
	}

	function showEditForm() {
		document.getElementById("profile-view").style.display = "none";
		document.getElementById("profile-edit-form").style.display = "block";
	}

	function hideEditForm() {
		document.getElementById("profile-view").style.display = "block";
		document.getElementById("profile-edit-form").style.display = "none";
		document.getElementById("profile-error").textContent = "";
	}

	async function handleUpdateProfile(e) {
		e.preventDefault();
		const errorDiv = document.getElementById("profile-error");
		errorDiv.textContent = "";

		const data = {
			pseudo: document.getElementById("profile-edit-pseudo").value,
			first_name: document.getElementById("profile-edit-firstname").value,
			last_name: document.getElementById("profile-edit-lastname").value,
		};

		try {
			const result = await api.post("profile_update", data);
			// Mettre à jour l'affichage avec les nouvelles données
			await loadProfileAndBadgesData();
			hideEditForm();
		} catch (error) {
			errorDiv.textContent = error.error || "Une erreur est survenue. Err:6";
		}
	}

	return {
		init,
		loadProfileData: loadProfileAndBadgesData,
	};
})();
