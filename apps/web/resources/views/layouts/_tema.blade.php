{{--
    Tema claro/oscuro (T-028). Va en <head>, antes de los estilos, para fijar
    el tema sin parpadeo: la elección guardada o, si no hay, la del sistema.
    No depende de datos del servidor (sin estado en disco ni en sesión).
--}}
<meta name="color-scheme" content="light dark">
<script>
    (function () {
        var tema = null;
        try { tema = localStorage.getItem('cofrial.tema'); } catch (e) {}
        if (tema !== 'dark' && tema !== 'light') {
            tema = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.dataset.theme = tema;
    })();
</script>
