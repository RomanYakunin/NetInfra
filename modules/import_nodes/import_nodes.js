// modules/import_nodes/import_nodes.js
document.addEventListener('DOMContentLoaded', () => {
    const uploadBtn = document.getElementById('uploadBtn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', () => {
            const fileInput = document.getElementById('excelFile');
            const file = fileInput.files[0];
            if (!file) return alert('Выберите файл');

            const formData = new FormData();
            formData.append('excel_file', file);

            fetch('?ajax=import_nodes', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('importResult').innerHTML = `<p style="color:red">${data.error}</p>`;
                        return;
                    }
                    const rows = data.rows;
                    if (!rows.length) {
                        document.getElementById('importResult').innerHTML = '<p>Файл пуст</p>';
                        return;
                    }
                    let html = '<table><thead><tr>';
                    rows[0].forEach(cell => html += `<th>${cell}</th>`);
                    html += '</tr></thead><tbody>';
                    for (let i = 1; i < rows.length; i++) {
                        html += '<tr>';
                        rows[i].forEach(cell => html += `<td>${cell}</td>`);
                        html += '</tr>';
                    }
                    html += '</tbody></table>';
                    document.getElementById('importResult').innerHTML = html;
                })
                .catch(err => {
                    document.getElementById('importResult').innerHTML = `<p style="color:red">Ошибка: ${err.message}</p>`;
                });
        });
    }
});