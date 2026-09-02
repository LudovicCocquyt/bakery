/**
 * Recherche instantanée sur la liste des clients : à chaque lettre tapée,
 * interroge /admin/clients/recherche.json et met à jour la liste affichée
 * sans recharger la page.
 */
document.addEventListener('DOMContentLoaded', function () {
    const champRecherche = document.getElementById('recherche-client');
    const conteneurResultats = document.getElementById('resultats-clients');

    if (!champRecherche || !conteneurResultats) {
        return;
    }

    let delai = null;

    function afficherResultats(clients) {
        if (0 === clients.length) {
            conteneurResultats.innerHTML = '<p>Aucun client trouvé.</p>';
            return;
        }

        let html = '<table class="responsive-mobile"><thead><tr><th>Nom & prénom</th><th>Email</th><th>Téléphone</th><th></th></tr></thead><tbody>';
        clients.forEach(function (client) {
            html += '<tr>'
                + '<td data-label="Nom & prénom">' + escapeHtml(client.nomPrenom) + '</td>'
                + '<td data-label="Email">' + escapeHtml(client.email) + '</td>'
                + '<td data-label="Téléphone">' + escapeHtml(client.telephone || '—') + '</td>'
                + '<td data-label="Actions"><a class="btn secondaire" href="' + client.url + '">Voir la fiche</a></td>'
                + '</tr>';
        });
        html += '</tbody></table>';

        conteneurResultats.innerHTML = html;
    }

    function escapeHtml(texte) {
        const div = document.createElement('div');
        div.textContent = texte || '';
        return div.innerHTML;
    }

    champRecherche.addEventListener('input', function () {
        clearTimeout(delai);
        const terme = champRecherche.value;

        delai = setTimeout(function () {
            fetch('/admin/clients/recherche.json?q=' + encodeURIComponent(terme))
                .then(function (reponse) { return reponse.json(); })
                .then(afficherResultats)
                .catch(function () {
                    conteneurResultats.innerHTML = '<p>Erreur lors de la recherche.</p>';
                });
        }, 200);
    });
});
