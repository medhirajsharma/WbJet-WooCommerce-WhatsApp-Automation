jQuery(document).ready(function($) {
    // Initialize color picker
    $('.swiftchats-admin input[type="color"]').wpColorPicker();

    // Handle business hours toggle
    $('#business_hours').on('change', function() {
        if ($(this).val() === 'custom') {
            $('.custom-hours').show();
        } else {
            $('.custom-hours').hide();
        }
    });

    // Initialize tooltips
    $('.swiftchats-tooltip').tooltip();
});
