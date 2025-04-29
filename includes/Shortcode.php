<?php

function gcsn_check_availability_shortcode() {
    ob_start();

$enable_venue = get_option('gcsn_enable_venue_textbox', false);

    ?>
<div id="gcsn-availability-checker">
    <?php if ($enable_venue): ?>
        <label for="gcsn-venue-input" style="display: inline-block; cursor: pointer;">
            <strong>Enter Venue </strong><br>
            <input type="text" id="gcsn-venue-input" placeholder="Enter venue" style="cursor: text;" />
        </label>
        <br><br>
    <?php endif; ?>

    <label for="gcsn-date-input" style="display: inline-block; cursor: pointer;">
        <strong>Select Date:</strong><br>
        <input type="date" id="gcsn-date-input" style="cursor: pointer;" required />
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
    const venueInput = document.getElementById('gcsn-venue-input'); // optional, may not exist

    if (checkBtn) {
        checkBtn.addEventListener('click', async function () {
            const date = dateInput.value;
            const venue = venueInput ? venueInput.value : '';

            if (!date) {
                resultBox.innerHTML = '<span style="color: red;">Please select a date.</span>';
                return;
            }

            resultBox.innerHTML = '⏳ Checking...';

            try {
                const response = await fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=gcsn_check_date_available&date=' + encodeURIComponent(date) + '&venue=' + encodeURIComponent(venue));
                const data = await response.json();
                resultBox.innerHTML = data.message;
            } catch (error) {
                resultBox.innerHTML = '<span style="color: red;">Something went wrong. Please try again later.</span>';
            }
        });
    }
});
</script>
    <?php

    return ob_get_clean();
}

?>
