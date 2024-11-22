document.addEventListener('DOMContentLoaded', function () {
    const copyButtons = document.querySelectorAll('.gamify-copy-button');

    copyButtons.forEach(button => {
        button.addEventListener('click', function () {
            const couponCode = this.getAttribute('data-code');

            // Create a temporary textarea to hold the code.
            const tempInput = document.createElement('textarea');
            tempInput.value = couponCode;
            document.body.appendChild(tempInput);

            // Select and copy the text.
            tempInput.select();
            tempInput.setSelectionRange(0, 99999); // For mobile devices.
            document.execCommand('copy');

            // Remove the temporary input.
            document.body.removeChild(tempInput);

            // Change the icon to a checkmark.
            const icon = this.querySelector('.gamify-copy-icon');
            icon.classList.remove('gamify-icon-clipboard');
            icon.classList.add('gamify-icon-check');

            // Reset the icon after 2 seconds.
            setTimeout(() => {
                icon.classList.remove('gamify-icon-check');
                icon.classList.add('gamify-icon-clipboard');
            }, 2000);
        });
    });
});
