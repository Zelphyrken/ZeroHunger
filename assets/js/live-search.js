// Live search + filter untuk daftar donasi penerima. Tanpa reload, tanpa request ke server.
document.addEventListener('DOMContentLoaded', function () {
    var q = document.getElementById('ls-q');
    var cat = document.getElementById('ls-cat');
    var del = document.getElementById('ls-del');
    var count = document.getElementById('ls-count');
    var empty = document.getElementById('ls-empty');
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-food-card]'));

    if (!q || !cards.length) {
        return;
    }

    function apply() {
        var keyword = q.value.trim().toLowerCase();
        var category = cat.value;
        var method = del.value;
        var shown = 0;

        cards.forEach(function (card) {
            var match = (keyword === '' || card.dataset.title.indexOf(keyword) !== -1)
                && (category === '' || card.dataset.cat === category)
                && (method === '' || card.dataset.del === method);

            card.hidden = !match;
            if (match) {
                shown++;
            }
        });

        if (count) {
            count.textContent = shown;
        }
        if (empty) {
            empty.hidden = shown !== 0;
        }
    }

    [q, cat, del].forEach(function (el) {
        el.addEventListener('input', apply);
        el.addEventListener('change', apply);
    });

    apply();
});
