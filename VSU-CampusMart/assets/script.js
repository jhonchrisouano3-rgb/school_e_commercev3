document.addEventListener('DOMContentLoaded', function () {

    const delivery = document.querySelector('#delivery');
    const address = document.querySelector('#addressGroup');
    const payment = document.querySelector('#payment');

    function updateCheckout() {

        if (!delivery || !payment) {
            return;
        }

        const isCOD = delivery.value === 'cod';

        if (address) {
            address.hidden = !isCOD;
        }

        if (isCOD) {

            payment.innerHTML = `
                <option>Cash</option>
                <option>GCash</option>
                <option>Maya</option>
                <option>Landbank</option>
                <option>BPI</option>
                <option>Credit Card</option>
                <option>Debit Card</option>
            `;

        } else {

            payment.innerHTML = `
                <option>Pay at Pickup</option>
            `;
        }
    }

    if (delivery) {
        delivery.addEventListener('change', updateCheckout);
        updateCheckout();
    }

    document.querySelectorAll('[data-confirm]').forEach(function (form) {

        form.addEventListener('submit', function (event) {

            if (!confirm(form.dataset.confirm)) {
                event.preventDefault();
            }

        });

    });

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {

        button.addEventListener('click', function () {

            const field = document.querySelector(
                button.dataset.passwordToggle
            );

            if (field) {
                field.type = field.type === 'password'
                    ? 'text'
                    : 'password';
            }

        });

    });

    document.querySelectorAll('[data-quantity]').forEach(function (input) {

        input.addEventListener('change', function () {

            const minimum = Number(input.min || 1);
            const maximum = Number(input.max || 999);

            input.value = Math.max(
                minimum,
                Math.min(maximum, Number(input.value || minimum))
            );

        });

    });

    const role = document.querySelector('#role');
    const picture = document.querySelector('#schoolPicture');

    if (role && picture) {

        function updatePicture() {

            picture.hidden = ![
                'student',
                'teacher',
                'seller'
            ].includes(role.value);

        }

        role.addEventListener('change', updatePicture);

        updatePicture();
    }

});