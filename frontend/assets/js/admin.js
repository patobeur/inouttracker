const admin = (() => {
	let adminSections = {};
	let adminNavLinks = {};

	function init() {
		// Les éléments sont sélectionnés directement, car app.js s'exécute après DOMContentLoaded
		adminSections = {
			dashboard: document.getElementById("admin-dashboard"),
			users: document.getElementById("admin-users"),
			polls: document.getElementById("admin-polls"),
		};

		adminNavLinks = document.querySelectorAll(".admin-nav-link");
		adminNavLinks.forEach((link) => {
			link.addEventListener("click", handleAdminNav);
		});

		// La gestion de la route est maintenant pilotée par app.js via hashchange
		// On peut appeler handleAdminRoute une fois pour l'état initial
		handleAdminRoute();

		// Écouter les changements de hash pour la navigation dans les sous-sections admin
		window.addEventListener("hashchange", handleAdminRoute);
	}

	function handleAdminRoute() {
		const hash = window.location.hash;

		// Ne rien faire si on n'est pas dans la section admin
		if (!hash.startsWith("#admin")) {
			return;
		}

		// Si on est sur #admin, rediriger vers #admin/dashboard
		if (hash === "#admin") {
			history.replaceState(null, "", "#admin/dashboard");
		}

		// Extraire la sous-route (ex: "dashboard", "users")
		const subRoute = window.location.hash.split("/")[1] || "dashboard";

		showAdminSection(subRoute);
	}

	function handleAdminNav(e) {
		e.preventDefault();
		const targetSection = e.target.dataset.target;

		// Mettre à jour l'URL sans recharger la page
		history.pushState(null, "", `#admin/${targetSection}`);
		showAdminSection(targetSection);
	}

	function showAdminSection(targetId) {
		if (Object.keys(adminSections).length === 0) {
			console.error(
				"Les sections d'administration ne sont pas initialisées."
			);
			return;
		}

		// Cacher toutes les sections
		for (const key in adminSections) {
			if (adminSections[key]) {
				adminSections[key].style.display = "none";
			}
		}

		// Mettre à jour la classe 'active' sur les liens de navigation
		adminNavLinks.forEach((link) => {
			link.classList.toggle("active", link.dataset.target === targetId);
		});

		// Afficher la section cible
		if (adminSections[targetId]) {
			adminSections[targetId].style.display = "block";

			// Charger les données spécifiques à la section si elle est affichée
			if (targetId === "users") {
				loadUsers();
			} else if (targetId === "dashboard") {
				loadDashboardData();
			}
		} else {
			// Si la section n'existe pas, afficher le tableau de bord par défaut
			if (adminSections.dashboard)
				adminSections.dashboard.style.display = "block";
		}
	}

	async function loadDashboardData() {
		try {
			const data = await api.get("admin/dashboard");
			document.getElementById("stats-total-users").textContent =
				data.total_users;
			document.getElementById("stats-total-polls").textContent =
				data.total_polls;
			document.getElementById("stats-finished-polls").textContent =
				data.finished_polls;
			document.getElementById("stats-total-badges").textContent =
				data.total_badges_awarded;

			const leaderboardList = document.getElementById("leaderboard-points");
			leaderboardList.innerHTML = "";
			if (data.top_10_users_by_points.length > 0) {
				data.top_10_users_by_points.forEach((user) => {
					const li = document.createElement("li");
					li.textContent = `${user.pseudo} - ${user.total_points} points`;
					leaderboardList.appendChild(li);
				});
			} else {
				leaderboardList.innerHTML = "<li>Pas encore de données.</li>";
			}
		} catch (error) {
			console.error(
				"Erreur lors du chargement des données du tableau de bord",
				error
			);
			// Afficher une erreur sur le tableau de bord
		}
	}

	async function loadUsers() {
		const userListBody = document.getElementById("admin-user-list");
		if (!userListBody) return;

		try {
			const users = await api.get("admin_get_users");
			renderUserTable(users);
		} catch (error) {
			console.error("Erreur lors du chargement des utilisateurs", error);
			userListBody.innerHTML = `<tr><td colspan="5" class="error">Erreur de chargement des données.</td></tr>`;
		}
	}

	function renderUserTable(users) {
		const userListBody = document.getElementById("admin-user-list");
		userListBody.innerHTML = "";

		if (users.length === 0) {
			userListBody.innerHTML =
				'<tr><td colspan="5">Aucun utilisateur trouvé.</td></tr>';
			return;
		}

		users.forEach((user) => {
			const tr = document.createElement("tr");
			tr.innerHTML = `
                <td>${user.id}</td>
                <td>${user.email}</td>
                <td>${user.pseudo}</td>
                <td>${user.is_admin ? "Oui" : "Non"}</td>
                <td class="actions">
                    ${
								!user.is_admin
									? `<button class="promote-btn" data-user-id="${user.id}">Promouvoir</button>`
									: ""
							}
                    ${
								user.is_admin
									? `<button class="demote-btn" data-user-id="${user.id}">Rétrograder</button>`
									: ""
							}
                </td>
            `;
			userListBody.appendChild(tr);
		});

		userListBody
			.querySelectorAll(".promote-btn, .demote-btn")
			.forEach((btn) => {
				btn.addEventListener("click", handleRoleChange);
			});
	}

	async function handleRoleChange(event) {
		const button = event.target;
		const userId = button.dataset.userId;
		const action = button.classList.contains("promote-btn")
			? "admin_promote_user"
			: "admin_demote_user";

		if (
			!confirm(
				`Êtes-vous sûr de vouloir ${
					action === "admin_promote_user" ? "promouvoir" : "rétrograder"
				} cet utilisateur ?`
			)
		) {
			return;
		}

		try {
			await api.post(action, { user_id: userId });
			loadUsers();
		} catch (error) {
			alert(`Erreur: ${error.error || "Une erreur est survenue. Err: 1"}`);
		}
	}

	return {
		init,
	};
})();
