jQuery(document).ready(function ($) {

    var $visibleUsers, $totalUsers;

    function userValidation(input) {
        if (typeof (input) === 'undefined') input = '.kokagrams-UserValidation';

        $(input).typeWatch({
            callback: function (value) {
                var user = value;
                var $elem = $(this);
                var loading = $elem.next('.kokagrams-live-icon').find('.kokagrams-loading');
                var ok = $elem.next('.kokagrams-live-icon').find('.kokagrams-yes');
                var fail = $elem.next('.kokagrams-live-icon').find('.kokagrams-no');
                var alert = $elem.next('.kokagrams-live-icon').find('.kokagrams-alert');
                var message = $elem.parent('td').find('.kokagrams-message');
                var hiddenInput = $elem.next().next('input[type=hidden]');
                $.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: {
                        username: user,
                        action: 'kokagrams_check_user_id'
                    },
                    beforeSend: function () {
                        fail.add(ok).add(alert).add(message).hide();
                        loading.show();
                    },
                    success: function (response) {
                        loading.hide();
                        if (response == 'false') {
                            fail.show();
                            hiddenInput.val('');
                        } else if (response == 'alert') {
                            alert.show();
                            message.show();
                            hiddenInput.val('');
                        } else {
                            ok.show();
                            hiddenInput.val(response);
                        }
                    }
                });
            },
            wait: 400,
            captureLength: 1
        });
    }

    userValidation();


    $totalUsers = 10;

    $visibleUsers = $('.kokagrams_user_hashtag:visible').length;

    kokagramsUpdateContent();

    var clonedField = $('#kokagramsMainTable .kokagrams_user_hashtag:first');
    var separator = $('#kokagramsMainTable .kokagrams_user_tr:first');
    var userContainer = $('#kokagramsMainTable > tbody');

    $('#kokagrams-addUser').on('click', function (e) {
        e.preventDefault();

        if ($visibleUsers === 0) {
            $('#kokagramsMainTable .kokagrams_user_hashtag:first:hidden').show().removeClass('hidden');
            $('#kokagramsMainTable .kokagrams_user_tr:first:hidden').show().removeClass('hidden');
            console.log('remove hidden');
        } else {
            var clone = clonedField.clone();
            clone.find('.kokagrams-trash').hide();
            clone.find('.kokagrams-live-icon img').hide();
            clone.find('input').val('');
            userContainer.append(clone);
            userValidation(clone.find('input.kokagrams-UserValidation'));
            userContainer.append(separator.clone());
        }
        $visibleUsers++;
        kokagramsUpdateContent();
    });


    $(document).on('click', '.kokagrams-trash', function (e) {
        e.preventDefault();
        if ($visibleUsers === 1) {
            $(this).parents('.kokagrams_user_hashtag').hide();
            $(this).parents('.kokagrams_user_hashtag').next('tr').hide();
            $(this).parents('.kokagrams_user_hashtag').find('input').val('');
        } else {
            $(this).parents('.kokagrams_user_hashtag').next('tr').remove();
            $(this).parents('.kokagrams_user_hashtag').remove();
        }

        $visibleUsers--;
        kokagramsUpdateContent();
    });

    function kokagramsUpdateContent() {
        if ($visibleUsers >= 1) {
            $('#kokagramsUserLabel').html('"Add Another Team Member"');
            $('#kokagramsUserNumber').html('more');
            $('#kokagrams-addUser').html('Add Another Team Member');
        } else {
            $('#kokagramsUserLabel').html('"Add New Team Member"');
            $('#kokagramsUserNumber').html('your first one');
            $('#kokagrams-addUser').html('Add New Team Member');
        }
        if ($totalUsers == $visibleUsers) {
            $('#kokagrams-addUser').hide();
        } else {
            $('#kokagrams-addUser').show();
        }
    }

    $('#kokagrams-unlinkAccount').on('click', function () {
        var kokagramsConfirm = confirm("Are you sure you want to do this?");
        return kokagramsConfirm;
    });

    var somethingChanged = false;

    $('.kokagrams_settings_form input, .kokagrams_settings_form select, .kokagrams_settings_form textarea').change(function () {
        somethingChanged = true;
    });

    $('.kokagramsNavTab a').on('click', function () {
        if (somethingChanged) {
            var kokagramsConfirm = confirm("You have pending changes. Please save them or they will be lost. Do you want to continue without saving?");
            return kokagramsConfirm;
        } else {
            return true;
        }
    });

    $(document).on('mouseenter', '.kokagrams-hover-table', function () {
        $(this).find('.kokagrams-trash').fadeIn('fast');
    }).on('mouseleave', '.kokagrams-hover-table', function () {
        $(this).find('.kokagrams-trash').fadeOut('fast');
    });




});