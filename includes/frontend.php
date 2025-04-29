<?php

// Shortcode to display availability checker
function gcsn_check_availability_shortcode() {
    ob_start();
    ?>
    <div id="gcsn-availability-checker">
        <label for="gcsn-date-input">
            <strong>Select Date:</strong><br>
            <input type="date" id="gcsn-date-input" required />
        </label>
        <br><br>

        <button id="gcsn_check_btn" class="button">Check Availability</button>
        <div id="gcsn_result" style="margin-top: 10px;"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkBtn = document.getElementById('gcsn_check_btn');
            const resultBox = document.getElementById('gcsn_result');
            const dateInput = document.getElementById('gcsn-date-input');

            checkBtn.addEventListener('click', async function () {
                const date = dateInput.value;

                if (!date) {
                    resultBox.innerHTML = '<span style="color: red;">Please select a date.</span>';
                    return;
                }

                resultBox.innerHTML = '⏳ Checking...';

                try {
                    const response = await fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=gcsn_check_date_available&date=' + encodeURIComponent(date));
                    const data = await response.json();
                    resultBox.innerHTML = data.message;
                } catch (error) {
                    resultBox.innerHTML = '<span style="color: red;">Something went wrong. Please try again later.</span>';
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
