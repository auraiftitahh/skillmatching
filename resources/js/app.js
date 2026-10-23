document.querySelectorAll('.mobile-navigation a').forEach((link) => {
    link.addEventListener('click', () => {
        link.closest('details').open = false;
    });
});
