jQuery(document).ready(function ($) {
  function loadRewards(page = 1) {
    const userPoints = $("#gamify-rewards-container").data("user-points");

    $.ajax({
      url: gamifyRewards.ajax_url,
      type: "POST",
      data: {
        action: "load_rewards",
        security: gamifyRewards.security,
        page: page,
        user_points: userPoints,
      },
      beforeSend: function () {
        $("#gamify-rewards-container").html("<p>Loading rewards...</p>");
      },
      success: function (response) {
        $("#gamify-rewards-container").html(response);
      },
    });
  }

  // Initial load.
  loadRewards();

  // Handle pagination.
  $(document).on("click", ".gamify-pagination-button", function () {
    const page = $(this).data("page");
    loadRewards(page);
  });
});

jQuery(document).ready(function ($) {
  // Handle redeem button click.
  $(document).on('click', '.gamify-redeem-button', function () {
    const button = $(this);
    const rewardId = button.data('reward-id');

    if (!rewardId) {
      alert('Invalid reward selected.');
      return;
    }

    $.ajax({
      url: gamifyRewards.ajax_url,
      type: 'POST',
      data: {
        action: 'redeem_reward',
        security: gamifyRewards.security,
        reward_id: rewardId,
      },
      beforeSend: function () {
        button.prop('disabled', true).text('Processing...');
      },
      success: function (response) {
        button.prop('disabled', false).text('Redeem Now');

        if (response.success) {
          alert(response.data.message);

          // Redirect to My Rewards page if redirect URL is provided.
          if (response.data.redirect_url) {
            window.location.href = response.data.redirect_url;
          }
        } else {
          alert(response.data.message);
        }
      },
      error: function () {
        button.prop('disabled', false).text('Redeem Now');
        alert('Something went wrong. Please try again.');
      },
    });
  });
});
