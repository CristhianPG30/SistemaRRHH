// Manejo de eventos de formularios y validaciones
document.addEventListener('DOMContentLoaded', function() {
    const salarioForm = document.getElementById('salarioForm');
    if(salarioForm) {
        salarioForm.addEventListener('submit', function(event) {
            // Validaciones de ejemplo
            const salario = document.getElementById('salario').value;
            if(salario <= 0) {
                alert('El salario debe ser mayor a cero.');
                event.preventDefault();
            }
        });
    }
});
