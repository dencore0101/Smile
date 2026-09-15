</div>
<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});
function confirmDelete(formId, msg) {
    if (confirm(msg || 'Are you sure you want to delete this?')) {
        document.getElementById(formId).submit();
    }
}
</script>
</body>
</html>
