const ui = (() => {
    let modalInstance = null; // Pour garder une référence au modal actuel

    function showModal(contentElement) {
        if (modalInstance) {
            hideModal(); // Assure qu'un seul modal est ouvert à la fois
        }

        // Crée l'overlay du modal
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'modal-overlay';
        modalOverlay.addEventListener('click', handleOutsideClick);

        // Crée le conteneur du modal
        const modalContainer = document.createElement('div');
        modalContainer.className = 'modal-container';
        modalContainer.appendChild(contentElement);

        // Crée l'élément complet du modal
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.appendChild(modalOverlay);
        modal.appendChild(modalContainer);

        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden'; // Empêche le défilement de l'arrière-plan

        modalInstance = modal;
        contentElement.style.display = 'block'; // Assure que le contenu est visible
    }

    function hideModal() {
        if (modalInstance) {
            const contentElement = modalInstance.querySelector('.modal-container').firstChild;
            // On le rattache au corps pour ne pas le perdre s'il est réutilisé
            document.body.appendChild(contentElement);
            contentElement.style.display = 'none';

            modalInstance.remove();
            modalInstance = null;
            document.body.style.overflow = ''; // Rétablit le défilement
        }
    }

    function handleOutsideClick(event) {
        // Si l'utilisateur clique sur l'overlay (et non sur le contenu du modal)
        if (event.target.classList.contains('modal-overlay')) {
            hideModal();
        }
    }

    return {
        showModal,
        hideModal
    };
})();