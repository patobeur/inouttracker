const admin = (() => {
    let adminSections = {};
    let adminNavLinks = {};
    let articlesCache = []; // Cache pour les articles

    function init() {
        adminSections = {
            dashboard: document.getElementById('admin-dashboard'),
            users: document.getElementById('admin-users'),
            articles: document.getElementById('admin-articles'),
            polls: document.getElementById('admin-polls'),
        };

        adminNavLinks = document.querySelectorAll('.admin-nav-link');
        adminNavLinks.forEach(link => {
            link.addEventListener('click', handleAdminNav);
        });

        handleAdminRoute();
        window.addEventListener('hashchange', handleAdminRoute);

        // Event listeners for article form
        document.getElementById('add-article-btn').addEventListener('click', showArticleFormForCreate);
        document.getElementById('article-form').addEventListener('submit', handleArticleFormSubmit);
        document.getElementById('cancel-article-form').addEventListener('click', hideArticleForm);
    }

    function handleAdminRoute() {
        const hash = window.location.hash;
        if (!hash.startsWith('#admin')) return;

        if (hash === '#admin') {
            history.replaceState(null, '', '#admin/dashboard');
        }

        const subRoute = window.location.hash.split('/')[1] || 'dashboard';
        showAdminSection(subRoute);
    }

    function handleAdminNav(e) {
        // Au lieu de gérer manuellement l'état, on met simplement à jour le hash.
        // Le listener 'hashchange' s'occupera du reste.
        const newHash = e.currentTarget.hash;
        if (window.location.hash !== newHash) {
            window.location.hash = newHash;
        }
    }

    function showAdminSection(targetId) {
        for (const key in adminSections) {
            if (adminSections[key]) adminSections[key].style.display = 'none';
        }

        adminNavLinks.forEach(link => {
            link.classList.toggle('active', link.dataset.target === targetId);
        });

        if (adminSections[targetId]) {
            adminSections[targetId].style.display = 'block';
            if (targetId === 'users') loadUsers();
            else if (targetId === 'dashboard') loadDashboardData();
            else if (targetId === 'articles') loadArticles();
        } else {
            if (adminSections.dashboard) adminSections.dashboard.style.display = 'block';
        }
    }

    async function loadDashboardData() {
        try {
            const data = await api.get('admin/dashboard');
            document.getElementById('stats-total-users').textContent = data.total_users;
            document.getElementById('stats-total-polls').textContent = data.total_polls;
            document.getElementById('stats-finished-polls').textContent = data.finished_polls;
            document.getElementById('stats-total-badges').textContent = data.total_badges_awarded;

            const leaderboardList = document.getElementById('leaderboard-points');
            leaderboardList.innerHTML = '';
            if (data.top_10_users_by_points.length > 0) {
                data.top_10_users_by_points.forEach(user => {
                    const li = document.createElement('li');
                    li.textContent = user.pseudo + ' - ' + user.total_points + ' points';
                    leaderboardList.appendChild(li);
                });
            } else {
                leaderboardList.innerHTML = '<li>Pas encore de données.</li>';
            }
        } catch (error) {
            console.error("Erreur lors du chargement des données du tableau de bord", error);
        }
    }

    async function loadUsers() {
        const userListBody = document.getElementById('admin-user-list');
        if (!userListBody) return;
        try {
            const users = await api.get('admin_get_users');
            renderUserTable(users);
        } catch (error) {
            console.error("Erreur lors du chargement des utilisateurs", error);
            userListBody.innerHTML = '<tr><td colspan="5" class="error">Erreur de chargement des données.</td></tr>';
        }
    }

    function renderUserTable(users) {
        const userListBody = document.getElementById('admin-user-list');
        userListBody.innerHTML = '';
        if (users.length === 0) {
            userListBody.innerHTML = '<tr><td colspan="5">Aucun utilisateur trouvé.</td></tr>';
            return;
        }
        users.forEach(user => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + user.id + '</td>' +
                '<td>' + user.email + '</td>' +
                '<td>' + user.pseudo + '</td>' +
                '<td>' + (user.is_admin ? 'Oui' : 'Non') + '</td>' +
                '<td class="actions">' +
                (!user.is_admin ? '<button class="promote-btn" data-user-id="' + user.id + '">Promouvoir</button>' : '') +
                (user.is_admin ? '<button class="demote-btn" data-user-id="' + user.id + '">Rétrograder</button>' : '') +
                '</td>';
            userListBody.appendChild(tr);
        });
        userListBody.querySelectorAll('.promote-btn, .demote-btn').forEach(btn => {
            btn.addEventListener('click', handleRoleChange);
        });
    }

    async function handleRoleChange(event) {
        const button = event.target;
        const userId = button.dataset.userId;
        const action = button.classList.contains('promote-btn') ? 'admin_promote_user' : 'admin_demote_user';
        const confirmationText = 'Êtes-vous sûr de vouloir ' + (action === 'admin_promote_user' ? 'promouvoir' : 'rétrograder') + ' cet utilisateur ?';
        if (!confirm(confirmationText)) return;
        try {
            await api.post(action, { user_id: userId });
            loadUsers();
        } catch (error) {
            alert('Erreur: ' + (error.error || 'Une erreur est survenue. Err:1'));
        }
    }

    // --- Article Management ---

    async function loadArticles() {
        try {
            articlesCache = await api.get('admin/articles');
            renderArticleTable(articlesCache);
        } catch (error) {
            console.error("Erreur lors du chargement des articles", error);
            document.getElementById('admin-article-list').innerHTML = '<tr><td colspan="6" class="error">Erreur de chargement des données.</td></tr>';
        }
    }

    function renderArticleTable(articles) {
        const articleListBody = document.getElementById('admin-article-list');
        articleListBody.innerHTML = '';
        if (articles.length === 0) {
            articleListBody.innerHTML = '<tr><td colspan="6">Aucun article trouvé.</td></tr>';
            return;
        }
        articles.forEach(article => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + article.id + '</td>' +
                '<td>' + article.barcode + '</td>' +
                '<td>' + article.name + '</td>' +
                '<td>' + (article.category || 'N/A') + '</td>' +
                '<td>' + (article.condition || 'N/A') + '</td>' +
                '<td class="actions">' +
                '<button class="edit-btn" data-article-id="' + article.id + '">Modifier</button>' +
                '<button class="delete-btn" data-article-id="' + article.id + '">Supprimer</button>' +
                '</td>';
            articleListBody.appendChild(tr);
        });

        articleListBody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', handleEditArticle));
        articleListBody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', handleDeleteArticle));
    }

    function showArticleFormForCreate() {
        document.getElementById('article-form').reset();
        document.getElementById('article-id').value = '';
        document.getElementById('article-form-title').textContent = 'Ajouter un article';
        document.getElementById('article-form-container').style.display = 'block';
    }

    function showArticleFormForEdit(articleId) {
        const article = articlesCache.find(a => a.id === articleId);
        if (!article) return;
        document.getElementById('article-id').value = article.id;
        document.getElementById('article-barcode').value = article.barcode;
        document.getElementById('article-name').value = article.name;
        document.getElementById('article-category').value = article.category || '';
        document.getElementById('article-condition').value = article.condition || '';
        document.getElementById('article-form-title').textContent = 'Modifier l\'article';
        document.getElementById('article-form-container').style.display = 'block';
    }

    function hideArticleForm() {
        document.getElementById('article-form-container').style.display = 'none';
        document.getElementById('article-error').textContent = '';
    }

    function handleEditArticle(event) {
        const articleId = parseInt(event.target.dataset.articleId, 10);
        showArticleFormForEdit(articleId);
    }

    async function handleDeleteArticle(event) {
        const articleId = event.target.dataset.articleId;
        if (!confirm('Êtes-vous sûr de vouloir supprimer cet article ?')) return;
        try {
            await api.post('admin/articles/delete', { id: articleId });
            loadArticles();
        } catch (error) {
            alert('Erreur: ' + (error.error || 'Impossible de supprimer l\'article.'));
        }
    }

    async function handleArticleFormSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const articleId = form.querySelector('#article-id').value;
        const data = {
            barcode: form.querySelector('#article-barcode').value,
            name: form.querySelector('#article-name').value,
            category: form.querySelector('#article-category').value,
            condition: form.querySelector('#article-condition').value,
        };

        const endpoint = articleId ? 'admin/articles/update' : 'admin/articles/create';
        if (articleId) {
            data.id = articleId;
        }

        try {
            await api.post(endpoint, data);
            hideArticleForm();
            loadArticles();
        } catch (error) {
            document.getElementById('article-error').textContent = error.error || 'Une erreur est survenue.';
        }
    }

    return {
        init
    };
})();
